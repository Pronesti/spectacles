import { decodeLocalVenue, decodePerformancePreview, decodePerformanceSummary, decodeProviderPage, decodeTour, decodeVenue, decodeManualPerformance, isRecord } from './catalogProviders.ts'
import type { ArtistScope, PerformanceFilters, PerformanceSummary, ManualPerformance } from './catalogProviders.ts'
export class CatalogProvidersError extends Error {
  readonly status: number
  readonly retryAfter: number | null
  readonly manual: ManualPerformance | null
  constructor(status: number, message: string, retryAfter: number | null = null, manual: ManualPerformance | null = null) { super(message); this.status = status; this.retryAfter = retryAfter; this.manual = manual }
}
export type CatalogProvidersAccess = { client: ReturnType<typeof createCatalogProvidersClient>; enabled: boolean; sessionKey: string; onUnauthorized: () => void }
export function createCatalogProvidersClient(baseUrl: string, fetcher: typeof fetch = fetch) {
  const base = baseUrl.replace(/\/+$/, '')
  async function read(path: string, params: Record<string, string | number | undefined>, signal?: AbortSignal, body?: unknown): Promise<unknown> {
    const controller = new AbortController(), abort = () => controller.abort()
    if (signal?.aborted) abort()
    signal?.addEventListener('abort', abort, { once: true })
    const timer = setTimeout(abort, 12000)
    try {
      const query = new URLSearchParams()
      for (const [key, value] of Object.entries(params)) if (value !== undefined && value !== '') query.set(key, String(value))
      const headers = new Headers({ Accept: 'application/json' })
      if (body !== undefined) {
        const csrf = await fetcher(`${base}/sanctum/csrf-cookie`, { credentials: 'include', signal: controller.signal, redirect: 'error' })
        if (!csrf.ok) throw new CatalogProvidersError(csrf.status, 'Session initialization failed. Your draft is unchanged.')
        const cookie = typeof document === 'undefined' ? '' : document.cookie
        const token = cookie.split('; ').find(p => p.startsWith('XSRF-TOKEN='))?.slice(11)
        if (!token) throw new CatalogProvidersError(419, 'Allow session cookies to confirm this Venue. Your draft is unchanged.')
        headers.set('X-XSRF-TOKEN', decodeURIComponent(token)); headers.set('Content-Type', 'application/json')
      }
      const response = await fetcher(`${base}/api/v1/${path}${query.size ? `?${query}` : ''}`, { method: body === undefined ? 'GET' : 'POST', headers, body: body === undefined ? undefined : JSON.stringify(body), credentials: 'include', cache: 'no-store', redirect: 'error', signal: controller.signal })
      if (!response.ok) {
        let manual: ManualPerformance | null = null, missingArtistIdentity = false, sourceNotReady = false
        try { const payload: unknown = await response.json(); if (isRecord(payload)) { if (payload.manual && [429,503].includes(response.status)) manual = decodeManualPerformance(payload.manual); missingArtistIdentity = response.status === 422 && isRecord(payload.errors) && Array.isArray(payload.errors.artistUuid) && payload.errors.artistUuid.includes('Select an Artist with a MusicBrainz identity or enter details manually.'); sourceNotReady = response.status === 503 && payload.reason === 'setlistfm_not_ready' } } catch { /* Malformed optional hints never replace an actionable source error. */ }
        throw new CatalogProvidersError(response.status, missingArtistIdentity ? 'Online search needs an Artist linked to MusicBrainz. You can enter details manually.' : sourceNotReady ? 'setlist.fm did not provide a page. Try again later or enter details manually.' : response.status === 401 ? 'Sign in again to search providers. Your draft is unchanged.' : response.status === 429 ? 'The source is busy. Try again shortly or enter details manually.' : 'Provider details are unavailable. Your draft is unchanged; manual entry is available.', Number(response.headers.get('Retry-After')) || null, manual)
      }
      return await response.json()
    } catch (error) {
      if (signal?.aborted || error instanceof CatalogProvidersError) throw error
      throw new CatalogProvidersError(0, 'Provider details are unavailable. Your draft is unchanged; manual entry is available.')
    } finally { clearTimeout(timer); signal?.removeEventListener('abort', abort) }
  }
  const single = <T,>(body: unknown, decode: (v: unknown) => T): T => { if (!isRecord(body) || !Array.isArray(body.data) || body.data.length !== 1) throw new Error('Invalid provider response'); return decode(body.data[0]) }
  return {
    tours: async (scope: ArtistScope, q: string, signal?: AbortSignal, cursor?: string) => decodeProviderPage(await read('providers/setlistfm/tours', { ...scope, q, cursor }, signal), decodeTour),
    performances: async (filters: PerformanceFilters, signal?: AbortSignal) => decodeProviderPage(await read('providers/setlistfm/performances', { ...filters }, signal), decodePerformanceSummary),
    preview: async (summary: PerformanceSummary, signal?: AbortSignal) => single(await read(`providers/setlistfm/performances/${encodeURIComponent(summary.ref.externalId)}`, { locator: summary.locator }, signal), decodePerformancePreview),
    places: async (q: string, signal?: AbortSignal) => decodeProviderPage(await read('musicbrainz/places', { q }, signal), decodeVenue),
    place: async (id: string, signal?: AbortSignal) => single(await read(`musicbrainz/places/${encodeURIComponent(id)}`, {}, signal), decodeVenue),
    venue: async (id: string, venueUrl: string, signal?: AbortSignal) => single(await read(`providers/setlistfm/venues/${encodeURIComponent(id)}`, { venueUrl }, signal), decodeVenue),
    catalogVenue: async (id: string, signal?: AbortSignal) => { const body = await read(`providers/venues/${encodeURIComponent(id)}`, {}, signal); if (!isRecord(body)) throw new Error('Invalid Venue response'); return decodeLocalVenue(body.data) },
    confirmVenue: async (selections: [string, string], signal?: AbortSignal) => single(await read('providers/venues/confirm', {}, signal, { selections }), decodeVenue),
  }
}
