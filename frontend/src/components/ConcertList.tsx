import { Button } from 'react-aria-components'
import { dateKey, formatDate, relation } from '../domain'
import type { Concert, Venue } from '../domain'
import { Icon } from './Icon'

export function DateBlock({ concert, today }: { concert: Concert; today: string }) {
  const month = concert.date.month === null ? 'TBC' : new Intl.DateTimeFormat('en', { month: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(2000, concert.date.month - 1, 1)))
  return <div className={`date-block ${relation(concert.date, today)}`}><span className="sr-only">{formatDate(concert.date)}</span><span aria-hidden="true">{month}</span><strong aria-hidden="true">{concert.date.day === null ? '—' : String(concert.date.day).padStart(2, '0')}</strong><small aria-hidden="true">{concert.date.year ?? 'TBC'}</small></div>
}

/** `emptyHeading` keeps the empty state's heading one level below the section that contains the list. */
export function ConcertList({ concerts, venues, today, onOpen, onEdit, onTicket, compact = false, emptyHeading: EmptyHeading = 'h2' }: {
  concerts: Concert[]; venues: Venue[]; today: string; onOpen: (concert: Concert) => void;
  onEdit?: (concert: Concert) => void; onTicket?: (concert: Concert) => void; compact?: boolean; emptyHeading?: 'h2' | 'h3' | 'h4';
}) {
  if (!concerts.length) return <div className="empty-state"><Icon name="music" size={32} /><EmptyHeading>No concerts here, yet.</EmptyHeading><p>Try another filter or add a night to look forward to.</p></div>
  return <div className={`concert-list ${compact ? 'compact' : ''}`}>
    {!compact && <div className="concert-table-head" aria-hidden="true"><span>Date</span><span>Concert</span><span>Venue</span><span>Tickets</span><span className="sr-only">Actions</span></div>}
    {concerts.map((concert, i) => {
      const venue = venues.find(item => item.id === concert.venueId)
      const lineup = concert.artists.join(' · ') || 'Lineup to confirm'
      return <article className="concert-row" key={concert.id}>
        <DateBlock concert={concert} today={today} />
        <Button type="button" className="concert-identity" onPress={() => onOpen(concert)}>
          <span className={`concert-thumb tone-${i % 4}`} aria-hidden="true"><Icon name={i % 3 === 0 ? 'music' : 'ticket'} size={24} /></span>
          <span><strong>{concert.title}</strong>{compact ? <small>{venue?.name ?? 'Venue to confirm'}</small> : <small aria-hidden={lineup === concert.title || undefined}>{lineup}</small>}{compact && <small>{venue?.city || 'Location to confirm'}</small>}</span>
        </Button>
        {!compact && <span className="concert-venue">{venue?.name ?? 'Venue to confirm'}<small>{venue?.city || 'Location to confirm'}</small>{!dateKey(concert.date) && <small>Date to confirm</small>}</span>}
        {!compact && <label className="ticket-toggle" title="Tickets bought"><input type="checkbox" aria-label={`Tickets bought for ${concert.title} on ${formatDate(concert.date)}`} checked={concert.ticketsBought} onChange={() => onTicket?.(concert)} /><Icon name="ticket" size={18} /></label>}
        <div className="row-actions">{onEdit && <Button type="button" className="icon-button" aria-label={`Edit ${concert.title} on ${formatDate(concert.date)}`} onPress={() => onEdit(concert)}><Icon name="edit" size={18} /></Button>}<span aria-hidden="true" style={{ display: 'contents' }}><Button type="button" className="icon-button" excludeFromTabOrder onPress={() => onOpen(concert)}><Icon name="arrow" size={19} /></Button></span></div>
      </article>
    })}
  </div>
}
