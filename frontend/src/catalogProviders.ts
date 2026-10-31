import { catalogUuid } from './catalog.ts'
import type { CatalogEvent, CatalogVenue } from './catalog.ts'
import { validDate } from './domain.ts'
import type { EventDate, VenueImage } from './domain.ts'
export type { VenueImage } from './domain.ts'
export type ProviderRef = { provider: 'musicbrainz' | 'setlist_fm'; resourceType: 'artist' | 'work' | 'series' | 'tour' | 'place' | 'venue' | 'setlist'; externalId: string }
export type VenueValues = { name: string; city: string; region: string | null; countryCode: string | null; address: string | null; aliases: string[]; lat: number | null; lng: number | null; timezone: string | null; image: VenueImage | null }
export type ProviderEntity<T> = { ref: ProviderRef; values: T; url: string; selection: string; localId: string | null }
export type TourSuggestion = ProviderEntity<{ name: string }>
export type VenueSource = { externalId: string; url: string }
export type VenueSuggestion = ProviderEntity<VenueValues> & { linkedRefs: ProviderRef[]; venueUrl?: string; venueSources?: VenueSource[] }
export type PerformanceSummary = { ref: ProviderRef; artistName: string; venueName: string; city: string; date: { year: number; month: number; day: number }; url: string; locator: string; localEventId: string | null }
export type PerformancePreview = PerformanceSummary & { artist: ProviderEntity<{ name: string; type: string | null; disambiguation: string | null }>; tour: TourSuggestion | null; venue: VenueSuggestion; time: string | null; timezone: string | null; selection: string; localEvent: CatalogEvent | null }
export type ManualPerformance = { artist: PerformancePreview['artist']; venue: VenueSuggestion; tourName: string | null; date: PerformanceSummary['date']; time: null; timezone: null }
export type ProviderPage<T> = { items: T[]; nextCursor: string | null; partial: boolean }
export type ArtistScope = { artistUuid?: string; artistMbid?: string }
export type LocalVenuePreview = { localId: string; values: VenueValues; refs: ProviderRef[]; venueUrl?: string; venueSources?: VenueSource[] }
export type PerformanceFilters = ArtistScope & { period: 'past' | 'upcoming'; venueId?: string; venueUrl?: string; tourId?: string; tourName?: string; date?: string; year?: number; countryCode?: string; q?: string; cursor?: string }
export const isRecord = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v)
// oxlint-disable-next-line no-control-regex -- Provider fields must not include control characters.
export const boundedText = (v: unknown, max: number): v is string => typeof v === 'string' && !!v.trim() && [...v].length <= max && !/[\u0000-\u0008\u000b\u000c\u000e-\u001f]/.test(v)
const bad = (): never => { throw new Error('Invalid provider response') }
const object = (v: unknown) => isRecord(v) ? v : bad()
const text = (v: unknown, max = 255): string => boundedText(v, max) ? v : bad()
const nullable = (v: unknown, max = 255) => v === null ? null : text(v, max)
const uuid = (v: unknown) => v === null ? null : catalogUuid(v) ? v : bad()
const zone = (v: unknown) => { const value = nullable(v); if (value) { try { new Intl.DateTimeFormat('en', { timeZone: value }) } catch { bad() } } return value }
export const providerKey = (ref: ProviderRef) => `${ref.provider}:${ref.resourceType}:${ref.externalId}`
export function decodeProviderRef(value: unknown): ProviderRef {
  const v = object(value), id = text(v.externalId, 128)
  if (v.provider === 'musicbrainz' && ['artist', 'work', 'series', 'place'].includes(String(v.resourceType)) && catalogUuid(id) || v.provider === 'setlist_fm' && ['artist', 'tour', 'venue', 'setlist'].includes(String(v.resourceType)) && /^[\w-]+$/.test(id)) return { provider: v.provider, resourceType: v.resourceType, externalId: id } as ProviderRef
  return bad()
}
function url(value: unknown, hosts: string[]): string {
  const s = text(value, 2048)
  try { const u = new URL(s); if (u.protocol !== 'https:' || !hosts.includes(u.hostname) || u.username || u.password || u.port) return bad() } catch { return bad() }
  return s
}
export function decodeVenueImage(value: unknown): VenueImage | null {
  if (value === null) return null
  const v = object(value)
  if (typeof v.credit !== 'string' || /[<>]/.test(v.credit)) return bad()
  return { url: url(v.url, ['upload.wikimedia.org', 'thumb.wikimedia.org']), thumbnailUrl: url(v.thumbnailUrl, ['upload.wikimedia.org', 'thumb.wikimedia.org']), sourceUrl: url(v.sourceUrl, ['commons.wikimedia.org']), credit: text(v.credit, 2000), license: text(v.license), licenseUrl: url(v.licenseUrl, ['creativecommons.org', 'commons.wikimedia.org']) }
}
export function decodeVenueValues(value: unknown): VenueValues {
  const v = object(value)
  if (!Array.isArray(v.aliases) || v.aliases.length > 100 || typeof v.city !== 'string' || v.city.length > 255) return bad()
  const lat = v.lat, lng = v.lng
  if ((lat === null) !== (lng === null) || lat !== null && (typeof lat !== 'number' || typeof lng !== 'number' || !Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180)) return bad()
  const countryCode = nullable(v.countryCode, 2), aliases = v.aliases.map(a => text(a))
  if (countryCode !== null && !/^[A-Z]{2}$/.test(countryCode) || new Set(aliases).size !== aliases.length) return bad()
  return { name: text(v.name), city: v.city, region: nullable(v.region), countryCode, address: nullable(v.address, 2000), aliases, lat: lat as number | null, lng: lng as number | null, timezone: zone(v.timezone), image: decodeVenueImage(v.image) }
}
function entity(value: unknown): Record<string, unknown> & { ref: ProviderRef; url: string; selection: string; localId: string | null } {
  const v = object(value)
  const ref = decodeProviderRef(v.ref), source = url(v.url, ['www.setlist.fm', 'musicbrainz.org']), u = new URL(source)
  if (ref.provider === 'setlist_fm') {
    if (u.hostname !== 'www.setlist.fm' || (ref.resourceType === 'tour' ? u.pathname !== '/search' || u.searchParams.get('tour') !== ref.externalId : !u.pathname.startsWith(ref.resourceType === 'artist' ? '/setlists/' : '/venue/') || !u.pathname.endsWith(`-${ref.externalId}.html`))) return bad()
  } else if (!(ref.resourceType === 'artist' && u.hostname === 'www.setlist.fm' && /^\/setlists\/[^/]+-[\w-]+\.html$/.test(u.pathname)) && source !== `https://musicbrainz.org/${ref.resourceType}/${ref.externalId}`) return bad()
  return { ...v, ref, url: source, selection: text(v.selection, 32768), localId: uuid(v.localId) }
}
export function decodeTour(value: unknown): TourSuggestion {
  const v = entity(value)
  if (!(v.ref.provider === 'setlist_fm' && v.ref.resourceType === 'tour' || v.ref.provider === 'musicbrainz' && v.ref.resourceType === 'series')) return bad()
  return { ref: v.ref, url: v.url, selection: v.selection, localId: v.localId, values: { name: text(object(v.values).name) } }
}
function venueSource(value: unknown, refs: ProviderRef[]): string | undefined {
  if (value === undefined || value === null) return undefined
  const source = url(value, ['www.setlist.fm']), u = new URL(source)
  if (u.search || u.hash || !refs.some(ref => ref.provider === 'setlist_fm' && ref.resourceType === 'venue' && /^\/venue\/[^/]+\.html$/.test(u.pathname) && u.pathname.endsWith(`-${ref.externalId}.html`))) return bad()
  return source
}
function decodeVenueSources(value: unknown, refs: ProviderRef[]): VenueSource[] {
  if (value === undefined) return []
  if (!Array.isArray(value) || value.length > 6) return bad()
  const sources = value.map(item => {
    const source = object(item), externalId = text(source.externalId, 128)
    const matches = refs.filter(ref => ref.provider === 'setlist_fm' && ref.resourceType === 'venue' && ref.externalId === externalId)
    return { externalId, url: venueSource(source.url, matches) ?? bad() }
  })
  if (new Set(sources.map(source => source.externalId)).size !== sources.length) return bad()
  return sources
}
export function decodeVenue(value: unknown): VenueSuggestion {
  const v = entity(value)
  if (!['place', 'venue'].includes(v.ref.resourceType) || !Array.isArray(v.linkedRefs) || v.linkedRefs.length > 5) return bad()
  const linkedRefs = v.linkedRefs.map(decodeProviderRef)
  if (linkedRefs.some(r => !['place', 'venue'].includes(r.resourceType)) || new Set([providerKey(v.ref), ...linkedRefs.map(providerKey)]).size !== linkedRefs.length + 1) return bad()
  return { ref: v.ref, url: v.url, selection: v.selection, localId: v.localId, linkedRefs, values: decodeVenueValues(v.values), venueUrl: venueSource(v.venueUrl ?? (v.ref.provider === 'setlist_fm' ? v.url : undefined), [v.ref, ...linkedRefs]), venueSources: decodeVenueSources(v.venueSources, [v.ref, ...linkedRefs]) }
}
export function decodePerformanceSummary(value: unknown): PerformanceSummary {
  const v = object(value), ref = decodeProviderRef(v.ref), date = object(v.date)
  if (ref.provider !== 'setlist_fm' || ref.resourceType !== 'setlist' || !validDate(date as EventDate) || !Number.isInteger(date.year) || !Number.isInteger(date.month) || !Number.isInteger(date.day)) return bad()
  const sourceUrl = url(v.url, ['www.setlist.fm']), u = new URL(sourceUrl)
  if (u.pathname.startsWith('/show/') ? u.searchParams.get('setlist') !== ref.externalId : !u.pathname.endsWith(`-${ref.externalId}.html`)) return bad()
  return { ref, artistName: text(v.artistName), venueName: text(v.venueName), city: typeof v.city === 'string' && v.city.length <= 255 ? v.city : bad(), date: date as PerformanceSummary['date'], url: sourceUrl, locator: text(v.locator, 32768), localEventId: uuid(v.localEventId) }
}
function localEvent(value: unknown): CatalogEvent {
  const v = object(value), d = object(v.date)
  if (!catalogUuid(v.id) || !validDate(d as EventDate) || !Array.isArray(v.artists) || !Array.isArray(v.tours) || v.artists.length > 100 || v.tours.length > 100) return bad()
  const decodeArtist = (raw: unknown) => { const a = object(raw); if (!catalogUuid(a.id)) return bad(); return { id: a.id, name: text(a.name), type: nullable(a.type), disambiguation: nullable(a.disambiguation) } }
  const artists = v.artists.map(decodeArtist)
  const tours = v.tours.map(raw => { const t = object(raw); if (!catalogUuid(t.id)) return bad(); if (!Array.isArray(t.artists) || t.artists.length > 100) return bad(); return { id: t.id, name: text(t.name), artists: t.artists.map(decodeArtist) } })
  let venue: CatalogVenue | null = null
  if (v.venue !== null) {
    const p = object(v.venue); if (!catalogUuid(p.id)) return bad()
    const values = decodeVenueValues({ ...p, city: p.city ?? '', countryCode: p.country_code ?? p.countryCode ?? null, aliases: p.aliases ?? [], image: p.image ?? null, address: p.address ?? null })
    venue = { id: p.id, name: values.name, street: nullable(p.street ?? null), house_number: nullable(p.house_number ?? null), postal_code: nullable(p.postal_code ?? null), city: values.city, region: values.region, country_code: values.countryCode, lat: values.lat, lng: values.lng, timezone: values.timezone, address: values.address, aliases: values.aliases, image: values.image }
  }
  const time = nullable(v.time, 32)
  if (time !== null && !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(time)) return bad()
  return { id: v.id, title: text(v.title), type: text(v.type), date: d as EventDate, time, timezone: zone(v.timezone), artists, tours, venue }
}
export function decodePerformancePreview(value: unknown): PerformancePreview {
  const v = object(value), summary = decodePerformanceSummary(v), a = entity(v.artist), av = object(a.values)
  if (a.ref.provider !== 'musicbrainz' || a.ref.resourceType !== 'artist') return bad()
  const time = nullable(v.time, 32), existing = v.localEvent === null ? null : localEvent(v.localEvent)
  if (time !== null && !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(time) || (existing?.id ?? null) !== summary.localEventId) return bad()
  return { ...summary, artist: { ref: a.ref, url: a.url, selection: a.selection, localId: a.localId, values: { name: text(av.name), type: nullable(av.type), disambiguation: nullable(av.disambiguation) } }, tour: v.tour === null ? null : decodeTour(v.tour), venue: decodeVenue(v.venue), time, timezone: zone(v.timezone), selection: text(v.selection, 32768), localEvent: existing }
}
export function decodeManualPerformance(value: unknown): ManualPerformance {
  const v = object(value), a = entity(v.artist), av = object(a.values), date = object(v.date)
  if (a.ref.provider !== 'musicbrainz' || a.ref.resourceType !== 'artist' || !validDate(date as EventDate) || !Number.isInteger(date.year) || !Number.isInteger(date.month) || !Number.isInteger(date.day) || v.time !== null || v.timezone !== null) return bad()
  return { artist: { ref: a.ref, url: a.url, selection: a.selection, localId: a.localId, values: { name: text(av.name), type: nullable(av.type), disambiguation: nullable(av.disambiguation) } }, venue: decodeVenue(v.venue), tourName: nullable(v.tourName), date: date as ManualPerformance['date'], time: null, timezone: null }
}
export function decodeProviderPage<T>(value: unknown, decode: (row: unknown) => T): ProviderPage<T> {
  const v = object(value), meta = object(v.meta)
  if (!Array.isArray(v.data) || v.data.length > 10 || typeof meta.partial !== 'boolean' || !(meta.next_cursor === null || boundedText(meta.next_cursor, 8192))) return bad()
  const items = v.data.map(decode), keys = items.map(item => providerKey((item as { ref: ProviderRef }).ref))
  if (new Set(keys).size !== keys.length) return bad()
  return { items, nextCursor: meta.next_cursor as string | null, partial: meta.partial }
}
export function decodeLocalVenue(value: unknown): LocalVenuePreview {
  const v = object(value)
  if (!catalogUuid(v.localId) || !Array.isArray(v.refs) || v.refs.length > 10) return bad()
  const refs = v.refs.map(decodeProviderRef)
  if (refs.some(r => !['place', 'venue'].includes(r.resourceType)) || new Set(refs.map(providerKey)).size !== refs.length) return bad()
  return { localId: v.localId, values: decodeVenueValues(v.values), refs, venueUrl: venueSource(v.venueUrl, refs), venueSources: decodeVenueSources(v.venueSources, refs) }
}
