import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createMusicBrainzClient, MusicBrainzApiError } from '../src/musicbrainzApi.ts'
const row = { id: '3f8a5e5b-c24b-4068-9f1c-afad8829e06b', kind: 'artist', label: 'Zoé', artistType: 'group', disambiguation: null, area: null, country: null, writers: [], local: null, selection: 'proof' }
test('provider client uses first-party cookie GET, strict allowlisted DTO and cancellation signal', async () => {
  let call
  const client = createMusicBrainzClient('https://api.spectacles.test', async (url, init) => { call = { url, init }; return Response.json({ data: [{ ...row, private: 'discard' }] }) })
  const data = await client.search('artist', ' Zoé ')
  assert.equal(data[0].private, undefined); assert.equal(data[0].label, 'Zoé')
  assert.equal(call.url, 'https://api.spectacles.test/api/v1/musicbrainz/artists?q=Zo%C3%A9'); assert.equal(call.init.credentials, 'include'); assert.equal(call.init.body, undefined); assert.equal(call.init.headers.Authorization, undefined)
  for (const candidate of [{ ...row, kind: 'work' }, { ...row, id: 'bad' }, { ...row, local: {} }]) await assert.rejects(createMusicBrainzClient('', async () => Response.json({ data: [candidate] })).search('artist', 'Zoé'))
})
test('errors remain distinguishable from empty results and discard upstream debug bodies', async () => {
  for (const status of [401, 422, 429, 503]) await assert.rejects(createMusicBrainzClient('', async () => new Response('private debug', { status })).search('series', 'Eras'), error => error instanceof MusicBrainzApiError && error.status === status && !error.message.includes('private'))
  assert.deepEqual(await createMusicBrainzClient('', async () => Response.json({ data: [] })).search('work', 'Eres'), [])
})
test('eight-second deadline and caller cancellation abort the provider read without retries', async t => {
  t.mock.timers.enable({ apis:['setTimeout'] })
  let calls = 0
  const client = createMusicBrainzClient('', (_url, init) => { calls++; return new Promise((_resolve, reject) => init.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')))) })
  const promise = client.search('series', 'Eras'); const assertion = assert.rejects(promise, error => error instanceof MusicBrainzApiError && error.status === 0)
  t.mock.timers.tick(8000); await assertion; assert.equal(calls, 1)
  const controller = new AbortController(); const cancelled = client.search('artist','Zoé', controller.signal); const aborted = assert.rejects(cancelled, error => error.name === 'AbortError'); controller.abort(); await aborted
})

test('Tour suggestions use the Series kind and fixed first-party Tour route', async () => {
  let call
  const tour = { ...row, id: '23aa9d94-ffc6-4160-a7f3-31cfba1ea646', kind: 'series',
    label: 'The Eras Tour', artistType: null, disambiguation: 'Taylor Swift tour',
    area: null, country: null, writers: [], local: null }
  const client = createMusicBrainzClient('https://api.spectacles.test', async (url, init) => {
    call = { url, init }; return Response.json({ data: [tour] })
  })
  const results = await client.search('series', ' Eras & Tour ')
  assert.equal(call.url, 'https://api.spectacles.test/api/v1/musicbrainz/tours?q=Eras+%26+Tour')
  assert.equal(call.init.credentials, 'include'); assert.equal(call.init.body, undefined)
  assert.equal(results[0].kind, 'series'); assert.equal(results[0].label, 'The Eras Tour')
  await assert.rejects(createMusicBrainzClient('', async () => Response.json({ data: [row] }))
    .search('series', 'Eras'))
})

test('Tour provider scope carries public Artist identities only and distinguishes missing identity from zero matches', async () => {
  let call
  const scope={artist_uuids:['00000000-0000-4000-8000-000000000001'],artist_mbids:['00000000-0000-4000-8000-000000000002']}
  const client=createMusicBrainzClient('',async url=>{call=new URL(url,'https://api.spectacles.test');return Response.json({data:[],meta:{scope_available:true}})})
  assert.deepEqual(await client.search('series','Tour',undefined,scope),[])
  assert.equal(call.searchParams.get('artist_uuids[0]'),scope.artist_uuids[0]);assert.equal(call.searchParams.get('artist_mbids[0]'),scope.artist_mbids[0])
  await assert.rejects(createMusicBrainzClient('',async()=>Response.json({data:[],meta:{scope_available:false}})).search('series','Tour',undefined,scope),error=>error.message.includes('no MusicBrainz identity')&&!error.message.includes('Sign in'))
})
