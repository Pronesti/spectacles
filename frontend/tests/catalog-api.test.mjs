import assert from 'node:assert/strict'
import { test } from 'node:test'
import { decodeCatalogEvent, decodeCatalogPage } from '../src/catalog.ts'
import { CatalogApiError, createCatalogClient, createLatestRead } from '../src/catalogApi.ts'
import { catalogFixture, id } from './catalog-fixtures.mjs'
const json = (value, status = 200) => new Response(JSON.stringify(value), { status, headers: { 'Content-Type': 'application/vnd.api+json' } })
test('compound Event decoding preserves linkage order, commas, null facts, seconds and zero coordinates', () => {
  const fixture = catalogFixture()
  const event = decodeCatalogEvent(fixture.detail)
  assert.deepEqual(event.artists.map(artist => artist.id), [id(1), id(2)])
  assert.deepEqual(event.artists.map(artist => artist.name), ['Björk, guest', 'Björk, guest'])
  assert.equal(event.venue.lat, 0); assert.equal(event.venue.lng, 0)
  assert.equal(event.venue.city, null); assert.equal(event.timezone, null)
  assert.equal(event.time, '00:00:07')
  assert.deepEqual(event.date, { year: 2024, month: null, day: null })
  assert.deepEqual(event.tours[0].artists.map(artist => artist.id), [id(2)])
  fixture.event.relationships.artistLinks.data = []
  fixture.event.relationships.venue.data = null
  fixture.event.attributes = { ...fixture.event.attributes, year: null, local_start_time: null }
  const unknown = decodeCatalogEvent(fixture.detail)
  assert.deepEqual(unknown.artists, []); assert.equal(unknown.venue, null); assert.equal(unknown.time, null)
})
test('all five collections decode explicit DTOs without provider or private attributes', () => {
  const f = catalogFixture()
  for (const [kind, rows] of [['events', [f.event]], ['artists', [f.a, f.b]], ['venues', [f.venue]], ['songs', [f.song]], ['tours', [f.tour]]]) {
    rows[0].attributes.private_debug = 'never-copy'
    const page = decodeCatalogPage(kind, f.page(rows))
    assert.equal(page.items.length, rows.length)
    assert.equal(JSON.stringify(page).includes('never-copy'), false)
    assert.equal(JSON.stringify(page).includes('untrusted.invalid'), false)
  }
})
test('invalid shapes, missing linkage, invalid dates and malformed coordinates fail atomically', () => {
  const f = catalogFixture()
  assert.throws(() => decodeCatalogPage('artists', { data: [f.a] }))
  assert.throws(() => decodeCatalogPage('artists', f.page([f.a, f.a])))
  assert.throws(() => decodeCatalogEvent({ data: f.event, included: [] }))
  f.event.attributes.month = 2; f.event.attributes.day = 30
  assert.throws(() => decodeCatalogEvent(f.detail))
  f.event.attributes.day = 29
  f.venue.attributes.lat = ''
  assert.throws(() => decodeCatalogEvent(f.detail))
  assert.throws(() => decodeCatalogEvent({ data: null }))
})
test('six reads use encoded filters and cookie GETs with no CSRF, body or bearer header', async () => {
  const f = catalogFixture(), calls = []
  const rows = { events: [f.event], artists: [f.a], venues: [f.venue], songs: [f.song], tours: [f.tour] }
  const client = createCatalogClient('https://api.spectacles.test/', async (value, init) => {
    const url = new URL(value); calls.push(url)
    assert.equal(init.method, 'GET'); assert.equal(init.credentials, 'include'); assert.equal(init.cache, 'no-store')
    assert.equal(init.body, undefined); assert.equal(new Headers(init.headers).get('Accept'), 'application/json')
    assert.equal(new Headers(init.headers).has('Authorization'), false)
    const kind = url.pathname.split('/')[3]
    return json(url.pathname.endsWith(id(7)) ? f.detail : f.page(rows[kind]))
  })
  for (const kind of Object.keys(rows)) await client.search(kind, { q: 'Björk & 100%_!' }, 2)
  await client.event(id(7))
  assert.equal(calls.length, 6)
  assert.equal(calls[0].searchParams.get('filter[q]'), 'Björk & 100%_!')
  assert.equal(calls[0].searchParams.get('page'), '2')
  assert.equal(calls.at(-1).searchParams.get('include'), 'tourLinks.tour.artistLinks.artist')
  assert.ok(calls.every(url => url.origin === 'https://api.spectacles.test'))
  await assert.rejects(client.search('songs', { artist_uuid: id(1) }), error => error.status === 422)
  await assert.rejects(client.event('local-concert-1'), error => error.status === 422)
  assert.equal(calls.length, 6)
})
test('401,404,422,429,5xx,HTML and network failures produce safe recoverable errors', async () => {
  for (const status of [401, 404, 422, 429, 503]) {
    const client = createCatalogClient('https://api.spectacles.test', async () => json({ message: 'private-debug' }, status))
    await assert.rejects(client.search('artists'), error => error instanceof CatalogApiError && error.status === status && !error.message.includes('private-debug'))
  }
  const html = createCatalogClient('https://api.spectacles.test', async () => new Response('<html>debug</html>'))
  await assert.rejects(html.search('artists'), error => error instanceof CatalogApiError && error.status === 200)
  const offline = createCatalogClient('https://api.spectacles.test', async () => { throw new TypeError('private-detail') })
  await assert.rejects(offline.search('artists'), error => error.status === 0 && !error.message.includes('private-detail'))
})
test('a stalled body times out and caller cancellation remains cancellation', async context => {
  context.mock.timers.enable({ apis: ['setTimeout'] })
  const client = createCatalogClient('https://api.spectacles.test', async (url, init) => new Response(new ReadableStream({ start(controller) {
    init.signal.addEventListener('abort', () => controller.error(new DOMException('Aborted', 'AbortError')), { once: true })
  } })))
  const pending = client.search('artists')
  await Promise.resolve(); context.mock.timers.tick(10_000)
  await assert.rejects(pending, error => error.status === 0 && error.message.includes('timed out'))
  const cancel = new AbortController()
  const cancelled = client.search('artists', {}, 1, cancel.signal)
  cancel.abort()
  await assert.rejects(cancelled, { name: 'AbortError' })
})
test('new searches and cancel suppress late success and obsolete unauthorized callbacks', async () => {
  const latest = createLatestRead(), seen = [], errors = []
  let finishA, finishB, rejectOld
  const a = latest.run(signal => new Promise(resolve => { finishA = value => { assert.equal(signal.aborted, true); resolve(value) } }), value => seen.push(value), error => errors.push(error))
  const b = latest.run(() => new Promise(resolve => { finishB = resolve }), value => seen.push(value), error => errors.push(error))
  finishB('new'); await b; finishA('old'); await a
  assert.deepEqual(seen, ['new'])
  const old = latest.run(() => new Promise((resolve, reject) => { rejectOld = reject }), value => seen.push(value), error => errors.push(error))
  latest.cancel(); rejectOld(new CatalogApiError(401, 'expired')); await old
  assert.deepEqual(errors, [])
})

test('page navigation retains typed filters on the configured origin and ignores response links', async () => {
  const f = catalogFixture(), calls = []
  const client = createCatalogClient('https://api.spectacles.test', async value => {
    const url = new URL(value); calls.push(url)
    return json({ ...f.page([f.a]), meta: { current_page: Number(url.searchParams.get('page')), per_page: 20, last_page: 2, total: 21 } })
  })
  for (const page of [1, 2, 1]) {
    const result = await client.search('artists', { q: 'Björk, guest', type: 'person' }, page)
    assert.equal(result.page, page); assert.equal(result.lastPage, 2)
  }
  assert.deepEqual(calls.map(url => url.searchParams.get('page')), ['1', '2', '1'])
  assert.ok(calls.every(url => url.origin === 'https://api.spectacles.test' && url.searchParams.get('filter[q]') === 'Björk, guest' && url.searchParams.get('filter[type]') === 'person'))
})
