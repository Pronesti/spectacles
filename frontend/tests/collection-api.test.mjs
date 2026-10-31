import assert from 'node:assert/strict'
import { test } from 'node:test'
import { CollectionError, createCollectionClient, emptyCollection } from '../src/collectionApi.ts'
const json = (data, status = 200) => new Response(JSON.stringify({ data }), { status })
const id = '00000000-0000-4000-8000-000000000001'
test('collection load uses cookie GET, and saves/deletes use CSRF without automatic replay', async () => {
  const calls = []
  const client = createCollectionClient('https://api.spectacles.test', async (url, options) => {
    calls.push([url, options])
    assert.equal(options.credentials, 'include')
    if (url.endsWith('csrf-cookie')) return new Response(null, { status: 204 })
    assert.equal(new Headers(options.headers).get('Accept'), 'application/json')
    if (options.method !== 'GET') assert.equal(new Headers(options.headers).get('X-XSRF-TOKEN'), 'a=b')
    return json(emptyCollection())
  }, () => 'XSRF-TOKEN=a%3Db')
  await client.load(); await client.save({ id }); await client.save({ id }, undefined, true); await client.remove(id)
  assert.deepEqual(calls.filter(([url]) => !url.endsWith('csrf-cookie')).map(([url, options]) => [new URL(url).pathname, options.method]), [
    ['/api/v1/collection', 'GET'], ['/api/v1/attendances', 'POST'], [`/api/v1/attendances/${id}`, 'PATCH'], [`/api/v1/attendances/${id}`, 'DELETE'],
  ])
  assert.equal(calls[0][1].body, undefined)
  assert.equal(calls.length, 7)
})
test('malformed collection is an error rather than a fallback to fabricated or browser data', async () => {
  const client = createCollectionClient('https://api.spectacles.test', async () => json({ version: 1, concerts: [{}], venues: [] }))
  await assert.rejects(client.load(), error => error instanceof CollectionError && error.message.includes('could not be read'))
})
test('401,419,403,409,422,HTML and offline errors are safe and writes are never replayed', async () => {
  for (const status of [401, 419, 403, 409, 422, 503]) {
    let mutations = 0
    const client = createCollectionClient('https://api.spectacles.test', async (url) => {
      if (url.endsWith('csrf-cookie')) return new Response(null, { status: 204 })
      mutations++; return json({ debug: 'private detail' }, status)
    }, () => 'XSRF-TOKEN=fixture')
    await assert.rejects(client.save({ id }), error => error.status === status && !error.message.includes('private detail'))
    assert.equal(mutations, 1)
  }
  for (const fetcher of [async () => new Response('<html>debug</html>'), async () => { throw new Error('secret') }]) {
    await assert.rejects(createCollectionClient('https://api.spectacles.test', fetcher).load(), error => error.status === 0 && !error.message.includes('secret'))
  }
})
test('cancelled collection reads cannot turn into account failures', async () => {
  const client = createCollectionClient('https://api.spectacles.test', async (url, options) => {
    options.signal.throwIfAborted(); return json(emptyCollection())
  })
  const controller = new AbortController(); controller.abort()
  await assert.rejects(client.load(controller.signal), { name: 'AbortError' })
})

test('wizard pending metadata travels once beside the concert and is omitted for old callers', async () => {
  const calls = [], pending = { artists: {}, songs: {}, lineup: {}, entries: {} }
  const client = createCollectionClient('https://api.spectacles.test', async (url, init) => {
    calls.push({url, init})
    if (url.endsWith('/sanctum/csrf-cookie')) return new Response(null, {status:204})
    return Response.json({data: { version:1, concerts:[], venues:[] }})
  }, () => 'XSRF-TOKEN=token')
  const concert = {id:'00000000-0000-4000-8000-000000000001'}
  await client.save(concert, undefined, false, pending)
  assert.deepEqual(JSON.parse(calls[1].init.body).pendingCatalog, pending)
  assert.equal(JSON.parse(calls[1].init.body).concert.pendingCatalog, undefined)
  await client.save(concert)
  assert.equal(JSON.parse(calls[3].init.body).pendingCatalog, undefined)
  assert.equal(calls.length, 4)
})

test('expired source proofs expose bounded field guidance without replaying the save',async()=>{
 let saves=0
 const client=createCollectionClient('https://api.spectacles.test',async url=>{
  if(url.endsWith('csrf-cookie'))return new Response(null,{status:204})
  saves++;return Response.json({errors:{'pendingCatalog.event.selection':['Select these provider details again or use manual creation.'],'pendingCatalog.tours.0.selection':['Select this Tour again.']}},{status:422})
 },()=> 'XSRF-TOKEN=fixture')
 await assert.rejects(client.save({id}),e=>e.status===422 && e.message==='Performance: Select these provider details again or use manual creation. Tour: Select this Tour again.')
 assert.equal(saves,1)
})
