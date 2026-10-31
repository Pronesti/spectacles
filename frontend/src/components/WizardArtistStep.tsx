import { useEffect, useState } from 'react'
import { detachWizardEvent } from '../concertWizard'
import type { WizardArtistChoice, WizardState } from '../concertWizard'
import type { CreationMatch } from '../creationSearch'
import type { WizardStepProps } from './wizardTypes'
import { CreationSearch } from './CreationSearch'
import { CreationSelection } from './CreationSelection'
import { useRecoveryField } from './ConcertRecovery'
import { ArtistDraftFields } from './ArtistDraftFields'
export function WizardArtistStep({ state, onChange, onEditingChange, catalog, musicbrainz }: WizardStepProps) {
  const [initial, setInitial] = useRecoveryField('artistEditor', null)
  const [decision, setDecision] = useRecoveryField('artistDecision', null)
  const [query, setQuery] = useRecoveryField('artistQuery', '')
  const [changing, setChanging] = useState(false)
  useEffect(() => { onEditingChange(!!initial || !!decision); return () => onEditingChange(false) }, [initial, decision, onEditingChange])
  const apply = (s: WizardState, action: NonNullable<typeof decision>): WizardState => {
    const artists = 'remove' in action ? s.draft.artists.filter((_, n) => n !== action.remove) : [action.add]
    // An Artist replacement invalidates the old Artist-scoped Tour choice.
    return { ...s, draft: { ...s.draft, artists, ...('add' in action ? { tours: [] } : {}) } }
  }
  const modify = (action: NonNullable<typeof decision>) => { if (state.draft.eventId || state.draft.providerEvent) setDecision(action); else { onChange(apply(state, action)); setChanging(false) } }
  const add = (choice: WizardArtistChoice) => modify({ add: choice })
  const pick = (option: CreationMatch) => {
    if (option.type === 'local' && option.kind === 'artists') add({ name: option.row.name, catalogId: option.row.id })
    if (option.type === 'musicbrainz') {
      const c = option.candidate
      if (c.local) add({ name: c.local.label, catalogId: c.local.id })
      else setInitial({ input: { name: c.label, type: c.artistType, disambiguation: c.disambiguation, selection: c.selection }, mbid: c.id })
    }
  }
  const artists = state.draft.artists
  return <div className="creation-step"><p className="creation-intro">Choose the Artist you’re going to see.</p>
    {artists.length > 1 && <div className="creation-conflict" role="alert"><p>This draft has several Artists. Choose one to continue. Your choices stay here until you decide. Replacing the Artist also clears its Tour.</p>{artists.map((a, i) => <div key={a.catalogId ?? a.draft?.key ?? i}><strong>{a.name}</strong><button type="button" onClick={() => add(a)}>Keep only {a.name}</button><button type="button" onClick={() => modify({ remove: i })}>Remove {a.name}</button></div>)}</div>}
    {artists.length === 1 && !initial && <CreationSelection label="Artist" name={artists[0].name} detail={artists[0].draft?.input.disambiguation || (artists[0].catalogId ? 'Your catalog' : 'New Artist')} onChange={() => setChanging(true)} />}
    {!initial && (!artists.length || changing) && <CreationSearch kind="artists" label="Artist" query={query} onQuery={setQuery} catalog={catalog} musicbrainz={musicbrainz} onPick={pick} onCreate={() => setInitial({ input: { name: query, type: null, disambiguation: null, selection: null } })} />}
    {changing && !initial && artists.length > 0 && <><p className="hint">Replacing the Artist clears the previous Tour.</p><button type="button" className="text-button" onClick={() => setChanging(false)}>Keep current Artist</button></>}
    {initial && <ArtistDraftFields initial={initial.input} value={initial.input} onChange={input => setInitial({ ...initial, input })} onCancel={() => setInitial(null)} onConfirm={input => { add({ name: input.name, catalogId: null, draft: { key: crypto.randomUUID(), input, ...(input.selection ? { mbid: initial.mbid } : {}) } }); setInitial(null) }} />}
    {decision && <div role="alert" className="creation-conflict"><p>Changing the Artist creates a new Event and clears the previous Tour. Your notes and setlist stay.</p><button type="button" onClick={() => { onChange(apply(detachWizardEvent(state), decision)); setDecision(null); setChanging(false) }}>Create new Event using these details</button><button type="button" onClick={() => setDecision(null)}>Keep selected Event</button></div>}
  </div>
}
