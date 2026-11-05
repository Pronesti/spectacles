import { InteractionDisabledContext } from './InteractionDisabledContext'
import { Button, ComboBox, ComboBoxStateContext, Label, ListBox, ListBoxItem } from 'react-aria-components'
import { InlineComboboxInput, InlineComboboxResults } from './InlineCombobox'
import { useContext, useEffect, useRef, useState } from 'react'
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
  const interactionDisabled = useContext(InteractionDisabledContext)
  const { client, enabled: catalogEnabled, sessionKey, onUnauthorized } = catalog
  const enabled = catalogEnabled && !interactionDisabled
  const input = useRef<HTMLInputElement>(null)
  const [query, setQuery] = useState(''), [page, setPage] = useState(1), [retry, setRetry] = useState(0), [open, setOpen] = useState(false), [composing, setComposing] = useState(false)
  const [latest] = useState(createLatestRead)
  const key = JSON.stringify([kind, sessionKey, enabled, query, page, retry])
  const [result, setResult] = useState<{ key: string; page?: CatalogPage<CatalogByKind[K]>; error?: string } | null>(null)
  const trimmedQuery = query.trim(), queryLength = [...trimmedQuery].length
  const searchable = queryLength === 0 || queryLength >= 3
  useEffect(() => {
    if (!enabled || !open || composing || !searchable) return
    const timer = setTimeout(() => {
      void latest.run(signal => client.search(kind, { q: trimmedQuery }, queryLength === 0 ? 1 : page, signal, queryLength === 0 ? 5 : 20), value => setResult({ key, page: value }), error => {
        setResult({ key, error: catalogErrorMessage(error) })
        if (error instanceof CatalogApiError && error.status === 401) onUnauthorized()
      })
    }, 300)
    return () => { clearTimeout(timer); latest.cancel() }
  }, [client, enabled, sessionKey, kind, query, page, retry, open, key, latest, onUnauthorized, composing, searchable, trimmedQuery, queryLength])
  const current = !composing && searchable && result?.key === key ? result : null
  const loading = open && enabled && searchable && !composing && !current
  const [selected, setSelected] = useState<string | null>(null)
  const search = () => { setOpen(true); setRetry(value => value + 1) }
  const rows = current?.page?.items ?? []
  return <ComboBox className="catalog-picker" isDisabled={!enabled} items={rows}
    inputValue={query} onInputChange={value => { setQuery(value); setPage(1); setSelected(null) }}
    value={selected} onChange={id => { const row = rows.find(row => row.id === id); if (row) { setSelected(row.id); latest.cancel(); onPick(row) } }}
    menuTrigger="focus" allowsCustomValue allowsEmptyCollection shouldFocusWrap={false}
    onOpenChange={value => { setOpen(value); if (!value) { latest.cancel(); setSelected(null) } }}>
    <Label>{label}</Label><div className="catalog-search">
      <InlineComboboxInput inputRef={input} type="search" onComposing={setComposing} onSearch={search} />
      <ComboBoxStateContext.Consumer>{state => <Button slot={null} type="button" className="button secondary small" isDisabled={!enabled} onKeyDown={event => {
        if (event.key === 'Escape' && state?.isOpen && !event.nativeEvent.isComposing) { event.preventDefault(); event.stopPropagation(); input.current?.focus(); state.close() }
      }} onPress={() => { search(); state?.open(null, 'manual') }}>Search</Button>}</ComboBoxStateContext.Consumer>
    </div>
    {!catalogEnabled && <p className="hint" role="status">Sign in to search the shared catalog. Manual entry is available below.</p>}
    <InlineComboboxResults className="catalog-results catalog-picker-popup" onDismiss={() => input.current?.focus()}>
      {queryLength > 0 && queryLength < 3 && <p role="status">Type at least 3 characters to search.</p>}{loading && <p role="status">Searching…</p>}
      {current?.error && <p role="alert">{current.error} <Button slot={null} type="button" className="text-button" onPress={() => { input.current?.focus(); search() }}>Try again</Button></p>}
      {current?.page && <p role="status">{current.page.total} results · page {current.page.page} of {current.page.lastPage}</p>}
      <ListBox items={rows} aria-label={`${label} results`} className="catalog-picker-options">{row => <ListBoxItem id={row.id} textValue={'name' in row ? row.name : row.title} className="catalog-picker-option"><strong>{'name' in row ? row.name : row.title}</strong><small>{hint(row)}</small></ListBoxItem>}</ListBox>
      {current?.page && <>{!rows.length && <p>No matches. Continue with manual entry.</p>}{queryLength > 0 && <div className="catalog-pages"><Button slot={null} type="button" isDisabled={current.page.page <= 1} onPress={() => { input.current?.focus(); setPage(value => value - 1) }}>Previous</Button><Button slot={null} type="button" isDisabled={current.page.page >= current.page.lastPage} onPress={() => { input.current?.focus(); setPage(value => value + 1) }}>Next</Button></div>}</>}
      <ComboBoxStateContext.Consumer>{state => <Button slot={null} type="button" className="text-button" onPress={() => { input.current?.focus(); state?.close() }}>Close results</Button>}</ComboBoxStateContext.Consumer>
    </InlineComboboxResults>
  </ComboBox>
}
