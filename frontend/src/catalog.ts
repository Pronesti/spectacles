import { validDate } from './domain.ts'
import type { EventDate } from './domain.ts'

export type CatalogKind = 'events' | 'artists' | 'venues' | 'songs' | 'tours'
export type CatalogArtist = { id: string; name: string; type: string | null; disambiguation: string | null }
export type CatalogSong = { id: string; title: string; disambiguation: string | null }
export type CatalogVenue = { id: string; name: string; street: string | null; house_number: string | null; postal_code: string | null; city: string | null; region: string | null; country_code: string | null; lat: number | null; lng: number | null; timezone: string | null }
export type CatalogTour = { id: string; name: string; artists: CatalogArtist[] }
export type CatalogEvent = { id: string; title: string; type: string; date: EventDate; time: string | null; timezone: string | null; venue: CatalogVenue | null; artists: CatalogArtist[]; tours: CatalogTour[] }
export type CatalogByKind = { events: CatalogEvent; artists: CatalogArtist; venues: CatalogVenue; songs: CatalogSong; tours: CatalogTour }
export type CatalogPage<T> = { items: T[]; page: number; lastPage: number; total: number }
type Obj = { id: string; type: string; attributes: Record<string, unknown>; relationships?: Record<string, unknown> }
const record = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value)
export const catalogUuid = (value: unknown): value is string => typeof value === 'string' && /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i.test(value)
const invalid = (): never => { throw new Error('Invalid catalog response') }
const string = (value: unknown): string => typeof value === 'string' && value.trim() ? value : invalid()
const nullableString = (value: unknown): string | null => value === null ? null : typeof value === 'string' ? value : invalid()
const integer = (value: unknown, min: number): number => typeof value === 'number' && Number.isInteger(value) && value >= min ? value : invalid()
function timezone(value: unknown): string | null {
  const zone = nullableString(value)
  if (zone !== null) { try { new Intl.DateTimeFormat('en', { timeZone: zone }) } catch { invalid() } }
  return zone
}
function object(value: unknown): Obj {
  if (!record(value) || !catalogUuid(value.id) || typeof value.type !== 'string' || !record(value.attributes)) return invalid()
  if (value.relationships !== undefined && !record(value.relationships)) return invalid()
  return value as Obj
}
function decoder(document: unknown) {
  if (!record(document) || !('data' in document) || document.included !== undefined && !Array.isArray(document.included)) return invalid()
  const roots = Array.isArray(document.data) ? document.data.map(object) : [object(document.data)]
  const all = [...roots, ...(Array.isArray(document.included) ? document.included.map(object) : [])]
  const index = new Map(all.map(row => [`${row.type}:${row.id}`, row]))
  const resolve = (link: unknown, expected: string): Obj => {
    if (!record(link) || link.type !== expected || !catalogUuid(link.id)) return invalid()
    return index.get(`${expected}:${link.id}`) ?? invalid()
  }
  const relation = (row: Obj, name: string): unknown => {
    const value = row.relationships?.[name]
    if (!record(value) || !('data' in value)) return invalid()
    return value.data
  }
  const many = (row: Obj, name: string, type: string): Obj[] => {
    const links = relation(row, name)
    return Array.isArray(links) ? links.map(link => resolve(link, type)) : invalid()
  }
  const artist = (row: Obj): CatalogArtist => ({ id: row.id, name: string(row.attributes.name), type: nullableString(row.attributes.type), disambiguation: nullableString(row.attributes.disambiguation) })
  const venue = (row: Obj): CatalogVenue => {
    const a = row.attributes
    const coordinate = (value: unknown): number | null => {
      if (value === null) return null
      if (typeof value !== 'number' && (typeof value !== 'string' || !/^-?\d+(?:\.\d+)?$/.test(value))) return invalid()
      const number = Number(value)
      return Number.isFinite(number) ? number : invalid()
    }
    const lat = coordinate(a.lat), lng = coordinate(a.lng)
    if ((lat === null) !== (lng === null) || lat !== null && (Math.abs(lat) > 90 || Math.abs(lng!) > 180)) return invalid()
    return { id: row.id, name: string(a.name), street: nullableString(a.street), house_number: nullableString(a.house_number), postal_code: nullableString(a.postal_code), city: nullableString(a.city), region: nullableString(a.region), country_code: nullableString(a.country_code), lat, lng, timezone: timezone(a.timezone) }
  }
  const tour = (row: Obj): CatalogTour => ({ id: row.id, name: string(row.attributes.name), artists: many(row, 'artistLinks', 'tour_artists').map(link => artist(resolve(relation(link, 'artist'), 'artists'))) })
  const event = (row: Obj, requireTours = false): CatalogEvent => {
    const a = row.attributes
    const date: EventDate = { year: a.year as number | null, month: a.month as number | null, day: a.day as number | null }
    if (!validDate(date)) return invalid()
    const time = nullableString(a.local_start_time)
    if (time !== null && !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(time)) return invalid()
    const venueLink = relation(row, 'venue')
    const hasTours = row.relationships?.tourLinks !== undefined
    if (requireTours && !hasTours) return invalid()
    return { id: row.id, title: string(a.title), type: string(a.type), date, time, timezone: timezone(a.timezone), venue: venueLink === null ? null : venue(resolve(venueLink, 'venues')), artists: many(row, 'artistLinks', 'event_artists').map(link => artist(resolve(relation(link, 'artist'), 'artists'))), tours: hasTours ? many(row, 'tourLinks', 'event_tours').map(link => tour(resolve(relation(link, 'tour'), 'tours'))) : [] }
  }
  const convert = (kind: CatalogKind, row: Obj): CatalogByKind[CatalogKind] => {
    if (row.type !== kind) return invalid()
    switch (kind) {
      case 'artists': return artist(row)
      case 'venues': return venue(row)
      case 'songs': return { id: row.id, title: string(row.attributes.title), disambiguation: nullableString(row.attributes.disambiguation) }
      case 'tours': return tour(row)
      case 'events': return event(row)
    }
  }
  return { roots, convert, event, document }
}
export function decodeCatalogPage<K extends CatalogKind>(kind: K, value: unknown): CatalogPage<CatalogByKind[K]> {
  const decoded = decoder(value)
  if (!Array.isArray(decoded.document.data) || !record(decoded.document.meta)) return invalid()
  const meta = decoded.document.meta
  const perPage = integer(meta.per_page, 1)
  if (perPage > 100 || decoded.roots.length > perPage || new Set(decoded.roots.map(row => row.id)).size !== decoded.roots.length) return invalid()
  return { items: decoded.roots.map(row => decoded.convert(kind, row)) as CatalogByKind[K][], page: integer(meta.current_page, 1), lastPage: integer(meta.last_page, 1), total: integer(meta.total, 0) }
}
export function decodeCatalogEvent(value: unknown): CatalogEvent {
  const decoded = decoder(value)
  if (Array.isArray(decoded.document.data) || decoded.roots[0]?.type !== 'events') return invalid()
  return decoded.event(decoded.roots[0], true)
}
