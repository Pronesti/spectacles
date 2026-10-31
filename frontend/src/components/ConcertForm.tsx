import { useState } from 'react'
import type { FormEvent } from 'react'
import type { Concert, Venue } from '../domain'
import { adoptCatalogEvent, adoptCatalogVenue, createConcertDraft, finishConcertDraft } from '../concertDraft'
import type { ConcertDraft, Precision } from '../concertDraft'
import { Dialog } from './Dialog'
import { Icon } from './Icon'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
import { CatalogChoices } from './CatalogChoices'
import { EventCatalogPicker } from './EventCatalogPicker'
export function ConcertForm({ concert, venues, initialDate, catalog, disabled = false, busy = false, onSignIn, onClose, onSave }: { concert?: Concert; venues: Venue[]; initialDate?: string; catalog: CatalogAccess; disabled?: boolean; busy?: boolean; onSignIn?: () => void; onClose: () => void; onSave: (concert: Concert, venue?: Venue) => void | Promise<void> }) {
  const [draft, setDraft] = useState(() => createConcertDraft(concert, initialDate))
  const [error, setError] = useState('')
  const shared = (patch: Partial<ConcertDraft>) => setDraft(current => ({ ...current, ...patch, eventId: undefined }))
  const field = <K extends keyof Concert,>(key: K, value: Concert[K], detach = true) => setDraft(current => ({ ...current, concert: { ...current.concert, [key]: value }, eventId: detach ? undefined : current.eventId }))
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (disabled || busy) return
    try { const result = finishConcertDraft(draft); await onSave(result.concert, result.venue) }
    catch (failure) { setError(failure instanceof Error ? failure.message : 'Check the draft fields.') }
  }
  const pending = draft.pendingVenue
  const stagedVenues = pending ? venues.some(venue => venue.id === pending.id) ? venues.map(venue => venue.id === pending.id ? pending : venue) : [...venues, pending] : venues
  return <Dialog title={concert ? 'Edit concert' : 'Add a concert'} onClose={onClose} drawer><form onSubmit={submit} noValidate className="concert-form"><fieldset disabled={busy} style={{ display: 'contents' }}><div className="form-body"><p className="muted">Choose shared details or enter your own.</p>
    <EventCatalogPicker catalog={catalog} onUse={event => setDraft(current => adoptCatalogEvent(current, event, venues))} />
    {draft.eventId && <p className="hint">Using selected Event details.</p>}
    <label>Concert title<input data-autofocus maxLength={255} value={draft.concert.title} onChange={event => field('title', event.target.value)} placeholder="Title, or use the artist names" /></label>
    <CatalogChoices kind="artists" label="Artists" choices={draft.artists} onChange={artists => shared({ artists })} catalog={catalog} />
    <CatalogPicker catalog={catalog} kind="venues" label="Search shared Venues" onPick={venue => setDraft(current => adoptCatalogVenue(current, venue, venues))} />
    <label>Venue<select value={draft.concert.venueId ?? ''} onChange={event => { shared({ pendingVenue: undefined, lat: '', lng: '' }); field('venueId', event.target.value || null) }}><option value="">Venue to confirm</option>{stagedVenues.map(venue => <option key={venue.id} value={venue.id}>{venue.name} · {venue.city || 'City to confirm'}</option>)}<option value="new">+ Add a venue…</option></select></label>
    {draft.concert.venueId === 'new' && <div className="new-venue-fields"><label>Venue name<input maxLength={150} value={draft.venueName} onChange={event => shared({ venueName: event.target.value })} /></label><label>City<input maxLength={100} value={draft.city} onChange={event => shared({ city: event.target.value })} /></label><details><summary>Map location <span className="optional">optional</span></summary><p className="hint">Leave unknown coordinates empty.</p><div className="form-columns"><label>Latitude<input type="number" step="any" min="-90" max="90" value={draft.lat} onChange={event => shared({ lat: event.target.value })} /></label><label>Longitude<input type="number" step="any" min="-180" max="180" value={draft.lng} onChange={event => shared({ lng: event.target.value })} /></label></div></details></div>}
    <CatalogChoices kind="tours" label="Tours (optional)" choices={draft.tours} onChange={tours => shared({ tours })} catalog={catalog} />
    <label>Date precision<select value={draft.precision} onChange={event => { const precision = event.target.value as Precision; setDraft(current => ({ ...current, precision, eventId: undefined, dateText: precision === 'year' ? current.dateText.slice(0, 4) : precision === 'month' && current.precision === 'exact' ? current.dateText.slice(0, 7) : precision === current.precision ? current.dateText : '' })); setError('') }}><option value="exact">Exact date</option><option value="month">Month only</option><option value="year">Year only</option><option value="unknown">Date to confirm</option></select></label>
    <div className="form-columns">{draft.precision !== 'unknown' && <label>{draft.precision === 'exact' ? 'Date' : draft.precision === 'month' ? 'Month' : 'Year'}<input type={draft.precision === 'exact' ? 'date' : draft.precision === 'month' ? 'month' : 'number'} min={draft.precision === 'year' ? '1' : draft.precision === 'month' ? '0001-01' : '0001-01-01'} max={draft.precision === 'year' ? '9999' : draft.precision === 'month' ? '9999-12' : '9999-12-31'} value={draft.dateText} onChange={event => shared({ dateText: event.target.value })} /></label>}<label>Local time <span className="optional">optional</span><input type="time" step={1} value={draft.concert.time} onChange={event => field('time', event.target.value)} /></label></div>
    <label>Timezone <span className="optional">optional</span><input value={draft.timezoneText} onChange={event => shared({ timezoneText: event.target.value })} placeholder="Unknown, or an IANA timezone" /><small>Leave unknown values empty. Shared Event timezones are kept as returned.</small></label>
    <div className="flag-fields"><label><input type="checkbox" checked={draft.concert.interested} onChange={event => field('interested', event.target.checked, false)} /><Icon name="heart" size={18} />Interested</label><label><input type="checkbox" checked={draft.concert.ticketsBought} onChange={event => field('ticketsBought', event.target.checked, false)} /><Icon name="ticket" size={18} />Tickets bought</label></div>
    <label>Notes <span className="optional">optional</span><textarea rows={3} maxLength={2000} value={draft.concert.notes} onChange={event => field('notes', event.target.value, false)} /></label>
    <p className="hint">Saved to your account in the database. Catalog selections reuse existing records.</p>{error && <p className="form-error" role="alert">{error}</p>}
  </div><div className="form-footer">{onSignIn && <button type="button" className="button secondary" onClick={onSignIn}>Sign in again</button>}<button type="submit" disabled={disabled} className="button primary"><Icon name="plus" />Save concert</button><button type="button" className="button secondary" onClick={onClose}>Cancel</button></div></fieldset></form></Dialog>
}
