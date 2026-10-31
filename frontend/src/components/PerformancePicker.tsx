import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import { createLatestRead } from '../catalogApi'
import { CatalogProvidersError } from '../catalogProvidersApi'
import type { CatalogProvidersAccess } from '../catalogProvidersApi'
import type { PerformanceFilters, PerformancePreview, PerformanceSummary, ProviderPage, ManualPerformance } from '../catalogProviders'
import { providerKey } from '../catalogProviders'
import { performanceSummary } from '../providerDraft'
import { formatDate } from '../domain'
import { useRecoveryField } from './ConcertRecovery'
export function PerformanceFeedback({ loading, error, manual, onUseManual }: { loading: boolean; error: string; manual: ManualPerformance | null; onUseManual?: () => void }) {
  return <>{loading && <p role="status">Loading source details…</p>}{error && <p role="alert">{error}</p>}{manual && onUseManual && <div><p>Tour ID enrichment failed. Available values can fill a manual Event; the Event and Tour will have no setlist.fm identity.</p><button type="button" onClick={onUseManual}>Use available details manually</button></div>}</>
}
export function PerformancePicker({ providers, filters, query, onApply, onApplyManual, onEditingChange, history = false, previewOnly = false, initialSummary, onDismiss }: { onDismiss?: () => void; previewOnly?: boolean; initialSummary?: PerformanceSummary; query?: string; providers?: CatalogProvidersAccess; filters: PerformanceFilters; onApply: (preview: PerformancePreview) => void; onApplyManual?: (value: ManualPerformance) => void; onEditingChange?: (value: boolean) => void; history?: boolean }) {
  const [storedFilters, setFilters] = useRecoveryField(history ? 'historyPerformanceFilters' : 'performanceFilters', filters)
  const [selected, setSelected] = useRecoveryField(history ? 'historyPerformancePreview' : 'performancePreview', null)
  const [decision, setDecision] = useRecoveryField(history ? 'historyPerformanceDecision' : 'performanceDecision', null)
  const [storedPage, setPage] = useState<(ProviderPage<PerformanceSummary> & {scope:string}) | null>(null), [storedDetail, setDetail] = useState<{scope:string; value:PerformancePreview} | null>(null)
  const [manual, setManual] = useState<ManualPerformance | null>(null)
  const [error, setError] = useState(''), [busyScope, setBusyScope] = useState<string | null>(null)
  const [search] = useState(createLatestRead), [preview] = useState(createLatestRead)
  const heading = useRef<HTMLDivElement>(null)
  const effective: PerformanceFilters = { ...(previewOnly ? {} : storedFilters), ...filters, period: previewOnly || history ? filters.period : storedFilters.period, q: query ?? storedFilters.q }
  const scope = JSON.stringify([providers?.sessionKey, effective])
  const page = storedPage?.scope === scope ? storedPage : null, detail = storedDetail?.scope === scope ? storedDetail.value : null, loading = busyScope === scope
  const readScope = useRef(scope)
  useLayoutEffect(() => { readScope.current = scope }, [scope])
  useEffect(() => () => { search.cancel(); preview.cancel() }, [scope, search, preview])
  useEffect(() => { onEditingChange?.(!!selected); return () => onEditingChange?.(false) }, [selected, onEditingChange])
  const canSearch = providers?.enabled && !!(filters.artistUuid || filters.artistMbid || filters.venueId)
  const failure = (e: unknown) => { setManual(e instanceof CatalogProvidersError ? e.manual : null); setBusyScope(null); setError(e instanceof Error ? e.message : 'Performance search unavailable. Your draft is unchanged.'); if (e instanceof CatalogProvidersError && e.status === 401) providers?.onUnauthorized(); requestAnimationFrame(() => heading.current?.focus()) }
  const find = (cursor?: string) => {
    if (!canSearch || !providers) return
    const started = scope; setError(''); setBusyScope(scope)
    void search.run(signal => providers.client.performances({ ...effective, ...(cursor ? { cursor } : {}) }, signal), result => {
      if (readScope.current !== started) return
      setPage(previous => ({ ...result, scope:started, items: cursor ? [...new Map([...(previous?.items ?? []), ...result.items].map(p => [providerKey(p.ref), p])).values()] : result.items })); setBusyScope(null)
    }, e => { if (readScope.current === started) failure(e) })
  }
  const choose = (summary: PerformanceSummary) => {
    if (!providers?.enabled) return
    const started = scope; setSelected(performanceSummary(summary)); setDecision(null); setDetail(null); setManual(null); setBusyScope(scope); setError('')
    void preview.run(signal => providers.client.preview(summary, signal), result => { if (readScope.current !== started) return; setDetail({scope:started,value:result}); setBusyScope(null); requestAnimationFrame(() => heading.current?.focus()) }, e => { if (readScope.current === started) failure(e) })
  }
  const dismiss = () => { preview.cancel(); setSelected(null); setDecision(null); setDetail(null); setManual(null); setError(''); setBusyScope(null); onDismiss?.() }
  useEffect(() => {
    if (initialSummary) choose(initialSummary)
    // A new list selection requests review; recovered selections wait for Resume.
    // oxlint-disable-next-line react/exhaustive-deps
  }, [initialSummary])
  return <section className={`provider-performance-picker ${previewOnly ? 'creation-performance-review' : ''}`}>
    {!previewOnly && <>
    <div className="form-columns">{!history && <label>Performance period<select value={storedFilters.period} onChange={e => { search.cancel(); preview.cancel(); setBusyScope(null); setDetail(null); setPage(null); setSelected(null); setFilters({ ...effective, period: e.target.value as 'past' | 'upcoming' }) }}><option value="past">Past performances</option><option value="upcoming">Upcoming performances</option></select></label>}
      {query === undefined && <label>Performance search<input maxLength={200} value={storedFilters.q ?? ''} onChange={e => { search.cancel(); preview.cancel(); setBusyScope(null); setDetail(null); setPage(null); setSelected(null); setFilters({ ...effective, q: e.target.value }) }} onKeyDown={e => { if (e.key === 'Enter' && !e.nativeEvent.isComposing) { e.preventDefault(); find() } }} /></label>}</div>
    <button type="button" onMouseDown={e => e.preventDefault()} disabled={!canSearch || loading} onClick={() => find()}>Search setlist.fm</button>
    {!canSearch && <p className="hint">Select an identified Artist or a linked Venue to search. Manual entry remains available.</p>}
    {page && <><ul>{page.items.map(p => <li key={providerKey(p.ref)}><button type="button" onClick={() => choose(p)}>{p.artistName} · {p.venueName} · {formatDate(p.date)}</button></li>)}</ul>{!page.items.length && <p role="status">{page.partial ? 'No verified matches in this batch. Continue or narrow the search.' : 'No matching performances. Enter details manually.'}</p>}{page.partial && <p className="hint">These results cover a limited source search. Narrow the filters if needed.</p>}{page.nextCursor && <button type="button" disabled={loading} onClick={() => find(page.nextCursor!)}>Load more performances</button>}</>}
    </>}
    {!selected && <PerformanceFeedback loading={loading} error={error} manual={null} />}
    {selected && <div className="catalog-event-preview" ref={heading} tabIndex={-1}>
      <PerformanceFeedback loading={loading} error={error} manual={manual} onUseManual={manual && onApplyManual ? () => { onApplyManual(manual); dismiss() } : undefined} />
      {!detail && <><p>Selected: {selected.artistName} · {selected.venueName} · {formatDate(selected.date)}</p><button type="button" disabled={!providers?.enabled || loading} onClick={() => choose(selected)}>Resume review</button></>}
      {detail && <><h4>{detail.localEvent?.title ?? detail.artist.values.name}</h4><p>{formatDate(detail.localEvent?.date ?? detail.date)} · {detail.localEvent?.venue?.name ?? detail.venue.values.name}</p><p>{detail.localEvent ? detail.localEvent.artists.map(a => a.name).join(' · ') : detail.artist.values.name}</p><p>{detail.localEvent ? detail.localEvent.tours.map(t => t.name).join(' · ') || 'No Tour' : detail.tour?.values.name ?? 'No Tour information'}</p>
        {detail.localEvent && <p className="hint">This Event already exists. Its saved details and relationships will be used.</p>}
        <p className="hint">Use this concert’s Artist, Tour, Venue and date together. Your notes, flags and setlist stay.</p>
        <button type="button" className="button primary" onClick={() => { setDecision('apply'); onApply(detail); dismiss() }}>Apply performance details</button></>}
      <p><a href={selected.url} target="_blank" rel="noreferrer">Source: setlist.fm</a></p><button type="button" onClick={dismiss}>{decision ? 'Keep current draft' : 'Dismiss preview'}</button>
    </div>}
  </section>
}
