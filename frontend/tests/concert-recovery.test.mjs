import assert from 'node:assert/strict'
import { test } from 'node:test'
import { openRecovery, decodeRecovery, recoveryKey } from '../src/concertRecovery.ts'
import {applyPerformance,performanceSummary} from '../src/providerDraft.ts'
import {performance,venue} from './provider-fixtures.mjs'
const A = '00000000-0000-4000-8000-000000000001', B = '00000000-0000-4000-8000-000000000002'
const id = n => `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`
const store = () => { const map = new Map(); return { map, getItem: key => map.get(key) ?? null, setItem: (key, value) => map.set(key, value), removeItem: key => map.delete(key) } }
const artist = { name: '', type: '', disambiguation: 'unfinished', selection: 'opaque-public-identity-proof' }
test('valid version one migrates in memory and writes version two only on an edit',()=>{
 const storage=store(),base=openRecovery(A,undefined,storage).snapshot
 const old={...base,version:1,fields:{tourEditor:{input:{name:'Legacy Tour ',selection:'legacy-proof'},mbid:id(50),context:'Keep'}}}
 const raw=JSON.stringify(old);storage.setItem(recoveryKey(A),raw)
 const recovered=openRecovery(A,undefined,storage)
 assert.equal(recovered.snapshot.version,2);assert.equal(recovered.snapshot.fields.tourEditor.input.name,'Legacy Tour ')
 assert.equal(storage.getItem(recoveryKey(A)),raw)
 recovered.changeField('eventQuery','new edit')
 assert.equal(JSON.parse(storage.getItem(recoveryKey(A))).version,2);assert.equal(storage.map.size,1)
})
test('provider group and every unfinished Venue field survive without source result caches',()=>{
 const storage=store(),first=openRecovery(A,undefined,storage)
 first.changeState(applyPerformance(first.snapshot.state,performance,[]))
 first.changeField('performancePreview',performanceSummary(performance))
 first.changeField('performanceFilters',{period:'upcoming',artistMbid:performance.artist.ref.externalId})
 first.changeField('venueHistoryTab','upcoming')
 first.changeField('venueEditor',{name:'Arena Ñ ',city:'',address:'Humboldt ',aliasesText:'Alias one\nAlias two ',lat:'-',lng:'.',selection:'public-proof',ref:performance.venue.ref,metadata:venue.values,linkedRefs:venue.linkedRefs,url:venue.url})
 const restored=openRecovery(A,undefined,storage)
 assert.equal(restored.restored,true);assert.equal(restored.snapshot.state.draft.providerEvent.ref.externalId,'b4cc196')
 assert.equal(restored.snapshot.fields.venueEditor.lat,'-');assert.equal(restored.snapshot.fields.venueEditor.aliasesText,'Alias one\nAlias two ')
 assert.deepEqual(restored.snapshot.fields.venueEditor.linkedRefs,venue.linkedRefs)
 assert.deepEqual(restored.snapshot.fields.venueEditor.metadata,venue.values)
 assert.equal(restored.snapshot.fields.performancePreview.ref.externalId,'b4cc196')
 assert.equal(restored.snapshot.fields.performancePreview.localEvent,undefined)
 assert.equal(restored.snapshot.fields.performancePreview.selection,undefined)
 assert.equal(restored.snapshot.fields.venueHistoryTab,'upcoming')
 const raw=storage.getItem(recoveryKey(A));assert.doesNotMatch(raw,/"(?:localEvent|items|html|cookie|csrf)":/)
 const bad=JSON.parse(raw);bad.version=3
 assert.equal(decodeRecovery(JSON.stringify(bad),A),null)
})
test('normalized selected summaries and metadata reject wrong kinds, impossible dates and hidden raw data',()=>{
 const storage=store(),draft=openRecovery(A,undefined,storage)
 draft.changeState(applyPerformance(draft.snapshot.state,performance,[]))
 draft.changeField('performancePreview',performanceSummary(performance))
 const snapshot=draft.snapshot
 for(const mutate of [v=>{v.fields.performancePreview.date.day=40},v=>{v.fields.performancePreview.url='https://evil.example'},v=>{v.state.draft.providerEvent.ref=performance.artist.ref},v=>{v.fields.venueEditor={name:'x',city:'',address:'',aliasesText:'',lat:'',lng:'',selection:null,metadata:{...venue.values,rawResponse:'never'}}}]){
  const value=structuredClone(snapshot);mutate(value);assert.equal(decodeRecovery(JSON.stringify(value),A),null)
 }
})
test('dismiss/remount and reload retain step, incomplete raw input and stable identities, overriding calendar date', () => {
  const storage = store(), first = openRecovery(A, '2026-10-25', storage), state = first.snapshot.state
  state.step = 'event'; state.draft.precision = 'year'; state.draft.dateText = '20'; state.draft.lat = '-'; state.draft.lng = '.'; state.draft.concert.title = ''; state.draft.concert.notes = 'Keep exact whitespace  '
  state.draft.concert.setlist = [{ id: id(3), type: 'song', title: 'Repeat', artist: '', tape: false }, { id: id(4), type: 'song', title: 'Repeat', artist: '', tape: true }]
  first.changeState(state)
  const reopened = openRecovery(A, '2030-01-01', storage)
  assert.deepEqual(reopened.snapshot.state, JSON.parse(JSON.stringify(state))); assert.equal(reopened.restored, true)
  assert.equal(reopened.snapshot.state.draft.concert.id, state.draft.concert.id)
  assert.match(reopened.notice, /restored/)
})
test('every unconfirmed editor, pending semantic action and query survives without callbacks or result caches', () => {
  const storage = store(), first = openRecovery(A, undefined, storage)
  const values = {
    artistEditor: { input: artist, mbid: id(7) }, artistDecision: { add: { name: 'Same', catalogId: id(8) } },
    tourEditor: { input: { name: 'Partial tour ', selection: 'series-proof' }, mbid: id(9), context: 'Tour' }, tourDecision: { remove: 0 },
    row: { id: id(5), type: 'song', title: '', artist: 'Part', tape: true },
    songEditor: { title: '', disambiguation: 'part', selection: 'work-proof' }, performerEditor: artist,
    artistQuery: 'Art', tourQuery: 'To', eventQuery: 'Ev', venueQuery: 'Ve', songQuery: 'So', performerQuery: 'Pe', eventPreviewId: id(6),
  }
  for (const [name, value] of Object.entries(values)) first.changeField(name, value)
  assert.deepEqual(openRecovery(A, undefined, storage).snapshot.fields, values)
  assert.equal(storage.map.size, 1)
})
test('owner is bound once: another account never restores or receives writes/deletes from the original controller', () => {
  const storage = store(), a = openRecovery(A, undefined, storage)
  a.changeField('artistQuery', 'Only A'); const b = openRecovery(B, undefined, storage)
  assert.equal(b.snapshot.fields.artistQuery, undefined)
  b.changeField('artistQuery', 'Only B'); a.changeField('artistQuery', 'Late A')
  assert.equal(openRecovery(B, undefined, storage).snapshot.fields.artistQuery, 'Only B')
  a.clear(); assert.equal(openRecovery(B, undefined, storage).snapshot.fields.artistQuery, 'Only B')
  assert.equal(decodeRecovery(JSON.stringify(b.snapshot), A), null)
  assert.equal(openRecovery('guest', undefined, storage).restored, false)
})
test('explicit discard/confirmed success clear only the creation key; no later stale write resurrects it', () => {
  const storage = store(); storage.setItem('spectacles.preview.v1', 'untouched')
  for (const outcome of ['discard', 'confirmed-save']) {
    const draft = openRecovery(A, undefined, storage), oldId = draft.snapshot.state.draft.concert.id
    draft.changeField('artistQuery', outcome); assert.equal(draft.clear(), true)
    draft.changeField('artistQuery', 'late child callback'); draft.changeState(draft.snapshot.state)
    assert.equal(storage.getItem(recoveryKey(A)), null)
    assert.notEqual(openRecovery(A, undefined, storage).snapshot.state.draft.concert.id, oldId)
  }
  assert.equal(storage.getItem('spectacles.preview.v1'), 'untouched')
})
test('failed or uncertain save leaves current state and sidecars recoverable without submission/replay', () => {
  const storage = store(), draft = openRecovery(A, undefined, storage)
  const state = draft.snapshot.state
  state.draft.artists = [{ name: 'Artist', catalogId: null, draft: { key: id(10), mbid: id(11), input: { ...artist, name: 'Artist' } } }]
  draft.changeState(state)
  // A rejected onSave does not call clear; recovery has no transport or submit capability.
  assert.deepEqual(openRecovery(A, undefined, storage).snapshot.state, JSON.parse(JSON.stringify(state)))
})
test('corrupt, unknown-version and oversized storage is preserved until explicit discard, including after edits', () => {
  for (const raw of ['{', JSON.stringify({ version: 2 }), 'x'.repeat(2_000_001)]) {
    const storage = store(); storage.setItem(recoveryKey(A), raw)
    const draft = openRecovery(A, undefined, storage)
    draft.changeField('artistQuery', 'New text'); assert.equal(storage.getItem(recoveryKey(A)), raw)
    assert.match(draft.notice, /left untouched/); assert.equal(draft.clear(), true)
  }
})
test('strict decoder rejects unknown keys, credentials, malformed UUIDs, enums, bounds and callback descriptors', () => {
  const base = openRecovery(A, undefined, store()).snapshot
  const invalid = mutate => { const value = structuredClone(base); mutate(value); assert.equal(decodeRecovery(JSON.stringify(value), A), null) }
  invalid(v => { v.token = 'never' }); invalid(v => { v.fields.secret = 'never' })
  invalid(v => { v.state.step = 'publish' }); invalid(v => { v.state.draft.concert.id = 'bad' })
  invalid(v => { v.fields.artistQuery = 'x'.repeat(201) }); invalid(v => { v.state.draft.artists = Array(101).fill({ name: '', catalogId: null }) })
  invalid(v => { v.fields.artistDecision = { callback: 'eval()' } }); invalid(v => { v.fields.artistEditor = { input: { ...artist, selection: 'x'.repeat(4097) } } })
  invalid(v => { v.state.draft.concert.interested = 'true' })
})
test('read/quota/removal failures retain editing and give actionable feedback; failed discard does not freeze editing', () => {
  const storage = { getItem() { throw Error('blocked') }, setItem() { throw Error('quota') }, removeItem() { throw Error('blocked') } }
  const draft = openRecovery(A, undefined, storage)
  assert.match(draft.notice, /unavailable/)
  draft.changeField('artistQuery', 'Kept'); assert.equal(draft.snapshot.fields.artistQuery, 'Kept'); assert.match(draft.notice, /latest changes/)
  assert.equal(draft.clear(), false)
  draft.changeField('artistQuery', 'Still editable'); assert.equal(draft.snapshot.fields.artistQuery, 'Still editable')
})
test('canonical Venue navigation survives creation, history filters and unfinished editor recovery',()=>{
 const storage=store(), first=openRecovery(A,undefined,storage)
 const sources=[{externalId:venue.ref.externalId,url:venue.url},{externalId:'63d626d3',url:'https://www.setlist.fm/venue/rosemont-horizon-rosemont-il-usa-63d626d3.html'}]
 const preview={...performance,venue:{...venue,linkedRefs:[{provider:'setlist_fm',resourceType:'venue',externalId:'63d626d3'}],venueUrl:venue.url,venueSources:sources}}
 first.changeState(applyPerformance(first.snapshot.state,preview,[]))
 first.changeField('historyPerformanceFilters',{period:'past',venueId:venue.ref.externalId,venueUrl:venue.url})
 first.changeField('venueEditor',{name:'Arena',city:'',address:'',aliasesText:'',lat:'',lng:'',selection:venue.selection,ref:venue.ref,metadata:venue.values,linkedRefs:preview.venue.linkedRefs,url:venue.url,venueUrl:venue.url,venueSources:sources})
 const restored=openRecovery(A,undefined,storage)
 assert.equal(restored.restored,true)
 assert.equal(restored.snapshot.state.draft.providerVenue.venueUrl,venue.url)
 assert.equal(restored.snapshot.fields.historyPerformanceFilters.venueUrl,venue.url)
 assert.equal(restored.snapshot.fields.venueEditor.venueUrl,venue.url)
 assert.deepEqual(restored.snapshot.fields.venueEditor.venueSources,sources)
 assert.deepEqual(restored.snapshot.state.draft.providerVenue.venueSources,sources)
})
