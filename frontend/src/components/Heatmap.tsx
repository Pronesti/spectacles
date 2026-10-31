import { useState } from 'react'
import { groupByDay } from '../domain'
import type { Concert } from '../domain'
import { Icon } from './Icon'

export function Heatmap({ concerts, today, onOpen }: { concerts: Concert[]; today: string; onOpen: (concert: Concert) => void }) {
  const [year, setYear] = useState(Number(today.slice(0, 4)))
  const [active, setActive] = useState<string | null>(null)
  const days = groupByDay(concerts)
  const first = new Date(Date.UTC(year, 0, 1))
  const offset = (first.getUTCDay() + 6) % 7
  const end = new Date(Date.UTC(year, 11, 31))
  const totalDays = Math.round((end.getTime() - first.getTime()) / 86_400_000) + 1
  const weeks = Math.ceil((totalDays + offset) / 7)
  const yearConcerts = concerts.filter(c => c.date.year === year)
  const selected = active ? days.get(active) ?? [] : []
  const years = Array.from(new Set([Number(today.slice(0, 4)), ...concerts.flatMap(c => c.date.year === null ? [] : [c.date.year])])).sort((a, b) => b - a)
  return <section className="activity-section" aria-labelledby="activity-heading">
    <div className="section-heading"><div><div className="eyebrow">THE BIGGER PICTURE</div><h2 id="activity-heading">A year in live music<span className="lime-dot">.</span></h2><p>Every little square, a night to remember.</p></div><label className="year-select"><span className="sr-only">Activity year</span><select value={year} onChange={e => { setYear(Number(e.target.value)); setActive(null) }}>{years.map(y => <option key={y}>{y}</option>)}</select></label></div>
    <div className="heatmap-scroll" role="region" aria-label="Annual concert activity, scroll horizontally on smaller screens" tabIndex={0}>
      <div className="heatmap-inner" style={{ '--weeks': weeks } as React.CSSProperties}>
        <div className="heatmap-months">{Array.from({ length: 12 }, (_, month) => {
          const date = new Date(Date.UTC(year, month, 1)); const day = Math.round((date.getTime() - first.getTime()) / 86_400_000)
          return <span key={month} style={{ gridColumn: Math.floor((day + offset) / 7) + 1 }}>{date.toLocaleString('en', { month: 'short', timeZone: 'UTC' })}</span>
        })}</div>
        <div className="heatmap-body"><div className="weekday-labels"><span>Mon</span><span>Wed</span><span>Fri</span></div><div className="heatmap-grid">
          {Array.from({ length: weeks * 7 }, (_, index) => {
            const day = index - offset
            if (day < 0 || day >= totalDays) return <span key={index} className="heat-cell outside" />
            const date = new Date(Date.UTC(year, 0, day + 1)); const key = date.toISOString().slice(0, 10); const shows = days.get(key) ?? []
            const label = `${date.toLocaleDateString('en', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' })}: ${shows.length ? shows.map(c => c.title).join(', ') : 'No concerts'}`
            return <button key={index} className={`heat-cell level-${Math.min(shows.length, 3)} ${active === key ? 'active' : ''} ${key > today ? 'future' : ''}`} aria-label={label} aria-pressed={active === key} onMouseEnter={() => setActive(key)} onFocus={() => setActive(key)} onClick={() => setActive(key)} />
          })}
        </div></div>
      </div>
    </div>
    <div className="heatmap-footer"><span><strong>{yearConcerts.length}</strong> concerts in {year}{yearConcerts.some(c => c.date.day === null) && ' · exact dates shown'}</span><div className="heat-legend"><span>Less</span>{[0, 1, 2, 3].map(level => <i key={level} className={`heat-cell level-${level}`} />)}<span>More</span></div></div>
    <div className={`activity-detail ${active ? 'has-selection' : ''}`} aria-live="polite">{active ? <><Icon name="calendar" size={17} /><span><strong>{new Date(`${active}T12:00:00Z`).toLocaleDateString('en', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' })}</strong>{selected.length ? selected.map(c => <button className="text-button" key={c.id} onClick={() => onOpen(c)}>{c.title}<Icon name="arrow" size={15} /></button>) : <span className="muted">No concerts in your collection.</span>}</span></> : <><Icon name="music" size={17} /><span>Hover, focus, or tap a square to explore your concert history.</span></>}</div>
  </section>
}
