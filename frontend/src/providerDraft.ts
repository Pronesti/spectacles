import { adoptCatalogVenue } from './concertDraft.ts'
import type { CatalogVenue } from './catalog.ts'
import { adoptWizardEvent } from './concertWizard.ts'
import type { WizardState, WizardArtistChoice, WizardTourChoice } from './concertWizard.ts'
import type { Venue } from './domain.ts'
import type { PerformancePreview, PerformanceSummary, PerformanceFilters, ProviderRef, VenueSuggestion, VenueSource, ArtistScope, ManualPerformance } from './catalogProviders.ts'
import { providerKey } from './catalogProviders.ts'
export function performanceSummary(p: PerformanceSummary): PerformanceSummary { return { ref: p.ref, artistName: p.artistName, venueName: p.venueName, city: p.city, date: p.date, url: p.url, locator: p.locator, localEventId: p.localEventId } }
export function detachPerformance(state: WizardState): WizardState {
  const draft = { ...state.draft }; delete draft.eventId; delete draft.providerEvent
  return { ...state, draft }
}
export function performanceChangeNeedsDetach(state: WizardState, patch: { artist?: boolean; tour?: boolean; venue?: boolean; date?: boolean }): boolean { return !!(state.draft.eventId || state.draft.providerEvent) && Object.values(patch).some(Boolean) }
export function applyLocalVenue(state: WizardState, venue: CatalogVenue, locals: Venue[]): WizardState {
  const draft = { ...adoptCatalogVenue(state.draft, venue, locals) } as WizardState['draft']
  delete draft.providerVenue
  return { ...state, draft }
}
export function applyProviderVenue(state: WizardState, candidate: VenueSuggestion): WizardState {
  const values = candidate.values, id = candidate.localId ?? crypto.randomUUID()
  const pending: Venue = { id, ...values, neighborhood: '' }
  const draft = { ...state.draft, pendingVenue: pending, venueName: values.name, city: values.city, lat: values.lat === null ? '' : String(values.lat), lng: values.lng === null ? '' : String(values.lng), concert: { ...state.draft.concert, venueId: id } }
  draft.providerVenue = { key: crypto.randomUUID(), ref: candidate.ref, selection: candidate.selection, linkedRefs: candidate.linkedRefs, url: candidate.url, venueUrl: candidate.venueUrl, venueSources: candidate.venueSources }
  return { ...state, draft }
}
export function applyPerformance(state: WizardState, preview: PerformancePreview, venues: Venue[]): WizardState {
  if (preview.localEvent) {
    const result = adoptWizardEvent(state, preview.localEvent, venues)
    delete result.draft.providerEvent; delete result.draft.providerVenue
    return result
  }
  const result = applyProviderVenue(detachPerformance(state), preview.venue)
  const a = preview.artist, t = preview.tour
  const artist: WizardArtistChoice = { name: a.values.name, catalogId: a.localId, ...(a.localId ? {} : { draft: { key: crypto.randomUUID(), mbid: a.ref.externalId, input: { ...a.values, selection: a.selection } } }) }
  const tours: WizardTourChoice[] = t ? [{ name: t.values.name, catalogId: t.localId, ...(t.localId ? {} : { draft: { key: crypto.randomUUID(), ref: t.ref, input: { name: t.values.name, selection: t.selection } } }) }] : []
  return { ...result, draft: { ...result.draft, artists: [artist], tours, precision: 'exact', dateText: `${String(preview.date.year).padStart(4, '0')}-${String(preview.date.month).padStart(2, '0')}-${String(preview.date.day).padStart(2, '0')}`, timezoneText: preview.timezone ?? '', providerEvent: { key: crypto.randomUUID(), ref: preview.ref, selection: preview.selection }, concert: { ...result.draft.concert, title: a.values.name, eventType: 'concert', time: preview.time ?? '', timezone: preview.timezone } } }
}
export function applyManualPerformance(state: WizardState, manual: ManualPerformance): WizardState {
  const staged = applyPerformance(state, { ...manual, ref: { provider: 'setlist_fm', resourceType: 'setlist', externalId: 'manual' }, artistName: manual.artist.values.name, venueName: manual.venue.values.name, city: manual.venue.values.city, url: '', locator: '', localEventId: null, localEvent: null, selection: '', tour: null }, [])
  delete staged.draft.providerEvent
  staged.draft.tours = manual.tourName ? [{ name: manual.tourName, catalogId: null }] : []
  return staged
}
export function venueHistoryFilters(refs: ProviderRef[], period: 'past' | 'upcoming', venueUrl?: string, sources: VenueSource[] = []): PerformanceFilters | null {
  const matches = [...new Map(refs.filter(r => r.provider === 'setlist_fm' && r.resourceType === 'venue').map(r => [providerKey(r), r])).values()]
  if (matches.length !== 1) return null
  const sourceUrl = sources.find(source => source.externalId === matches[0].externalId)?.url ?? venueUrl
  if (!sourceUrl) return null
  try {
    const source = new URL(sourceUrl)
    if (source.protocol !== 'https:' || source.hostname !== 'www.setlist.fm' || source.username || source.password || source.port || source.search || source.hash || !/^\/venue\/[^/]+\.html$/.test(source.pathname) || !source.pathname.endsWith(`-${matches[0].externalId}.html`)) return null
  } catch { return null }
  return { period, venueId: matches[0].externalId, venueUrl: sourceUrl }
}
export function providerArtistScopes(state: WizardState) {
  return state.draft.artists.flatMap<{ label: string; scope: ArtistScope }>(a => a.catalogId ? [{ label: a.name, scope: { artistUuid: a.catalogId } }] : a.draft?.input.selection && a.draft.mbid ? [{ label: a.name, scope: { artistMbid: a.draft.mbid } }] : [])
}
