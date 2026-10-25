import { useState } from 'react'
import { changeArtistInput } from '../concertWizard'
import type { ArtistDraftInput } from '../concertWizard'
export function ArtistDraftFields({ initial, onConfirm, onCancel }: { initial: ArtistDraftInput; onConfirm: (input: ArtistDraftInput) => void; onCancel: () => void }) {
  const [input, setInput] = useState(initial)
  const valid = !!input.name.trim() && input.name.length <= 255 && (input.type?.length ?? 0) <= 255 && (input.disambiguation?.length ?? 0) <= 255
  return <section><p className="hint">{input.selection ? 'MusicBrainz match selected' : 'Manual entry'}</p><label>Name<input maxLength={255} value={input.name} onChange={e => setInput(changeArtistInput(input, { name: e.target.value }))} /></label><label>Type<input maxLength={255} value={input.type ?? ''} onChange={e => setInput(changeArtistInput(input, { type: e.target.value }))} /></label><label>Disambiguation<input maxLength={255} value={input.disambiguation ?? ''} onChange={e => setInput(changeArtistInput(input, { disambiguation: e.target.value }))} /></label><button type="button" className="button primary small" disabled={!valid} onClick={() => { if (valid) onConfirm({ ...input, name: input.name.trim(), type: input.type?.trim() || null, disambiguation: input.disambiguation?.trim() || null }) }}>Use artist</button><button type="button" className="button secondary small" onClick={onCancel}>Cancel</button></section>
}
