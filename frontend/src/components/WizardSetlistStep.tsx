import { useEffect, useState } from 'react'
import { changeWizardEntry } from '../concertWizard'
import type { ArtistDraftInput, SongDraftInput, WizardEntry } from '../concertWizard'
import type { EntityOption } from '../autocomplete'
import type { WizardStepProps } from './wizardTypes'
import { EntityAutocomplete } from './EntityAutocomplete'
import { ArtistDraftFields } from './ArtistDraftFields'
import { SongDraftFields } from './SongDraftFields'
import { SetlistEntryFields } from './SetlistEntryFields'
export function WizardSetlistStep({ state, onChange, onEditingChange, catalog, musicbrainz }: WizardStepProps) {
  const [editing, setEditing] = useState<WizardEntry | null>(null)
  const [song, setSong] = useState<SongDraftInput | null>(null), [artist, setArtist] = useState<ArtistDraftInput | null>(null)
  useEffect(() => { onEditingChange(!!editing); return () => onEditingChange(false) }, [editing, onEditingChange])
  const rows = state.draft.concert.setlist
  const updateRows = (setlist: WizardEntry[]) => onChange({ ...state, draft: { ...state.draft, concert: { ...state.draft.concert, setlist } } })
  const change = (patch: Partial<WizardEntry>) => setEditing(row => row ? changeWizardEntry(row, patch) : row)
  const pick = (option: EntityOption, kind: 'song' | 'artist') => {
    if (!editing) return
    if (option.source === 'draft' && option.artist.draft) change({ artist: option.artist.name, artistCatalogId: undefined, performerDraft: option.artist.draft })
    if (option.source === 'catalog') {
      if (option.kind === 'songs') change({ title: option.row.title, songCatalogId: option.row.id, songDraft: undefined })
      if (option.kind === 'artists') change({ artist: option.row.name, artistCatalogId: option.row.id, performerDraft: undefined })
    }
    if (option.source === 'musicbrainz') {
      const c = option.candidate
      if (c.local) { if (kind === 'song') change({ title: c.local.label, songCatalogId: c.local.id, songDraft: undefined }); else change({ artist: c.local.label, artistCatalogId: c.local.id, performerDraft: undefined }) }
      else if (kind === 'song') setSong({ title: c.label, disambiguation: c.disambiguation, selection: c.selection })
      else setArtist({ name: c.label, type: c.artistType, disambiguation: c.disambiguation, selection: c.selection })
    }
    if (option.source === 'create') { if (kind === 'song') setSong({ title: option.query, disambiguation: null, selection: null }); else setArtist({ name: option.query, type: null, disambiguation: null, selection: null }) }
  }
  return <><p className="hint">Unsaved draft. Skip this step to save an empty setlist.</p><button type="button" disabled={!!editing} onClick={() => setEditing({ id: crypto.randomUUID(), type: 'song', title: '', artist: '', tape: false })}>Add song</button><button type="button" disabled={!!editing} onClick={() => setEditing({ id: crypto.randomUUID(), type: 'note', title: '', artist: '', tape: false })}>Add note</button>
    {editing && <section className="setlist-editor"><SetlistEntryFields entry={editing} onChange={patch => { change(patch); if (patch.type === 'note') { setSong(null); setArtist(null) } }} songLookup={!song && !artist && <EntityAutocomplete kind="songs" label="Find a Song / MusicBrainz Work" catalog={catalog} musicbrainz={musicbrainz} onPick={o => pick(o, 'song')} />} performerLookup={!song && !artist && <EntityAutocomplete kind="artists" label="Find a performer" catalog={catalog} musicbrainz={musicbrainz} draftArtists={state.draft.artists} onPick={o => pick(o, 'artist')} />} />
    {song && <SongDraftFields initial={song} onCancel={() => setSong(null)} onConfirm={input => { change({ title: input.title, songCatalogId: undefined, songDraft: { key: crypto.randomUUID(), input } }); setSong(null) }} />}{artist && <ArtistDraftFields initial={artist} onCancel={() => setArtist(null)} onConfirm={input => { change({ artist: input.name, artistCatalogId: undefined, performerDraft: { key: crypto.randomUUID(), input } }); setArtist(null) }} />}
    {editing.type === 'song' && <button type="button" onClick={() => change({ songCatalogId: undefined, songDraft: undefined })}>Use text only</button>}
    <button type="button" disabled={!editing.title.trim() || !!song || !!artist} onClick={() => { updateRows(rows.some(r => r.id === editing.id) ? rows.map(r => r.id === editing.id ? editing : r) : [...rows, editing]); setEditing(null) }}>{rows.some(r => r.id === editing.id) ? 'Update row' : 'Add row'}</button><button type="button" onClick={() => { setEditing(null); setSong(null); setArtist(null) }}>Cancel row</button></section>}
    <ol>{rows.map((row, index) => <li key={row.id}><strong>{row.title}</strong>{row.artist && ` · ${row.artist}`}{row.tape && ' · Tape'}<button type="button" disabled={!!editing} onClick={() => setEditing(row)}>Edit {index + 1}</button><button type="button" disabled={!!editing || index === 0} aria-label={`Move entry ${index + 1} up`} onClick={() => { const next = [...rows]; [next[index - 1], next[index]] = [next[index], next[index - 1]]; updateRows(next) }}>Up</button><button type="button" disabled={!!editing || index === rows.length - 1} aria-label={`Move entry ${index + 1} down`} onClick={() => { const next = [...rows]; [next[index], next[index + 1]] = [next[index + 1], next[index]]; updateRows(next) }}>Down</button><button type="button" disabled={!!editing} onClick={() => updateRows(rows.filter(r => r.id !== row.id))}>Remove {index + 1}</button></li>)}</ol>
    <aside className="hint"><strong>Review</strong><p>{state.draft.artists.map(a => a.name).join(' · ') || 'Lineup to confirm'}</p><p>{state.draft.tours.map(t => t.name).join(' · ') || 'No Tours'}</p><p>{state.draft.concert.title || 'Title from Artists'} · {state.draft.dateText || 'Date to confirm'}</p></aside></>
}
