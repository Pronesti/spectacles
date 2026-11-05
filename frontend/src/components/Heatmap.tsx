import { SelectField } from './SelectField'
import { InteractionDisabledContext } from './InteractionDisabledContext'
import { Button, ListBox, ListBoxItem } from 'react-aria-components'
import { useContext, useState } from 'react'
import { groupByDay } from '../domain'
import type { Concert } from '../domain'
import { Icon } from './Icon'

export function Heatmap({ concerts, today, onOpen }: { concerts: Concert[]; today: string; onOpen: (concert: Concert) => void }) {
  const interactionDisabled = useContext(InteractionDisabledContext)
  const [year, setYear] = useState(Number(today.slice(0, 4)))
  const [active, setActive] = useState<string | null>(today)
  const [pinned, setPinned] = useState(false)
  const previewDate = (date: string) => { if (!pinned) setActive(date) }
  const resetPreview = () => { if (!pinned) setActive(year === Number(today.slice(0, 4)) ? today : null) }
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
    <div className="section-heading"><div><div className="eyebrow">THE BIGGER PICTURE</div><h2 id="activity-heading">A year in live music<span className="lime-dot">.</span></h2><p>Every little square, a night to remember.</p></div><SelectField label="Activity year" hiddenLabel className="year-select" value={String(year)} onChange={value => { setYear(Number(value)); setPinned(false); setActive(Number(value) === Number(today.slice(0, 4)) ? today : null) }} options={years.map(year => ({ id: String(year), label: String(year) }))} /></div>
    <div className="heatmap-scroll" role="region" aria-label="Annual concert activity, scroll horizontally on smaller screens" onMouseLeave={event => { if (!event.currentTarget.contains(document.activeElement)) resetPreview() }} onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget)) resetPreview() }}>
      <div className="heatmap-inner" style={{ '--weeks': weeks } as React.CSSProperties}>
        <div className="heatmap-months">{Array.from({ length: 12 }, (_, month) => {
          const date = new Date(Date.UTC(year, month, 1)); const day = Math.round((date.getTime() - first.getTime()) / 86_400_000)
          return <span key={month} style={{ gridColumn: Math.floor((day + offset) / 7) + 1 }}>{date.toLocaleString('en', { month: 'short', timeZone: 'UTC' })}</span>
        })}</div>
        <div className="heatmap-body"><div className="weekday-labels"><span>Mon</span><span>Wed</span><span>Fri</span></div><ListBox key={year} className="heatmap-grid" aria-label={`Concert activity in ${year}`} layout="grid" orientation="horizontal" selectionMode="single" disallowEmptySelection selectedKeys={pinned && active ? [active] : []} onSelectionChange={keys => { const date = [...keys][0]; if (typeof date === 'string') { setPinned(true); setActive(date) } }}>
          {Array.from({ length: totalDays }, (_, day) => {
            const index = day + offset
            const date = new Date(Date.UTC(year, 0, day + 1)); const key = date.toISOString().slice(0, 10); const shows = days.get(key) ?? []
            const label = `${date.toLocaleDateString('en', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' })}: ${shows.length ? shows.map(c => c.title).join(', ') : 'No concerts'}`
            return <ListBoxItem isDisabled={interactionDisabled} id={key} key={key} textValue={label} style={{ gridColumn: Math.floor(index / 7) + 1, gridRow: index % 7 + 1 }} className={`heat-cell level-${Math.min(shows.length, 3)} ${active === key ? 'active' : ''} ${key > today ? 'future' : ''}`} aria-label={label} onHoverStart={() => previewDate(key)} onFocus={() => previewDate(key)}  />
          })}
        </ListBox></div>
      </div>
    </div>
    <div className="heatmap-footer"><span><strong>{yearConcerts.length}</strong> concerts in {year}{yearConcerts.some(c => c.date.day === null) && ' · exact dates shown'}</span><div className="heat-legend"><span>Less</span>{[0, 1, 2, 3].map(level => <i key={level} className={`heat-cell level-${level}`} />)}<span>More</span></div></div>
    <div className={`activity-detail ${active ? 'has-selection' : ''}`} aria-live="polite">{active ? <><Icon name="calendar" size={17} /><span><strong>{new Date(`${active}T12:00:00Z`).toLocaleDateString('en', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' })}</strong>{selected.length ? selected.map(c => <Button type="button" className="text-button" key={c.id} onPress={() => onOpen(c)}>{c.title}<Icon name="arrow" size={15} /></Button>) : <span className="muted">No concerts in your collection.</span>}</span></> : <><Icon name="music" size={17} /><span>Hover, focus, or tap a square to explore your concert history.</span></>}</div>
  </section>
}
