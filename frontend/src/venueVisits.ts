import { relation } from './domain.ts'
import type { Workspace, Venue } from './domain'

export type VenueVisit = { venue: Venue; count: number }
export type LocatedVenueVisit = VenueVisit & { venue: Venue & { lat: number; lng: number } }

export function visitedVenues(workspace: Workspace, today: string): VenueVisit[] {
  const counts = new Map<string, number>()
  for (const concert of workspace.concerts) {
    if (concert.venueId && relation(concert.date, today) === 'past') {
      counts.set(concert.venueId, (counts.get(concert.venueId) ?? 0) + 1)
    }
  }
  return workspace.venues.flatMap(venue => {
    const count = counts.get(venue.id)
    return count ? [{ venue, count }] : []
  }).sort((a, b) => b.count - a.count || a.venue.name.localeCompare(b.venue.name))
}

export function hasVenueCoordinates(visit: VenueVisit): visit is LocatedVenueVisit {
  const { lat, lng } = visit.venue
  return lat !== null && lng !== null && Number.isFinite(lat) && Number.isFinite(lng)
    && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
}
