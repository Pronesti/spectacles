import { useEffect, useId, useState } from 'react'
import { CatalogApiError, createLatestRead } from '../catalogApi'
import type { CatalogFilters } from '../catalogApi'
import type { CatalogAccess } from './CatalogPicker'
import type { CatalogByKind, CatalogKind } from '../catalog'
import { MusicBrainzApiError } from '../musicbrainzApi'
import type { createMusicBrainzClient } from '../musicbrainzApi'
import type { MusicBrainzCandidate, MusicBrainzKind } from '../musicbrainz'
import type { WizardArtistChoice } from '../concertWizard'
import { autocompleteKey, searchEntity } from '../autocomplete'
import type { EntityOption, LocalEntityOption } from '../autocomplete'
export type MusicBrainzAccess = { client: ReturnType<typeof createMusicBrainzClient>; enabled: boolean; sessionKey: string; onUnauthorized: () => void }
export function EntityAutocomplete({ kind, label, catalog, musicbrainz, filters = {}, draftArtists = [], onPick }: { kind: CatalogKind; label: string; catalog: CatalogAccess; musicbrainz: MusicBrainzAccess; filters?: CatalogFilters; draftArtists?: WizardArtistChoice[]; onPick: (option: EntityOption) => void }) {
  const { client: localClient, enabled: localEnabled, onUnauthorized: localUnauthorized } = catalog
  const { client: providerClient, enabled: providerEnabled, onUnauthorized: providerUnauthorized } = musicbrainz
  const providerKind: MusicBrainzKind | null = kind === 'artists' ? 'artist' : kind === 'songs' ? 'work' : kind === 'tours' ? 'series' : null
  const id = useId()
  const [query, setQuery] = useState(''), [keyboard, setKeyboard] = useState({ open: false, activeKey: null as string | null }), [composing, setComposing] = useState(false)
  const [pagination, setPagination] = useState({ scope: '', page: 1 }), [retry, setRetry] = useState(0)
  const [localRead] = useState(createLatestRead)
  const identity = JSON.stringify([kind, query, catalog.sessionKey, localEnabled, musicbrainz.sessionKey, providerEnabled, filters])
  const [local, setLocal] = useState<{ key: string; rows: CatalogByKind[CatalogKind][]; last: number; page?: number; error?: string } | null>(null)
  const [provider, setProvider] = useState<{ key: string; rows: MusicBrainzCandidate[]; loading?: boolean; error?: string } | null>(null)
  const filterKey = JSON.stringify(filters)
  const page = pagination.scope === filterKey ? pagination.page : 1
  const trimmedQuery = query.trim(), queryLength = [...trimmedQuery].length
  const hasScope = kind !== 'tours' || !!(filters.artist_uuids?.length || filters.artist_mbids?.length)
  const searchable = hasScope && (queryLength === 0 || queryLength >= 3)
  useEffect(() => {
    if (!keyboard.open || composing || !localEnabled || !searchable) return
    const timer = setTimeout(() => { setProvider(null); void localRead.run(signal => searchEntity({
      query: trimmedQuery, signal, providerEnabled: providerEnabled && providerKind !== null,
      local: (q, perPage, signal) => localClient.search(kind, { ...JSON.parse(filterKey), q }, queryLength === 0 ? 1 : page, signal, perPage),
      publishLocal: result => setLocal(previous => ({ key: identity, rows: page > 1 && queryLength > 0 && previous?.key === identity ? [...previous.rows, ...result.items] : result.items, last: queryLength === 0 ? 1 : result.lastPage, page })),
      provider: async (q, signal) => {
        setProvider({ key: identity, rows: [], loading: true })
        try {
          const rows = await providerClient.search(providerKind!, q, signal, JSON.parse(filterKey))
          return rows
        } catch (error) {
          if (signal.aborted) return []
          setProvider({ key: identity, rows: [], error: error instanceof MusicBrainzApiError ? error.message : 'MusicBrainz unavailable. Create manually.' })
          if (error instanceof MusicBrainzApiError && error.status === 401) providerUnauthorized()
          return null
        }
      },
      publishProvider: rows => { if (rows) setProvider({ key: identity, rows }) },
    }), () => {}, error => {
      setLocal({ key: identity, rows: [], last: 1, error: 'Local search unavailable. Create manually or try again.' })
      if (error instanceof CatalogApiError && error.status === 401) localUnauthorized()
    }) }, 350)
    return () => { clearTimeout(timer); localRead.cancel() }
  }, [identity, trimmedQuery, queryLength, searchable, page, retry, keyboard.open, composing, localClient, localEnabled, localUnauthorized, filterKey, kind, localRead, providerClient, providerEnabled, providerKind, providerUnauthorized])
  const currentLocal = !composing && searchable && local?.key === identity ? local : null, currentProvider = !composing && queryLength >= 3 && provider?.key === identity ? provider : null
  const options: EntityOption[] = [
    ...draftArtists.filter(a => a.draft && a.name.toLocaleLowerCase().includes(query.toLocaleLowerCase())).map(artist => ({ key: `draft:${artist.draft!.key}`, source: 'draft' as const, artist })),
    ...(currentLocal?.rows ?? []).map(row => ({ key: `catalog:${kind}:${row.id}`, source: 'catalog', kind, row } as LocalEntityOption)),
    ...(currentProvider?.rows ?? []).filter(c => !c.local || !currentLocal?.rows.some(r => r.id === c.local!.id)).map(candidate => ({ key: `musicbrainz:${candidate.kind}:${candidate.id}`, source: 'musicbrainz' as const, candidate })),
    { key: 'create', source: 'create', query },
  ]
  const pick = (option: EntityOption) => { localRead.cancel(); setKeyboard({ open: false, activeKey: null }); onPick(option) }
  const active = options.some(o => o.key === keyboard.activeKey) ? keyboard.activeKey : null
  return <section className="catalog-picker entity-autocomplete"><label htmlFor={id}>{label}</label><input id={id} role="combobox" aria-autocomplete="list" aria-expanded={keyboard.open} aria-controls={`${id}-list`} aria-activedescendant={keyboard.open && active ? `${id}-${options.findIndex(o => o.key === active)}` : undefined} autoComplete="off" maxLength={200} value={query} onFocus={() => setKeyboard(k => ({ ...k, open: true }))} onClick={() => setKeyboard(k => ({ ...k, open: true }))} onBlur={event => { if (!event.currentTarget.parentElement?.contains(event.relatedTarget)) { setKeyboard({ open: false, activeKey: null }); localRead.cancel() } }} onChange={event => { setQuery(event.target.value); setPagination({ scope: filterKey, page: 1 }); setKeyboard({ open: true, activeKey: null }) }} onCompositionStart={() => setComposing(true)} onCompositionEnd={() => setComposing(false)} onKeyDown={event => {
    const result = autocompleteKey(keyboard, event.key, options.map(o => o.key), composing || event.nativeEvent.isComposing)
    if (result.preventDefault) { event.preventDefault(); if (event.key === 'Escape') event.stopPropagation() }
    setKeyboard(result.state)
    const option = options.find(o => o.key === result.pickKey); if (option) pick(option)
  }} />
    {keyboard.open && <>{!hasScope && <p role="status">Select an Artist with a catalog or MusicBrainz identity to find Tours, or create a Tour manually.</p>}{queryLength > 0 && queryLength < 3 && <p role="status">Type at least 3 characters to search.</p>}<div id={`${id}-list`} role="listbox" aria-label={`${label} results`} className="catalog-results">{(['draft', 'catalog', 'musicbrainz', 'create'] as const).map(source => <div role="group" aria-label={source === 'musicbrainz' ? 'MusicBrainz' : source === 'catalog' ? 'Local catalog' : source === 'draft' ? 'Draft Artists' : 'Create manually'} key={source}>{options.filter(o => o.source === source).map(option => { const index = options.indexOf(option)
      const name = option.source === 'catalog' ? 'name' in option.row ? option.row.name : option.row.title : option.source === 'musicbrainz' ? option.candidate.local?.label ?? option.candidate.label : option.source === 'draft' ? option.artist.name : `Create manually${query ? `: ${query}` : ''}`
      const context = option.source === 'musicbrainz' ? [option.candidate.local ? 'Existing record' : 'MusicBrainz', option.candidate.artistType, option.candidate.disambiguation, option.candidate.area, option.candidate.country, ...option.candidate.writers.map(w => `Writer: ${w}`)].filter(Boolean).join(' · ') : option.source === 'catalog' ? `Local catalog · ${'disambiguation' in option.row ? option.row.disambiguation ?? '' : ''}` : option.source === 'draft' ? 'Draft Artist' : 'Nothing is saved yet'
      return <div role="option" aria-selected={active === option.key} id={`${id}-${index}`} key={option.key} onMouseDown={e => e.preventDefault()} onClick={() => pick(option)}><strong>{name}</strong><small>{context}</small></div>
    })}</div>)}</div>{currentLocal && !currentLocal.error && !currentLocal.rows.length && <p role="status">No local matches. Manual entry is available.</p>}{currentProvider && !currentProvider.loading && !currentProvider.error && !currentProvider.rows.length && <p role="status">No MusicBrainz matches. Manual entry is available.</p>}{!providerEnabled && providerKind && <p className="hint">Sign in to search MusicBrainz. Manual entry is available.</p>}{localEnabled && searchable && !composing && !currentLocal && <p role="status">Searching local catalog…</p>}{currentLocal?.error && <p role="alert">{currentLocal.error}</p>}{currentProvider?.loading && <p role="status">Searching MusicBrainz…</p>}{currentProvider?.error && <p role="alert">{currentProvider.error}</p>}{(currentLocal?.error || currentProvider?.error) && <button type="button" onClick={() => setRetry(n => n + 1)}>Try again</button>}{queryLength > 0 && currentLocal && page < currentLocal.last && <button type="button" disabled={currentLocal.page !== page} onClick={() => setPagination({ scope: filterKey, page: page + 1 })}>Load more local results</button>}</>}
    <button type="button" className="text-button" onClick={() => pick({ key: 'create', source: 'create', query })}>Create manually</button>
  </section>
}
