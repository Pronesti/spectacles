import { useState } from 'react'
import type { FormEvent } from 'react'
import { formatDate, moveEntry, sortConcerts } from '../domain'
import type { Concert, SetlistEntry, Venue } from '../domain'
import stagePhoto from '../assets/concert-stage.png'
import { Icon } from './Icon'

export function SetlistsView({ concerts, venues, selectedId, onSelect, onUpdate }: { concerts: Concert[]; venues: Venue[]; selectedId: string; onSelect: (id: string) => void; onUpdate: (concert: Concert, message: string, undoable?: boolean) => void }) {
  const sorted = sortConcerts(concerts, true)
  const concert = concerts.find(c => c.id === selectedId) ?? sorted[0]
  const [editing, setEditing] = useState<SetlistEntry | null>(null)
  if (!concert) return <div className="empty-state"><Icon name="list" size={36} /><h3>Your first setlist starts with a concert.</h3><p>Add a concert, then come back to capture the songs.</p></div>
  const venue = venues.find(v => v.id === concert.venueId)
  const songs = concert.setlist.filter(e => e.type === 'song').length
  const save = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!editing?.title.trim()) return
    const entry = { ...editing, title: editing.title.trim(), artist: editing.artist.trim() }
    onUpdate({ ...concert, setlist: concert.setlist.some(e => e.id === entry.id) ? concert.setlist.map(e => e.id === entry.id ? entry : e) : [...concert.setlist, entry] }, 'Setlist saved in this browser.')
    setEditing(null)
  }
  const add = (type: 'song' | 'note') => setEditing({ id: crypto.randomUUID(), type, title: '', artist: type === 'song' ? concert.artists[0] : '', tape: false })
  let songNumber = 0
  const editor = editing && <form className="setlist-editor" onSubmit={save}><div className="entry-editor-top"><label>Entry type<select value={editing.type} onChange={e => setEditing({ ...editing, type: e.target.value as 'song' | 'note' })}><option value="song">Song</option><option value="note">Note / section</option></select></label><label className="tape-checkbox"><input type="checkbox" checked={editing.tape} onChange={e => setEditing({ ...editing, tape: e.target.checked })} /><Icon name="tape" size={17} />Tape / playback</label></div><div className="entry-editor-bottom"><label>{editing.type === 'song' ? 'Song title' : 'Note'}<input autoFocus required maxLength={300} placeholder={editing.type === 'song' ? 'Song title' : 'e.g. Encore'} value={editing.title} onChange={e => setEditing({ ...editing, title: e.target.value })} /></label>{editing.type === 'song' && <label>Performer<input maxLength={150} value={editing.artist} onChange={e => setEditing({ ...editing, artist: e.target.value })} placeholder="Artist" /></label>}<div className="entry-save-actions"><button className="button primary small" type="submit">Save entry</button><button className="button secondary small" type="button" onClick={() => setEditing(null)}>Cancel</button></div></div></form>
  return <section className="setlists-view"><div className="setlist-toolbar"><label><span className="sr-only">Concert setlist</span><select value={concert.id} onChange={e => { onSelect(e.target.value); setEditing(null) }}>{sorted.map(c => <option key={c.id} value={c.id}>{c.title} — {formatDate(c.date)}</option>)}</select></label><span className="saved-label"><Icon name="check" size={15} />Saved in this browser</span></div>
    <div className="setlist-banner"><div className="setlist-photo"><img src={stagePhoto} alt="Stage lights and the silhouettes of a live band" /></div><div className="setlist-title"><div className="eyebrow">{formatDate(concert.date)}</div><h2>{concert.title}</h2><p>{venue?.name ?? 'Venue to confirm'}{venue?.city ? ` · ${venue.city}` : ''}</p></div><div className="setlist-count"><strong>{songs}</strong><span>songs</span></div></div>
    <div className="setlist-list-heading"><p>Every song. In its place.</p><div><button className="text-button" onClick={() => add('note')}><Icon name="note" size={16} />Add note</button><button className="button primary small" onClick={() => add('song')}><Icon name="plus" size={17} />Add song</button></div></div>
    <ol className="setlist-rows">{concert.setlist.map((entry, index) => {
      const number = entry.type === 'song' ? ++songNumber : null
      return <li key={entry.id}>{editing?.id === entry.id ? editor : <div className={`setlist-row ${entry.type === 'note' ? 'note-row' : ''}`}><div className="reorder-actions"><button className="icon-button" disabled={index === 0} aria-label={`Move ${entry.title} up`} onClick={() => onUpdate({ ...concert, setlist: moveEntry(concert.setlist, entry.id, -1) }, 'Setlist order saved.')}><Icon name="up" size={14} /></button><button className="icon-button" disabled={index === concert.setlist.length - 1} aria-label={`Move ${entry.title} down`} onClick={() => onUpdate({ ...concert, setlist: moveEntry(concert.setlist, entry.id, 1) }, 'Setlist order saved.')}><Icon name="down" size={14} /></button></div><span className="song-number">{number === null ? <Icon name="note" size={18} /> : String(number).padStart(2, '0')}</span><span className="song-title"><strong>{entry.title}</strong>{entry.type === 'song' && <small>{entry.artist || concert.artists.join(' · ')}</small>}</span>{entry.tape && <span className="tape-label"><Icon name="tape" size={16} />Tape</span>}<div className="row-actions"><button className="icon-button" aria-label={`Edit entry ${entry.title}`} onClick={() => setEditing({ ...entry })}><Icon name="edit" size={17} /></button><button className="icon-button danger-hover" aria-label={`Remove entry ${entry.title}`} onClick={() => onUpdate({ ...concert, setlist: concert.setlist.filter(e => e.id !== entry.id) }, 'Entry removed from the setlist.', true)}><Icon name="trash" size={17} /></button></div></div>}</li>
    })}</ol>
    {editing && !concert.setlist.some(e => e.id === editing.id) && editor}
    {!concert.setlist.length && !editing && <div className="empty-state"><Icon name="music" size={34} /><h3>What did they open with?</h3><p>Start with a song, add an encore, make it yours.</p><button className="button secondary" onClick={() => add('song')}><Icon name="plus" size={18} />Add the first song</button></div>}
    <p className="hint setlist-hint">Songs can repeat. Notes and tape entries keep the whole performance in order.</p>
  </section>
}
