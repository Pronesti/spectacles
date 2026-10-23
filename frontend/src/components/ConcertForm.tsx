import { useState } from 'react'
import type { FormEvent } from 'react'
import { dateKey, TIMEZONE, validDate } from '../domain'
import type { Concert, EventDate, Venue } from '../domain'
import { Dialog } from './Dialog'
import { Icon } from './Icon'

type Precision = 'exact' | 'month' | 'year' | 'unknown'

export function ConcertForm({ concert, venues, initialDate, onClose, onSave }: { concert?: Concert; venues: Venue[]; initialDate?: string; onClose: () => void; onSave: (concert: Concert, venue?: Venue) => void }) {
  const original = concert?.date
  const [artists, setArtists] = useState(concert?.artists.join(', ') ?? '')
  const [title, setTitle] = useState(concert?.title ?? '')
  const [venueId, setVenueId] = useState(concert?.venueId ?? '')
  const [venueName, setVenueName] = useState('')
  const [city, setCity] = useState('Buenos Aires')
  const [lat, setLat] = useState(''), [lng, setLng] = useState('')
  const [precision, setPrecision] = useState<Precision>(original ? original.day !== null ? 'exact' : original.month !== null ? 'month' : original.year !== null ? 'year' : 'unknown' : 'exact')
  const [date, setDate] = useState(original ? dateKey(original) ?? `${original.year ?? ''}-${String(original.month ?? 1).padStart(2, '0')}-01` : initialDate ?? '')
  const [time, setTime] = useState(concert?.time ?? '')
  const [interested, setInterested] = useState(concert?.interested ?? true)
  const [tickets, setTickets] = useState(concert?.ticketsBought ?? false)
  const [notes, setNotes] = useState(concert?.notes ?? '')
  const [error, setError] = useState('')
  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()
    const artistNames = artists.split(',').map(a => a.trim()).filter(Boolean)
    if (!artistNames.length) return setError('Add at least one artist.')
    const parts = date.split('-').map(Number)
    const eventDate: EventDate = { year: precision === 'unknown' ? null : parts[0], month: precision === 'exact' || precision === 'month' ? parts[1] : null, day: precision === 'exact' ? parts[2] : null }
    if (!validDate(eventDate)) return setError('Enter a valid date at the selected precision.')
    if (venueId === 'new' && !venueName.trim()) return setError('Enter the venue name.')
    if ((lat === '') !== (lng === '') || lat !== '' && (!Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng)) || Math.abs(Number(lat)) > 90 || Math.abs(Number(lng)) > 180)) return setError('Supply both valid latitude and longitude, or leave both empty.')
    const venue: Venue | undefined = venueId === 'new' ? { id: crypto.randomUUID(), name: venueName.trim(), city: city.trim(), neighborhood: '', lat: lat === '' ? null : Number(lat), lng: lng === '' ? null : Number(lng) } : undefined
    onSave({ id: concert?.id ?? crypto.randomUUID(), title: title.trim() || artistNames.join(' & '), artists: artistNames, venueId: venue?.id ?? (venueId || null), date: eventDate, time, timezone: concert?.timezone ?? TIMEZONE, interested, ticketsBought: tickets, notes: notes.trim(), setlist: concert?.setlist ?? [] }, venue)
  }
  return <Dialog title={concert ? 'Edit concert' : 'Add a concert'} onClose={onClose} drawer><form onSubmit={handleSubmit} className="concert-form"><div className="form-body"><p className="muted">A night worth making room for.</p>
    <label>Artists<input autoFocus data-autofocus required maxLength={300} value={artists} onChange={e => setArtists(e.target.value)} placeholder="e.g. Arctic Monkeys" /><small>Separate multiple artists with commas.</small></label>
    <label>Concert title <span className="optional">optional</span><input maxLength={200} value={title} onChange={e => setTitle(e.target.value)} placeholder="Uses the artist name by default" /></label>
    <label>Venue<select value={venueId} onChange={e => { setVenueId(e.target.value); setLat(''); setLng('') }}><option value="">Venue to confirm</option>{venues.map(v => <option key={v.id} value={v.id}>{v.name} · {v.city || 'City to confirm'}</option>)}<option value="new">+ Add a venue…</option></select></label>
    {venueId === 'new' && <div className="new-venue-fields"><label>Venue name<input required maxLength={150} value={venueName} onChange={e => setVenueName(e.target.value)} /></label><label>City<input maxLength={100} value={city} onChange={e => setCity(e.target.value)} /></label><details><summary>Map location <span className="optional">optional</span></summary><p className="hint">Leave unknown coordinates empty. The venue will remain in your list without a map pin.</p><div className="form-columns"><label>Latitude<input type="number" step="any" min="-90" max="90" value={lat} onChange={e => setLat(e.target.value)} /></label><label>Longitude<input type="number" step="any" min="-180" max="180" value={lng} onChange={e => setLng(e.target.value)} /></label></div></details></div>}
    <label>Date precision<select value={precision} onChange={e => { setPrecision(e.target.value as Precision); setError('') }}><option value="exact">Exact date</option><option value="month">Month only</option><option value="year">Year only</option><option value="unknown">Date to confirm</option></select></label>
    <div className="form-columns">{precision !== 'unknown' && <label>{precision === 'exact' ? 'Date' : precision === 'month' ? 'Month' : 'Year'}<input required type={precision === 'exact' ? 'date' : precision === 'month' ? 'month' : 'number'} min={precision === 'year' ? '1' : precision === 'month' ? '0001-01' : '0001-01-01'} max={precision === 'year' ? '9999' : precision === 'month' ? '9999-12' : '9999-12-31'} value={precision === 'exact' ? date : precision === 'month' ? date.slice(0, 7) : date.slice(0, 4)} onChange={e => setDate(e.target.value)} /></label>}<label>Time <span className="optional">optional</span><input type="time" value={time} onChange={e => setTime(e.target.value)} /></label></div>
    <div className="flag-fields"><label><input type="checkbox" checked={interested} onChange={e => setInterested(e.target.checked)} /><Icon name="heart" size={18} />Interested</label><label><input type="checkbox" checked={tickets} onChange={e => setTickets(e.target.checked)} /><Icon name="ticket" size={18} />Tickets bought</label></div>
    <label>Notes <span className="optional">optional</span><textarea rows={3} maxLength={2000} value={notes} onChange={e => setNotes(e.target.value)} placeholder="The seats, the company, the things to remember…" /></label>
    <p className="hint">Preview data · saved in this browser only.</p>{error && <p className="form-error" role="alert">{error}</p>}
  </div><div className="form-footer"><button type="submit" className="button primary"><Icon name="plus" />Save concert</button><button type="button" className="button secondary" onClick={onClose}>Cancel</button></div></form></Dialog>
}
