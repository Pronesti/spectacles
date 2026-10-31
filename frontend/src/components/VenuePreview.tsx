import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import type { Venue } from '../domain'
import type { ManualPerformance, PerformancePreview, ProviderRef, VenueSuggestion, VenueSource, VenueValues } from '../catalogProviders'
import type { CatalogProvidersAccess } from '../catalogProvidersApi'
import { CatalogProvidersError } from '../catalogProvidersApi'
import { createLatestRead } from '../catalogApi'
import { venueHistoryFilters } from '../providerDraft'
import { useRecoveryField } from './ConcertRecovery'
import { PerformancePicker } from './PerformancePicker'
export function VenuePreview({ venue, providers, onApplyPerformance, onChooseVenue, onApplyManual, onEditingChange, compact = false }: { compact?: boolean; venue: Venue | VenueSuggestion; providers?: CatalogProvidersAccess; onApplyPerformance: (p: PerformancePreview) => void; onChooseVenue?: (v: VenueSuggestion) => void; onApplyManual?: (value: ManualPerformance) => void; onEditingChange?: (v: boolean) => void }) {
  const suggestion = 'ref' in venue ? venue : null
  const initial: VenueValues = suggestion ? suggestion.values : { name: (venue as Venue).name, city: (venue as Venue).city, address: (venue as Venue).address ?? null, aliases: (venue as Venue).aliases ?? [], lat: (venue as Venue).lat, lng: (venue as Venue).lng, region: (venue as Venue).region ?? null, countryCode: (venue as Venue).countryCode ?? null, timezone: (venue as Venue).timezone ?? null, image: (venue as Venue).image ?? null }
  const [loaded, setLoaded] = useState<{ values: VenueValues; refs: ProviderRef[]; venueUrl?: string; venueSources?: VenueSource[] } | null>(null)
  const [tab, setTab] = useRecoveryField('venueHistoryTab', 'past'), [error, setError] = useState(''), [latest] = useState(createLatestRead)
  const [other, setOther] = useState<VenueSuggestion | null>(null), [query, setQuery] = useRecoveryField('placeQuery', ''), [places, setPlaces] = useState<VenueSuggestion[]>([])
  const [savedRef, setSavedRef] = useRecoveryField('venuePreviewRef', null), [historyEditing, setHistoryEditing] = useState(false)
  useEffect(() => { onEditingChange?.(!!other || !!savedRef || historyEditing); return () => onEditingChange?.(false) }, [other, savedRef, historyEditing, onEditingChange])
  const handlers = useRef({onChooseVenue})
  useLayoutEffect(() => { handlers.current = {onChooseVenue} }, [onChooseVenue])
  const identity = `${'id' in venue ? venue.id : JSON.stringify(venue.ref)}:${providers?.sessionKey}`, current = useRef(identity); useLayoutEffect(() => { current.current = identity }, [identity])
  useEffect(() => () => latest.cancel(), [identity, latest])
  const inspect = () => {
    if (!providers?.enabled || suggestion) return
    const id = (venue as Venue).catalogId ?? (venue as Venue).id, started = identity
    void latest.run(signal => providers.client.catalogVenue(id, signal), result => { if (current.current === started) setLoaded(result) }, e => { setError(e instanceof Error ? e.message : 'Venue history is unavailable.'); if (e instanceof CatalogProvidersError && e.status === 401) providers.onUnauthorized() })
  }
  const chooseOther = (candidate: VenueSuggestion) => {
    if (!suggestion || !providers?.enabled || !onChooseVenue) return
    if (candidate.linkedRefs.some(r => r.provider === suggestion.ref.provider && r.resourceType === suggestion.ref.resourceType && r.externalId === suggestion.ref.externalId)) {
      void latest.run(signal => providers.client.confirmVenue([candidate.selection, suggestion.selection], signal), result => { setOther(null); setSavedRef(null); handlers.current.onChooseVenue?.(result) }, e => setError(e instanceof Error ? e.message : 'Venue association unavailable.'))
    } else { setOther(candidate); setSavedRef(candidate.ref) }
  }
  const values = loaded?.values ?? initial, refs = suggestion ? [suggestion.ref, ...suggestion.linkedRefs] : loaded?.refs ?? []
  const [historySource, setHistorySource] = useRecoveryField('venueHistorySource', null)
  const options = refs.filter(r => r.provider === 'setlist_fm' && r.resourceType === 'venue')
  const chosenHistory = historySource && options.some(r => r.externalId === historySource.externalId) ? historySource : null
  const filters = venueHistoryFilters(chosenHistory ? [chosenHistory] : refs, tab, suggestion?.venueUrl ?? (suggestion?.ref.provider === 'setlist_fm' ? suggestion.url : loaded?.venueUrl), suggestion?.venueSources ?? loaded?.venueSources ?? [])
  return <section className="provider-venue-preview"><h4>{values.name}</h4><p>{values.city}</p>{values.address && <p>{values.address}</p>}{values.aliases.length > 0 && <p>Also known as: {values.aliases.join(' · ')}</p>}{values.lat !== null && values.lng !== null && <p>Venue location: {values.lat}, {values.lng}</p>}
    {values.image && <figure><img src={values.image.thumbnailUrl} alt={values.name} loading="lazy" onError={e => { e.currentTarget.hidden = true }} /><figcaption>{values.image.credit} · <a href={values.image.licenseUrl}>{values.image.license}</a> · <a href={values.image.sourceUrl}>Image source</a></figcaption></figure>}
    {!compact && <>{!suggestion && !loaded && <button type="button" disabled={!providers?.enabled} onClick={inspect}>Load Venue source links and history</button>}{error && <p role="alert">{error}</p>}
    <details><summary>Past and upcoming performances</summary>{options.length > 1 && <label>Venue identity for history<select value={chosenHistory?.externalId ?? ''} onChange={e => setHistorySource(options.find(r => r.externalId === e.target.value) ?? null)}><option value="">Choose a source Venue</option>{options.map(r => <option key={r.externalId} value={r.externalId}>{r.externalId}</option>)}</select></label>}<label>Venue history period<select value={tab} onChange={e => setTab(e.target.value as 'past' | 'upcoming')}><option value="past">Past performances</option><option value="upcoming">Upcoming performances</option></select></label>{filters ? <PerformancePicker history key={`${identity}:${tab}`} providers={providers} filters={filters} onApply={onApplyPerformance} onApplyManual={onApplyManual} onEditingChange={setHistoryEditing} /> : <p className="hint">{options.length > 1 && !chosenHistory ? 'Choose a source Venue above to show its performance history.' : 'Choose this Venue from a provider again to confirm its setlist.fm source URL and show performance history.'}</p>}</details>
    {suggestion?.ref.provider === 'setlist_fm' && providers && onChooseVenue && <details><summary>Find MusicBrainz Place details</summary><label>Place name<input value={query} maxLength={200} onChange={e => { latest.cancel(); setQuery(e.target.value); setPlaces([]) }} /></label><button type="button" disabled={!providers.enabled || query.trim().length < 3} onClick={() => { void latest.run(signal => providers.client.places(query, signal), result => setPlaces(result.items), e => setError(e instanceof Error ? e.message : 'Place lookup unavailable.')) }}>Search MusicBrainz Places</button><ul>{places.map(p => <li key={p.ref.externalId}><button type="button" onClick={() => { void latest.run(signal => providers.client.place(p.ref.externalId, signal), chooseOther, e => setError(e instanceof Error ? e.message : 'Place lookup unavailable.')) }}>{p.values.name} · {p.values.city}</button></li>)}</ul>{savedRef && !other && <div><button type="button" onClick={() => { void latest.run(signal => providers.client.place(savedRef.externalId, signal), chooseOther, e => setError(e instanceof Error ? e.message : 'Venue preview unavailable.')) }}>Resume Venue review</button><button type="button" onClick={() => setSavedRef(null)}>Dismiss Venue review</button></div>}{other && <div><p>Confirm that {suggestion.values.name} ({suggestion.values.city}) and {other.values.name} ({other.values.city}) are the same Venue.</p><button type="button" onClick={() => { void latest.run(signal => providers.client.confirmVenue([other.selection, suggestion.selection], signal), result => { setOther(null); setSavedRef(null); handlers.current.onChooseVenue?.(result) }, e => setError(e instanceof Error ? e.message : 'Venue association could not be confirmed.')) }}>Confirm same Venue</button><button type="button" onClick={() => { setOther(null); setSavedRef(null) }}>Keep current Venue</button></div>}</details>}
    </>}
  </section>
}
