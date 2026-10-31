import type { CatalogByKind, CatalogPage } from './catalog.ts'
import type { CatalogFilters } from './catalogApi.ts'
import type { CatalogAccess } from './components/CatalogPicker.tsx'
import type { MusicBrainzAccess } from './components/EntityAutocomplete.tsx'
import type { CatalogProvidersAccess } from './catalogProvidersApi.ts'
import type { ArtistScope, PerformanceFilters, PerformanceSummary, TourSuggestion, VenueSuggestion } from './catalogProviders.ts'
import type { MusicBrainzCandidate } from './musicbrainz.ts'
import { formatDate } from './domain.ts'
export type CreationKind = 'artists' | 'tours' | 'venues' | 'events'
export type CreationMatch = { [K in CreationKind]: { type: 'local'; kind: K; row: CatalogByKind[K] } }[CreationKind]
  | { type: 'musicbrainz'; candidate: MusicBrainzCandidate }
  | { type: 'tour'; candidate: TourSuggestion }
  | { type: 'venue'; candidate: VenueSuggestion }
  | { type: 'performance'; candidate: PerformanceSummary }
export type CreationResult = { items: CreationMatch[]; messages: string[]; partial: boolean; nextPage?: number; nextCursor?: string }
export type CreationSearch = { kind: CreationKind; catalog: CatalogAccess; musicbrainz: MusicBrainzAccess; providers?: CatalogProvidersAccess; filters?: CatalogFilters; artist?: ArtistScope; performance?: PerformanceFilters }
export function describeCreationMatch(match: CreationMatch) {
  if (match.type === 'local') {
    const row = match.row
    return { key: `local:${match.kind}:${row.id}`, label: 'title' in row ? row.title : row.name, source: 'Your catalog', detail: 'date' in row ? [formatDate(row.date), row.venue?.name].filter(Boolean).join(' · ') : 'city' in row ? row.city ?? '' : 'disambiguation' in row ? row.disambiguation ?? '' : '' }
  }
  if (match.type === 'musicbrainz') { const c = match.candidate; return { key: `musicbrainz:${c.kind}:${c.id}`, label: c.label, source: c.local ? 'Your catalog' : 'MusicBrainz', detail: [c.disambiguation, c.area, c.country].filter(Boolean).join(' · ') } }
  const c = match.candidate
  return { key: `${c.ref.provider}:${c.ref.resourceType}:${c.ref.externalId}`, label: 'values' in c ? c.values.name : `${c.artistName} at ${c.venueName}`, source: c.ref.provider === 'setlist_fm' ? 'setlist.fm' : 'MusicBrainz', detail: 'date' in c ? `${formatDate(c.date)} · ${c.city}` : 'city' in c.values ? c.values.city : '' }
}
export function mergeCreationMatches(items: CreationMatch[]): CreationMatch[] {
  const unique = [...new Map(items.map(item => [describeCreationMatch(item).key, item])).values()]
  const localIds = new Set(unique.filter(item => item.type === 'local' && item.kind === 'events').map(item => item.row.id))
  return [...unique.filter(item => item.type === 'local'), ...unique.filter(item => item.type !== 'local' && !(item.type === 'performance' && item.candidate.localEventId && localIds.has(item.candidate.localEventId)))]
}
export async function searchCreation(options: CreationSearch, query: string, signal: AbortSignal, page = 1, cursor?: string): Promise<CreationResult> {
  const q = query.trim(), length = [...q].length
  const date = options.kind === 'events' && /^\d{4}-\d{2}-\d{2}$/.test(q) && !Number.isNaN(Date.parse(`${q}T00:00:00Z`)) && new Date(`${q}T00:00:00Z`).toISOString().slice(0, 10) === q ? q : undefined
  const empty: CreationResult = { items: [], messages: [], partial: false }
  if (signal.aborted || length > 0 && length < 3 || !options.catalog.enabled) return empty
  const artistScope = options.performance?.artistUuid ? { artist_uuid: options.performance.artistUuid } : options.performance?.artistMbid ? { artist_mbid: options.performance.artistMbid } : undefined
  const local: CatalogPage<CatalogByKind[CreationKind]> = cursor && options.kind === 'events' ? { items: [], total: 0, page: 1, lastPage: 1 } : await options.catalog.client.search(options.kind, { ...options.filters, ...(date && artistScope ? { ...artistScope, year: Number(date.slice(0, 4)), month: Number(date.slice(5, 7)), day: Number(date.slice(8, 10)) } : { q }) }, length ? page : 1, signal, length ? 20 : 5)
  if (signal.aborted) return empty
  const localResult = { ...empty, items: local.items.map(row => ({ type: 'local', kind: options.kind, row }) as CreationMatch), ...(length && page < local.lastPage ? { nextPage: page + 1 } : {}) }
  if (!length || options.kind !== 'events' && local.total || options.kind === 'events' && page > 1 && !cursor) return localResult
  const reads: Promise<CreationResult>[] = []
  if (!cursor && options.musicbrainz.enabled && (options.kind === 'artists' || options.kind === 'tours') && (options.kind !== 'tours' || options.artist)) {
    reads.push(options.musicbrainz.client.search(options.kind === 'artists' ? 'artist' : 'series', q, signal, options.filters).then(rows => ({ ...empty, items: rows.map(candidate => ({ type: 'musicbrainz', candidate })) })))
  }
  const providers = options.providers
  if (providers?.enabled) {
    if (options.kind === 'tours' && options.artist) reads.push(providers.client.tours(options.artist, q, signal, cursor).then(p => ({ items: p.items.map(candidate => ({ type: 'tour', candidate })), messages: [], partial: p.partial, nextCursor: p.nextCursor ?? undefined })))
    if (options.kind === 'venues') reads.push(providers.client.places(q, signal).then(p => ({ items: p.items.map(candidate => ({ type: 'venue', candidate })), messages: [], partial: p.partial })))
    if (options.kind === 'events' && options.performance && (options.performance.artistUuid || options.performance.artistMbid || options.performance.venueId)) reads.push(providers.client.performances({ ...options.performance, q, ...(date && !options.performance.date ? { date } : {}), ...(date && options.performance.period === 'past' && !options.performance.year ? { year: Number(date.slice(0, 4)) } : {}), ...(cursor ? { cursor } : {}) }, signal).then(p => ({ items: p.items.map(candidate => ({ type: 'performance', candidate })), messages: [], partial: p.partial, nextCursor: p.nextCursor ?? undefined })))
  }
  const result = { ...localResult, messages: [] as string[] }
  if (providers?.enabled && options.kind === 'events' && !reads.length) result.messages.push('Online event search needs an identified Artist or Venue. You can enter details manually.')
  for (const read of await Promise.allSettled(reads)) {
    if (signal.aborted) return empty
    if (read.status === 'fulfilled') { result.items.push(...read.value.items); result.partial ||= read.value.partial; result.nextCursor ??= read.value.nextCursor }
    else {
      const error = read.reason
      if (error?.status === 401) { if (options.kind === 'artists') options.musicbrainz.onUnauthorized(); else providers?.onUnauthorized() }
      result.messages.push(error instanceof Error ? error.message : 'Online matches are unavailable. You can create manually.')
    }
  }
  result.items = mergeCreationMatches(result.items)
  return result
}
