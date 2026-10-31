import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createDemoWorkspace, decodeWorkspace } from '../src/domain.ts'
import { createConcertDraft, adoptCatalogEvent, adoptCatalogVenue, finishConcertDraft, changeSetlistEntry } from '../src/concertDraft.ts'
import { decodeCatalogEvent } from '../src/catalog.ts'
import { catalogFixture, id } from './catalog-fixtures.mjs'
test('legacy workspaces and new references round-trip without changing local identities', () => {
  const workspace = createDemoWorkspace('2026-10-23')
  assert.deepEqual(decodeWorkspace(JSON.stringify(workspace)), { ...workspace, venues: workspace.venues.map(v => ({ address: null, aliases: [], image: null, ...v })) })
  const original = workspace.concerts[0]
  const draft = adoptCatalogEvent(createConcertDraft(original), decodeCatalogEvent(catalogFixture().detail), workspace.venues)
  draft.concert.notes = 'Keep my local notes'; draft.concert.interested = false
  const result = finishConcertDraft(draft)
  assert.equal(result.concert.id, original.id)
  assert.equal(result.concert.notes, 'Keep my local notes')
  assert.equal(result.concert.interested, false)
  assert.deepEqual(result.concert.setlist, original.setlist)
  assert.equal(result.concert.catalog.eventId, id(7))
  assert.deepEqual(result.concert.catalog.artistIds, [id(1), id(2)])
  assert.deepEqual(result.concert.artists, ['Björk, guest', 'Björk, guest'])
  assert.equal(result.concert.catalog.tours[0].catalogId, id(5))
  assert.equal(result.concert.timezone, null); assert.equal(result.concert.time, '00:00:07')
  workspace.concerts[0] = result.concert; workspace.venues.push(result.venue)
  assert.deepEqual(decodeWorkspace(JSON.stringify(workspace)), { ...workspace, venues: workspace.venues.map(v => ({ address: null, aliases: [], image: null, ...v })) })
})
test('empty lineup and unknown date/timezone remain valid without inventing facts', () => {
  const f = catalogFixture()
  f.event.relationships.artistLinks.data = []
  f.event.relationships.venue.data = null
  f.event.attributes.year = null; f.event.attributes.local_start_time = null
  const result = finishConcertDraft(adoptCatalogEvent(createConcertDraft(), decodeCatalogEvent(f.detail), []))
  assert.deepEqual(result.concert.artists, [])
  assert.deepEqual(result.concert.date, { year: null, month: null, day: null })
  assert.equal(result.concert.venueId, null); assert.equal(result.concert.time, ''); assert.equal(result.concert.timezone, null)
})
test('Venue reuse uses catalog UUID rather than labels and changes only the returned draft', () => {
  const venue = decodeCatalogEvent(catalogFixture().detail).venue
  const locals = [{ id: 'local-a', name: venue.name, city: '', neighborhood: '', lat: null, lng: null }, { id: 'local-b', name: 'Old name', city: '', neighborhood: '', lat: null, lng: null, catalogId: venue.id }]
  const adopted = adoptCatalogVenue(createConcertDraft(), venue, locals)
  assert.equal(adopted.concert.venueId, 'local-b')
  assert.equal(adopted.pendingVenue.id, 'local-b')
  assert.equal(adopted.pendingVenue.lat, 0)
  assert.equal(locals[1].name, 'Old name')
  const separate = adoptCatalogVenue(createConcertDraft(), { ...venue, id: id(99) }, locals)
  assert.notEqual(separate.concert.venueId, 'local-a')
  assert.notEqual(separate.concert.venueId, id(99))
})
test('misaligned or malformed optional references are unreadable rather than partially hydrated', () => {
  const workspace = createDemoWorkspace('2026-10-23')
  workspace.concerts[0].catalog = { eventId: 'not-a-uuid', artistIds: [null], tours: [] }
  assert.equal(decodeWorkspace(JSON.stringify(workspace)), null)
  workspace.concerts[0].catalog = { artistIds: [], tours: [] }
  assert.equal(decodeWorkspace(JSON.stringify(workspace)), null)
  workspace.concerts[0].catalog = { artistIds: [null], tours: [] }
  workspace.concerts[0].setlist = [{ id: 'note', type: 'note', title: 'Encore', artist: '', tape: false, songCatalogId: id(4) }]
  assert.equal(decodeWorkspace(JSON.stringify(workspace)), null)
})

test('Song selection never infers a performer and manual edits detach only the changed identity', () => {
  const entry = { id: 'local-entry', type: 'song', title: 'Original', artist: 'My performer', tape: false }
  const picked = changeSetlistEntry(entry, { title: 'Canción', songCatalogId: id(4) })
  assert.equal(picked.artist, 'My performer')
  const performer = changeSetlistEntry(picked, { artist: 'Björk', artistCatalogId: id(1) })
  assert.equal(performer.songCatalogId, id(4))
  assert.equal(changeSetlistEntry(performer, { title: 'My version' }).songCatalogId, undefined)
  assert.equal(changeSetlistEntry(performer, { artist: 'Someone else' }).artistCatalogId, undefined)
  const note = changeSetlistEntry(performer, { type: 'note' })
  assert.equal(note.songCatalogId, undefined); assert.equal(note.artistCatalogId, undefined)
})

test('new concert starts with unknown timezone and existing timezone is preserved', () => {
  const fresh = createConcertDraft()
  assert.equal(fresh.timezoneText, '')
  assert.equal(fresh.concert.timezone, null)
  fresh.precision = 'unknown'; fresh.artists = [{ name: 'Maná', catalogId: null }]
  assert.equal(finishConcertDraft(fresh).concert.timezone, null)
  const existing = { ...fresh.concert, timezone: 'America/Los_Angeles' }
  assert.equal(createConcertDraft(existing).timezoneText, 'America/Los_Angeles')
})
