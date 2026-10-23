import { dateKey, formatDate, relation } from '../domain'
import type { Concert, Venue } from '../domain'
import { Icon } from './Icon'

export function DateBlock({ concert, today }: { concert: Concert; today: string }) {
  const month = concert.date.month === null ? 'TBC' : new Intl.DateTimeFormat('en', { month: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(2000, concert.date.month - 1, 1)))
  return <div className={`date-block ${relation(concert.date, today)}`} aria-label={formatDate(concert.date)}><span>{month}</span><strong>{concert.date.day === null ? '—' : String(concert.date.day).padStart(2, '0')}</strong><small>{concert.date.year ?? 'TBC'}</small></div>
}

export function ConcertList({ concerts, venues, today, onOpen, onEdit, onTicket, compact = false }: {
  concerts: Concert[]; venues: Venue[]; today: string; onOpen: (concert: Concert) => void;
  onEdit?: (concert: Concert) => void; onTicket?: (concert: Concert) => void; compact?: boolean;
}) {
  if (!concerts.length) return <div className="empty-state"><Icon name="music" size={32} /><h3>No concerts here, yet.</h3><p>Try another filter or add a night to look forward to.</p></div>
  return <div className={`concert-list ${compact ? 'compact' : ''}`}>
    {!compact && <div className="concert-table-head"><span>Date</span><span>Concert</span><span>Venue</span><span>Tickets</span><span className="sr-only">Actions</span></div>}
    {concerts.map((concert, i) => {
      const venue = venues.find(item => item.id === concert.venueId)
      return <article className="concert-row" key={concert.id}>
        <DateBlock concert={concert} today={today} />
        <button className="concert-identity" onClick={() => onOpen(concert)}>
          <span className={`concert-thumb tone-${i % 4}`} aria-hidden="true"><Icon name={i % 3 === 0 ? 'music' : 'ticket'} size={24} /></span>
          <span><strong>{concert.title}</strong><small>{compact ? venue?.name ?? 'Venue to confirm' : concert.artists.join(' · ')}</small>{compact && <small>{venue?.city ?? 'Location to confirm'}</small>}</span>
        </button>
        {!compact && <span className="concert-venue">{venue?.name ?? 'Venue to confirm'}<small>{venue?.city ?? 'Location to confirm'}</small>{!dateKey(concert.date) && <small>Date to confirm</small>}</span>}
        {!compact && <label className="ticket-toggle" title="Tickets bought"><input type="checkbox" aria-label={`Tickets bought for ${concert.title} on ${formatDate(concert.date)}`} checked={concert.ticketsBought} onChange={() => onTicket?.(concert)} /><Icon name="ticket" size={18} /></label>}
        <div className="row-actions">{onEdit && <button className="icon-button" aria-label={`Edit ${concert.title} on ${formatDate(concert.date)}`} onClick={() => onEdit(concert)}><Icon name="edit" size={18} /></button>}<button className="icon-button" aria-label={`View ${concert.title} on ${formatDate(concert.date)}`} onClick={() => onOpen(concert)}><Icon name="arrow" size={19} /></button></div>
      </article>
    })}
  </div>
}
