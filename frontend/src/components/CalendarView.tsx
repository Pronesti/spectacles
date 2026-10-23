import { useState } from 'react'
import { dateKey, formatDate, groupByDay, relation, sortConcerts } from '../domain'
import type { Concert, Venue } from '../domain'
import stagePhoto from '../assets/concert-stage.png'
import { Icon } from './Icon'
import { ConcertList } from './ConcertList'

export function CalendarView({ concerts, venues, today, onOpen, onAdd }: { concerts: Concert[]; venues: Venue[]; today: string; onOpen: (concert: Concert) => void; onAdd: (date?: string) => void }) {
  const [month, setMonth] = useState(today.slice(0, 7))
  const [year, monthNumber] = month.split('-').map(Number)
  const start = new Date(Date.UTC(year, monthNumber - 1, 1))
  const offset = (start.getUTCDay() + 6) % 7
  const length = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate()
  const weeks = Math.ceil((length + offset) / 7)
  const groups = groupByDay(concerts)
  const monthly = sortConcerts(concerts.filter(c => dateKey(c.date)?.startsWith(month)))
  const uncertain = concerts.filter(c => c.date.year === year && (c.date.month === monthNumber || c.date.month === null) && c.date.day === null)
  const changeMonth = (delta: number) => { const d = new Date(Date.UTC(year, monthNumber - 1 + delta, 1)); setMonth(d.toISOString().slice(0, 7)) }
  const title = start.toLocaleDateString('en', { month: 'long', year: 'numeric', timeZone: 'UTC' })
  return <div className="calendar-layout"><section className="calendar-main" aria-label="Concert calendar">
    <div className="calendar-toolbar"><div className="month-controls"><button className="icon-button" aria-label="Previous month" onClick={() => changeMonth(-1)}><Icon name="left" /></button><h2>{title}</h2><button className="icon-button" aria-label="Next month" onClick={() => changeMonth(1)}><Icon name="right" /></button><button className="button secondary small" onClick={() => setMonth(today.slice(0, 7))}>Today</button></div><div className="date-legend"><span><i className="past" />Past</span><span><i className="upcoming" />Upcoming</span></div></div>
    <div className="calendar-scroll"><div className="calendar-grid" role="group" aria-label={title}>
      {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(day => <div className="calendar-weekday" key={day}>{day}</div>)}
      {Array.from({ length: weeks * 7 }, (_, index) => {
        const day = index - offset + 1
        if (day < 1 || day > length) return <div className="calendar-day outside" key={index} />
        const key = `${month}-${String(day).padStart(2, '0')}`; const shows = groups.get(key) ?? []
        return <div className={`calendar-day ${key === today ? 'is-today' : ''}`} key={index} aria-label={key}>
          <button className="calendar-date" onClick={() => onAdd(key)} aria-label={`Add concert on ${key}`}><span>{day}</span><Icon name="plus" size={13} /></button>
          {shows.map(concert => <button key={concert.id} className={`calendar-event ${relation(concert.date, today)}`} onClick={() => onOpen(concert)}><strong>{concert.title}</strong><small>{venues.find(v => v.id === concert.venueId)?.name ?? 'Venue to confirm'}</small></button>)}
        </div>
      })}
    </div></div>
    {uncertain.length > 0 && <div className="partial-dates"><Icon name="clock" size={18} /><div><strong>Dates still to be confirmed</strong>{uncertain.map(c => <button className="text-button" key={c.id} onClick={() => onOpen(c)}>{c.title} · {formatDate(c.date)}</button>)}</div></div>}
    <p className="hint">Choose a day to add a concert. Only concerts with an exact date appear in the calendar.</p>
  </section><aside className="calendar-agenda"><img src={stagePhoto} alt="A crowd beneath bright stage lights" /><div className="section-heading"><div><h3>{start.toLocaleDateString('en', { month: 'long', timeZone: 'UTC' })}, at a glance</h3><p>{monthly.length} concerts in your collection</p></div></div><ConcertList compact concerts={monthly} venues={venues} today={today} onOpen={onOpen} /></aside></div>
}
