import { useEffect, useState } from 'react'
import { Button, Collection, ComboBox, ComboBoxStateContext, Label, ListBox, ListBoxItem, ListBoxSection } from 'react-aria-components'
import { InlineComboboxInput, InlineComboboxResults } from './InlineCombobox'
import { CatalogApiError, createLatestRead } from '../catalogApi'
import type { CatalogFilters } from '../catalogApi'
import type { CatalogAccess } from './CatalogPicker'
import type { CatalogByKind, CatalogKind } from '../catalog'
import { MusicBrainzApiError } from '../musicbrainzApi'
import type { createMusicBrainzClient } from '../musicbrainzApi'
import type { MusicBrainzCandidate, MusicBrainzKind } from '../musicbrainz'
import type { WizardArtistChoice } from '../concertWizard'
import { useRecoveryField } from './ConcertRecovery'
import type { RecoveryFields } from '../concertRecovery'
import { searchEntity } from '../autocomplete'
import type { EntityOption, LocalEntityOption } from '../autocomplete'
export type MusicBrainzAccess = { client: ReturnType<typeof createMusicBrainzClient>; enabled: boolean; sessionKey: string; onUnauthorized: () => void }
export function EntityAutocomplete({ kind, label, recoveryQuery, catalog, musicbrainz, filters = {}, draftArtists = [], queryControl, onLocalIds, suppressEmpty = false, onPick }: { onLocalIds?: (ids: string[]) => void; queryControl?: { value: string; onChange: (value: string) => void }; suppressEmpty?: boolean; recoveryQuery?: Extract<keyof RecoveryFields, `${string}Query`>; kind: CatalogKind; label: string; catalog: CatalogAccess; musicbrainz: MusicBrainzAccess; filters?: CatalogFilters; draftArtists?: WizardArtistChoice[]; onPick: (option: EntityOption) => void }) {
  const { client: localClient, enabled: localEnabled, onUnauthorized: localUnauthorized } = catalog
  const { client: providerClient, enabled: providerEnabled, onUnauthorized: providerUnauthorized } = musicbrainz
  const providerKind: MusicBrainzKind | null = kind === 'artists' ? 'artist' : kind === 'songs' ? 'work' : null
  const [storedQuery, setStoredQuery] = useRecoveryField(recoveryQuery ?? 'artistQuery', '')
  const [localQuery, setLocalQuery] = useState('')
  const query = queryControl?.value ?? (recoveryQuery ? storedQuery : localQuery), setQuery = queryControl?.onChange ?? (recoveryQuery ? setStoredQuery : setLocalQuery)
  const [open, setOpen] = useState(false), [composing, setComposing] = useState(false)
  const [selected, setSelected] = useState<string | null>(null)
  const [externalRequested, setExternalRequested] = useState(false)
  const [pagination, setPagination] = useState({ scope: '', page: 1 }), [retry, setRetry] = useState(0)
  const [localRead] = useState(createLatestRead)
  const identity = JSON.stringify([kind, query, catalog.sessionKey, localEnabled, musicbrainz.sessionKey, providerEnabled, filters])
  const [local, setLocal] = useState<{ key: string; rows: CatalogByKind[CatalogKind][]; last: number; page?: number; error?: string } | null>(null)
  const [provider, setProvider] = useState<{ key: string; rows: MusicBrainzCandidate[]; loading?: boolean; error?: string } | null>(null)
  useEffect(() => { if (!open) onLocalIds?.([]) }, [open, onLocalIds])
  const filterKey = JSON.stringify(filters)
  const page = pagination.scope === filterKey ? pagination.page : 1
  const trimmedQuery = query.trim(), queryLength = [...trimmedQuery].length
  const hasScope = true
  const searchable = hasScope && (queryLength === 0 || queryLength >= 3)
  useEffect(() => {
    if (!open || composing || !localEnabled || !searchable) return
    const timer = setTimeout(() => { setProvider(null); void localRead.run(signal => searchEntity({
      query: trimmedQuery, signal, providerEnabled: providerEnabled && providerKind !== null, externalRequested,
      local: (q, perPage, signal) => localClient.search(kind, { ...JSON.parse(filterKey), q }, queryLength === 0 ? 1 : page, signal, perPage),
      publishLocal: result => { onLocalIds?.(result.items.map(row => row.id)); setLocal(previous => ({ key: identity, rows: page > 1 && queryLength > 0 && previous?.key === identity ? [...previous.rows, ...result.items] : result.items, last: queryLength === 0 ? 1 : result.lastPage, page })) },
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
  }, [identity, trimmedQuery, queryLength, searchable, page, retry, open, composing, localClient, localEnabled, localUnauthorized, filterKey, kind, localRead, providerClient, providerEnabled, providerKind, providerUnauthorized, externalRequested, onLocalIds])
  const currentLocal = !composing && searchable && local?.key === identity ? local : null, currentProvider = !composing && queryLength >= 3 && provider?.key === identity ? provider : null
  const options: EntityOption[] = [
    ...draftArtists.filter(a => a.draft && a.name.toLocaleLowerCase().includes(query.toLocaleLowerCase())).map(artist => ({ key: `draft:${artist.draft!.key}`, source: 'draft' as const, artist })),
    ...(currentLocal?.rows ?? []).map(row => ({ key: `catalog:${kind}:${row.id}`, source: 'catalog', kind, row } as LocalEntityOption)),
    ...(currentProvider?.rows ?? []).filter(c => !c.local || !currentLocal?.rows.some(r => r.id === c.local!.id)).map(candidate => ({ key: `musicbrainz:${candidate.kind}:${candidate.id}`, source: 'musicbrainz' as const, candidate })),
    { key: 'create', source: 'create', query },
  ]
  const pick = (option: EntityOption) => { localRead.cancel(); setOpen(false); onPick(option) }
  // Result actions must not inherit the combobox trigger Button context.
  const footer = <ComboBoxStateContext.Consumer>{state => <>
    {providerKind && <Button slot={null} type="button" isDisabled={!providerEnabled || queryLength < 3} onPress={() => { setExternalRequested(true); setRetry(n => n + 1); state?.open(null, 'manual') }}>Search MusicBrainz</Button>}
    <Button slot={null} type="button" className="text-button" onPress={() => { state?.close(); pick({ key: 'create', source: 'create', query }) }}>Create manually</Button>
  </>}</ComboBoxStateContext.Consumer>
  return <ComboBox className="catalog-picker entity-autocomplete" items={options} inputValue={query}
    onInputChange={value => { setSelected(null); setQuery(value); setExternalRequested(false); setPagination({ scope: filterKey, page: 1 }) }}
    value={selected} onChange={key => { const option = options.find(option => option.key === key); if (option) { setSelected(option.key); pick(option) } }}
    menuTrigger="focus" allowsCustomValue allowsEmptyCollection shouldFocusWrap={false}
    onOpenChange={value => { if (!value) { setSelected(null); localRead.cancel() } setOpen(value) }}>
    <Label>{label}</Label><InlineComboboxInput onComposing={setComposing} />
    <InlineComboboxResults className="entity-autocomplete-popup" persistent={footer}>
    <>{!hasScope && <p role="status">Select an Artist with a catalog or MusicBrainz identity to find Tours, or create a Tour manually.</p>}{queryLength > 0 && queryLength < 3 && <p role="status">Type at least 3 characters to search.</p>}<ListBox aria-label={`${label} results`} className="catalog-results">{(['draft', 'catalog', 'musicbrainz', 'create'] as const).map(source => <ListBoxSection id={`source:${source}`} aria-label={source === 'musicbrainz' ? 'MusicBrainz' : source === 'catalog' ? 'Local catalog' : source === 'draft' ? 'Draft Artists' : 'Create manually'} key={source}><Collection items={options.filter(o => o.source === source)}>{option => {
      const name = option.source === 'catalog' ? 'name' in option.row ? option.row.name : option.row.title : option.source === 'musicbrainz' ? option.candidate.local?.label ?? option.candidate.label : option.source === 'draft' ? option.artist.name : `Create manually${query ? `: ${query}` : ''}`
      const context = option.source === 'musicbrainz' ? [option.candidate.local ? 'Existing record' : 'MusicBrainz', option.candidate.artistType, option.candidate.disambiguation, option.candidate.area, option.candidate.country, ...option.candidate.writers.map(w => `Writer: ${w}`)].filter(Boolean).join(' · ') : option.source === 'catalog' ? `Local catalog · ${'disambiguation' in option.row ? option.row.disambiguation ?? '' : ''}` : option.source === 'draft' ? 'Draft Artist' : 'Nothing is saved yet'
      return <ListBoxItem id={option.key} textValue={name}><strong>{name}</strong><small>{context}</small></ListBoxItem>
    }}</Collection></ListBoxSection>)}</ListBox>{!suppressEmpty && currentLocal && !currentLocal.error && !currentLocal.rows.length && <p role="status">No local matches. Manual entry is available.</p>}{currentProvider && !currentProvider.loading && !currentProvider.error && !currentProvider.rows.length && <p role="status">No MusicBrainz matches. Manual entry is available.</p>}{!providerEnabled && providerKind && <p className="hint">Sign in to search MusicBrainz. Manual entry is available.</p>}{localEnabled && searchable && !composing && !currentLocal && <p role="status">Searching local catalog…</p>}{currentLocal?.error && <p role="alert">{currentLocal.error}</p>}{currentProvider?.loading && <p role="status">Searching MusicBrainz…</p>}{currentProvider?.error && <p role="alert">{currentProvider.error}</p>}{(currentLocal?.error || currentProvider?.error) && <Button slot={null} type="button" onPress={() => setRetry(n => n + 1)}>Try again</Button>}{queryLength > 0 && currentLocal && page < currentLocal.last && <Button slot={null} type="button" isDisabled={currentLocal.page !== page} onPress={() => setPagination({ scope: filterKey, page: page + 1 })}>Load more local results</Button>}</>
    </InlineComboboxResults>
  </ComboBox>
}
