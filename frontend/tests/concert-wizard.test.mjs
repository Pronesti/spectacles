import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createWizard, moveWizard, finishWizard, adoptWizardEvent, detachWizardEvent, changeWizardEntry, changeArtistInput, changeSongInput, changeTourInput, changeWizardArtists, changeWizardTours, resolveWizardTour, keepWizardEvent, wizardStepError, beginWizardEventChange, selectManualWizardEvent } from '../src/concertWizard.ts'
import { id, catalogFixture } from './catalog-fixtures.mjs'
import { decodeCatalogEvent } from '../src/catalog.ts'
test('Back retains one draft; finalization collects only referenced identities outside durable rows', () => {
  let state = createWizard()
  const artist = { key: id(80), input: { name: 'Soda Stereo', type: 'group', disambiguation: null, selection: 'proof' } }
  state.draft.artists = [{ name: artist.input.name, catalogId: null, draft: artist }]
  state.draft.precision = 'unknown'; state.draft.timezoneText = ''; state.draft.concert.notes = 'Keep this note'
  state.draft.concert.setlist = [{ id: id(81), type: 'song', title: 'Eres', artist: artist.input.name, tape: true, performerDraft: artist }]
  state = moveWizard(moveWizard(state, 'tour'), 'artist')
  const body = finishWizard(state)
  assert.equal(state.draft.concert.notes, 'Keep this note')
  assert.deepEqual(Object.keys(body.pendingCatalog.artists), [id(80)])
  assert.equal(body.pendingCatalog.lineup['0'], id(80)); assert.equal(body.pendingCatalog.entries[id(81)].artist, id(80))
  assert.equal(body.concert.setlist[0].performerDraft, undefined); assert.equal(body.concert.setlist[0].songCatalogId, undefined)
})
test('Event adoption replaces lineage, prunes abandoned pending identities and retains personal data', () => {
  const state = createWizard()
  state.draft.artists = [{ name: 'Abandoned', catalogId: null, draft: { key: id(90), input: { name: 'Abandoned', type: null, disambiguation: null, selection: 'unused' } } }]
  state.draft.concert.notes = 'Personal'
  state.draft.concert.setlist = [{ id: id(91), type: 'note', title: 'Encore', artist: '', tape: false }]
  const adopted = adoptWizardEvent(state, decodeCatalogEvent(catalogFixture().detail), [])
  assert.equal(adopted.draft.concert.notes, 'Personal'); assert.deepEqual(adopted.draft.concert.setlist, state.draft.concert.setlist)
  const body = finishWizard(adopted)
  assert.equal(body.pendingCatalog, undefined); assert.deepEqual(body.concert.catalog.artistIds, [id(1), id(2)])
  assert.equal(detachWizardEvent(adopted).draft.eventId, undefined); assert.equal(adopted.draft.eventId, id(7))
  assert.equal(state.draft.artists[0].name, 'Abandoned')
})
test('editing each label detaches only that identity; notes clear both and tape', () => {
  const entry = { id: id(92), type: 'song', title: 'Eres', artist: 'Café Tacvba', tape: true, songCatalogId: id(4), artistCatalogId: id(1), songDraft: { key: id(93), input: { title: 'Eres', disambiguation: null, selection: 'proof' } }, performerDraft: { key: id(94), input: { name: 'Café Tacvba', type: null, disambiguation: null, selection: null } } }
  const changed = changeWizardEntry(entry, { title: 'My title' })
  assert.equal(changed.songDraft, undefined); assert.equal(changed.songCatalogId, undefined); assert.deepEqual(changed.performerDraft, entry.performerDraft)
  assert.equal(changeWizardEntry(entry, { artist: 'Else' }).performerDraft, undefined)
  const note = changeWizardEntry(entry, { type: 'note' }); assert.equal(note.tape, false); assert.equal(note.artist, ''); assert.equal(note.songCatalogId, undefined); assert.equal(note.performerDraft, undefined)
  const input = { name: 'Soda', type: 'group', disambiguation: null, selection: 'proof' }
  assert.equal(changeArtistInput(input, { name: 'Other' }).selection, null); assert.equal(changeArtistInput(input, { type: 'person' }).selection, 'proof')
  assert.equal(changeSongInput({ title: 'Eres', selection: 'proof', disambiguation: null }, { title: 'Other' }).selection, null)
})
test('empty optional steps, seconds, repeated songs and unknown facts survive', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.timezoneText = ''; state.draft.concert.title = 'Theatre'; state.draft.concert.eventType = 'theatre'; state.draft.concert.time = '00:00:07'
  state.draft.concert.setlist = [1, 2].map(n => ({ id: id(100 + n), type: 'song', title: 'Repeated', artist: '', tape: false, songCatalogId: id(4) }))
  const body = finishWizard(state)
  assert.deepEqual(body.concert.date, { year: null, month: null, day: null }); assert.equal(body.concert.time, '00:00:07'); assert.equal(body.concert.timezone, null); assert.deepEqual(body.concert.setlist.map(r => r.id), [id(101), id(102)])
})
test('same names retain different identities, duplicate identities and conflicting keys fail', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Festival'
  state.draft.artists = [{ name: 'Same', catalogId: id(1) }, { name: 'Same', catalogId: id(2) }]
  assert.equal(finishWizard(state).concert.artists.length, 2)
  state.draft.artists[1].catalogId = id(1); assert.throws(() => finishWizard(state), /once/)
})

const tourChoice = (number = 80, mbid = '23aa9d94-ffc6-4160-a7f3-31cfba1ea646') => ({
  name: 'The Eras Tour', catalogId: null,
  draft: { key: id(number), mbid, input: { name: 'The Eras Tour', selection: 'series-proof' } },
})
test('Tour-only sidecars preserve slot mapping and never leak draft data into Concert choices', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  state.draft.tours = [{ name: 'Existing', catalogId: id(5) }, tourChoice()]
  const body = finishWizard(state)
  assert.deepEqual(body.pendingCatalog.tourChoices, { '1': id(80) })
  assert.deepEqual(body.pendingCatalog.tours, { [id(80)]: { name: 'The Eras Tour', selection: 'series-proof' } })
  assert.deepEqual(body.concert.catalog.tours, [
    { name: 'Existing', catalogId: id(5) }, { name: 'The Eras Tour', catalogId: null },
  ])
  assert.equal(JSON.stringify(body.concert).includes('series-proof'), false)
  assert.equal(JSON.stringify(body.concert).includes('draft'), false)
})
test('removed Tours and adopted Events prune pending Tour metadata while retaining personal data', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  state.draft.tours = [tourChoice()]; state.draft.concert.notes = 'Keep notes'
  const adopted = adoptWizardEvent(state, decodeCatalogEvent(catalogFixture().detail), [])
  assert.equal(finishWizard(adopted).pendingCatalog, undefined)
  assert.equal(adopted.draft.concert.notes, 'Keep notes')
  state.draft.tours = []
  assert.equal(finishWizard(state).pendingCatalog, undefined)
})
test('Tour name edits become manual and duplicates use identity rather than name', () => {
  const input = { name: 'The Eras Tour', selection: 'series-proof' }
  assert.equal(changeTourInput(input, { name: 'My Tour' }).selection, null)
  assert.equal(input.selection, 'series-proof')
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  state.draft.tours = [tourChoice(80), tourChoice(81)]
  assert.throws(() => finishWizard(state), /once/)
  state.draft.tours[1] = tourChoice(81, id(99))
  assert.equal(finishWizard(state).concert.catalog.tours.length, 2)
})

test('empty and plain manual Tours need no sidecar; selected Event rejects an injected pending Tour', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  assert.equal(finishWizard(state).pendingCatalog, undefined)
  state.draft.tours = [{ name: 'Manual Tour', catalogId: null }]
  assert.equal(finishWizard(state).pendingCatalog, undefined)
  const adopted = adoptWizardEvent(state, decodeCatalogEvent(catalogFixture().detail), [])
  adopted.draft.tours = [tourChoice()]
  assert.throws(() => finishWizard(adopted), /Tour identity/)
})

test('Tour draft keys cannot collide with Artist or Song and cleared proofs serialize manual input', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  const tour = tourChoice(); state.draft.tours = [tour]
  state.draft.artists = [{ name: 'Artist', catalogId: null, draft: { key: tour.draft.key, input: { name: 'Artist', type: null, disambiguation: null, selection: null } } }]
  assert.throws(() => finishWizard(state), /distinct/)
  state.draft.artists = []
  state.draft.concert.setlist = [{ id: id(90), type: 'song', title: 'Song', artist: '', tape: false, songDraft: { key: tour.draft.key, input: { title: 'Song', disambiguation: null, selection: null } } }]
  assert.throws(() => finishWizard(state), /distinct/)
  state.draft.concert.setlist = []
  tour.name = 'Manual'; tour.draft.input = changeTourInput(tour.draft.input, { name: 'Manual' })
  const body = finishWizard(state)
  assert.deepEqual(body.pendingCatalog.tours[tour.draft.key], { name: 'Manual', selection: null })
  assert.deepEqual(body.concert.catalog.tours, [{ name: 'Manual', catalogId: null }])
})


test('upstream changes preserve linked choices and guard only their owning steps', () => {
  const original = createWizard()
  original.draft.artists = [{ name: 'Original Artist', catalogId: id(301) }]
  original.draft.tours = [{ name: 'Original Tour', catalogId: id(302) }]
  original.draft.eventId = id(303)
  original.draft.concert.notes = 'Personal notes'
  original.draft.concert.setlist = [{ id: id(304), type: 'note', title: 'Encore', artist: '', tape: false }]
  const changed = changeWizardArtists(original, [{ name: 'New Artist', catalogId: id(305) }])
  assert.equal(wizardStepError(changed, 'artist'), null)
  assert.equal(moveWizard(changed, 'tour').step, 'tour')
  assert.deepEqual(changed.draft.tours, original.draft.tours)
  assert.equal(changed.draft.eventId, original.draft.eventId)
  assert.match(wizardStepError(changed, 'tour'), /previous Tour/)
  assert.match(wizardStepError(changed, 'event'), /selected Event/)
  assert.throws(() => finishWizard(changed), /previous Tour/)
  const withoutTour = resolveWizardTour(changed, false)
  assert.deepEqual(withoutTour.draft.tours, [])
  assert.equal(wizardStepError(withoutTour, 'tour'), null)
  assert.equal(withoutTour.draft.eventId, original.draft.eventId)
  assert.ok(withoutTour.draft.lineupReview.event)
  const detached = detachWizardEvent(withoutTour)
  assert.equal(detached.draft.eventId, undefined)
  assert.equal(detached.draft.lineupReview, undefined)
  assert.equal(detached.draft.concert.notes, 'Personal notes')
  assert.deepEqual(detached.draft.concert.setlist, original.draft.concert.setlist)
  assert.deepEqual(keepWizardEvent(withoutTour).draft, original.draft)
  assert.deepEqual(resolveWizardTour(changed, true).draft, original.draft)
  assert.deepEqual(changeWizardArtists(changed, original.draft.artists).draft, original.draft)
  assert.equal(original.draft.lineupReview, undefined)
})

test('Tour changes defer linked Event replacement and preserve the first rollback context', () => {
  const original = createWizard()
  original.draft.artists = [{ name: 'Artist', catalogId: id(310) }]
  original.draft.tours = [{ name: 'Original Tour', catalogId: id(311) }]
  original.draft.providerEvent = { key: id(312), ref: { provider: 'setlist_fm', resourceType: 'setlist', externalId: 'abc' }, selection: 'proof' }
  const first = changeWizardTours(original, [{ name: 'New Tour', catalogId: id(313) }])
  const second = changeWizardTours(first, [])
  assert.equal(wizardStepError(second, 'tour'), null)
  assert.ok(second.draft.providerEvent)
  assert.match(wizardStepError(second, 'event'), /selected Event/)
  assert.deepEqual(keepWizardEvent(second).draft, original.draft)
  assert.deepEqual(changeWizardTours(first, original.draft.tours).draft, original.draft)
})


test('explicit Event change clears its binding and requires a fresh choice while preserving personal draft data', () => {
 const state=createWizard()
 state.draft.eventId=id(350)
 state.draft.concert.notes='Personal notes'
 state.draft.concert.setlist=[{id:id(351),type:'note',title:'Encore',artist:'',tape:false}]
 const changed=beginWizardEventChange(state)
 assert.equal(changed.draft.eventId,undefined)
 assert.equal(changed.draft.eventChoicePending,true)
 assert.equal(changed.draft.concert.notes,'Personal notes')
 assert.deepEqual(changed.draft.concert.setlist,state.draft.concert.setlist)
 assert.match(wizardStepError(changed,'event'),/Choose an Event/)
 assert.equal(detachWizardEvent(changed).draft.eventChoicePending,undefined)
 assert.equal(adoptWizardEvent(changed,decodeCatalogEvent(catalogFixture().detail),[]).draft.eventChoicePending,undefined)
 assert.equal(state.draft.eventId,id(350))
})


test('manual Event choice uses the typed name and default exact precision without inventing a day', () => {
 const state=createWizard();state.draft.eventId=id(360);state.draft.precision='month';state.draft.dateText='2026-11';state.draft.concert.notes='Keep'
 const manual=selectManualWizardEvent(state,' My Event ')
 assert.equal(manual.draft.manualEvent,true);assert.equal(manual.draft.eventId,undefined)
 assert.equal(manual.draft.concert.title,'My Event');assert.equal(manual.draft.precision,'exact');assert.equal(manual.draft.dateText,'')
 assert.equal(manual.draft.concert.notes,'Keep')
 assert.equal(beginWizardEventChange(manual).draft.manualEvent,undefined)
 assert.equal(adoptWizardEvent(manual,decodeCatalogEvent(catalogFixture().detail),[]).draft.manualEvent,undefined)
})
