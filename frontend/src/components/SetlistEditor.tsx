import { useState } from 'react'
import type { FormEvent } from 'react'
import type { SetlistEntry } from '../domain'
import { changeSetlistEntry } from '../concertDraft'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
import { Icon } from './Icon'
export function SetlistEditor({ entry, catalog, onSave, onCancel }: { entry: SetlistEntry; catalog: CatalogAccess; onSave: (entry: SetlistEntry) => void | Promise<boolean>; onCancel: () => void }) {
  const [draft, setDraft] = useState(entry)
  const [message, setMessage] = useState('')
  const change = (patch: Partial<SetlistEntry>) => setDraft(current => changeSetlistEntry(current, patch))
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!draft.title.trim()) return
    const saved = await onSave({ ...draft, title: draft.songCatalogId ? draft.title : draft.title.trim(), artist: draft.artistCatalogId ? draft.artist : draft.artist.trim() })
    if (saved === false) setMessage('The database save was not confirmed. Your entry is still open. Check your connection or session.')
  }
  return <form className="setlist-editor" onSubmit={submit}><div className="entry-editor-top"><label>Entry type<select value={draft.type} onChange={event => change({ type: event.target.value as 'song' | 'note' })}><option value="song">Song</option><option value="note">Note / section</option></select></label><label className="tape-checkbox"><input type="checkbox" disabled={draft.type === 'note'} checked={draft.tape} onChange={event => change({ tape: event.target.checked })} /><Icon name="tape" size={17} />Tape / playback</label></div>
    {draft.type === 'song' && <CatalogPicker catalog={catalog} kind="songs" label="Find an existing Song" onPick={song => change({ title: song.title, songCatalogId: song.id })} />}
    <div className="entry-editor-bottom"><label>{draft.type === 'song' ? 'Song title' : 'Note'}<input autoFocus required maxLength={300} value={draft.title} onChange={event => change({ title: event.target.value })} /></label>{draft.type === 'song' && <label>Performer<input maxLength={255} value={draft.artist} onChange={event => change({ artist: event.target.value })} /></label>}<div className="entry-save-actions"><button className="button primary small" type="submit">Save entry</button><button className="button secondary small" type="button" onClick={onCancel}>Cancel</button></div></div>
    {draft.type === 'song' && <CatalogPicker catalog={catalog} kind="artists" label="Search a performer" onPick={artist => change({ artist: artist.name, artistCatalogId: artist.id })} />}
    {message && <p role="alert">{message}</p>}<p className="hint">Saved to your account. A Song selection does not identify its performer.</p>
  </form>
}
