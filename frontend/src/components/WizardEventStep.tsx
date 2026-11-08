import { InteractionFieldset } from './InteractionFieldset'
import { Button, DateField, DateInput, DateSegment, Label } from 'react-aria-components'
import { parseDate } from '@internationalized/date'
import { discoveryQuery } from '../discoveryQuery'
import { RecoveredSearch } from './RecoveredSearch'
import { useContext, useEffect, useImperativeHandle, useLayoutEffect, useRef, useState } from 'react'
import type { Concert } from '../domain'
import { adoptWizardEvent, detachWizardEvent, keepWizardEvent, beginWizardEventChange, selectManualWizardEvent } from '../concertWizard'
import type { WizardStepProps } from './wizardTypes'
import { Icon } from './Icon'
import { CreationSearch } from './CreationSearch'
import { CreationSelection } from './CreationSelection'
import { EventCatalogPicker } from './EventCatalogPicker'
import { PerformancePicker } from './PerformancePicker'
import { prepareVenueDraft, applyVenueDraft, validVenueDraft } from '../venueDraft'
import { VenueDraftFields } from './VenueDraftFields'
import { ConcertRecoveryContext, useRecoveryField } from './ConcertRecovery'
import { applyPerformance, applyManualPerformance, applyProviderVenue, applyLocalVenue, providerArtistScopes } from '../providerDraft'
import type { PerformanceSummary, VenueSuggestion, VenueValues } from '../catalogProviders'
import type { CreationMatch } from '../creationSearch'
import { CatalogProvidersError } from '../catalogProvidersApi'
import { createLatestRead } from '../catalogApi'
function dateFieldValue(value: string) {
  try { return value ? parseDate(value) : null } catch { return null }
}
export function WizardEventStep({ state, onChange, onEditingChange, catalog, musicbrainz, providers, venues, continueAction }: WizardStepProps) {
  const latestState = useRef(state)
  useLayoutEffect(() => { latestState.current = state }, [state])
  const draft = state.draft, recovery = useContext(ConcertRecoveryContext)
  const [localEditing, setLocalEditing] = useState(false), [performanceEditing, setPerformanceEditing] = useState(false), [legacyEditing, setLegacyEditing] = useState(false)
  const [localPick, setLocalPick] = useState<string>(), [performancePick, setPerformancePick] = useState<PerformanceSummary>()
  const [venueQuery, setVenueQuery] = useRecoveryField('venueQuery', '')
  const [placeQuery, setPlaceQuery] = useRecoveryField('placeQuery', '')
  const [eventQuery, setEventQuery] = useRecoveryField('eventQuery', '')
  const [performanceFilters, setPerformanceFilters] = useRecoveryField('performanceFilters', { period: 'past' })
  const [venueEditor, setVenueEditor] = useRecoveryField('venueEditor', null)
  const eventSearch = discoveryQuery(eventQuery, performanceFilters.q ?? ''), venueSearch = discoveryQuery(venueQuery, placeQuery)
  const [changingVenue, setChangingVenue] = useState(false), [editingEventName, setEditingEventName] = useState(false)
  const eventNameInput = useRef<HTMLInputElement>(null)
  useEffect(() => { if (editingEventName) eventNameInput.current?.focus() }, [editingEventName])
  const changingEvent = !!draft.eventChoicePending
  const [placeBusy, setPlaceBusy] = useState(false), [placeError, setPlaceError] = useState(''), [latest] = useState(createLatestRead)
  const recoveredEditor = recovery?.controller.snapshot.fields.venueEditor ?? venueEditor
  const artist = draft.artists.length === 1 ? providerArtistScopes(state)[0]?.scope : undefined
  const sourceTour = draft.tours.length === 1 && draft.tours[0].draft?.ref?.provider === 'setlist_fm' ? draft.tours[0] : null
  const sourceLocked = !!(draft.eventId || draft.providerEvent), reviewing = localEditing || performanceEditing || legacyEditing || !!recoveredEditor && !sourceLocked || placeBusy
  const manualSelected = !sourceLocked && !changingEvent && (!!draft.manualEvent || !!draft.concert.title)
  const performance = { period: performanceFilters.period ?? 'past', ...artist, ...(sourceTour?.draft?.ref ? { tourId: sourceTour.draft.ref.externalId, tourName: sourceTour.name } : state.draft.tours.length === 1 && !state.draft.tours[0].catalogId ? { tourName: state.draft.tours[0].name } : {}) }
  useEffect(() => { onEditingChange(reviewing || changingEvent); return () => onEditingChange(false) }, [reviewing, changingEvent, onEditingChange])
  useEffect(() => () => latest.cancel(), [latest, providers?.sessionKey, providers?.enabled])
  const setDraft = (patch: Partial<typeof draft>) => onChange({ ...state, draft: { ...draft, ...patch } })
  const field = <K extends keyof Concert,>(key: K, value: Concert[K]) => setDraft({ concert: { ...draft.concert, [key]: value } })
  const currentVenue = draft.pendingVenue ?? venues.find(v => v.id === draft.concert.venueId)
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
  if (draft.lineupReview?.event) return <div className="creation-step creation-event-step">
    <p className="creation-context">{draft.artists.map(artist => artist.name).join(' · ')}{draft.tours.length ? ` · ${draft.tours.map(tour => tour.name).join(' · ')}` : ''}</p>
    <CreationSelection label="Selected Event" name={draft.concert.title || 'Selected Event'} detail={`${draft.dateText || 'Date to confirm'} · ${selectedVenueName || 'Venue to confirm'}`} />
    <div role="alert" className="creation-conflict"><p>This Event was selected for {draft.lineupReview.event.artists.map(artist => artist.name).join(' · ')}{draft.lineupReview.event.tours.length ? ` · ${draft.lineupReview.event.tours.map(tour => tour.name).join(' · ')}` : ''}. Create a new Event to use your changed Artist or Tour. Your notes and setlist stay.</p><Button type="button" onPress={() => onChange(detachWizardEvent(state))}>Create new Event using these details</Button><Button type="button" onPress={() => onChange(keepWizardEvent(state))}>Keep selected Event</Button></div>
  </div>
  return <div className="creation-step creation-event-step">
    <p className="creation-context">{draft.artists.map(a => a.name).join(' · ')}{draft.tours.length > 0 ? ` · ${draft.tours.map(t => t.name).join(' · ')}` : ''}</p>
    {!sourceLocked && !manualSelected && <p className="creation-intro">Find your concert, or add the details yourself.</p>}
    {sourceLocked && !changingEvent && <CreationSelection label="Event / performance" name={draft.concert.title || draft.artists[0]?.name || 'Selected Event'} detail={`${draft.dateText || 'Date to confirm'} · ${selectedVenueName || 'Venue to confirm'}`} onChange={() => onChange(beginWizardEventChange(state))} />}
    {manualSelected && <div className="manual-event-selection">
      <CreationSelection label="Event / performance" name={draft.concert.title || draft.artists[0]?.name || 'New Event'} detail="Manual entry" actions={<>
        <Button type="button" className="icon-button" aria-label={editingEventName ? 'Finish editing Event name' : 'Edit Event name'} aria-expanded={editingEventName} onPress={() => setEditingEventName(!editingEventName)}><Icon name="edit" size={18} /></Button>
        <Button type="button" className="icon-button" aria-label="Clear Event" onPress={() => { setEditingEventName(false); onChange(beginWizardEventChange(state)) }}><Icon name="close" size={18} /></Button>
      </>} />
      {editingEventName && <label>Event name<input ref={eventNameInput} maxLength={255} value={draft.concert.title} onChange={event => field('title', event.target.value)} /></label>}
    </div>}
    <InteractionFieldset disabled={reviewing} className="wizard-shared-fields">
      {(!sourceLocked && !manualSelected || changingEvent) && <><CreationSearch kind="events" label="Event or performance" query={eventSearch.value} onQuery={setEventQuery} catalog={catalog} musicbrainz={musicbrainz} providers={providers} performance={performance} onPick={selectEvent} onCreate={() => { setEditingEventName(false); onChange(selectManualWizardEvent(state, eventSearch.value)) }} />
      <RecoveredSearch value={eventSearch.alternative} onUse={() => { setEventQuery(performanceFilters.q ?? ''); setPerformanceFilters({ ...performanceFilters, q: '' }) }} />
      </>}
    </InteractionFieldset>
    <EventCatalogPicker applyOnSelect hideSearch requestedId={localPick} onDismiss={() => setLocalPick(undefined)} recoverDraft musicbrainz={musicbrainz} catalog={catalog} onEditingChange={setLocalEditing} onUse={event => { onChange(adoptWizardEvent(latestState.current, event, venues)); setLocalPick(undefined) }} />
    <PerformancePicker applyOnSelect previewOnly initialSummary={performancePick} onDismiss={() => setPerformancePick(undefined)} providers={providers} filters={performance} query={eventSearch.value} onApply={preview => { onChange(applyPerformance(latestState.current, preview, venues)); setPerformancePick(undefined) }} onApplyManual={manual => { onChange(applyManualPerformance(latestState.current, manual)); setPerformancePick(undefined) }} onEditingChange={setPerformanceEditing} />
    {recovery?.controller.snapshot.fields.historyPerformancePreview && <details open><summary>Finish previous performance review</summary><PerformancePicker previewOnly history providers={providers} filters={recovery.controller.snapshot.fields.historyPerformanceFilters ?? { period: 'past' }} onApply={preview => onChange(applyPerformance(latestState.current, preview, venues))} onApplyManual={manual => onChange(applyManualPerformance(latestState.current, manual))} onEditingChange={setLegacyEditing} /></details>}
    {!sourceLocked && !changingEvent && <InteractionFieldset disabled={reviewing} className="manual-event-date wizard-shared-fields">
      <DateField className="event-date-label" isRequired isDisabled={reviewing} minValue={parseDate('0001-01-01')} maxValue={parseDate('9999-12-31')} value={dateFieldValue(draft.precision === 'exact' ? draft.dateText : '')} onChange={value => setDraft({ precision: 'exact', dateText: value?.toString() ?? '' })}>
        <Label>Date</Label>
        <DateInput className="event-date-input">{segment => <DateSegment segment={segment} />}</DateInput>
      </DateField>
    </InteractionFieldset>}
    {!sourceLocked && !changingEvent && <>
    {selectedVenueName && !recoveredEditor && <CreationSelection label="Venue" name={selectedVenueName} detail={currentVenue ? currentVenue.city : draft.concert.venueId === 'new' ? draft.city : ''} onChange={() => setChangingVenue(true)} />}
    <InteractionFieldset disabled={reviewing} className="wizard-shared-fields">
      {(!selectedVenueName || changingVenue) && <><CreationSearch kind="venues" label="Venue" query={venueSearch.value} onQuery={setVenueQuery} catalog={catalog} musicbrainz={musicbrainz} providers={providers} onPick={option => { if (option.type === 'local' && option.kind === 'venues') { onChange(applyLocalVenue(state, option.row, venues)); setChangingVenue(false) } if (option.type === 'venue') choosePlace(option.candidate) }} onCreate={() => setVenueEditor({ name: venueQuery, city: '', address: '', aliasesText: '', lat: '', lng: '', selection: null })} /><RecoveredSearch value={venueSearch.alternative} onUse={() => { setVenueQuery(placeQuery); setPlaceQuery('') }} /></>}
      {changingVenue && <Button type="button" className="text-button" onPress={() => setChangingVenue(false)}>Keep current Venue</Button>}
    </InteractionFieldset>
    {placeBusy && <p role="status">Loading Venue details…</p>}{placeError && <p role="alert">{placeError}</p>}
    {recoveredEditor && <VenueDraftFields onConfirm={confirmVenue} onCancel={() => { setVenueEditor(null); setChangingVenue(false) }} />}
    </>}

    <div className="flag-fields"><label><input type="checkbox" checked={draft.concert.interested} onChange={e => field('interested', e.target.checked)} /><Icon name="heart" size={18} />Interested</label><label><input type="checkbox" checked={draft.concert.ticketsBought} onChange={e => field('ticketsBought', e.target.checked)} /><Icon name="ticket" size={18} />Tickets bought</label></div>
    <label>Notes <span className="optional">optional</span><textarea rows={3} maxLength={2000} value={draft.concert.notes} onChange={e => field('notes', e.target.value)} /></label>
  </div>
}
