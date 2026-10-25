import { useEffect, useState } from 'react'
import { detachWizardEvent } from '../concertWizard'
import type { ArtistDraftInput, WizardArtistChoice, WizardState } from '../concertWizard'
import type { EntityOption } from '../autocomplete'
import type { WizardStepProps } from './wizardTypes'
import { EntityAutocomplete } from './EntityAutocomplete'
import { ArtistDraftFields } from './ArtistDraftFields'
export function WizardArtistStep({ state, onChange, onEditingChange, catalog, musicbrainz }: WizardStepProps) {
  const [initial, setInitial] = useState<{ input: ArtistDraftInput; mbid?: string } | null>(null)
  const [decision, setDecision] = useState<((s: WizardState) => WizardState) | null>(null)
  useEffect(() => { onEditingChange(!!initial || !!decision); return () => onEditingChange(false) }, [initial, decision, onEditingChange])
  const modify = (change: (s: WizardState) => WizardState) => { if (state.draft.eventId) setDecision(() => change); else onChange(change(state)) }
  const add = (choice: WizardArtistChoice) => modify(s => {
    const duplicate = s.draft.artists.some(a => choice.catalogId ? a.catalogId === choice.catalogId : a.draft && choice.draft && (a.draft.key === choice.draft.key || a.draft.mbid && a.draft.mbid === choice.draft.mbid))
    return duplicate ? s : { ...s, draft: { ...s.draft, artists: [...s.draft.artists, choice] } }
  })
  const pick = (option: EntityOption) => {
    if (option.source === 'catalog' && option.kind === 'artists') add({ name: option.row.name, catalogId: option.row.id })
    if (option.source === 'musicbrainz') {
      const c = option.candidate
      if (c.local) add({ name: c.local.label, catalogId: c.local.id })
      else setInitial({ input: { name: c.label, type: c.artistType, disambiguation: c.disambiguation, selection: c.selection }, mbid: c.id })
    }
    if (option.source === 'create') setInitial({ input: { name: option.query, type: null, disambiguation: null, selection: null } })
  }
  return <>{!initial && <EntityAutocomplete kind="artists" label="Find Artists" catalog={catalog} musicbrainz={musicbrainz} onPick={pick} />}{initial && <ArtistDraftFields initial={initial.input} onCancel={() => setInitial(null)} onConfirm={input => { add({ name: input.name, catalogId: null, draft: { key: crypto.randomUUID(), input, ...(input.selection ? { mbid: initial.mbid } : {}) } }); setInitial(null) }} />}<ul>{state.draft.artists.map((a, i) => <li key={a.catalogId ?? a.draft?.key ?? i}>{a.name} <small>{a.catalogId ? 'Local catalog' : a.draft?.input.disambiguation || 'Draft Artist'}</small><button type="button" aria-label={`Remove ${a.name} ${i + 1}`} onClick={() => modify(s => ({ ...s, draft: { ...s.draft, artists: s.draft.artists.filter((_, n) => n !== i) } }))}>Remove</button></li>)}</ul><p className="hint">Multiple Artists or an unknown lineup are welcome.</p>{decision && <div role="alert"><p>The selected Event has a saved lineup. Create a new Event to change these details.</p><button type="button" onClick={() => { onChange(decision(detachWizardEvent(state))); setDecision(null) }}>Create new Event using these details</button><button type="button" onClick={() => setDecision(null)}>Keep selected Event</button></div>}</>
}
