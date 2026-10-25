import { useEffect, useRef, useState } from 'react'
import { CatalogApiError, catalogErrorMessage, createLatestRead } from '../catalogApi'
import type { CatalogEvent } from '../catalog'
import { formatDate } from '../domain'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
export function EventCatalogPicker({ catalog, onUse }: { catalog: CatalogAccess; onUse: (event: CatalogEvent) => void }) {
  const [latest] = useState(createLatestRead)
  const [state, setState] = useState<{ session: string; event?: CatalogEvent; loading?: boolean; error?: string } | null>(null)
  const preview = useRef<HTMLDivElement>(null)
  useEffect(() => () => latest.cancel(), [latest, catalog.enabled, catalog.sessionKey])
  const choose = (event: CatalogEvent) => {
    setState({ session: catalog.sessionKey, loading: true })
    void latest.run(signal => catalog.client.event(event.id, signal), detail => {
      setState({ session: catalog.sessionKey, event: detail })
      requestAnimationFrame(() => preview.current?.focus())
    }, error => {
      setState({ session: catalog.sessionKey, error: catalogErrorMessage(error) })
      if (error instanceof CatalogApiError && error.status === 401) catalog.onUnauthorized()
    })
  }
  const current = catalog.enabled && state?.session === catalog.sessionKey ? state : null
  return <section><CatalogPicker catalog={catalog} kind="events" label="Find an existing Event" onPick={choose} />
    {current && <div className="catalog-event-preview" ref={preview} tabIndex={-1}>
      {current.loading && <p role="status">Loading Event details…</p>}{current.error && <p role="alert">{current.error} Your draft has not changed.</p>}
      {current.event && <><h3>{current.event.title}</h3><p>{current.event.type}</p><p>{formatDate(current.event.date)} · {current.event.venue?.name ?? 'Venue to confirm'}</p><p>{current.event.artists.map(a => a.name).join(' · ') || 'Lineup to confirm'}</p><p>{current.event.time ?? 'Time to confirm'} · {current.event.timezone ?? 'Timezone to confirm'}</p><p>{current.event.tours.map(t => t.name).join(' · ') || 'Tour information unavailable'}</p><p className="hint">Use these shared details in your concert draft. Your flags, notes, and setlist will stay as entered.</p><button type="button" className="button primary" onClick={() => { onUse(current.event!); setState(null) }}>Use these details</button></>}
      <button type="button" className="text-button" onClick={() => { latest.cancel(); setState(null) }}>Dismiss preview</button>
    </div>}
  </section>
}
