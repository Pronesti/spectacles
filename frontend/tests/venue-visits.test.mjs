import assert from 'node:assert/strict'
import { test } from 'node:test'
import { hasVenueCoordinates, visitedVenues } from '../src/venueVisits.ts'

const venue = (id, lat, lng) => ({ id, name: id, city: id === 'one' ? 'Buenos Aires' : 'London', neighborhood: '', lat, lng })
const show = (id, venueId, date) => ({ id, venueId, date })
const today = '2026-11-03'
const past = { year: 2026, month: 11, day: 2 }

test('visited venues aggregate past visits by identity across cities and exclude future, today, and uncertain dates', () => {
  const workspace = { venues: [venue('two', 51.5, -.1), venue('one', -34.6, -58.4), venue('unused', 0, 0)], concerts: [
    show('a', 'one', past), show('b', 'one', past), show('c', 'two', past),
    show('future', 'two', { year: 2026, month: 11, day: 4 }),
    show('today', 'two', { year: 2026, month: 11, day: 3 }),
    show('unknown', 'two', { year: 2026, month: null, day: null }),
    show('missing', null, past),
  ] }
  assert.deepEqual(visitedVenues(workspace, today).map(({ venue, count }) => [venue.id, count]), [['one', 2], ['two', 1]])
  assert.equal(workspace.concerts.length, 7)
})

test('unlocated visited venues remain in the directory but only valid coordinates produce pins', () => {
  const venues = [venue('missing', null, null), venue('zero', 0, 0), venue('partial', 1, null), venue('invalid', 91, 0), venue('nan', NaN, 0)]
  const visits = visitedVenues({ venues, concerts: venues.map(v => show(v.id, v.id, past)) }, today)
  assert.equal(visits.length, 5)
  assert.deepEqual(visits.filter(hasVenueCoordinates).map(v => v.venue.id), ['zero'])
  assert.equal(visitedVenues({ venues, concerts: [] }, today).length, 0)
})
