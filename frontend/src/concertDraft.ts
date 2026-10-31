import { dateKey, validDate } from './domain.ts'
import type { CatalogChoice, Concert, EventDate, SetlistEntry, Venue } from './domain.ts'
import type { CatalogEvent, CatalogVenue } from './catalog.ts'
export type Precision = 'exact' | 'month' | 'year' | 'unknown'
export type ConcertDraft = { concert: Concert; artists: CatalogChoice[]; tours: CatalogChoice[]; eventId?: string; precision: Precision; dateText: string; timezoneText: string; pendingVenue?: Venue; venueName: string; city: string; lat: string; lng: string }
function dateInput(date: EventDate): { precision: Precision; dateText: string } {
  if (date.year === null) return { precision: 'unknown', dateText: '' }
  const year = String(date.year).padStart(4, '0')
  if (date.month === null) return { precision: 'year', dateText: year }
  if (date.day === null) return { precision: 'month', dateText: `${year}-${String(date.month).padStart(2, '0')}` }
  return { precision: 'exact', dateText: dateKey(date)! }
}
export function createConcertDraft(concert?: Concert, initialDate?: string): ConcertDraft {
  const base: Concert = concert ?? { id: crypto.randomUUID(), title: '', artists: [], venueId: null, date: { year: null, month: null, day: null }, time: '', timezone: null, interested: true, ticketsBought: false, notes: '', setlist: [] }
  const date = concert ? dateInput(concert.date) : { precision: 'exact' as const, dateText: initialDate ?? '' }
  return { concert: { ...base }, artists: base.artists.map((name, index) => ({ name, catalogId: base.catalog?.artistIds[index] ?? null })), tours: [...(base.catalog?.tours ?? [])], eventId: base.catalog?.eventId, ...date, timezoneText: base.timezone ?? '', venueName: '', city: 'Buenos Aires', lat: '', lng: '' }
}
export function adoptCatalogVenue(draft: ConcertDraft, venue: CatalogVenue, locals: Venue[]): ConcertDraft {
  const known = locals.find(local => local.catalogId === venue.id)
  const pending: Venue = { id: known?.id ?? crypto.randomUUID(), catalogId: venue.id, name: venue.name, city: venue.city ?? '', neighborhood: '', lat: venue.lat, lng: venue.lng, timezone: venue.timezone, address: venue.address ?? null, aliases: venue.aliases ?? [], image: venue.image ?? null, region: venue.region, countryCode: venue.country_code }
  return { ...draft, eventId: undefined, concert: { ...draft.concert, venueId: pending.id }, pendingVenue: pending }
}
export function adoptCatalogEvent(draft: ConcertDraft, event: CatalogEvent, locals: Venue[]): ConcertDraft {
  const withVenue = event.venue ? adoptCatalogVenue(draft, event.venue, locals) : { ...draft, concert: { ...draft.concert, venueId: null }, pendingVenue: undefined }
  return { ...withVenue, eventId: event.id, concert: { ...withVenue.concert, title: event.title, eventType: event.type, time: event.time ?? '' }, artists: event.artists.map(a => ({ name: a.name, catalogId: a.id })), tours: event.tours.map(t => ({ name: t.name, catalogId: t.id })), ...dateInput(event.date), timezoneText: event.timezone ?? '' }
}
export function finishConcertDraft(draft: ConcertDraft): { concert: Concert; venue?: Venue } {
  if (draft.artists.some(artist => !artist.name.trim())) throw new Error('Enter each artist name.')
  const artists = draft.artists.map(a => a.catalogId ? a.name : a.name.trim())
  const title = draft.eventId ? draft.concert.title : draft.concert.title.trim() || artists.join(' & ')
  if (!title) throw new Error('Enter a title or add an artist.')
  const parts = draft.dateText.split('-').map(Number)
  const date: EventDate = { year: draft.precision === 'unknown' ? null : parts[0], month: draft.precision === 'exact' || draft.precision === 'month' ? parts[1] : null, day: draft.precision === 'exact' ? parts[2] : null }
  if (!validDate(date)) throw new Error('Enter a valid date at the selected precision.')
  if (draft.concert.time !== '' && !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(draft.concert.time)) throw new Error('Enter a valid local time.')
  const timezone = draft.timezoneText.trim() || null
  if (timezone !== null) { try { new Intl.DateTimeFormat('en', { timeZone: timezone }) } catch { throw new Error('Enter a valid IANA timezone or leave it unknown.') } }
  let venue = draft.pendingVenue
  if (draft.concert.venueId === 'new') {
    if (!draft.venueName.trim()) throw new Error('Enter the venue name.')
    if ((draft.lat === '') !== (draft.lng === '') || draft.lat !== '' && (!Number.isFinite(Number(draft.lat)) || !Number.isFinite(Number(draft.lng)) || Math.abs(Number(draft.lat)) > 90 || Math.abs(Number(draft.lng)) > 180)) throw new Error('Supply both valid coordinates or leave both empty.')
    venue = { id: crypto.randomUUID(), name: draft.venueName.trim(), city: draft.city.trim(), neighborhood: '', lat: draft.lat === '' ? null : Number(draft.lat), lng: draft.lng === '' ? null : Number(draft.lng) }
  }
  return { concert: { ...draft.concert, title, artists, venueId: venue?.id ?? draft.concert.venueId, date, timezone, notes: draft.concert.notes.trim(), catalog: { ...(draft.eventId ? { eventId: draft.eventId } : {}), artistIds: draft.artists.map(a => a.catalogId), tours: draft.tours.map(t => ({ ...t })) } }, venue }
}

export function changeSetlistEntry(entry: SetlistEntry, patch: Partial<SetlistEntry>): SetlistEntry {
  const next = { ...entry, ...patch }
  if (patch.title !== undefined && patch.title !== entry.title && patch.songCatalogId === undefined) delete next.songCatalogId
  if (patch.artist !== undefined && patch.artist !== entry.artist && patch.artistCatalogId === undefined) delete next.artistCatalogId
  if (next.type === 'note') { next.tape = false; delete next.songCatalogId; delete next.artistCatalogId }
  return next
}
