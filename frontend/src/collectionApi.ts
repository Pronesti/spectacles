import { decodeWorkspace } from './domain.ts'
import type { Concert, Venue, Workspace } from './domain.ts'
export class CollectionError extends Error {
  readonly status: number
  constructor(status: number, message: string) { super(message); this.status = status }
}
export const emptyCollection = (): Workspace => ({ version: 1, concerts: [], venues: [] })
export function createCollectionClient(baseUrl: string, fetcher: typeof fetch = fetch, cookie: () => string = () => document.cookie) {
  const base = baseUrl.replace(/\/+$/, '')
  async function send(path: string, method = 'GET', body?: unknown, signal?: AbortSignal): Promise<Workspace> {
    const controller = new AbortController()
    const abort = () => controller.abort()
    if (signal?.aborted) abort()
    signal?.addEventListener('abort', abort, { once: true })
    const timer = setTimeout(abort, 15000)
    const headers = new Headers({ Accept: 'application/json' })
    const options = { credentials: 'include' as const, cache: 'no-store' as const, redirect: 'error' as const, signal: controller.signal }
    try {
      if (method !== 'GET') {
        const csrf = await fetcher(`${base}/sanctum/csrf-cookie`, options)
        if (csrf.status !== 204) throw new CollectionError(csrf.status, 'Unable to initialize a secure save. Try again.')
        const token = cookie().split(';').map(part => part.trim()).find(part => part.startsWith('XSRF-TOKEN='))?.slice(11)
        if (!token) throw new CollectionError(419, 'Allow session cookies before saving.')
        headers.set('X-XSRF-TOKEN', decodeURIComponent(token)); headers.set('Content-Type', 'application/json')
      }
      const response = await fetcher(`${base}/api/v1/${path}`, { ...options, method, headers, ...(body === undefined ? {} : { body: JSON.stringify(body) }) })
      if (!response.ok) {
        const messages: Record<number, string> = {
          401: 'Your session expired. Sign in before saving. Your draft has not been saved.',
          403: 'This change is not permitted for this shared concert.',
          404: 'This concert or catalog selection is no longer available. Reload the collection.',
          409: 'This concert already exists or changed. Reload the collection before trying again.',
          419: 'Your session changed. Try again; the save was not replayed.',
          422: 'Check the concert fields and catalog selections. Nothing was saved.',
        }
        throw new CollectionError(response.status, messages[response.status] ?? 'The collection service is unavailable. Your changes were not confirmed. Reload before retrying.')
      }
      const result = await response.json()
      const workspace = decodeWorkspace(JSON.stringify(result.data))
      if (!workspace) throw new CollectionError(response.status, 'The collection response could not be read. Reload before making more changes.')
      return workspace
    } catch (error) {
      if (signal?.aborted || error instanceof CollectionError) throw error
      throw new CollectionError(0, 'Unable to confirm the database response. Your draft remains open. Reload before retrying a save.')
    } finally { clearTimeout(timer); signal?.removeEventListener('abort', abort) }
  }
  return {
    load: (signal?: AbortSignal) => send('collection', 'GET', undefined, signal),
    save: (concert: Concert, venue?: Venue, exists = false) => send(exists ? `attendances/${encodeURIComponent(concert.id)}` : 'attendances', exists ? 'PATCH' : 'POST', { concert, ...(venue ? { venue } : {}) }),
    remove: (id: string) => send(`attendances/${encodeURIComponent(id)}`, 'DELETE'),
  }
}
