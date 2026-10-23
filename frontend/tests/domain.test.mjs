import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createDemoWorkspace, dateKey, daysUntil, decodeWorkspace, filteredConcerts, formatDate, fromKey, groupByDay, moveEntry, relation, sortConcerts, todayKey, uniqueVenues, validDate } from '../src/domain.ts'

const today = '2026-10-22'

test('countdowns use calendar days across daylight-saving boundaries', () => {
  assert.equal(daysUntil(fromKey('2026-11-06'), today), 15)
  assert.equal(daysUntil(fromKey(today), today), 0)
  assert.equal(daysUntil(fromKey('2026-10-21'), today), -1)
  assert.equal(daysUntil(fromKey('2026-03-09'), '2026-03-08'), 1)
  assert.equal(todayKey(new Date('2026-10-23T01:30:00Z')), today)
})

test('partial dates never become invented exact days or countdowns', () => {
  for (const date of [{ year: 2026, month: null, day: null }, { year: 2026, month: 11, day: null }, { year: null, month: null, day: null }]) {
    assert.equal(dateKey(date), null)
    assert.equal(daysUntil(date, today), null)
    assert.equal(relation(date, today), 'unconfirmed')
    assert.match(formatDate(date), /confirm/)
  }
})

test('date validation respects Gregorian leap years and component dependencies', () => {
  assert.equal(validDate(fromKey('2024-02-29')), true)
  assert.equal(validDate(fromKey('2026-02-29')), false)
  assert.equal(validDate(fromKey('1900-02-29')), false)
  assert.equal(validDate(fromKey('2000-02-29')), true)
  assert.equal(validDate({ year: 1, month: 2, day: 29 }), false)
  assert.equal(validDate({ year: 4, month: 2, day: 29 }), true)
  assert.equal(validDate({ year: null, month: 10, day: null }), false)
  assert.equal(validDate({ year: 2026, month: null, day: 1 }), false)
  assert.equal(validDate({ year: 2026, month: 4, day: 31 }), false)
})

test('daily activity aggregates multiple concerts and excludes uncertain dates', () => {
  const workspace = createDemoWorkspace(today)
  const show = workspace.concerts[0]
  const other = { ...show, id: 'second-show' }
  const unknown = { ...show, id: 'unknown', date: { year: 2026, month: null, day: null } }
  const groups = groupByDay([show, other, unknown])
  assert.equal(groups.size, 1)
  assert.equal(groups.get(dateKey(show.date)).length, 2)
})

test('venue pins are based on referenced identities, never per-concert duplicates', () => {
  const workspace = createDemoWorkspace(today)
  assert.equal(uniqueVenues(workspace).length, 6)
  assert.equal(uniqueVenues({ ...workspace, concerts: [workspace.concerts[0], { ...workspace.concerts[0], id: 'repeat' }] }).length, 1)
  assert.equal(uniqueVenues({ ...workspace, concerts: [] }).length, 0)
})

test('setlist reordering preserves notes, repeated songs, and stable identity', () => {
  const entries = [{ id: 'one', type: 'song', title: 'Airbag' }, { id: 'note', type: 'note', title: 'Encore' }, { id: 'repeat', type: 'song', title: 'Airbag' }]
  const moved = moveEntry(entries, 'repeat', -1)
  assert.deepEqual(moved.map(e => e.id), ['one', 'repeat', 'note'])
  assert.deepEqual(entries.map(e => e.id), ['one', 'note', 'repeat'])
  assert.equal(moveEntry(entries, 'one', -1), entries)
  assert.equal(moveEntry(entries, 'missing', 1), entries)
})

test('search and date/venue filters compose without changing the source collection', () => {
  const workspace = createDemoWorkspace(today)
  assert.equal(filteredConcerts(workspace.concerts, workspace.venues, '', 'upcoming', today).length, 4)
  const shows = filteredConcerts(workspace.concerts, workspace.venues, 'monkeys', 'all', today, 'movistar')
  assert.equal(shows.length, 2)
  assert.ok(shows.every(c => c.venueId === 'movistar'))
  assert.equal(filteredConcerts(workspace.concerts, workspace.venues, 'no matching artist', 'all', today).length, 0)
  assert.equal(workspace.concerts.length, 24)
  assert.ok(dateKey(sortConcerts(workspace.concerts)[0].date) < today)
})

test('saved workspace round-trip preserves explicit false, sparse dates, and zero coordinates', () => {
  const workspace = createDemoWorkspace(today)
  workspace.concerts[0].interested = false
  workspace.concerts[0].ticketsBought = true
  workspace.concerts[0].date = { year: 2026, month: null, day: null }
  workspace.venues[0].lat = 0
  workspace.venues[0].lng = 0
  assert.deepEqual(decodeWorkspace(JSON.stringify(workspace)), workspace)
})

test('unreadable saves and broken venue references are rejected without partial hydration', () => {
  assert.equal(decodeWorkspace('{bad json'), null)
  assert.equal(decodeWorkspace(JSON.stringify({ version: 2, concerts: [], venues: [] })), null)
  const workspace = createDemoWorkspace(today)
  workspace.concerts[0].venueId = 'missing'
  assert.equal(decodeWorkspace(JSON.stringify(workspace)), null)
  workspace.concerts[0].venueId = null
  workspace.venues[0].lat = null
  assert.equal(decodeWorkspace(JSON.stringify(workspace)), null)
})
