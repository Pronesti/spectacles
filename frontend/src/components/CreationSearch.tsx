import { Button } from 'react-aria-components'
import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import { CreationSearchCombobox } from './CreationSearchCombobox'
import { CatalogApiError, createLatestRead } from '../catalogApi'
import { describeCreationMatch, mergeCreationMatches, searchCreation } from '../creationSearch'
import type { CreationMatch, CreationResult, CreationSearch as SearchOptions } from '../creationSearch'
export function CreationSearch({ label, query, onQuery, onPick, onCreate, ...options }: SearchOptions & { label: string; query: string; onQuery: (q: string) => void; onPick: (match: CreationMatch) => void; onCreate: () => void }) {
  const [latest] = useState(createLatestRead)
  const [open, setOpen] = useState(false), [composing, setComposing] = useState(false)
  const [result, setResult] = useState<{ identity: string; value: CreationResult } | null>(null), [busy, setBusy] = useState(''), [error, setError] = useState('')
  const [request, setRequest] = useState({ identity: '', page: 1, cursor: undefined as string | undefined, retry: 0 })
  const identity = JSON.stringify([options.kind, query, options.catalog.sessionKey, options.catalog.enabled, options.musicbrainz.sessionKey, options.musicbrainz.enabled, options.providers?.sessionKey, options.providers?.enabled, options.filters, options.artist, options.performance])
  const handlers = useRef(options); useLayoutEffect(() => { handlers.current = options })
  const length = [...query.trim()].length, searchable = length === 0 || length >= 3
  useEffect(() => {
    if (!open || composing || !searchable) return
    const timer = setTimeout(() => {
      setBusy(identity); setError('')
      const page = request.identity === identity ? request.page : 1, cursor = request.identity === identity ? request.cursor : undefined
      void latest.run(signal => searchCreation(handlers.current, query, signal, page, cursor), value => {
        setResult(previous => ({ identity, value: (page > 1 || cursor) && previous?.identity === identity ? { ...value, items: mergeCreationMatches([...previous.value.items, ...value.items]), messages: cursor ? value.messages : previous.value.messages, nextPage: cursor ? previous.value.nextPage : value.nextPage, nextCursor: cursor ? value.nextCursor : previous.value.nextCursor, partial: previous.value.partial || value.partial } : value })); setBusy('')
      }, failure => { setBusy(''); setError(failure instanceof Error ? failure.message : 'Search unavailable. Create manually or try again.'); if (failure instanceof CatalogApiError && failure.status === 401) handlers.current.catalog.onUnauthorized() })
    }, 350)
    return () => { clearTimeout(timer); latest.cancel() }
  }, [identity, open, composing, searchable, request, query, latest])
  const current = result?.identity === identity && searchable && !composing ? result.value : null
  const rows = current?.items ?? []
  const emptyStatus = !!current && !rows.length && busy !== identity && !error && !current.messages.length
  const loading = searchable && (busy === identity || !current && !error && options.catalog.enabled)
  const status = length > 0 && length < 3 ? 'Type at least 3 characters to search.' : loading ? 'Finding matches…' : error || current?.messages.join(' ') || (emptyStatus ? length ? current.partial ? 'No matches in this source batch. You can load more or create manually.' : 'No matches. You can create manually.' : 'No saved matches yet.' : '')
  const pick = (key: string) => { latest.cancel(); setOpen(false); if (key === 'create') onCreate(); else { const item = rows.find(row => describeCreationMatch(row).key === key); if (item) onPick(item) } }
  // Result actions must not inherit the combobox trigger Button context.
  const actions = <>{current?.partial && <p className="hint">Showing a limited source batch. Narrow your search or load more.</p>}{current?.nextPage && <Button slot={null} type="button" className="text-button" isDisabled={busy === identity} onPress={() => setRequest({ identity, page: current.nextPage!, cursor: undefined, retry: request.retry + 1 })}>Load more saved matches</Button>}{current?.nextCursor && <Button slot={null} type="button" className="text-button" isDisabled={busy === identity} onPress={() => setRequest({ identity, page: 1, cursor: current.nextCursor, retry: request.retry + 1 })}>Load more online matches</Button>}{(error || !!current?.messages.length) && <Button slot={null} type="button" className="text-button" onPress={() => setRequest({ identity, page: 1, cursor: undefined, retry: request.retry + 1 })}>Try again</Button>}</>
  return <section className="creation-search">
    <CreationSearchCombobox label={label} query={query} rows={rows} status={status} loading={loading} actions={actions}
      onQuery={value => { onQuery(value); setError('') }} onPick={pick} onComposing={setComposing}
      onOpenChange={open => { if (!open) latest.cancel(); setOpen(open) }} />
  </section>
}
