import type { CatalogByKind, CatalogKind } from './catalog.ts'
import type { MusicBrainzCandidate } from './musicbrainz.ts'
import type { WizardArtistChoice } from './concertWizard.ts'
export type LocalEntityOption = { [K in CatalogKind]: { key: string; source: 'catalog'; kind: K; row: CatalogByKind[K] } }[CatalogKind]
export type EntityOption = LocalEntityOption | { key: string; source: 'musicbrainz'; candidate: MusicBrainzCandidate } | { key: string; source: 'draft'; artist: WizardArtistChoice } | { key: string; source: 'create'; query: string }
export type AutocompleteState = { open: boolean; activeKey: string | null }
export function autocompleteKey(state: AutocompleteState, key: string, keys: string[], composing: boolean): { state: AutocompleteState; pickKey: string | null; preventDefault: boolean } {
  if (composing) return { state, pickKey: null, preventDefault: false }
  const activeKey = state.activeKey && keys.includes(state.activeKey) ? state.activeKey : null
  if (key === 'Escape') return { state: { open: false, activeKey: null }, pickKey: null, preventDefault: state.open }
  if (key === 'Enter') return { state: { open: false, activeKey: null }, pickKey: state.open ? activeKey : null, preventDefault: true }
  if (key === 'ArrowDown' || key === 'ArrowUp') {
    const index = activeKey ? keys.indexOf(activeKey) : key === 'ArrowDown' ? -1 : keys.length
    return { state: { open: true, activeKey: keys[Math.max(0, Math.min(keys.length - 1, index + (key === 'ArrowDown' ? 1 : -1)))] ?? null }, pickKey: null, preventDefault: true }
  }
  return { state: { ...state, activeKey }, pickKey: null, preventDefault: false }
}

/** One abortable read owns both stages; only a successful empty catalog can use the provider. */
export async function searchEntity<Local extends { total: number }, Remote>(options: {
  query: string; signal: AbortSignal; providerEnabled: boolean;
  local: (query: string, perPage: number, signal: AbortSignal) => Promise<Local>;
  publishLocal: (result: Local) => void;
  provider: (query: string, signal: AbortSignal) => Promise<Remote>;
  publishProvider: (result: Remote) => void;
}) {
  const query = options.query.trim(), length = [...query].length
  if (options.signal.aborted || (length > 0 && length < 3)) return
  const result = await options.local(query, length === 0 ? 5 : 20, options.signal)
  if (options.signal.aborted) return
  options.publishLocal(result)
  if (result.total !== 0 || length < 3 || !options.providerEnabled || options.signal.aborted) return
  const remote = await options.provider(query, options.signal)
  if (!options.signal.aborted) options.publishProvider(remote)
}

export function tourArtistScope(artists: WizardArtistChoice[]) {
  return {
    artist_uuids: [...new Set(artists.flatMap(artist => artist.catalogId ? [artist.catalogId] : []))],
    artist_mbids: [...new Set(artists.flatMap(artist => !artist.catalogId && artist.draft?.input.selection && artist.draft.mbid ? [artist.draft.mbid] : []))],
  }
}
