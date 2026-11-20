import type { CatalogFilters } from './catalogApi.ts'
import { decodeMusicBrainzPage } from './musicbrainz.ts'
import type { MusicBrainzCandidate, MusicBrainzKind } from './musicbrainz.ts'
export class MusicBrainzApiError extends Error {
  readonly status: number
  readonly retryAfter: number | null
  constructor(status: number, message: string, retryAfter: number | null = null) { super(message); this.status = status; this.retryAfter = retryAfter }
}
export function createMusicBrainzClient(baseUrl: string, fetcher: typeof fetch = fetch) {
  return { async search(kind: MusicBrainzKind, q: string, signal?: AbortSignal, scope: Pick<CatalogFilters, 'artist_uuids' | 'artist_mbids'> = {}): Promise<MusicBrainzCandidate[]> {
    const paths = { artist: 'artists', work: 'works', series: 'tours' } as const
    if (!Object.hasOwn(paths, kind)) throw new MusicBrainzApiError(422, 'Unsupported search.')
    const params = new URLSearchParams({ q: q.trim() })
    if (kind === 'series') for (const key of ['artist_uuids', 'artist_mbids'] as const) scope[key]?.forEach((id, index) => params.set(`${key}[${index}]`, id))
    const url = `${baseUrl.replace(/\/+$/, '')}/api/v1/musicbrainz/${paths[kind]}?${params}`
    try { return await read(kind, url, signal) } catch (error) {
      if (!(error instanceof MusicBrainzApiError) || error.status !== 429 || (error.retryAfter ?? 60) > maxAutomaticRetryAfter) throw error
      await wait((error.retryAfter ?? 2) * 1000, signal)
      return read(kind, url, signal)
    }
  } }
  async function read(kind: MusicBrainzKind, url: string, signal?: AbortSignal): Promise<MusicBrainzCandidate[]> {
    const controller = new AbortController(), abort = () => controller.abort()
    if (signal?.aborted) abort()
    signal?.addEventListener('abort', abort, { once: true })
    const timer = setTimeout(abort, 8000)
    try {
      const response = await fetcher(url, { method: 'GET', headers: { Accept: 'application/json' }, credentials: 'include', cache: 'no-store', redirect: 'error', signal: controller.signal })
      if (response.status !== 200) throw new MusicBrainzApiError(response.status, response.status === 401 ? 'Sign in again to search MusicBrainz.' : response.status === 429 ? 'MusicBrainz is busy. Try again shortly, or create manually.' : 'MusicBrainz suggestions are unavailable. Manual entry is still available.', Math.min(60, Math.max(1, Number(response.headers.get('Retry-After')) || 2)))
      const body = await response.json()
      if (kind === 'series' && body?.meta?.scope_available === false) throw new MusicBrainzApiError(422, 'Selected Artists have no MusicBrainz identity. Create a Tour manually.')
      return decodeMusicBrainzPage(body, kind)
    } catch (error) {
      if (signal?.aborted || error instanceof MusicBrainzApiError) throw error
      throw new MusicBrainzApiError(0, 'MusicBrainz suggestions are unavailable. Manual entry is still available.')
    } finally { clearTimeout(timer); signal?.removeEventListener('abort', abort) }
  }
}
/** A 429 asking for at most this many seconds is retried once automatically. */
const maxAutomaticRetryAfter = 5
function wait(ms: number, signal?: AbortSignal): Promise<void> {
  return new Promise((resolve, reject) => {
    if (signal?.aborted) { reject(new DOMException('Aborted', 'AbortError')); return }
    const timer = setTimeout(() => { signal?.removeEventListener('abort', cancel); resolve() }, ms)
    function cancel() { clearTimeout(timer); reject(new DOMException('Aborted', 'AbortError')) }
    signal?.addEventListener('abort', cancel, { once: true })
  })
}
