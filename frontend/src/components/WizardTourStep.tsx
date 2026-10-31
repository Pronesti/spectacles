import { useEffect, useState } from 'react'
import { discoveryQuery } from '../discoveryQuery'
import { RecoveredSearch } from './RecoveredSearch'
import { changeTourInput, detachWizardEvent } from '../concertWizard'
import type { WizardState, WizardTourChoice } from '../concertWizard'
import type { CreationMatch } from '../creationSearch'
import type { WizardStepProps } from './wizardTypes'
import { tourArtistScope } from '../autocomplete'
import { providerArtistScopes } from '../providerDraft'
import { useRecoveryField } from './ConcertRecovery'
import { CreationSearch } from './CreationSearch'
import { CreationSelection } from './CreationSelection'
export function WizardTourStep({ state, onChange, onEditingChange, catalog, musicbrainz, providers }: WizardStepProps) {
  const [query, setQuery] = useRecoveryField('tourQuery', '')
  const [sourceQuery, setSourceQuery] = useRecoveryField('tourProviderQuery', '')
  const search = discoveryQuery(query, sourceQuery)
  const [editing, setEditing] = useRecoveryField('tourEditor', null)
  const [decision, setDecision] = useRecoveryField('tourDecision', null)
  const [changing, setChanging] = useState(false)
  useEffect(() => { onEditingChange(editing !== null || !!decision); return () => onEditingChange(false) }, [editing, decision, onEditingChange])
  const apply = (s: WizardState, action: NonNullable<typeof decision>): WizardState => ({ ...s, draft: { ...s.draft, tours: 'remove' in action ? s.draft.tours.filter((_, n) => n !== action.remove) : [action.add] } })
  const modify = (action: NonNullable<typeof decision>) => { if (state.draft.eventId || state.draft.providerEvent) setDecision(action); else { onChange(apply(state, action)); setChanging(false) } }
  const add = (choice: WizardTourChoice) => modify({ add: choice })
  const pick = (option: CreationMatch) => {
    if (option.type === 'local' && option.kind === 'tours') add({ name: option.row.name, catalogId: option.row.id })
    if (option.type === 'tour') { const c = option.candidate; if (c.localId) add({ name: c.values.name, catalogId: c.localId }); else setEditing({ input: { name: c.values.name, selection: c.selection }, ref: c.ref, context: 'setlist.fm' }) }
    if (option.type === 'musicbrainz') { const c = option.candidate; if (c.local) add({ name: c.local.label, catalogId: c.local.id }); else setEditing({ input: { name: c.label, selection: c.selection }, mbid: c.id, context: 'MusicBrainz' }) }
  }
  const tours = state.draft.tours
  return <div className="creation-step"><p className="creation-intro">Optional. Find a Tour for {state.draft.artists[0]?.name || 'your Artist'}, or continue without one.</p>
    {tours.length > 1 && <div role="alert" className="creation-conflict"><p>This draft has several Tours. Keep one or remove them before continuing.</p>{tours.map((t, i) => <div key={t.catalogId ?? t.draft?.key ?? i}><strong>{t.name}</strong><button type="button" onClick={() => add(t)}>Keep only {t.name}</button><button type="button" onClick={() => modify({ remove: i })}>Remove {t.name}</button></div>)}</div>}
    {tours.length === 1 && !editing && <CreationSelection label="Tour" name={tours[0].name} detail={state.draft.artists[0]?.name} onChange={() => setChanging(true)} onRemove={() => modify({ remove: 0 })} />}
    {!editing && (!tours.length || changing) && <><CreationSearch kind="tours" label="Tour" query={search.value} onQuery={setQuery} catalog={catalog} musicbrainz={musicbrainz} providers={providers} filters={tourArtistScope(state.draft.artists)} artist={state.draft.artists.length === 1 ? providerArtistScopes(state)[0]?.scope : undefined} onPick={pick} onCreate={() => setEditing({ input: { name: query, selection: null } })} /><RecoveredSearch value={search.alternative} onUse={() => { setQuery(sourceQuery); setSourceQuery('') }} /></>}
    {changing && !editing && <button type="button" className="text-button" onClick={() => setChanging(false)}>Keep current Tour</button>}
    {editing && <section className="creation-review"><h4>Review Tour</h4><p className="hint">{editing.input.selection ? editing.context || 'Provider match' : 'Manual entry'}</p><label>Tour name<input maxLength={255} value={editing.input.name} onChange={e => { const input = changeTourInput(editing.input, { name: e.target.value }); setEditing(input.selection ? { ...editing, input } : { input }) }} /></label><button type="button" className="button primary small" disabled={!editing.input.name.trim()} onClick={() => { const input = { ...editing.input, name: editing.input.name.trim() }; add({ name: input.name, catalogId: null, ...(input.selection ? { draft: { key: crypto.randomUUID(), input, mbid: editing.mbid, ...(editing.ref ? { ref: editing.ref } : {}) } } : {}) }); setEditing(null) }}>Use tour</button><button type="button" className="text-button" onClick={() => setEditing(null)}>Cancel</button></section>}
    {!tours.length && <p className="hint">No Tour? Skip this step with Continue.</p>}
    {decision && <div role="alert" className="creation-conflict"><p>Create a new Event to change its Tour. Your notes and setlist stay.</p><button type="button" onClick={() => { onChange(apply(detachWizardEvent(state), decision)); setDecision(null); setChanging(false) }}>Create new Event using these details</button><button type="button" onClick={() => setDecision(null)}>Keep selected Event</button></div>}
  </div>
}
