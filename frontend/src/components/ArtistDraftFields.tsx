import { Button } from 'react-aria-components'
import { useEffect, useRef, useState } from 'react'
import { Icon } from './Icon'
import { CreationSelection } from './CreationSelection'
import { changeArtistInput, normalizeArtistDraft, validArtistDraft } from '../concertWizard'
import type { ArtistDraftInput } from '../concertWizard'
export function ArtistDraftFields({ initial, value, onChange, onConfirm, onCancel, review = false }: { review?: boolean; initial: ArtistDraftInput; value?: ArtistDraftInput; onChange?: (input: ArtistDraftInput) => void; onConfirm: (input: ArtistDraftInput) => void; onCancel: () => void }) {
  const [local, setLocal] = useState(initial)
  const [editing, setEditing] = useState(!initial.name.trim())
  const nameInput = useRef<HTMLInputElement>(null)
  useEffect(() => { if (!review || editing) nameInput.current?.focus() }, [review, editing])
  const input = value ?? local
  const setInput = (next: ArtistDraftInput) => { setLocal(next); onChange?.(next) }
  const valid = validArtistDraft(input)
  if (review) return (
    <div className="artist-draft-review">
      <CreationSelection label="Artist" name={input.name} detail={[input.selection ? 'MusicBrainz match selected' : 'Manual entry', input.type, input.disambiguation].filter(Boolean).join(' · ')} actions={<>
        <Button type="button" className="icon-button" aria-label={editing ? 'Finish editing Artist' : 'Edit Artist'} aria-expanded={editing} onPress={() => setEditing(!editing)}><Icon name="edit" size={18} /></Button>
        <Button type="button" className="icon-button" aria-label="Clear Artist" onPress={onCancel}><Icon name="close" size={18} /></Button>
      </>} />
      {editing && <div className="artist-draft-fields">
        <label>Name<input ref={nameInput} maxLength={255} value={input.name} onChange={e => setInput(changeArtistInput(input, { name: e.target.value }))} /></label>
        <label>Type<input maxLength={255} value={input.type ?? ''} onChange={e => setInput(changeArtistInput(input, { type: e.target.value }))} /></label>
        <label>Disambiguation<input maxLength={255} value={input.disambiguation ?? ''} onChange={e => setInput(changeArtistInput(input, { disambiguation: e.target.value }))} /></label>
      </div>}
    </div>
  )
  return <section><p className="hint">{input.selection ? 'MusicBrainz match selected' : 'Manual entry'}</p><label>Name<input ref={nameInput} maxLength={255} value={input.name} onChange={e => setInput(changeArtistInput(input, { name: e.target.value }))} /></label><details><summary>Artist details (optional)</summary><label>Type<input maxLength={255} value={input.type ?? ''} onChange={e => setInput(changeArtistInput(input, { type: e.target.value }))} /></label><label>Disambiguation<input maxLength={255} value={input.disambiguation ?? ''} onChange={e => setInput(changeArtistInput(input, { disambiguation: e.target.value }))} /></label></details><Button type="button" className="button primary small" isDisabled={!valid} onPress={() => { if (valid) onConfirm(normalizeArtistDraft(input)) }}>Use artist</Button><Button type="button" className="button secondary small" onPress={onCancel}>Cancel</Button></section>
}
