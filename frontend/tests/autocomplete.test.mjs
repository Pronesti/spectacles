import assert from 'node:assert/strict'
import { test } from 'node:test'
import { autocompleteKey } from '../src/autocomplete.ts'
test('keyboard requires explicit active identity, suppresses Enter save, and respects IME', () => {
  const keys = ['catalog:artists:a', 'musicbrainz:artist:b', 'create'], initial = { open: false, activeKey: null }
  const first = autocompleteKey(initial, 'ArrowDown', keys, false)
  assert.equal(first.state.activeKey, keys[0])
  const second = autocompleteKey(first.state, 'ArrowDown', keys, false)
  assert.equal(autocompleteKey(second.state, 'Enter', keys, false).pickKey, keys[1])
  assert.equal(autocompleteKey(second.state, 'Enter', keys, true).pickKey, null)
  assert.equal(autocompleteKey(second.state, 'Escape', keys, false).state.open, false)
  assert.equal(autocompleteKey(initial, 'Enter', keys, false).preventDefault, true)
  assert.equal(autocompleteKey(initial, 'ArrowUp', keys, false).state.activeKey, 'create')
  assert.equal(autocompleteKey(second.state, 'Enter', ['create'], false).pickKey, null)
  assert.equal(autocompleteKey(initial, 'ArrowDown', [], false).state.activeKey, null)
  assert.equal(autocompleteKey(initial, 'x', keys, false).preventDefault, false)
})

test('production entity search browses five, blocks short Unicode queries, and sequences fallback', async () => {
  const { searchEntity } = await import('../src/autocomplete.ts')
  const calls = []
  const run = (query, total = 0, providerEnabled = true) => searchEntity({ query, signal: new AbortController().signal, providerEnabled,
    local: async (q, size) => { calls.push(['local', q, size]); return { total, items: [] } },
    publishLocal: () => calls.push(['published local']),
    provider: async q => { calls.push(['provider', q]); return [] }, publishProvider: () => calls.push(['published provider']) })
  for (const q of ['a', ' ab ', '😀😀']) await run(q)
  assert.deepEqual(calls, [])
  await run('   ')
  assert.deepEqual(calls.splice(0), [['local', '', 5], ['published local']])
  await run(' abc ', 1)
  assert.deepEqual(calls.splice(0), [['local', 'abc', 20], ['published local']])
  await run('😀😀😀')
  assert.deepEqual(calls.splice(0), [['local', '😀😀😀', 20], ['published local'], ['provider', '😀😀😀'], ['published provider']])
  await run('abc', 0, false)
  assert.deepEqual(calls, [['local', 'abc', 20], ['published local']])
})

test('production entity search never falls back on failure or an obsolete zero result', async () => {
  const { searchEntity } = await import('../src/autocomplete.ts')
  const { createLatestRead } = await import('../src/catalogApi.ts')
  let resolve, remote = 0, published = 0
  const options = { query: 'abc', signal: new AbortController().signal, providerEnabled: true,
    local: async () => { throw new Error('offline') }, publishLocal: () => published++, provider: async () => { remote++; return [] }, publishProvider: () => published++ }
  await assert.rejects(searchEntity(options), /offline/)
  assert.equal(remote, 0)
  const read = createLatestRead()
  const pending = read.run(signal => searchEntity({ ...options, signal, local: () => new Promise(done => { resolve = done }) }), () => {}, () => {})
  read.cancel()
  resolve({ total: 0 })
  await pending
  assert.equal(remote, 0); assert.equal(published, 0)
})

test('Tour scope unions persisted and confirmed pending Artists without guessing manual identities', async () => {
  const { tourArtistScope } = await import('../src/autocomplete.ts')
  const confirmed = {name:'Same',catalogId:null,draft:{key:'draft',mbid:'provider-a',input:{selection:'proof'}}}
  assert.deepEqual(tourArtistScope([{name:'Same',catalogId:'local-a'},confirmed,{name:'Manual',catalogId:null},{...confirmed,draft:{...confirmed.draft,input:{selection:null}}},{name:'Duplicate',catalogId:'local-a'}]), {artist_uuids:['local-a'],artist_mbids:['provider-a']})
  assert.deepEqual(tourArtistScope([]), {artist_uuids:[],artist_mbids:[]})
})
