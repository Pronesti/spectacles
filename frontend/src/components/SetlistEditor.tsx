import { useState } from 'react'
import type { FormEvent } from 'react'
import type { SetlistEntry } from '../domain'
import { changeSetlistEntry } from '../concertDraft'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
import { SetlistEntryFields } from './SetlistEntryFields'
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
  return <form className="setlist-editor" onSubmit={submit}><SetlistEntryFields entry={draft} onChange={change} songLookup={<CatalogPicker catalog={catalog} kind="songs" label="Find an existing Song" onPick={song => change({ title: song.title, songCatalogId: song.id })} />} performerLookup={<CatalogPicker catalog={catalog} kind="artists" label="Search a performer" onPick={artist => change({ artist: artist.name, artistCatalogId: artist.id })} />} /><div className="entry-save-actions"><button className="button primary small" type="submit">Save entry</button><button className="button secondary small" type="button" onClick={onCancel}>Cancel</button></div>
    {message && <p role="alert">{message}</p>}<p className="hint">Saved to your account. A Song selection does not identify its performer.</p>
  </form>
}
