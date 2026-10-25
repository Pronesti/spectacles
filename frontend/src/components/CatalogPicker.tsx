import { useEffect, useId, useRef, useState } from 'react'
import { CatalogApiError, catalogErrorMessage, createLatestRead } from '../catalogApi'
import type { CatalogClient } from '../catalogApi'
import type { CatalogByKind, CatalogKind, CatalogPage } from '../catalog'
import { formatDate } from '../domain'
export type CatalogAccess = { client: CatalogClient; enabled: boolean; sessionKey: string; onUnauthorized: () => void }
function hint(row: CatalogByKind[CatalogKind]): string {
  if ('date' in row) return `${formatDate(row.date)} · ${row.venue?.name ?? 'Venue to confirm'} · ${row.artists.map(a => a.name).join(' · ') || 'Lineup to confirm'}`
  if ('street' in row) return [row.street, row.house_number, row.city, row.country_code].filter(Boolean).join(' · ') || 'Location to confirm'
  if ('disambiguation' in row) return row.disambiguation || ('type' in row ? row.type : '') || 'No description'
  return row.artists.map(a => a.name).join(' · ') || 'Artists to confirm'
}
export function CatalogPicker<K extends CatalogKind>({ catalog, kind, label, onPick }: { catalog: CatalogAccess; kind: K; label: string; onPick: (row: CatalogByKind[K]) => void }) {
  const { client, enabled, sessionKey, onUnauthorized } = catalog
  const id = useId(), input = useRef<HTMLInputElement>(null)
  const [query, setQuery] = useState(''), [page, setPage] = useState(1), [retry, setRetry] = useState(0), [open, setOpen] = useState(false)
  const [latest] = useState(createLatestRead)
  const key = JSON.stringify([kind, sessionKey, enabled, query, page, retry])
  const [result, setResult] = useState<{ key: string; page?: CatalogPage<CatalogByKind[K]>; error?: string } | null>(null)
  useEffect(() => {
    if (!enabled || !open) return
    const timer = setTimeout(() => {
      void latest.run(signal => client.search(kind, { q: query }, page, signal), value => setResult({ key, page: value }), error => {
        setResult({ key, error: catalogErrorMessage(error) })
        if (error instanceof CatalogApiError && error.status === 401) onUnauthorized()
      })
    }, 300)
    return () => { clearTimeout(timer); latest.cancel() }
  }, [client, enabled, sessionKey, kind, query, page, retry, open, key, latest, onUnauthorized])
  const current = result?.key === key ? result : null
  const loading = open && enabled && !current
  const close = () => { setOpen(false); latest.cancel(); input.current?.focus() }
  const search = () => { setOpen(true); setRetry(value => value + 1) }
  return <section className="catalog-picker" aria-label={label} onKeyDown={event => {
    if (event.key === 'Escape' && open) { event.preventDefault(); event.stopPropagation(); close() }
  }}>
    <label htmlFor={id}>{label}</label><div className="catalog-search"><input ref={input} id={id} type="search" autoComplete="off" maxLength={200} disabled={!enabled} value={query} onChange={event => { setQuery(event.target.value); setPage(1); setOpen(true) }} onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); search() } }} /><button type="button" className="button secondary small" disabled={!enabled} onClick={search}>Search</button></div>
    {!enabled && <p className="hint" role="status">Sign in to search the shared catalog. Manual entry is available below.</p>}
    {open && enabled && <div className="catalog-results" aria-label={`${label} results`}>
      {loading && <p role="status">Searching…</p>}
      {current?.error && <p role="alert">{current.error} <button type="button" className="text-button" onClick={search}>Try again</button></p>}
      {current?.page && <><p role="status">{current.page.total} results · page {current.page.page} of {current.page.lastPage}</p><ul>{current.page.items.map(row => <li key={row.id}><button type="button" onClick={() => { onPick(row); close() }}><strong>{'name' in row ? row.name : row.title}</strong><small>{hint(row)}</small></button></li>)}</ul>{!current.page.items.length && <p>No matches. Continue with manual entry.</p>}<div className="catalog-pages"><button type="button" disabled={current.page.page <= 1} onClick={() => setPage(value => value - 1)}>Previous</button><button type="button" disabled={current.page.page >= current.page.lastPage} onClick={() => setPage(value => value + 1)}>Next</button></div></>}
      <button type="button" className="text-button" onClick={close}>Close results</button>
    </div>}
  </section>
}
