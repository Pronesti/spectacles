import { InteractionFieldset } from './InteractionFieldset'
import { SelectField } from './SelectField'
import { Button } from 'react-aria-components'
import { discoveryQuery } from '../discoveryQuery'
import { RecoveredSearch } from './RecoveredSearch'
import { useContext, useEffect, useImperativeHandle, useLayoutEffect, useRef, useState } from 'react'
import type { Precision } from '../concertDraft'
import type { Concert, Venue } from '../domain'
import { adoptWizardEvent, detachWizardEvent } from '../concertWizard'
import type { WizardStepProps } from './wizardTypes'
import { Icon } from './Icon'
import { CreationSearch } from './CreationSearch'
import { CreationSelection } from './CreationSelection'
import { EventCatalogPicker } from './EventCatalogPicker'
import { PerformancePicker } from './PerformancePicker'
import { VenuePreview } from './VenuePreview'
import { prepareVenueDraft, applyVenueDraft, validVenueDraft } from '../venueDraft'
import { VenueDraftFields } from './VenueDraftFields'
import { ConcertRecoveryContext, useRecoveryField } from './ConcertRecovery'
import { applyPerformance, applyManualPerformance, applyProviderVenue, applyLocalVenue, providerArtistScopes } from '../providerDraft'
import type { PerformanceSummary, VenueSuggestion, VenueValues } from '../catalogProviders'
import type { CreationMatch } from '../creationSearch'
import { CatalogProvidersError } from '../catalogProvidersApi'
import { createLatestRead } from '../catalogApi'
export function WizardEventStep({ state, onChange, onEditingChange, catalog, musicbrainz, providers, venues, continueAction }: WizardStepProps) {
  const latestState = useRef(state)
  useLayoutEffect(() => { latestState.current = state }, [state])
  const draft = state.draft, recovery = useContext(ConcertRecoveryContext)
  const [localEditing, setLocalEditing] = useState(false), [performanceEditing, setPerformanceEditing] = useState(false), [legacyEditing, setLegacyEditing] = useState(false)
  const [legacyVenueEditing, setLegacyVenueEditing] = useState(false)
  const [localPick, setLocalPick] = useState<string>(), [performancePick, setPerformancePick] = useState<PerformanceSummary>()
  const [venueQuery, setVenueQuery] = useRecoveryField('venueQuery', '')
  const [placeQuery, setPlaceQuery] = useRecoveryField('placeQuery', '')
  const [eventQuery, setEventQuery] = useRecoveryField('eventQuery', '')
  const [performanceFilters, setPerformanceFilters] = useRecoveryField('performanceFilters', { period: 'past' })
  const [venueEditor, setVenueEditor] = useRecoveryField('venueEditor', null)
  const eventSearch = discoveryQuery(eventQuery, performanceFilters.q ?? ''), venueSearch = discoveryQuery(venueQuery, placeQuery)
  const [changingVenue, setChangingVenue] = useState(false), [changingEvent, setChangingEvent] = useState(false), [manualOpen, setManualOpen] = useState(false)
  const [changeTarget, setChangeTarget] = useState<'venue' | 'event' | null>(null)
  const [placeBusy, setPlaceBusy] = useState(false), [placeError, setPlaceError] = useState(''), [latest] = useState(createLatestRead)
  const recoveredEditor = recovery?.controller.snapshot.fields.venueEditor ?? venueEditor
  const artist = draft.artists.length === 1 ? providerArtistScopes(state)[0]?.scope : undefined
  const sourceTour = draft.tours.length === 1 && draft.tours[0].draft?.ref?.provider === 'setlist_fm' ? draft.tours[0] : null
  const sourceLocked = !!(draft.eventId || draft.providerEvent), reviewing = localEditing || performanceEditing || legacyEditing || legacyVenueEditing || !!recoveredEditor || placeBusy
  const performance = { period: performanceFilters.period ?? 'past', ...artist, ...(sourceTour?.draft?.ref ? { tourId: sourceTour.draft.ref.externalId, tourName: sourceTour.name } : state.draft.tours.length === 1 && !state.draft.tours[0].catalogId ? { tourName: state.draft.tours[0].name } : {}) }
  useEffect(() => { onEditingChange(reviewing); return () => onEditingChange(false) }, [reviewing, onEditingChange])
  useEffect(() => () => latest.cancel(), [latest, providers?.sessionKey, providers?.enabled])
  const setDraft = (patch: Partial<typeof draft>) => onChange({ ...state, draft: { ...draft, ...patch } })
  const field = <K extends keyof Concert,>(key: K, value: Concert[K]) => setDraft({ concert: { ...draft.concert, [key]: value } })
  const currentVenue = draft.pendingVenue ?? venues.find(v => v.id === draft.concert.venueId)
  let venueDisplay: Venue | VenueSuggestion | undefined = currentVenue
  if (currentVenue && draft.providerVenue) {
    const p = draft.providerVenue
    venueDisplay = { ref: p.ref, linkedRefs: p.linkedRefs ?? [], selection: p.selection, url: p.url ?? '', venueUrl: p.venueUrl, venueSources: p.venueSources, localId: null, values: { name: currentVenue.name, city: currentVenue.city, lat: currentVenue.lat, lng: currentVenue.lng, region: currentVenue.region ?? null, countryCode: currentVenue.countryCode ?? null, address: currentVenue.address ?? null, aliases: currentVenue.aliases ?? [], image: currentVenue.image ?? null, timezone: currentVenue.timezone ?? null } }
  }
  const choosePlace = (candidate: VenueSuggestion) => {
    if (!providers?.enabled) return
    setPlaceBusy(true); setPlaceError('')
    void latest.run(signal => providers.client.place(candidate.ref.externalId, signal), detail => {
      setPlaceBusy(false)
      if (detail.localId) { onChange(applyProviderVenue(latestState.current, detail)); setChangingVenue(false) }
      else setVenueEditor({ name: detail.values.name, city: detail.values.city, address: detail.values.address ?? '', aliasesText: detail.values.aliases.join('\n'), lat: detail.values.lat === null ? '' : String(detail.values.lat), lng: detail.values.lng === null ? '' : String(detail.values.lng), selection: detail.selection, ref: detail.ref, metadata: detail.values, linkedRefs: detail.linkedRefs, url: detail.url, venueUrl: detail.venueUrl, venueSources: detail.venueSources })
    }, e => { setPlaceBusy(false); setPlaceError(e instanceof Error ? e.message : 'Venue details unavailable. Choose again or create manually.'); if (e instanceof CatalogProvidersError && e.status === 401) providers.onUnauthorized() })
  }
  const confirmVenue = (values: VenueValues, selection: string | null, source?: VenueSuggestion) => {
    onChange(applyVenueDraft(latestState.current, { values, selection, source }))
    setVenueEditor(null); setChangingVenue(false)
  }
  useImperativeHandle(continueAction, () => () => {
    if (!recoveredEditor || !validVenueDraft(recoveredEditor) || sourceLocked || changingEvent || localEditing || performanceEditing || legacyEditing || placeBusy) return null
    const next = applyVenueDraft(latestState.current, prepareVenueDraft(recoveredEditor))
    setVenueEditor(null); setChangingVenue(false)
    return next
  })
  const selectEvent = (option: CreationMatch) => {
    if (option.type === 'local' && option.kind === 'events') { setLocalPick(option.row.id); setPerformancePick(undefined) }
    if (option.type === 'performance') { setPerformancePick(option.candidate); setLocalPick(undefined) }
  }
  const selectedVenueName = currentVenue?.name || (draft.concert.venueId === 'new' ? draft.venueName : '')
  return <div className="creation-step creation-event-step">
    <p className="creation-context">{draft.artists.map(a => a.name).join(' · ')}{draft.tours.length > 0 ? ` · ${draft.tours.map(t => t.name).join(' · ')}` : ''}</p>
    {!sourceLocked && <p className="creation-intro">Find your concert, or add the details yourself.</p>}
    {sourceLocked && !changingEvent && <CreationSelection label="Event / performance" name={draft.concert.title || draft.artists[0]?.name || 'Selected Event'} detail={`${draft.dateText || 'Date to confirm'} · ${selectedVenueName || 'Venue to confirm'}`} onChange={() => setChangingEvent(true)} />}
    <InteractionFieldset disabled={reviewing} className="wizard-shared-fields">
      {(!sourceLocked || changingEvent) && <><CreationSearch kind="events" label="Event or performance" query={eventSearch.value} onQuery={setEventQuery} catalog={catalog} musicbrainz={musicbrainz} providers={providers} performance={performance} onPick={selectEvent} onCreate={() => { if (sourceLocked) setChangeTarget('event'); else { setManualOpen(true); onChange(detachWizardEvent(state)) } }} />
      <RecoveredSearch value={eventSearch.alternative} onUse={() => { setEventQuery(performanceFilters.q ?? ''); setPerformanceFilters({ ...performanceFilters, q: '' }) }} />
      <details className="creation-search-options"><summary>Search options</summary><SelectField label="Performance period" className="form-select" value={performanceFilters.period ?? 'past'} onChange={value => setPerformanceFilters({ ...performanceFilters, period: value as 'past' | 'upcoming' })} options={[{ id: 'past', label: 'Past performances' }, { id: 'upcoming', label: 'Upcoming performances' }]} />{performance.period === 'upcoming' && <p className="hint">Upcoming results include only future-dated performances available from setlist.fm. Enter missing events manually.</p>}</details></>}
      {changingEvent && <Button type="button" className="text-button" onPress={() => setChangingEvent(false)}>Keep selected Event</Button>}
    </InteractionFieldset>
    <EventCatalogPicker hideSearch requestedId={localPick} onDismiss={() => setLocalPick(undefined)} recoverDraft musicbrainz={musicbrainz} catalog={catalog} onEditingChange={setLocalEditing} onUse={event => { onChange(adoptWizardEvent(latestState.current, event, venues)); setChangingEvent(false); setLocalPick(undefined) }} />
    <PerformancePicker previewOnly initialSummary={performancePick} onDismiss={() => setPerformancePick(undefined)} providers={providers} filters={performance} query={eventSearch.value} onApply={preview => { onChange(applyPerformance(latestState.current, preview, venues)); setChangingEvent(false); setPerformancePick(undefined) }} onApplyManual={manual => { onChange(applyManualPerformance(latestState.current, manual)); setPerformancePick(undefined) }} onEditingChange={setPerformanceEditing} />
    {recovery?.controller.snapshot.fields.historyPerformancePreview && <details open><summary>Finish previous performance review</summary><PerformancePicker previewOnly history providers={providers} filters={recovery.controller.snapshot.fields.historyPerformanceFilters ?? { period: 'past' }} onApply={preview => onChange(applyPerformance(latestState.current, preview, venues))} onApplyManual={manual => onChange(applyManualPerformance(latestState.current, manual))} onEditingChange={setLegacyEditing} /></details>}
    {selectedVenueName && !recoveredEditor && <CreationSelection label="Venue" name={selectedVenueName} detail={currentVenue ? currentVenue.city : draft.concert.venueId === 'new' ? draft.city : ''} onChange={() => sourceLocked ? setChangeTarget('venue') : setChangingVenue(true)} />}
    <InteractionFieldset disabled={reviewing || sourceLocked} className="wizard-shared-fields">
      {(!selectedVenueName || changingVenue) && <><CreationSearch kind="venues" label="Venue" query={venueSearch.value} onQuery={setVenueQuery} catalog={catalog} musicbrainz={musicbrainz} providers={providers} onPick={option => { if (option.type === 'local' && option.kind === 'venues') { onChange(applyLocalVenue(state, option.row, venues)); setChangingVenue(false) } if (option.type === 'venue') choosePlace(option.candidate) }} onCreate={() => setVenueEditor({ name: venueQuery, city: '', address: '', aliasesText: '', lat: '', lng: '', selection: null })} /><RecoveredSearch value={venueSearch.alternative} onUse={() => { setVenueQuery(placeQuery); setPlaceQuery('') }} /></>}
      {changingVenue && <Button type="button" className="text-button" onPress={() => setChangingVenue(false)}>Keep current Venue</Button>}
    </InteractionFieldset>
    {placeBusy && <p role="status">Loading Venue details…</p>}{placeError && <p role="alert">{placeError}</p>}
    {recoveredEditor && <VenueDraftFields onConfirm={confirmVenue} onCancel={() => { setVenueEditor(null); setChangingVenue(false) }} />}
    {venueDisplay && !recoveredEditor && <details className="creation-venue-details" open={!!recovery?.controller.snapshot.fields.venuePreviewRef}><summary>Venue details</summary><VenuePreview compact={!recovery?.controller.snapshot.fields.venuePreviewRef} venue={venueDisplay} providers={providers} onChooseVenue={candidate => onChange(applyProviderVenue(latestState.current, candidate))} onEditingChange={setLegacyVenueEditing} onApplyPerformance={preview => onChange(applyPerformance(latestState.current, preview, venues))} /></details>}
    {changeTarget && <div role="alert" className="creation-conflict"><p>Changing these details creates a new Event. Your notes and setlist stay.</p><Button type="button" onPress={() => { onChange(detachWizardEvent(state)); if (changeTarget === 'venue') setChangingVenue(true); setManualOpen(true); setChangingEvent(false); setChangeTarget(null) }}>Create new Event using these details</Button><Button type="button" onPress={() => setChangeTarget(null)}>Keep selected Event</Button></div>}
    <details key={sourceLocked ? 'source' : 'manual'} className="creation-details" open={manualOpen || !sourceLocked && !!(draft.dateText || draft.concert.title || draft.concert.venueId === 'new')}><summary>{sourceLocked ? 'Concert details' : 'Add concert details'}</summary>
      <InteractionFieldset disabled={!!draft.eventId || reviewing} className="wizard-shared-fields"><label>Concert title<input maxLength={255} value={draft.concert.title} onChange={e => field('title', e.target.value)} placeholder="Title, or use the Artist name" /></label>
        <InteractionFieldset disabled={sourceLocked} className="wizard-shared-fields">
          {draft.concert.venueId === 'new' && <div className="new-venue-fields"><label>Venue name<input maxLength={150} value={draft.venueName} onChange={e => setDraft({ venueName: e.target.value })} /></label><label>City<input maxLength={100} value={draft.city} onChange={e => setDraft({ city: e.target.value })} /></label><details><summary>Map location (optional)</summary><div className="form-columns"><label>Latitude<input inputMode="decimal" value={draft.lat} onChange={e => setDraft({ lat: e.target.value })} /></label><label>Longitude<input inputMode="decimal" value={draft.lng} onChange={e => setDraft({ lng: e.target.value })} /></label></div></details></div>}
          <SelectField label="Date precision" className="form-select" value={draft.precision} onChange={value => { const precision = value as Precision; setDraft({ precision, dateText: precision === 'year' ? draft.dateText.slice(0, 4) : precision === 'month' && draft.precision === 'exact' ? draft.dateText.slice(0, 7) : precision === draft.precision ? draft.dateText : '' }) }} options={[{ id: 'exact', label: 'Exact date' }, { id: 'month', label: 'Month only' }, { id: 'year', label: 'Year only' }, { id: 'unknown', label: 'Date to confirm' }]} />
          {draft.precision !== 'unknown' && <label>{draft.precision === 'exact' ? 'Date' : draft.precision === 'month' ? 'Month' : 'Year'}<input type={draft.precision === 'exact' ? 'date' : draft.precision === 'month' ? 'month' : 'number'} min={draft.precision === 'year' ? '1' : draft.precision === 'month' ? '0001-01' : '0001-01-01'} max={draft.precision === 'year' ? '9999' : draft.precision === 'month' ? '9999-12' : '9999-12-31'} value={draft.dateText} onChange={e => setDraft({ dateText: e.target.value })} /></label>}
        </InteractionFieldset>
        <details><summary>More details (optional)</summary><SelectField label="Event type" className="form-select" value={draft.concert.eventType ?? 'concert'} onChange={value => field('eventType', value)} options={['concert', 'festival', 'theatre', 'comedy', 'dance', 'other'].map(type => ({ id: type, label: type }))} /><label>Local time<input type="time" step={1} value={draft.concert.time} onChange={e => field('time', e.target.value)} /></label><label>Timezone<input value={draft.timezoneText} onChange={e => setDraft({ timezoneText: e.target.value })} placeholder="Unknown, or an IANA timezone" /></label></details>
      </InteractionFieldset>
    </details>
    <div className="flag-fields"><label><input type="checkbox" checked={draft.concert.interested} onChange={e => field('interested', e.target.checked)} /><Icon name="heart" size={18} />Interested</label><label><input type="checkbox" checked={draft.concert.ticketsBought} onChange={e => field('ticketsBought', e.target.checked)} /><Icon name="ticket" size={18} />Tickets bought</label></div>
    <label>Notes <span className="optional">optional</span><textarea rows={3} maxLength={2000} value={draft.concert.notes} onChange={e => field('notes', e.target.value)} /></label>
  </div>
}
