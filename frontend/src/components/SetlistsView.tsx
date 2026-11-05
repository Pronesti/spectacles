import { SelectField } from './SelectField'
import { Button } from 'react-aria-components'
import { useState } from 'react'
import { formatDate, moveEntry, sortConcerts } from '../domain'
import type { Concert, SetlistEntry, Venue } from '../domain'
import stagePhoto from '../assets/concert-stage.png'
import { Icon } from './Icon'
import { SetlistEditor } from './SetlistEditor'
import type { CatalogAccess } from './CatalogPicker'

export function SetlistsView({ concerts, venues, selectedId, onSelect, onUpdate, catalog }: { concerts: Concert[]; venues: Venue[]; selectedId: string; catalog: CatalogAccess; onSelect: (id: string) => void; onUpdate: (concert: Concert, message: string, undoable?: boolean) => Promise<boolean> }) {
  const sorted = sortConcerts(concerts, true)
  const concert = concerts.find(c => c.id === selectedId) ?? sorted[0]
  const [editing, setEditing] = useState<SetlistEntry | null>(null)
  if (!concert) return <div className="empty-state"><Icon name="list" size={36} /><h3>Your first setlist starts with a concert.</h3><p>Add a concert, then come back to capture the songs.</p></div>
  const venue = venues.find(v => v.id === concert.venueId)
  const songs = concert.setlist.filter(e => e.type === 'song').length
  const add = (type: 'song' | 'note') => setEditing({ id: crypto.randomUUID(), type, title: '', artist: type === 'song' ? concert.artists[0] ?? '' : '', tape: false })
  let songNumber = 0
  const editor = editing && <SetlistEditor key={`${concert.id}:${editing.id}`} entry={editing} catalog={catalog} onCancel={() => setEditing(null)} onSave={async entry => {
    const saved = await onUpdate({ ...concert, setlist: concert.setlist.some(current => current.id === entry.id) ? concert.setlist.map(current => current.id === entry.id ? entry : current) : [...concert.setlist, entry] }, 'Setlist saved to your account.')
    if (saved) setEditing(null)
    return saved
  }} />
  return <section className="setlists-view"><div className="setlist-toolbar"><SelectField label="Concert setlist" hiddenLabel className="setlist-select" value={concert.id} onChange={id => { onSelect(id); setEditing(null) }} options={sorted.map(c => ({ id: c.id, label: `${c.title} — ${formatDate(c.date)}` }))} /><span className="saved-label"><Icon name="check" size={15} />Saved to your account</span></div>
    <div className="setlist-banner"><div className="setlist-photo"><img src={stagePhoto} alt="Stage lights and the silhouettes of a live band" /></div><div className="setlist-title"><div className="eyebrow">{formatDate(concert.date)}</div><h2>{concert.title}</h2><p>{venue?.name ?? 'Venue to confirm'}{venue?.city ? ` · ${venue.city}` : ''}</p></div><div className="setlist-count"><strong>{songs}</strong><span>songs</span></div></div>
    <div className="setlist-list-heading"><p>Every song. In its place.</p><div><Button type="button" className="text-button" onPress={() => add('note')}><Icon name="note" size={16} />Add note</Button><Button type="button" className="button primary small" onPress={() => add('song')}><Icon name="plus" size={17} />Add song</Button></div></div>
    <ol className="setlist-rows">{concert.setlist.map((entry, index) => {
      const number = entry.type === 'song' ? ++songNumber : null
      return <li key={entry.id}>{editing?.id === entry.id ? editor : <div className={`setlist-row ${entry.type === 'note' ? 'note-row' : ''}`}><div className="reorder-actions"><Button type="button" className="icon-button" isDisabled={index === 0} aria-label={`Move ${entry.title} up`} onPress={() => onUpdate({ ...concert, setlist: moveEntry(concert.setlist, entry.id, -1) }, 'Setlist order saved.')}><Icon name="up" size={14} /></Button><Button type="button" className="icon-button" isDisabled={index === concert.setlist.length - 1} aria-label={`Move ${entry.title} down`} onPress={() => onUpdate({ ...concert, setlist: moveEntry(concert.setlist, entry.id, 1) }, 'Setlist order saved.')}><Icon name="down" size={14} /></Button></div><span className="song-number">{number === null ? <Icon name="note" size={18} /> : String(number).padStart(2, '0')}</span><span className="song-title"><strong>{entry.title}</strong>{entry.type === 'song' && <small>{entry.artist || concert.artists.join(' · ') || 'Performer to confirm'}</small>}</span>{entry.tape && <span className="tape-label"><Icon name="tape" size={16} />Tape</span>}<div className="row-actions"><Button type="button" className="icon-button" aria-label={`Edit entry ${entry.title}`} onPress={() => setEditing({ ...entry })}><Icon name="edit" size={17} /></Button><Button type="button" className="icon-button danger-hover" aria-label={`Remove entry ${entry.title}`} onPress={() => onUpdate({ ...concert, setlist: concert.setlist.filter(e => e.id !== entry.id) }, 'Entry removed from the setlist.', true)}><Icon name="trash" size={17} /></Button></div></div>}</li>
    })}</ol>
    {editing && !concert.setlist.some(e => e.id === editing.id) && editor}
    {!concert.setlist.length && !editing && <div className="empty-state"><Icon name="music" size={34} /><h3>What did they open with?</h3><p>Start with a song, add an encore, make it yours.</p><Button type="button" className="button secondary" onPress={() => add('song')}><Icon name="plus" size={18} />Add the first song</Button></div>}
    <p className="hint setlist-hint">Songs can repeat. Notes and tape entries keep the whole performance in order.</p>
  </section>
}
