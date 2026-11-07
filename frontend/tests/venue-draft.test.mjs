import assert from 'node:assert/strict'
import { test } from 'node:test'
import { prepareVenueDraft, validVenueDraft, applyVenueDraft } from '../src/venueDraft.ts'
import { createWizard } from '../src/concertWizard.ts'
import { venue } from './provider-fixtures.mjs'
const editor = { name: ' Hall ', city: '', address: ' Street ', aliasesText: 'Hall\nAlternative\nHall', lat: '0', lng: '0', selection: null }
test('Venue confirmation preserves unknown city and zero coordinates without mutating unfinished input', () => {
 const prepared = prepareVenueDraft(editor)
 assert.equal(prepared.values.city, '')
 assert.equal(prepared.values.name, 'Hall')
 assert.deepEqual([prepared.values.lat, prepared.values.lng], [0, 0])
 assert.deepEqual(prepared.values.aliases, ['Hall','Alternative'])
 const state = createWizard()
 const next = applyVenueDraft(state, prepared)
 assert.equal(next.draft.pendingVenue.name, 'Hall')
 assert.equal(next.draft.concert.venueId, next.draft.pendingVenue.id)
 assert.equal(state.draft.pendingVenue, undefined)
 assert.equal(editor.name, ' Hall ')
})
test('invalid coordinates and aliases cannot be confirmed through Continue', () => {
 for(const patch of [{name:' '}, {lat:'-'}, {lat:'91'}, {lng:'181'}, {lat:'',lng:'1'}, {aliasesText:'a'.repeat(256)}]) {
  const draft = {...editor,...patch}
  assert.equal(validVenueDraft(draft), false)
  assert.throws(() => prepareVenueDraft(draft))
 }
 assert.equal(validVenueDraft({...editor,lat:'',lng:''}), true)
})
test('Venue confirmation retains provider provenance and manual confirmation removes the old binding', () => {
 const state=createWizard()
 const selected=prepareVenueDraft({...editor,selection:venue.selection,ref:venue.ref,url:venue.url,metadata:venue.values})
 const linked=applyVenueDraft(state,selected)
 assert.equal(linked.draft.providerVenue.selection,venue.selection)
 assert.deepEqual(linked.draft.providerVenue.ref,venue.ref)
 const manual=applyVenueDraft(linked,prepareVenueDraft(editor))
 assert.equal(manual.draft.providerVenue,undefined)
 assert.equal(manual.draft.pendingVenue.city,'')
})
