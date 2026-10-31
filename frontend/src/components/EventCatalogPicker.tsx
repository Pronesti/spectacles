import { useEffect, useRef, useState } from 'react'
import { CatalogApiError, catalogErrorMessage, createLatestRead } from '../catalogApi'
import type { CatalogEvent } from '../catalog'
import { formatDate } from '../domain'
import { useRecoveryField } from './ConcertRecovery'
import { CatalogPicker } from './CatalogPicker'
import { EntityAutocomplete } from './EntityAutocomplete'
import type { MusicBrainzAccess } from './EntityAutocomplete'
import type { CatalogAccess } from './CatalogPicker'
export function EventCatalogPicker({ catalog, queryControl, recoverDraft = false, onUse, onEditingChange, musicbrainz, onCreate, hideSearch = false, requestedId, onDismiss }: { onDismiss?: () => void; hideSearch?: boolean; requestedId?: string; queryControl?: { value: string; onChange: (value: string) => void }; recoverDraft?: boolean; musicbrainz?: MusicBrainzAccess; onCreate?: () => void; onEditingChange?: (editing: boolean) => void; catalog: CatalogAccess; onUse: (event: CatalogEvent) => void }) {
  const [selectedId, setSelectedId] = useRecoveryField('eventPreviewId', null)
  const [latest] = useState(createLatestRead)
  const [state, setState] = useState<{ session: string; event?: CatalogEvent; loading?: boolean; error?: string } | null>(null)
  const preview = useRef<HTMLDivElement>(null)
  useEffect(() => () => latest.cancel(), [latest, catalog.enabled, catalog.sessionKey])
  useEffect(() => { onEditingChange?.(!!state || recoverDraft && !!selectedId); return () => onEditingChange?.(false) }, [state, selectedId, recoverDraft, onEditingChange])
  const choose = (event: Pick<CatalogEvent, 'id'>) => {
    if (recoverDraft) setSelectedId(event.id)
    setState({ session: catalog.sessionKey, loading: true })
    void latest.run(signal => catalog.client.event(event.id, signal), detail => {
      setState({ session: catalog.sessionKey, event: detail })
      requestAnimationFrame(() => preview.current?.focus())
    }, error => {
      setState({ session: catalog.sessionKey, error: catalogErrorMessage(error) })
      if (error instanceof CatalogApiError && error.status === 401) catalog.onUnauthorized()
    })
  }
  useEffect(() => {
    if (recoverDraft && selectedId && catalog.enabled) choose({ id: selectedId })
    // A restored preview reloads only when its authorized session becomes available.
    // oxlint-disable-next-line react/exhaustive-deps
  }, [catalog.enabled, catalog.sessionKey])
  useEffect(() => {
    if (requestedId) choose({ id: requestedId })
    // Only a fresh unified-list choice starts this read.
    // oxlint-disable-next-line react/exhaustive-deps
  }, [requestedId])
  const dismiss = () => { latest.cancel(); setState(null); if (recoverDraft) setSelectedId(null); onDismiss?.() }
  const current = catalog.enabled && state?.session === catalog.sessionKey ? state : null
  return <section>{!hideSearch && (musicbrainz ? <EntityAutocomplete queryControl={queryControl} recoveryQuery={recoverDraft ? "eventQuery" : undefined} kind="events" label={queryControl ? "Find Event" : "Find an existing Event"} catalog={catalog} musicbrainz={musicbrainz} onPick={option => { if (option.source === 'catalog' && option.kind === 'events') choose(option.row); if (option.source === 'create') { dismiss(); onCreate?.() } }} /> : <CatalogPicker catalog={catalog} kind="events" label={queryControl ? "Find Event" : "Find an existing Event"} onPick={choose} />)}
    {recoverDraft && selectedId && !current && <div><p role="status">Sign in to reload the selected Event preview.</p><button type="button" onClick={dismiss}>Dismiss preview</button></div>}
    {current && <div className="catalog-event-preview" ref={preview} tabIndex={-1}>
      {current.loading && <p role="status">Loading Event details…</p>}{current.error && <p role="alert">{current.error} Your draft has not changed.</p>}
      {current.event && <><h3>{current.event.title}</h3><p>{current.event.type}</p><p>{formatDate(current.event.date)} · {current.event.venue?.name ?? 'Venue to confirm'}</p><p>{current.event.artists.map(a => a.name).join(' · ') || 'Lineup to confirm'}</p><p>{current.event.time ?? 'Time to confirm'} · {current.event.timezone ?? 'Timezone to confirm'}</p><p>{current.event.tours.map(t => t.name).join(' · ') || 'Tour information unavailable'}</p><p className="hint">This replaces the Artists and Tours chosen above. Your flags, notes, and setlist stay in the draft.</p><button type="button" className="button primary" onClick={() => { onUse(current.event!); dismiss() }}>Use these details</button></>}
      <button type="button" className="text-button" onClick={() => { dismiss() }}>Dismiss preview</button>
    </div>}
  </section>
}
