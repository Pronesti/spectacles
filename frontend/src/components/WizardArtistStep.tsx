import { Button } from 'react-aria-components'
import { useEffect, useImperativeHandle, useState } from 'react'
import { changeWizardArtists, normalizeArtistDraft, validArtistDraft } from '../concertWizard'
import type { WizardArtistChoice, WizardState } from '../concertWizard'
import type { CreationMatch } from '../creationSearch'
import type { WizardStepProps } from './wizardTypes'
import { CreationSearch } from './CreationSearch'
import { CreationSelection } from './CreationSelection'
import { useRecoveryField } from './ConcertRecovery'
import { ArtistDraftFields } from './ArtistDraftFields'
export function WizardArtistStep({ state, onChange, onEditingChange, catalog, musicbrainz, continueAction }: WizardStepProps) {
  const [initial, setInitial] = useRecoveryField('artistEditor', null)
  const [decision, setDecision] = useRecoveryField('artistDecision', null)
  const [query, setQuery] = useRecoveryField('artistQuery', '')
  const [changing, setChanging] = useState(false)
  useEffect(() => { onEditingChange(!!initial || !!decision); return () => onEditingChange(false) }, [initial, decision, onEditingChange])
  const apply = (s: WizardState, action: NonNullable<typeof decision>): WizardState => {
    const artists = 'remove' in action ? s.draft.artists.filter((_, n) => n !== action.remove) : [action.add]
    return changeWizardArtists(s, artists)
  }
  const modify = (action: NonNullable<typeof decision>) => { onChange(apply(state, action)); setDecision(null); setChanging(false) }
  const add = (choice: WizardArtistChoice) => modify({ add: choice })
  const pick = (option: CreationMatch) => {
    if (option.type === 'local' && option.kind === 'artists') add({ name: option.row.name, catalogId: option.row.id })
    if (option.type === 'musicbrainz') {
      const c = option.candidate
      if (c.local) add({ name: c.local.label, catalogId: c.local.id })
      else setInitial({ input: { name: c.label, type: c.artistType, disambiguation: c.disambiguation, selection: c.selection }, mbid: c.id })
    }
  }
  useImperativeHandle(continueAction, () => () => {
      if (decision) { const next = apply(state, decision); setDecision(null); setChanging(false); return next }
      if (!initial || !validArtistDraft(initial.input)) return null
      const input = normalizeArtistDraft(initial.input)
      const choice: WizardArtistChoice = { name: input.name, catalogId: null, draft: { key: crypto.randomUUID(), input, ...(input.selection ? { mbid: initial.mbid } : {}) } }
      setInitial(null)
      setChanging(false)
      return apply(state, { add: choice })
    })
  const artists = decision ? 'add' in decision ? [decision.add] : state.draft.artists.filter((_, index) => index !== decision.remove) : state.draft.artists
  return <div className="creation-step"><p className="creation-intro">Choose the Artist you’re going to see.</p>
    {artists.length > 1 && <div className="creation-conflict" role="alert"><p>This draft has several Artists. Choose one to continue. Your choices stay here until you decide. Review any existing Tour in the next step.</p>{artists.map((a, i) => <div key={a.catalogId ?? a.draft?.key ?? i}><strong>{a.name}</strong><Button type="button" onPress={() => add(a)}>Keep only {a.name}</Button><Button type="button" onPress={() => modify({ remove: i })}>Remove {a.name}</Button></div>)}</div>}
    {artists.length === 1 && !initial && <CreationSelection label="Artist" name={artists[0].name} detail={artists[0].draft?.input.disambiguation || (artists[0].catalogId ? 'Your catalog' : 'New Artist')} onChange={() => setChanging(true)} />}
    {!initial && (!artists.length || changing) && <CreationSearch kind="artists" label="Artist" query={query} onQuery={setQuery} catalog={catalog} musicbrainz={musicbrainz} onPick={pick} onCreate={() => setInitial({ input: { name: query, type: null, disambiguation: null, selection: null } })} />}
    {changing && !initial && artists.length > 0 && <><p className="hint">You can review the previous Tour in the next step.</p><Button type="button" className="text-button" onPress={() => setChanging(false)}>Keep current Artist</Button></>}
    {initial && <ArtistDraftFields review initial={initial.input} value={initial.input} onChange={input => setInitial({ ...initial, input })} onCancel={() => { setInitial(null); setQuery('') }} onConfirm={input => { add({ name: input.name, catalogId: null, draft: { key: crypto.randomUUID(), input, ...(input.selection ? { mbid: initial.mbid } : {}) } }); setInitial(null) }} />}

  </div>
}
