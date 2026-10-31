import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react'
import { autocompleteKey } from '../autocomplete'
import { CatalogApiError, createLatestRead } from '../catalogApi'
import { describeCreationMatch, mergeCreationMatches, searchCreation } from '../creationSearch'
import type { CreationMatch, CreationResult, CreationSearch as SearchOptions } from '../creationSearch'
export function CreationSearch({ label, query, onQuery, onPick, onCreate, ...options }: SearchOptions & { label: string; query: string; onQuery: (q: string) => void; onPick: (match: CreationMatch) => void; onCreate: () => void }) {
  const id = useId(), [latest] = useState(createLatestRead)
  const [keyboard, setKeyboard] = useState({ open: false, activeKey: null as string | null }), [composing, setComposing] = useState(false)
  const [result, setResult] = useState<{ identity: string; value: CreationResult } | null>(null), [busy, setBusy] = useState(''), [error, setError] = useState('')
  const [request, setRequest] = useState({ identity: '', page: 1, cursor: undefined as string | undefined, retry: 0 })
  const identity = JSON.stringify([options.kind, query, options.catalog.sessionKey, options.catalog.enabled, options.musicbrainz.sessionKey, options.musicbrainz.enabled, options.providers?.sessionKey, options.providers?.enabled, options.filters, options.artist, options.performance])
  const handlers = useRef(options); useLayoutEffect(() => { handlers.current = options })
  const length = [...query.trim()].length, searchable = length === 0 || length >= 3
  useEffect(() => {
    if (!keyboard.open || composing || !searchable) return
    const timer = setTimeout(() => {
      setBusy(identity); setError('')
      const page = request.identity === identity ? request.page : 1, cursor = request.identity === identity ? request.cursor : undefined
      void latest.run(signal => searchCreation(handlers.current, query, signal, page, cursor), value => {
        setResult(previous => ({ identity, value: (page > 1 || cursor) && previous?.identity === identity ? { ...value, items: mergeCreationMatches([...previous.value.items, ...value.items]), messages: cursor ? value.messages : previous.value.messages, nextPage: cursor ? previous.value.nextPage : value.nextPage, nextCursor: cursor ? value.nextCursor : previous.value.nextCursor, partial: previous.value.partial || value.partial } : value })); setBusy('')
      }, failure => { setBusy(''); setError(failure instanceof Error ? failure.message : 'Search unavailable. Create manually or try again.'); if (failure instanceof CatalogApiError && failure.status === 401) handlers.current.catalog.onUnauthorized() })
    }, 350)
    return () => { clearTimeout(timer); latest.cancel() }
  }, [identity, keyboard.open, composing, searchable, request, query, latest])
  const current = result?.identity === identity && searchable && !composing ? result.value : null
  const rows = current?.items ?? [], keys = [...rows.map(item => describeCreationMatch(item).key), 'create']
  const active = keys.includes(keyboard.activeKey ?? '') ? keyboard.activeKey : null
  const activeIndex = keys.indexOf(active ?? '')
  useEffect(() => {
    if (keyboard.open && active) document.getElementById(`${id}-${activeIndex}`)?.scrollIntoView({ block: 'nearest' })
  }, [keyboard.open, active, activeIndex, id])
  const pick = (key: string) => { latest.cancel(); setKeyboard({ open: false, activeKey: null }); if (key === 'create') onCreate(); else { const item = rows.find(row => describeCreationMatch(row).key === key); if (item) onPick(item) } }
  return <section className="creation-search"><label htmlFor={id}>{label}</label><input id={id} role="combobox" aria-autocomplete="list" aria-expanded={keyboard.open} aria-controls={`${id}-list`} aria-describedby={`${id}-status`} aria-activedescendant={keyboard.open && active ? `${id}-${keys.indexOf(active)}` : undefined} value={query} placeholder={`Search ${label.toLowerCase()}…`} maxLength={200} autoComplete="off"
    onFocus={() => setKeyboard(k => ({ ...k, open: true }))} onClick={() => setKeyboard(k => ({ ...k, open: true }))}
    onBlur={e => { if (!e.currentTarget.parentElement?.contains(e.relatedTarget)) { latest.cancel(); setKeyboard({ open: false, activeKey: null }) } }}
    onChange={e => { onQuery(e.target.value); setKeyboard({ open: true, activeKey: null }); setError('') }} onCompositionStart={() => setComposing(true)} onCompositionEnd={() => setComposing(false)}
    onKeyDown={e => { const next = autocompleteKey(keyboard, e.key, keys, composing || e.nativeEvent.isComposing); if (next.preventDefault) { e.preventDefault(); if (e.key === 'Escape') e.stopPropagation() } setKeyboard(next.state); if (next.pickKey) pick(next.pickKey) }} />
    <div id={`${id}-status`} className="creation-status" aria-live="polite">{keyboard.open && <>{length > 0 && length < 3 ? 'Type at least 3 characters to search.' : busy === identity || searchable && !current && !error && options.catalog.enabled ? 'Finding matches…' : error || current?.messages.join(' ') || (current && !rows.length ? length ? current.partial ? 'No matches in this source batch. You can load more or create manually.' : 'No matches. You can create manually.' : 'No saved matches yet.' : '')}</>}</div>
    {keyboard.open && <><div role="listbox" id={`${id}-list`} aria-label={`${label} results`} className="creation-results">{rows.map((row, index) => { const info = describeCreationMatch(row); return <div role="option" aria-selected={active === info.key} key={info.key} id={`${id}-${index}`} onMouseDown={e => e.preventDefault()} onMouseMove={() => setKeyboard({ open: true, activeKey: info.key })} onClick={() => pick(info.key)}><span><strong>{info.label}</strong>{info.detail && <small>{info.detail}</small>}</span><small className="creation-source">{info.source}</small></div> })}<div role="option" aria-selected={active === 'create'} id={`${id}-${rows.length}`} className="creation-manual" onMouseDown={e => e.preventDefault()} onClick={() => pick('create')}>+ Create manually{query.trim() ? `: ${query.trim()}` : ''}</div></div>
    {current?.partial && <p className="hint">Showing a limited source batch. Narrow your search or load more.</p>}{current?.nextPage && <button type="button" className="text-button" disabled={busy === identity} onClick={() => setRequest({ identity, page: current.nextPage!, cursor: undefined, retry: request.retry + 1 })}>Load more saved matches</button>}{current?.nextCursor && <button type="button" className="text-button" disabled={busy === identity} onClick={() => setRequest({ identity, page: 1, cursor: current.nextCursor, retry: request.retry + 1 })}>Load more online matches</button>}{(error || !!current?.messages.length) && <button type="button" className="text-button" onClick={() => setRequest({ identity, page: 1, cursor: undefined, retry: request.retry + 1 })}>Try again</button>}</>}
    {!keyboard.open && <button type="button" className="text-button creation-manual-link" onClick={onCreate}>Create manually</button>}
  </section>
}
