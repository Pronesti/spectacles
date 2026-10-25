import { catalogUuid, decodeCatalogEvent, decodeCatalogPage } from './catalog.ts'
import type { CatalogByKind, CatalogKind, CatalogPage } from './catalog.ts'
export type CatalogFilters = { q?: string; venue_uuid?: string; year?: number; month?: number; day?: number; provider?: string; resource_type?: string; provider_resource_id?: string; type?: string; city?: string; country_code?: string; disambiguation?: string; artist_uuid?: string; artist_uuids?: string[]; artist_mbids?: string[] }
const allowed: Record<CatalogKind, string[]> = {
  events: ['q', 'venue_uuid', 'year', 'month', 'day', 'provider', 'resource_type', 'provider_resource_id'],
  artists: ['q', 'type', 'provider', 'resource_type', 'provider_resource_id'], venues: ['q', 'city', 'country_code'],
  songs: ['q', 'disambiguation'], tours: ['q', 'artist_uuid', 'artist_uuids', 'artist_mbids'],
}
export class CatalogApiError extends Error {
  readonly status: number
  constructor(status: number, message: string) { super(message); this.name = 'CatalogApiError'; this.status = status }
}
export const catalogErrorMessage = (error: unknown): string => error instanceof CatalogApiError ? error.message : 'Catalog search is unavailable. Manual entry is still available.'
export function createCatalogClient(baseUrl: string, fetcher: typeof fetch = fetch) {
  const base = baseUrl.replace(/\/+$/, '')
  async function get(path: string, signal?: AbortSignal): Promise<unknown> {
    const controller = new AbortController()
    const abort = () => controller.abort()
    if (signal?.aborted) controller.abort()
    signal?.addEventListener('abort', abort, { once: true })
    const timer = setTimeout(abort, 10_000)
    try {
      const response = await fetcher(`${base}/api/v1/${path}`, { method: 'GET', headers: { Accept: 'application/json' }, credentials: 'include', cache: 'no-store', redirect: 'error', signal: controller.signal })
      if (!response.ok) {
        const messages: Record<number, string> = { 401: 'Your session expired. Sign in to search the catalog.', 404: 'This catalog record is no longer available.', 422: 'These search filters are not supported.', 429: 'Too many searches. Wait before trying again.' }
        throw new CatalogApiError(response.status, messages[response.status] ?? 'Catalog search is unavailable. Manual entry is still available.')
      }
      if (response.status !== 200) throw new CatalogApiError(response.status, 'The catalog returned an unexpected response.')
      try { return await response.json() }
      catch (error) {
        if (controller.signal.aborted) throw error
        throw new CatalogApiError(response.status, 'The catalog returned an unexpected response.')
      }
    } catch (error) {
      if (signal?.aborted || error instanceof CatalogApiError) throw error
      throw new CatalogApiError(0, controller.signal.aborted ? 'Catalog search timed out. Manual entry is still available.' : 'Catalog search is unavailable. Manual entry is still available.')
    } finally { clearTimeout(timer); signal?.removeEventListener('abort', abort) }
  }
  return {
    async search<K extends CatalogKind>(kind: K, filters: CatalogFilters = {}, page = 1, signal?: AbortSignal, perPage = 20): Promise<CatalogPage<CatalogByKind[K]>> {
      if (!Number.isInteger(page) || page < 1 || page > 100000) throw new CatalogApiError(422, 'Invalid catalog page.')
      if (!Number.isInteger(perPage) || perPage < 1 || perPage > 100) throw new CatalogApiError(422, 'Invalid catalog page size.')
      const query = new URLSearchParams({ page: String(page), per_page: String(perPage) })
      for (const [key, value] of Object.entries(filters)) {
        if (!allowed[kind].includes(key)) throw new CatalogApiError(422, 'These search filters are not supported.')
        if (Array.isArray(value)) { value.forEach((id, index) => query.set(`filter[${key}][${index}]`, id)); continue }
        if (value !== undefined && value !== '') query.set(`filter[${key}]`, String(value))
      }
      const value = await get(`${kind}?${query}`, signal)
      try { return decodeCatalogPage(kind, value) }
      catch { throw new CatalogApiError(200, 'The catalog returned an unexpected response.') }
    },
    async event(id: string, signal?: AbortSignal) {
      if (!catalogUuid(id)) throw new CatalogApiError(422, 'Invalid Event reference.')
      const query = new URLSearchParams({ include: 'tourLinks.tour.artistLinks.artist' })
      const value = await get(`events/${encodeURIComponent(id)}?${query}`, signal)
      try { return decodeCatalogEvent(value) }
      catch { throw new CatalogApiError(200, 'The catalog returned an unexpected response.') }
    },
  }
}
export type CatalogClient = ReturnType<typeof createCatalogClient>
export function createLatestRead() {
  let generation = 0
  let controller: AbortController | null = null
  return {
    cancel() { ++generation; controller?.abort() },
    async run<T>(read: (signal: AbortSignal) => Promise<T>, publish: (value: T) => void, reject: (error: unknown) => void): Promise<void> {
      const current = ++generation
      controller?.abort()
      const own = new AbortController(); controller = own
      try { const value = await read(own.signal); if (current === generation && !own.signal.aborted) publish(value) }
      catch (error) { if (current === generation && !own.signal.aborted) reject(error) }
    },
  }
}
