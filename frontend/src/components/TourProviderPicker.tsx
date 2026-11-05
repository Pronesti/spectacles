import { SelectField } from './SelectField'
import { Button } from 'react-aria-components'
import { visibleSourceTours } from '../discoveryQuery'
import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import type { CatalogProvidersAccess } from '../catalogProvidersApi'
import { CatalogProvidersError } from '../catalogProvidersApi'
import type { TourSearchSuggestion, ProviderPage } from '../catalogProviders'
import type { WizardState } from '../concertWizard'
import { providerArtistScopes } from '../providerDraft'
import { tourSuggestionKey } from '../catalogProviders'
import { createLatestRead } from '../catalogApi'
import { useRecoveryField } from './ConcertRecovery'
export function TourProviderPicker({ state, providers, query, localIds = [], onPick }: { localIds?: string[]; query: string; state: WizardState; providers?: CatalogProvidersAccess; onPick: (tour: TourSearchSuggestion) => void }) {
  const [active, setActive] = useRecoveryField('activeProviderArtist', null)
  const scopes = providerArtistScopes(state), chosen = scopes.find(s => JSON.stringify(s.scope) === JSON.stringify(active)) ?? scopes[0]
  const identity = JSON.stringify([providers?.sessionKey, chosen?.scope, query]), current = useRef(identity); useLayoutEffect(() => { current.current = identity }, [identity])
  const [latest] = useState(createLatestRead), [storedPage, setPage] = useState<(ProviderPage<TourSearchSuggestion> & {scope:string}) | null>(null)
  const [busyScope, setBusyScope] = useState<string | null>(null), [error, setError] = useState('')
  const page = storedPage?.scope === identity ? storedPage : null, loading = busyScope === identity
  useEffect(() => () => latest.cancel(), [identity, latest])
  const search = (cursor?: string) => {
    if (!providers?.enabled || !chosen) return
    const started = identity; setBusyScope(identity); setError('')
    void latest.run(signal => providers.client.tours(chosen.scope, query, signal, cursor), value => {
      if (current.current !== started) return
      setPage(p => ({ ...value, scope:started, items: cursor ? [...new Map([...(p?.items ?? []), ...value.items].map(t => [tourSuggestionKey(t), t])).values()] : value.items })); setBusyScope(null)
    }, e => { if (current.current !== started) return; setBusyScope(null); setError(e instanceof Error ? e.message : 'Tour suggestions unavailable. Create manually.'); if (e instanceof CatalogProvidersError && e.status === 401) providers.onUnauthorized() })
  }
  return <section className="provider-tour-picker">{scopes.length > 1 && <SelectField label="Search setlist.fm for Artist" className="form-select" value={chosen ? JSON.stringify(chosen.scope) : ''} onChange={value => { latest.cancel(); setActive(JSON.parse(value)); setPage(null); setBusyScope(null) }} options={scopes.map(scope => ({ id: JSON.stringify(scope.scope), label: scope.label }))} />}
    <Button type="button" preventFocusOnPress isDisabled={!providers?.enabled || !chosen || loading} onPress={() => search()}>Search setlist.fm Tours</Button>
    {!chosen && <p className="hint">Choose an Artist with a provider identity for external Tours. Local and manual Tours remain available.</p>}{loading && <p role="status">Searching Tours…</p>}{error && <p role="alert">{error}</p>}
    {page && <><ul>{visibleSourceTours(page.items, localIds).map(t => <li key={tourSuggestionKey(t)}><Button type="button" onPress={() => { latest.cancel(); onPick(t) }}>{t.values.name}{!t.ref && ' · Confirm manually'}{t.localId ? ' · Existing local Tour' : ''}</Button><a href={t.url} target="_blank" rel="noreferrer">Source: setlist.fm</a></li>)}</ul>{!page.items.length && <p role="status">No Tour matches in this source batch.</p>}{page.partial && <p className="hint">Source results are limited; narrow the name or continue.</p>}{page.nextCursor && <Button type="button" isDisabled={loading} onPress={() => search(page.nextCursor!)}>Load more Tours</Button>}</>}
  </section>
}
