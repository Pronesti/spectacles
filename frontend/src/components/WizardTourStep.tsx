import { Button } from 'react-aria-components'
import { useEffect, useImperativeHandle, useRef, useState } from 'react'
import { discoveryQuery } from '../discoveryQuery'
import { RecoveredSearch } from './RecoveredSearch'
import { changeTourInput, validTourDraft, changeWizardTours, resolveWizardTour } from '../concertWizard'
import type { WizardState, WizardTourChoice } from '../concertWizard'
import type { CreationMatch } from '../creationSearch'
import type { WizardStepProps } from './wizardTypes'
import { tourArtistScope } from '../autocomplete'
import { providerArtistScopes } from '../providerDraft'
import { useRecoveryField } from './ConcertRecovery'
import { CreationSearch } from './CreationSearch'
import { CreationSelection } from './CreationSelection'
export function WizardTourStep({ state, onChange, onEditingChange, catalog, musicbrainz, providers, continueAction }: WizardStepProps) {
  const [query, setQuery] = useRecoveryField('tourQuery', '')
  const [sourceQuery, setSourceQuery] = useRecoveryField('tourProviderQuery', '')
  const search = discoveryQuery(query, sourceQuery)
  const [editing, setEditing] = useRecoveryField('tourEditor', null)
  const [decision, setDecision] = useRecoveryField('tourDecision', null)
  const [changing, setChanging] = useState(false)
  const [fieldsOpen, setFieldsOpen] = useState(false)
  const nameInput = useRef<HTMLInputElement>(null)
  const showFields = !!editing && (fieldsOpen || !editing.input.name.trim())
  useEffect(() => { if (showFields) nameInput.current?.focus() }, [showFields])
  useEffect(() => { onEditingChange(editing !== null || !!decision); return () => onEditingChange(false) }, [editing, decision, onEditingChange])
  const apply = (s: WizardState, action: NonNullable<typeof decision>): WizardState => changeWizardTours(s, 'remove' in action ? s.draft.tours.filter((_, n) => n !== action.remove) : [action.add])
  const modify = (action: NonNullable<typeof decision>) => { onChange(apply(state, action)); setDecision(null); setChanging(false) }
  const add = (choice: WizardTourChoice) => modify({ add: choice })
  const pick = (option: CreationMatch) => {
    setFieldsOpen(false)
    if (option.type === 'local' && option.kind === 'tours') add({ name: option.row.name, catalogId: option.row.id })
    if (option.type === 'tour') { const c = option.candidate; if (c.localId) add({ name: c.values.name, catalogId: c.localId }); else if (c.ref) setEditing({ input: { name: c.values.name, selection: c.selection }, ref: c.ref, context: 'setlist.fm' }); else setEditing({ input: { name: c.values.name, selection: null }, context: 'Tour name from setlist.fm · confirm manually' }) }
    if (option.type === 'musicbrainz') { const c = option.candidate; if (c.local) add({ name: c.local.label, catalogId: c.local.id }); else setEditing({ input: { name: c.label, selection: c.selection }, mbid: c.id, context: 'MusicBrainz' }) }
  }
  useImperativeHandle(continueAction, () => () => {
    if (decision) { const next = apply(state, decision); setDecision(null); return next }
    if (!editing || !validTourDraft(editing.input)) return null
    const input = { ...editing.input, name: editing.input.name.trim() }
    const choice: WizardTourChoice = { name: input.name, catalogId: null, ...(input.selection ? { draft: { key: crypto.randomUUID(), input, mbid: editing.mbid, ...(editing.ref ? { ref: editing.ref } : {}) } } : {}) }
    const next = apply(state, { add: choice })
    setEditing(null); setChanging(false)
    return next
  })
  const tours = decision ? 'add' in decision ? [decision.add] : state.draft.tours.filter((_, index) => index !== decision.remove) : state.draft.tours
  if (state.draft.lineupReview?.tour) return <div className="creation-step">
    <p className="creation-intro">Choose a Tour for {state.draft.artists[0]?.name || 'your Artist'}.</p>
    {state.draft.tours.map((tour, index) => <CreationSelection key={index} label="Previous Tour" name={tour.name} detail={state.draft.lineupReview?.tour?.artists.map(artist => artist.name).join(' · ')} />)}
    <div role="alert" className="creation-conflict"><p>This Tour was selected for the previous Artist. Clear it to choose a Tour for the new Artist, or keep the previous Artist and Tour.</p><Button type="button" onPress={() => onChange(resolveWizardTour(state, false))}>Clear previous Tour</Button><Button type="button" onPress={() => onChange(resolveWizardTour(state, true))}>Keep previous Artist and Tour</Button></div>
  </div>
  return <div className="creation-step"><p className="creation-intro">Optional. Find a Tour for {state.draft.artists[0]?.name || 'your Artist'}, or continue without one.</p>
    {tours.length > 1 && <div role="alert" className="creation-conflict"><p>This draft has several Tours. Keep one or remove them before continuing.</p>{tours.map((t, i) => <div key={t.catalogId ?? t.draft?.key ?? i}><strong>{t.name}</strong><Button type="button" onPress={() => add(t)}>Keep only {t.name}</Button><Button type="button" onPress={() => modify({ remove: i })}>Remove {t.name}</Button></div>)}</div>}
    {tours.length === 1 && !editing && <CreationSelection label="Tour" name={tours[0].name} detail={state.draft.artists[0]?.name} onChange={() => setChanging(true)} onRemove={() => modify({ remove: 0 })} />}
    {!editing && (!tours.length || changing) && <><CreationSearch kind="tours" label="Tour" query={search.value} onQuery={setQuery} catalog={catalog} musicbrainz={musicbrainz} providers={providers} filters={tourArtistScope(state.draft.artists)} artist={state.draft.artists.length === 1 ? providerArtistScopes(state)[0]?.scope : undefined} onPick={pick} onCreate={() => { setFieldsOpen(false); setEditing({ input: { name: query, selection: null } }) }} /><RecoveredSearch value={search.alternative} onUse={() => { setQuery(sourceQuery); setSourceQuery('') }} /></>}
    {changing && !editing && <Button type="button" className="text-button" onPress={() => setChanging(false)}>Keep current Tour</Button>}
    {editing && <div className="tour-draft-review">
      <CreationSelection label="Tour" name={editing.input.name} detail={<>{[state.draft.artists[0]?.name, editing.context].filter(Boolean).join(' · ')}{editing.context?.includes('setlist.fm') && <> · <a href="https://www.setlist.fm/" target="_blank" rel="noreferrer">Source: setlist.fm</a></>}</>} onChange={() => setFieldsOpen(!fieldsOpen)} onRemove={() => { setEditing(null); setQuery(''); setSourceQuery(''); setFieldsOpen(false) }} />
      {showFields && <label>Tour name<input ref={nameInput} maxLength={255} value={editing.input.name} onChange={e => { const input = changeTourInput(editing.input, { name: e.target.value }); setEditing(input.selection ? { ...editing, input } : { input }) }} /></label>}
    </div>}

    {!tours.length && !editing && <p className="hint">No Tour? Skip this step with Continue.</p>}

  </div>
}
