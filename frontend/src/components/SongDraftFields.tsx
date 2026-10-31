import { useState } from 'react'
import { changeSongInput } from '../concertWizard'
import type { SongDraftInput } from '../concertWizard'
export function SongDraftFields({ initial, value, onChange, onConfirm, onCancel }: { initial: SongDraftInput; value?: SongDraftInput; onChange?: (input: SongDraftInput) => void; onConfirm: (input: SongDraftInput) => void; onCancel: () => void }) {
  const [local, setLocal] = useState(initial)
  const input = value ?? local
  const setInput = (next: SongDraftInput) => { setLocal(next); onChange?.(next) }
  const valid = !!input.title.trim() && input.title.length <= 255 && (input.disambiguation?.length ?? 0) <= 255
  return <section><p className="hint">{input.selection ? 'MusicBrainz match selected' : 'Manual entry'}</p><label>Title<input maxLength={255} value={input.title} onChange={e => setInput(changeSongInput(input, { title: e.target.value }))} /></label><label>Disambiguation<input maxLength={255} value={input.disambiguation ?? ''} onChange={e => setInput(changeSongInput(input, { disambiguation: e.target.value }))} /></label><button type="button" className="button primary small" disabled={!valid} onClick={() => { if (valid) onConfirm({ ...input, title: input.title.trim(), disambiguation: input.disambiguation?.trim() || null }) }}>Use song</button><button type="button" className="button secondary small" onClick={onCancel}>Cancel</button></section>
}
