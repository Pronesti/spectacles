# Frontend Catalog Read Integration Implementation Plan

> **Execution:** Follow the user-selected execution method and repository requirements. This plan does not require any additional skill. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connect the existing concert and setlist editors to the authenticated shared-catalog reads while preserving manual entry and truthful browser-local saves.

**Architecture:** Add a small catalog DTO decoder/client and one reusable search picker, then adapt the existing editors to confirm catalog selections into local drafts. Keep shared catalog facts separate from personal flags, notes, and setlists; use the existing account session and visual design. No backend write, schema, provider integration, or new package is part of this work.

**Tech Stack:** Node 24 (24.22.1 verified), React 19.3, TypeScript 6, Vite 8, native Fetch/AbortController/URLSearchParams, existing Node tests and Vite/React SSR markup tests.

**Spec:** `docs/superpowers/plans/2026-10-23-catalog-read-endpoints.md`, current backend `CatalogReadRequest`/`CatalogController`/resources/routes, `docs/superpowers/specs/2026-10-22-live-events-database-design.md`, and `frontend/design/visual-spec.md`. Current source, rather than historical planning assertions, governs the implemented API. This request is planning only.

## Global Constraints

- “Every field and setlist entry remains manually enterable.” Catalog lookup is optional; no results, errors, guest mode, or provider availability can disable manual local saves.
- “Catalog names and user content retain their original language and spelling.” Preserve Unicode and comma-containing names; do not infer identity from labels.
- “No substitute January 1 date is generated for a year-only entry.” Preserve partial/unknown dates, unknown Venue/time/timezone, local midnight and seconds, and zero coordinates.
- “Tours are optional.” Never derive an Event lineup from a Tour roster or a Song performer from its composition context.
- All six catalog reads require the existing Sanctum cookie session. Send `credentials: 'include'`, `Accept: application/json`, no body, no bearer header, and no CSRF mutation request. Guests get manual controls and a sign-in explanation, with no unauthorized catalog fetch.
- Existing `/api/v1/events` and Event detail include Venue/ordered lineup; only Event detail requests the supported optional Tour-roster include. Artist/Venue/Song results expose no private or provider relationships. Do not request sparse fields, client sort, private includes, or undocumented filters.
- The existing concert workspace is `spectacles.preview.v1`, shared across sign-ins. Selecting a catalog record and saving a concert/setlist is still a local browser operation. No Attendance, Event, Artist, Venue, Song, or Tour write request exists in this scope.
- Lookup lists/results are transient component memory; do not persist query caches, cookies, account identity, credentials, private Attendance data, or provider payloads in the workspace.
- Preserve the warm ivory/ink/lime/lavender colors, existing image, drawer, list rhythm, keyboard focus, touch targets, mobile layout, and reduced-motion behavior. Add no redesign or image generation.
- Add no npm dependency, lockfile change, backend endpoint, migration, or provider call. Do not alter the account mutation transport simply to share a few GET lines.
- Read current global/repository instructions and `~/.config/agent-skills/runtime.md` before PHP/Composer commands during execution. Creating a worktree requires user approval. Preserve unrelated changes and design/QA artifacts.
- This turn writes only this plan. Implementation, fixtures/server/database changes, commits, pushes, PRs, merges, deployment, and additional chats are not authorized by the planning request.

## Review Focus

- A slow old search, closed drawer, new query/page, or prior session must not publish results or a 401 over newer UI state. Pin in Task 1's latest-read tests and browser acceptance for rapid searches/session change.
- A catalog Event can have no lineup, no Venue, unknown timezone, partial dates, midnight, or nonzero seconds. Pin DTO/adoption/storage tests; do not fabricate facts to satisfy the old local model.
- Duplicate labels and comma-containing Artist names must remain distinct selectable records; manual edits must not retain a false selected identity. Pin draft/reference tests and browser save/reopen checks.
- Failed/expired catalog requests must preserve unsaved local inputs and existing account behavior. Pin safe HTTP-error tests and real-browser 401/offline/draft checks.
- JSON:API linkage, nullable decimal coordinates, malformed/HTML bodies, pagination URLs, and omitted private/provider fields must not corrupt drafts or leak requests to another origin. Pin decoder/client tests and browser Network checks.

---

## Current Evidence and Execution Boundary

Planning snapshot: `/Users/pronestivm/Projects/spectacles`, branch `feat/live-event-data-layer`, HEAD `fc1ed97 Add authenticated catalog read endpoints`. Account integration and backend catalog reads are implemented. In the inspected `frontend/src`, no catalog client/picker exists. `ConcertForm` uses comma-separated artists and a local Venue dropdown; `SetlistsView` edits title/performer text. Neither calls the catalog. `domain.ts` currently requires a nonempty Artist array and a non-null valid timezone, and accepts only minute-precision time strings. Those assumptions do not cover all valid catalog Events.

Preserve pre-existing modified `.gitignore`, `README.md`, `frontend/.env.example`, `frontend/vite.config.ts`, and untracked `.env.example`, `docs/`, `frontend/design/`, `qa-output/`. Recheck the live tree before execution. Existing manual account fixtures/test totals are historical; they are not proof that this new integration works.

## File Structure

| Path | Responsibility |
| --- | --- |
| `frontend/src/catalog.ts` | Explicit catalog DTOs and compound JSON:API decoding. |
| `frontend/src/catalogApi.ts` | Cookie GET client, filter encoding, safe errors/deadlines, latest-read guard. |
| `frontend/src/components/CatalogPicker.tsx` | Shared paged search UI and session-aware request lifecycle. |
| `frontend/src/components/EventCatalogPicker.tsx` | Fresh Event detail preview and explicit adoption. |
| `frontend/src/components/CatalogChoices.tsx` | Individual Artist/Tour chips plus manual entry. |
| `frontend/src/concertDraft.ts` | Catalog-to-local draft projection, Venue reuse, reference detachment helpers. |
| `frontend/src/domain.ts` | Optional public references and compatible empty-lineup/null-timezone/seconds validation. |
| `frontend/src/components/ConcertForm.tsx` | Integrated Event/Artist/Venue/Tour selection and manual editor. |
| `frontend/src/components/SetlistEditor.tsx`, `SetlistsView.tsx` | Extract existing inline editor and add independent Song/performer selection. |
| `frontend/src/components/ConcertList.tsx` | Honest unknown-lineup/location fallback for newly valid catalog drafts. |
| `frontend/src/useAccount.ts`, `App.tsx` | Stable expiry transition, editor props, and local Venue upsert. |
| `frontend/src/catalog.css` | Narrow picker/chip/preview styling using existing tokens. |
| `frontend/tests/catalog-fixtures.mjs`, `catalog-api.test.mjs` | Protocol/decoder/latest-read fixtures and tests. |
| `frontend/tests/catalog-picker.test.mjs`, `catalog-ui.test.mjs`, `catalog-draft.test.mjs` | Markup, draft projection, and storage compatibility regressions. |
| `frontend/README.md`, `README.md` | Truthful local-save and lookup documentation. |

Task 1 is a standalone protocol gate; Task 2 adds independently buildable reusable UI; Task 3 is the cohesive model/editor/App integration gate; Task 4 verifies browser behavior and updates documentation. Keep each gate buildable before handoff. Run Node commands in `frontend/`, and guarded Python patches from the repository root.

## API-to-UI Contract

| Read | Frontend use | Selection behavior |
| --- | --- | --- |
| `GET /events` | Search shared Events from the add/edit drawer, with paged results showing title, date precision, Venue, and lineup. | Choosing a result starts detail loading; it does not overwrite the draft. |
| `GET /events/{uuid}` | Fetch fresh facts with `include=tourLinks.tour.artistLinks.artist`. | Show an inline preview and a separate “Use these details” action. Adoption replaces shared fields only; personal flags, notes, and setlist remain. |
| `GET /artists` | Concert lineup and the existing setlist performer input. | Select by UUID, preserving the full returned name. Song selection does not auto-select a performer. |
| `GET /venues` | Shared Venue selection alongside the existing local/manual Venue controls. | Resolve local Venue reuse by its saved catalog UUID; never merge equal names automatically. No global workspace change before Save. |
| `GET /songs` | Existing setlist Song-title editor; show nullable disambiguation for duplicate titles. | Copy the composition title on explicit selection; performer remains independently editable. |
| `GET /tours` | Optional Tour search with Artist-roster context in the concert form, with independent manual Tour names. | A Tour roster is context, not automatic Event lineup. |

All five collection calls use typed `filter[...]`, `page`, and `per_page=20`. The picker initially uses `q`; the client supports the backend's documented optional filters. Construct next/previous page requests from validated page numbers and the same filters. Do not blindly follow server-provided links. No provider identity fields need to be exposed as technical form controls.

## Accepted Choices

The user explicitly selected saving optional catalog UUID references in browser-local drafts and adding an optional standalone Tour selector. Both decisions are incorporated below; no access or product-scope question remains open.

### Task 1: Typed Catalog DTOs, GET Client, and Latest-Read Guard

**Files:** Create `frontend/src/catalog.ts`, `frontend/src/catalogApi.ts`, `frontend/tests/catalog-fixtures.mjs`, and `frontend/tests/catalog-api.test.mjs`.

**Interfaces:** `CatalogKind`, `CatalogArtist`, `CatalogVenue`, `CatalogSong`, `CatalogTour`, `CatalogEvent`, `CatalogByKind`, `CatalogPage<T>`, `CatalogFilters`, `decodeCatalogPage(kind,unknown)`, and `decodeCatalogEvent(unknown)` define the public DTO boundary. `createCatalogClient(baseUrl,fetcher?)` produces `search(kind,filters,page,signal?)` and `event(uuid,signal?)`. `createLatestRead()` produces `run(read,publish,reject)` and `cancel()` for search/detail lifecycles. All reads are cancellable, bounded at 10 seconds including body consumption, and never replay a write.

- [ ] **Step 1: Create `frontend/tests/catalog-fixtures.mjs` with a reusable compound document.**

```js
export const id = number => `00000000-0000-4000-8000-${String(number).padStart(12, '0')}`
const ref = (type, number) => ({ type, id: id(number) })
const obj = (type, number, attributes, relationships) => ({ ...ref(type, number), attributes, ...(relationships ? { relationships } : {}) })
const rel = data => ({ data })
export function catalogFixture() {
  const a = obj('artists', 1, { name: 'Björk, guest', type: 'person', disambiguation: 'First artist' })
  const b = obj('artists', 2, { name: 'Björk, guest', type: 'person', disambiguation: 'Another artist' })
  const venue = obj('venues', 3, { name: 'Sala Ñ', street: null, house_number: '26-35', postal_code: 'C001', city: null, region: null, country_code: 'AR', lat: '0.0000000', lng: '0.0000000', timezone: null })
  const song = obj('songs', 4, { title: 'Canción', disambiguation: 'Composition context' })
  const tour = obj('tours', 5, { name: 'Gira del sur' }, { artistLinks: rel([ref('tour_artists', 6)]) })
  const tourArtist = obj('tour_artists', 6, {}, { artist: rel(ref('artists', 2)) })
  const event = obj('events', 7, { title: 'Noche de música', type: 'concert', year: 2024, month: null, day: null, local_start_time: '00:00:07', timezone: null }, { venue: rel(ref('venues', 3)), artistLinks: rel([ref('event_artists', 8), ref('event_artists', 9)]), tourLinks: rel([ref('event_tours', 10)]) })
  const first = obj('event_artists', 8, { position: 1 }, { artist: rel(ref('artists', 1)) })
  const last = obj('event_artists', 9, { position: null }, { artist: rel(ref('artists', 2)) })
  const eventTour = obj('event_tours', 10, {}, { tour: rel(ref('tours', 5)) })
  const included = [a, b, venue, tour, tourArtist, first, last, eventTour]
  const page = rows => ({ data: rows, included, meta: { current_page: 1, per_page: 20, last_page: 1, total: rows.length }, links: { next: 'https://untrusted.invalid/never-follow' } })
  return { a, b, venue, song, tour, event, detail: { data: event, included }, page }
}
```

- [ ] **Step 2: Write `frontend/tests/catalog-api.test.mjs` and observe red tests before creating source modules.**

```js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { decodeCatalogEvent, decodeCatalogPage } from '../src/catalog.ts'
import { CatalogApiError, createCatalogClient, createLatestRead } from '../src/catalogApi.ts'
import { catalogFixture, id } from './catalog-fixtures.mjs'
const json = (value, status = 200) => new Response(JSON.stringify(value), { status, headers: { 'Content-Type': 'application/vnd.api+json' } })
test('compound Event decoding preserves linkage order, commas, null facts, seconds and zero coordinates', () => {
  const fixture = catalogFixture()
  const event = decodeCatalogEvent(fixture.detail)
  assert.deepEqual(event.artists.map(artist => artist.id), [id(1), id(2)])
  assert.deepEqual(event.artists.map(artist => artist.name), ['Björk, guest', 'Björk, guest'])
  assert.equal(event.venue.lat, 0); assert.equal(event.venue.lng, 0)
  assert.equal(event.venue.city, null); assert.equal(event.timezone, null)
  assert.equal(event.time, '00:00:07')
  assert.deepEqual(event.date, { year: 2024, month: null, day: null })
  assert.deepEqual(event.tours[0].artists.map(artist => artist.id), [id(2)])
  fixture.event.relationships.artistLinks.data = []
  fixture.event.relationships.venue.data = null
  fixture.event.attributes = { ...fixture.event.attributes, year: null, local_start_time: null }
  const unknown = decodeCatalogEvent(fixture.detail)
  assert.deepEqual(unknown.artists, []); assert.equal(unknown.venue, null); assert.equal(unknown.time, null)
})
test('all five collections decode explicit DTOs without provider or private attributes', () => {
  const f = catalogFixture()
  for (const [kind, rows] of [['events', [f.event]], ['artists', [f.a, f.b]], ['venues', [f.venue]], ['songs', [f.song]], ['tours', [f.tour]]]) {
    rows[0].attributes.private_debug = 'never-copy'
    const page = decodeCatalogPage(kind, f.page(rows))
    assert.equal(page.items.length, rows.length)
    assert.equal(JSON.stringify(page).includes('never-copy'), false)
    assert.equal(JSON.stringify(page).includes('untrusted.invalid'), false)
  }
})
test('invalid shapes, missing linkage, invalid dates and malformed coordinates fail atomically', () => {
  const f = catalogFixture()
  assert.throws(() => decodeCatalogPage('artists', { data: [f.a] }))
  assert.throws(() => decodeCatalogPage('artists', f.page([f.a, f.a])))
  assert.throws(() => decodeCatalogEvent({ data: f.event, included: [] }))
  f.event.attributes.month = 2; f.event.attributes.day = 30
  assert.throws(() => decodeCatalogEvent(f.detail))
  f.event.attributes.day = 29
  f.venue.attributes.lat = ''
  assert.throws(() => decodeCatalogEvent(f.detail))
  assert.throws(() => decodeCatalogEvent({ data: null }))
})
test('six reads use encoded filters and cookie GETs with no CSRF, body or bearer header', async () => {
  const f = catalogFixture(), calls = []
  const rows = { events: [f.event], artists: [f.a], venues: [f.venue], songs: [f.song], tours: [f.tour] }
  const client = createCatalogClient('https://api.spectacles.test/', async (value, init) => {
    const url = new URL(value); calls.push(url)
    assert.equal(init.method, 'GET'); assert.equal(init.credentials, 'include'); assert.equal(init.cache, 'no-store')
    assert.equal(init.body, undefined); assert.equal(new Headers(init.headers).get('Accept'), 'application/json')
    assert.equal(new Headers(init.headers).has('Authorization'), false)
    const kind = url.pathname.split('/')[3]
    return json(url.pathname.endsWith(id(7)) ? f.detail : f.page(rows[kind]))
  })
  for (const kind of Object.keys(rows)) await client.search(kind, { q: 'Björk & 100%_!' }, 2)
  await client.event(id(7))
  assert.equal(calls.length, 6)
  assert.equal(calls[0].searchParams.get('filter[q]'), 'Björk & 100%_!')
  assert.equal(calls[0].searchParams.get('page'), '2')
  assert.equal(calls.at(-1).searchParams.get('include'), 'tourLinks.tour.artistLinks.artist')
  assert.ok(calls.every(url => url.origin === 'https://api.spectacles.test'))
  await assert.rejects(client.search('songs', { artist_uuid: id(1) }), error => error.status === 422)
  await assert.rejects(client.event('local-concert-1'), error => error.status === 422)
  assert.equal(calls.length, 6)
})
test('401,404,422,429,5xx,HTML and network failures produce safe recoverable errors', async () => {
  for (const status of [401, 404, 422, 429, 503]) {
    const client = createCatalogClient('https://api.spectacles.test', async () => json({ message: 'private-debug' }, status))
    await assert.rejects(client.search('artists'), error => error instanceof CatalogApiError && error.status === status && !error.message.includes('private-debug'))
  }
  const html = createCatalogClient('https://api.spectacles.test', async () => new Response('<html>debug</html>'))
  await assert.rejects(html.search('artists'), error => error instanceof CatalogApiError && error.status === 200)
  const offline = createCatalogClient('https://api.spectacles.test', async () => { throw new TypeError('private-detail') })
  await assert.rejects(offline.search('artists'), error => error.status === 0 && !error.message.includes('private-detail'))
})
test('a stalled body times out and caller cancellation remains cancellation', async context => {
  context.mock.timers.enable({ apis: ['setTimeout'] })
  const client = createCatalogClient('https://api.spectacles.test', async (url, init) => new Response(new ReadableStream({ start(controller) {
    init.signal.addEventListener('abort', () => controller.error(new DOMException('Aborted', 'AbortError')), { once: true })
  } })))
  const pending = client.search('artists')
  await Promise.resolve(); context.mock.timers.tick(10_000)
  await assert.rejects(pending, error => error.status === 0 && error.message.includes('timed out'))
  const cancel = new AbortController()
  const cancelled = client.search('artists', {}, 1, cancel.signal)
  cancel.abort()
  await assert.rejects(cancelled, { name: 'AbortError' })
})
test('new searches and cancel suppress late success and obsolete unauthorized callbacks', async () => {
  const latest = createLatestRead(), seen = [], errors = []
  let finishA, finishB, rejectOld
  const a = latest.run(signal => new Promise(resolve => { finishA = value => { assert.equal(signal.aborted, true); resolve(value) } }), value => seen.push(value), error => errors.push(error))
  const b = latest.run(() => new Promise(resolve => { finishB = resolve }), value => seen.push(value), error => errors.push(error))
  finishB('new'); await b; finishA('old'); await a
  assert.deepEqual(seen, ['new'])
  const old = latest.run(() => new Promise((resolve, reject) => { rejectOld = reject }), value => seen.push(value), error => errors.push(error))
  latest.cancel(); rejectOld(new CatalogApiError(401, 'expired')); await old
  assert.deepEqual(errors, [])
})
```

Run the command below and observe missing imports. Then implement Steps 3–4 and rerun. A test runner failure caused by configuration is not a valid red endpoint test.

```sh
node --test tests/catalog-api.test.mjs
```

- [ ] **Step 3: Create the DTO decoder in `frontend/src/catalog.ts`.**

```ts
import { validDate } from './domain.ts'
import type { EventDate } from './domain.ts'

export type CatalogKind = 'events' | 'artists' | 'venues' | 'songs' | 'tours'
export type CatalogArtist = { id: string; name: string; type: string | null; disambiguation: string | null }
export type CatalogSong = { id: string; title: string; disambiguation: string | null }
export type CatalogVenue = { id: string; name: string; street: string | null; house_number: string | null; postal_code: string | null; city: string | null; region: string | null; country_code: string | null; lat: number | null; lng: number | null; timezone: string | null }
export type CatalogTour = { id: string; name: string; artists: CatalogArtist[] }
export type CatalogEvent = { id: string; title: string; type: string; date: EventDate; time: string | null; timezone: string | null; venue: CatalogVenue | null; artists: CatalogArtist[]; tours: CatalogTour[] }
export type CatalogByKind = { events: CatalogEvent; artists: CatalogArtist; venues: CatalogVenue; songs: CatalogSong; tours: CatalogTour }
export type CatalogPage<T> = { items: T[]; page: number; lastPage: number; total: number }
type Obj = { id: string; type: string; attributes: Record<string, unknown>; relationships?: Record<string, unknown> }
const record = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value)
export const catalogUuid = (value: unknown): value is string => typeof value === 'string' && /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i.test(value)
const invalid = (): never => { throw new Error('Invalid catalog response') }
const string = (value: unknown): string => typeof value === 'string' && value.trim() ? value : invalid()
const nullableString = (value: unknown): string | null => value === null ? null : typeof value === 'string' ? value : invalid()
const integer = (value: unknown, min: number): number => typeof value === 'number' && Number.isInteger(value) && value >= min ? value : invalid()
function timezone(value: unknown): string | null {
  const zone = nullableString(value)
  if (zone !== null) { try { new Intl.DateTimeFormat('en', { timeZone: zone }) } catch { invalid() } }
  return zone
}
function object(value: unknown): Obj {
  if (!record(value) || !catalogUuid(value.id) || typeof value.type !== 'string' || !record(value.attributes)) return invalid()
  if (value.relationships !== undefined && !record(value.relationships)) return invalid()
  return value as Obj
}
function decoder(document: unknown) {
  if (!record(document) || !('data' in document) || document.included !== undefined && !Array.isArray(document.included)) return invalid()
  const roots = Array.isArray(document.data) ? document.data.map(object) : [object(document.data)]
  const all = [...roots, ...(Array.isArray(document.included) ? document.included.map(object) : [])]
  const index = new Map(all.map(row => [`${row.type}:${row.id}`, row]))
  const resolve = (link: unknown, expected: string): Obj => {
    if (!record(link) || link.type !== expected || !catalogUuid(link.id)) return invalid()
    return index.get(`${expected}:${link.id}`) ?? invalid()
  }
  const relation = (row: Obj, name: string): unknown => {
    const value = row.relationships?.[name]
    if (!record(value) || !('data' in value)) return invalid()
    return value.data
  }
  const many = (row: Obj, name: string, type: string): Obj[] => {
    const links = relation(row, name)
    return Array.isArray(links) ? links.map(link => resolve(link, type)) : invalid()
  }
  const artist = (row: Obj): CatalogArtist => ({ id: row.id, name: string(row.attributes.name), type: nullableString(row.attributes.type), disambiguation: nullableString(row.attributes.disambiguation) })
  const venue = (row: Obj): CatalogVenue => {
    const a = row.attributes
    const coordinate = (value: unknown): number | null => {
      if (value === null) return null
      if (typeof value !== 'number' && (typeof value !== 'string' || !/^-?\d+(?:\.\d+)?$/.test(value))) return invalid()
      const number = Number(value)
      return Number.isFinite(number) ? number : invalid()
    }
    const lat = coordinate(a.lat), lng = coordinate(a.lng)
    if ((lat === null) !== (lng === null) || lat !== null && (Math.abs(lat) > 90 || Math.abs(lng!) > 180)) return invalid()
    return { id: row.id, name: string(a.name), street: nullableString(a.street), house_number: nullableString(a.house_number), postal_code: nullableString(a.postal_code), city: nullableString(a.city), region: nullableString(a.region), country_code: nullableString(a.country_code), lat, lng, timezone: timezone(a.timezone) }
  }
  const tour = (row: Obj): CatalogTour => ({ id: row.id, name: string(row.attributes.name), artists: many(row, 'artistLinks', 'tour_artists').map(link => artist(resolve(relation(link, 'artist'), 'artists'))) })
  const event = (row: Obj, requireTours = false): CatalogEvent => {
    const a = row.attributes
    const date: EventDate = { year: a.year as number | null, month: a.month as number | null, day: a.day as number | null }
    if (!validDate(date)) return invalid()
    const time = nullableString(a.local_start_time)
    if (time !== null && !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(time)) return invalid()
    const venueLink = relation(row, 'venue')
    const hasTours = row.relationships?.tourLinks !== undefined
    if (requireTours && !hasTours) return invalid()
    return { id: row.id, title: string(a.title), type: string(a.type), date, time, timezone: timezone(a.timezone), venue: venueLink === null ? null : venue(resolve(venueLink, 'venues')), artists: many(row, 'artistLinks', 'event_artists').map(link => artist(resolve(relation(link, 'artist'), 'artists'))), tours: hasTours ? many(row, 'tourLinks', 'event_tours').map(link => tour(resolve(relation(link, 'tour'), 'tours'))) : [] }
  }
  const convert = (kind: CatalogKind, row: Obj): CatalogByKind[CatalogKind] => {
    if (row.type !== kind) return invalid()
    switch (kind) {
      case 'artists': return artist(row)
      case 'venues': return venue(row)
      case 'songs': return { id: row.id, title: string(row.attributes.title), disambiguation: nullableString(row.attributes.disambiguation) }
      case 'tours': return tour(row)
      case 'events': return event(row)
    }
  }
  return { roots, convert, event, document }
}
export function decodeCatalogPage<K extends CatalogKind>(kind: K, value: unknown): CatalogPage<CatalogByKind[K]> {
  const decoded = decoder(value)
  if (!Array.isArray(decoded.document.data) || !record(decoded.document.meta)) return invalid()
  const meta = decoded.document.meta
  const perPage = integer(meta.per_page, 1)
  if (perPage > 100 || decoded.roots.length > perPage || new Set(decoded.roots.map(row => row.id)).size !== decoded.roots.length) return invalid()
  return { items: decoded.roots.map(row => decoded.convert(kind, row)) as CatalogByKind[K][], page: integer(meta.current_page, 1), lastPage: integer(meta.last_page, 1), total: integer(meta.total, 0) }
}
export function decodeCatalogEvent(value: unknown): CatalogEvent {
  const decoded = decoder(value)
  if (Array.isArray(decoded.document.data) || decoded.roots[0]?.type !== 'events') return invalid()
  return decoded.event(decoded.roots[0], true)
}
```

Only explicit DTO fields leave the decoder. Additional timestamps/unknown attributes are not copied. All relationship resolution keys include both type and UUID. Preserve the server's Artist-link order, and reject missing required linkage rather than invent labels or silently select another record.

- [ ] **Step 4: Create the GET client and latest-read guard in `frontend/src/catalogApi.ts`.**

```ts
import { catalogUuid, decodeCatalogEvent, decodeCatalogPage } from './catalog.ts'
import type { CatalogByKind, CatalogKind, CatalogPage } from './catalog.ts'
export type CatalogFilters = { q?: string; venue_uuid?: string; year?: number; month?: number; day?: number; provider?: string; resource_type?: string; provider_resource_id?: string; type?: string; city?: string; country_code?: string; disambiguation?: string; artist_uuid?: string }
const allowed: Record<CatalogKind, string[]> = {
  events: ['q', 'venue_uuid', 'year', 'month', 'day', 'provider', 'resource_type', 'provider_resource_id'],
  artists: ['q', 'type', 'provider', 'resource_type', 'provider_resource_id'], venues: ['q', 'city', 'country_code'],
  songs: ['q', 'disambiguation'], tours: ['q', 'artist_uuid'],
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
    async search<K extends CatalogKind>(kind: K, filters: CatalogFilters = {}, page = 1, signal?: AbortSignal): Promise<CatalogPage<CatalogByKind[K]>> {
      if (!Number.isInteger(page) || page < 1 || page > 100000) throw new CatalogApiError(422, 'Invalid catalog page.')
      const query = new URLSearchParams({ page: String(page), per_page: '20' })
      for (const [key, value] of Object.entries(filters)) {
        if (!allowed[kind].includes(key)) throw new CatalogApiError(422, 'These search filters are not supported.')
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
```

The account client remains unchanged. Catalog GETs need neither its CSRF-cookie request nor its mutation behavior. Request errors never display backend debug/HTML messages. The decoder never follows top-level links; pagination stays on the configured API origin.

- [ ] **Step 5: Run the focused tests, lint and build after adding these standalone modules.**

```sh
node --test tests/catalog-api.test.mjs
npm run lint
npm run build
```

These modules do not yet change UI or storage. All original account and demo paths must still build. Do not call the API against a live user account to validate the fake-fetch tests.

### Task 2: Reusable Search and Explicit Event Confirmation

**Files:** Create `frontend/src/components/CatalogPicker.tsx`, `frontend/src/components/EventCatalogPicker.tsx`, and `frontend/src/catalog.css`. Create `frontend/tests/catalog-picker.test.mjs` for this reusable UI gate; full editor markup joins the next task.

**Interfaces:** `CatalogAccess` contains the stable `CatalogClient`, `enabled`, `sessionKey`, and stable `onUnauthorized()` callback. `CatalogPicker<K>` accepts that access, kind, label, and `onPick(CatalogByKind[K])`. `EventCatalogPicker` produces a confirmed `CatalogEvent` only after fresh detail loads and the user presses “Use these details”.

- [ ] **Step 1: Write the failing reusable-picker markup test in `frontend/tests/catalog-picker.test.mjs`.**

```js
import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'
let server, CatalogPicker, EventCatalogPicker
const catalog = { enabled: false, sessionKey: 'guest', client: { search: async () => { throw new Error('No SSR fetch') }, event: async () => { throw new Error('No SSR fetch') } }, onUnauthorized: () => {} }
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/catalog-picker-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
  ;({ CatalogPicker } = await server.ssrLoadModule('/src/components/CatalogPicker.tsx'))
  ;({ EventCatalogPicker } = await server.ssrLoadModule('/src/components/EventCatalogPicker.tsx'))
})
after(async () => { await server?.close() })
test('guest search is labelled and disabled without nesting another form', () => {
  const html = renderToStaticMarkup(createElement(CatalogPicker, { catalog, kind: 'artists', label: 'Search Artists', onPick: () => {} }))
  assert.match(html, /Search Artists/); assert.match(html, /<input[^>]*disabled/)
  assert.match(html, /Manual entry is available/); assert.doesNotMatch(html, /<form/)
  const event = renderToStaticMarkup(createElement(EventCatalogPicker, { catalog, onUse: () => {} }))
  assert.match(event, /Find an existing Event/); assert.doesNotMatch(event, /Use these details/)
})
```

Run `node --test tests/catalog-picker.test.mjs` and observe missing-component imports before adding the components. After the following steps, require it to pass alongside lint/build.

- [ ] **Step 2: Create `frontend/src/components/CatalogPicker.tsx`.**

```tsx
import { useEffect, useId, useRef, useState } from 'react'
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
  const { client, enabled, sessionKey, onUnauthorized } = catalog
  const id = useId(), input = useRef<HTMLInputElement>(null)
  const [query, setQuery] = useState(''), [page, setPage] = useState(1), [retry, setRetry] = useState(0), [open, setOpen] = useState(false)
  const [latest] = useState(createLatestRead)
  const key = JSON.stringify([kind, sessionKey, enabled, query, page, retry])
  const [result, setResult] = useState<{ key: string; page?: CatalogPage<CatalogByKind[K]>; error?: string } | null>(null)
  useEffect(() => {
    if (!enabled || !open) return
    const timer = setTimeout(() => {
      void latest.run(signal => client.search(kind, { q: query }, page, signal), value => setResult({ key, page: value }), error => {
        setResult({ key, error: catalogErrorMessage(error) })
        if (error instanceof CatalogApiError && error.status === 401) onUnauthorized()
      })
    }, 300)
    return () => { clearTimeout(timer); latest.cancel() }
  }, [client, enabled, sessionKey, kind, query, page, retry, open, key, latest, onUnauthorized])
  const current = result?.key === key ? result : null
  const loading = open && enabled && !current
  const close = () => { setOpen(false); latest.cancel(); input.current?.focus() }
  const search = () => { setOpen(true); setRetry(value => value + 1) }
  return <section className="catalog-picker" aria-label={label} onKeyDown={event => {
    if (event.key === 'Escape' && open) { event.preventDefault(); event.stopPropagation(); close() }
  }}>
    <label htmlFor={id}>{label}</label><div className="catalog-search"><input ref={input} id={id} type="search" autoComplete="off" maxLength={200} disabled={!enabled} value={query} onChange={event => { setQuery(event.target.value); setPage(1); setOpen(true) }} onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); search() } }} /><button type="button" className="button secondary small" disabled={!enabled} onClick={search}>Search</button></div>
    {!enabled && <p className="hint" role="status">Sign in to search the shared catalog. Manual entry is available below.</p>}
    {open && enabled && <div className="catalog-results" aria-label={`${label} results`}>
      {loading && <p role="status">Searching…</p>}
      {current?.error && <p role="alert">{current.error} <button type="button" className="text-button" onClick={search}>Try again</button></p>}
      {current?.page && <><p role="status">{current.page.total} results · page {current.page.page} of {current.page.lastPage}</p><ul>{current.page.items.map(row => <li key={row.id}><button type="button" onClick={() => { onPick(row); close() }}><strong>{'name' in row ? row.name : row.title}</strong><small>{hint(row)}</small></button></li>)}</ul>{!current.page.items.length && <p>No matches. Continue with manual entry.</p>}<div className="catalog-pages"><button type="button" disabled={current.page.page <= 1} onClick={() => setPage(value => value - 1)}>Previous</button><button type="button" disabled={current.page.page >= current.page.lastPage} onClick={() => setPage(value => value + 1)}>Next</button></div></>}
      <button type="button" className="text-button" onClick={close}>Close results</button>
    </div>}
  </section>
}
```

This is a labelled search and a native button list, not a partially implemented ARIA combobox. Keyboard users use Tab/Enter/Space; Enter in the search field searches rather than submitting the parent editor. First Escape closes results and returns focus; a subsequent Escape retains the existing drawer behavior. New query/page/session keys immediately hide stale results while debounce/abort cleanup invalidates old requests.

- [ ] **Step 3: Create `frontend/src/components/EventCatalogPicker.tsx`.**

```tsx
import { useEffect, useRef, useState } from 'react'
import { CatalogApiError, catalogErrorMessage, createLatestRead } from '../catalogApi'
import type { CatalogEvent } from '../catalog'
import { formatDate } from '../domain'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
export function EventCatalogPicker({ catalog, onUse }: { catalog: CatalogAccess; onUse: (event: CatalogEvent) => void }) {
  const [latest] = useState(createLatestRead)
  const [state, setState] = useState<{ session: string; event?: CatalogEvent; loading?: boolean; error?: string } | null>(null)
  const preview = useRef<HTMLDivElement>(null)
  useEffect(() => () => latest.cancel(), [latest, catalog.enabled, catalog.sessionKey])
  const choose = (event: CatalogEvent) => {
    setState({ session: catalog.sessionKey, loading: true })
    void latest.run(signal => catalog.client.event(event.id, signal), detail => {
      setState({ session: catalog.sessionKey, event: detail })
      requestAnimationFrame(() => preview.current?.focus())
    }, error => {
      setState({ session: catalog.sessionKey, error: catalogErrorMessage(error) })
      if (error instanceof CatalogApiError && error.status === 401) catalog.onUnauthorized()
    })
  }
  const current = catalog.enabled && state?.session === catalog.sessionKey ? state : null
  return <section><CatalogPicker catalog={catalog} kind="events" label="Find an existing Event" onPick={choose} />
    {current && <div className="catalog-event-preview" ref={preview} tabIndex={-1}>
      {current.loading && <p role="status">Loading Event details…</p>}{current.error && <p role="alert">{current.error} Your draft has not changed.</p>}
      {current.event && <><h3>{current.event.title}</h3><p>{current.event.type}</p><p>{formatDate(current.event.date)} · {current.event.venue?.name ?? 'Venue to confirm'}</p><p>{current.event.artists.map(a => a.name).join(' · ') || 'Lineup to confirm'}</p><p>{current.event.time ?? 'Time to confirm'} · {current.event.timezone ?? 'Timezone to confirm'}</p><p>{current.event.tours.map(t => t.name).join(' · ') || 'Tour information unavailable'}</p><p className="hint">Use these shared details in this browser draft. Your flags, notes, and setlist will stay as entered.</p><button type="button" className="button primary" onClick={() => { onUse(current.event!); setState(null) }}>Use these details</button></>}
      <button type="button" className="text-button" onClick={() => { latest.cancel(); setState(null) }}>Dismiss preview</button>
    </div>}
  </section>
}
```

- [ ] **Step 4: Add `frontend/src/catalog.css`.**

```css
.catalog-picker { display: grid; gap: 9px; min-width: 0; }
.catalog-picker > label { font-size: 12px; font-weight: 500; }
.catalog-search { display: flex; gap: 8px; min-width: 0; }
.catalog-search input { width: 100%; min-width: 0; min-height: 44px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 6px; background: transparent; color: var(--ink); }
.catalog-search button, .catalog-pages button, .catalog-results > .text-button, .catalog-chip button { min-height: 44px; }
.catalog-results { border-top: 1px solid var(--line); padding-top: 10px; font-size: 12px; line-height: 1.6; }
.catalog-results ul { list-style: none; margin: 0; padding: 0; }
.catalog-results li + li { border-top: 1px solid var(--line); }
.catalog-results li > button { width: 100%; min-height: 48px; text-align: left; padding: 10px 8px; display: grid; gap: 5px; }
.catalog-results li > button:hover, .catalog-results li > button:focus-visible { background: #e9f5cb; }
.catalog-results small { color: var(--muted); overflow-wrap: anywhere; }
.catalog-pages { display: flex; gap: 16px; justify-content: space-between; }
.catalog-event-preview { border-left: 2px solid var(--lime); padding: 16px; display: grid; gap: 12px; line-height: 1.6; font-size: 13px; }
.catalog-choices { display: grid; gap: 10px; min-width: 0; }
.catalog-chips { display: flex; gap: 8px; flex-wrap: wrap; list-style: none; padding: 0; margin: 0; }
.catalog-chip { display: inline-flex; align-items: center; gap: 8px; padding: 0 8px 0 12px; border: 1px solid var(--line); border-radius: 8px; font-size: 12px; max-width: 100%; overflow-wrap: anywhere; }
.catalog-manual { display: flex; gap: 8px; align-items: end; }
.catalog-manual > label { flex: 1; min-width: 0; }
.catalog-manual > button { min-height: 44px; }
@media (max-width: 480px) { .catalog-search, .catalog-manual { flex-wrap: wrap; } .catalog-manual > label { flex-basis: 100%; } }
```

- [ ] **Step 5: Run lint/build after adding these standalone components.**

```sh
node --test tests/catalog-picker.test.mjs
npm run lint
npm run build
```

No editor invokes these components until the next task. All effect callbacks must be stable at the App boundary; otherwise typing unrelated draft fields could repeatedly restart search. The markup tests in Tasks 2–3 and real-browser checks in Task 4 cover these controls alongside the existing account tests.

### Task 3: Backward-Compatible Local References and Integrated Editors

**Files:** Modify `frontend/src/domain.ts`, `frontend/src/components/ConcertForm.tsx`, `frontend/src/components/SetlistsView.tsx`, `frontend/src/components/ConcertList.tsx`, `frontend/src/useAccount.ts`, and `frontend/src/App.tsx`; create `frontend/src/components/SetlistEditor.tsx`, `frontend/tests/catalog-ui.test.mjs`, `frontend/src/concertDraft.ts`, `frontend/src/components/CatalogChoices.tsx`, and `frontend/tests/catalog-draft.test.mjs`.

**Interfaces:** Add `CatalogChoice = { name: string; catalogId: string | null }`; optional Concert `eventType` and `catalog: { eventId?: string; artistIds: (string|null)[]; tours: CatalogChoice[] }`; optional Venue `catalogId`/`timezone`; optional SetlistEntry `songCatalogId`/`artistCatalogId`. Existing local IDs remain unchanged. `createConcertDraft`, `adoptCatalogEvent`, `adoptCatalogVenue`, and `finishConcertDraft` own projection and identity updates. The workspace remains version 1 because these are optional additions; old records still decode without migration.

The user explicitly selected **save UUID references** and **add an optional Tour selector**. Lineup reference arrays align by index with existing Artist-name arrays; the decoder validates that alignment. Different Artists with the same name keep different UUIDs, including after reopen. Editing shared Event fields detaches its Event reference, while independent notes/flags/setlist edits retain it. Editing a selected Song/performer's display field detaches that specific reference. No name-based deduplication or automatic local/server merge is introduced.

- [ ] **Step 1: Write `frontend/tests/catalog-draft.test.mjs` before editing the model/helpers.**

```js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createDemoWorkspace, decodeWorkspace } from '../src/domain.ts'
import { createConcertDraft, adoptCatalogEvent, adoptCatalogVenue, finishConcertDraft } from '../src/concertDraft.ts'
import { decodeCatalogEvent } from '../src/catalog.ts'
import { catalogFixture, id } from './catalog-fixtures.mjs'
test('legacy workspaces and new references round-trip without changing local identities', () => {
  const workspace = createDemoWorkspace('2026-10-23')
  assert.deepEqual(decodeWorkspace(JSON.stringify(workspace)), workspace)
  const original = workspace.concerts[0]
  const draft = adoptCatalogEvent(createConcertDraft(original), decodeCatalogEvent(catalogFixture().detail), workspace.venues)
  draft.concert.notes = 'Keep my local notes'; draft.concert.interested = false
  const result = finishConcertDraft(draft)
  assert.equal(result.concert.id, original.id)
  assert.equal(result.concert.notes, 'Keep my local notes')
  assert.equal(result.concert.interested, false)
  assert.deepEqual(result.concert.setlist, original.setlist)
  assert.equal(result.concert.catalog.eventId, id(7))
  assert.deepEqual(result.concert.catalog.artistIds, [id(1), id(2)])
  assert.deepEqual(result.concert.artists, ['Björk, guest', 'Björk, guest'])
  assert.equal(result.concert.catalog.tours[0].catalogId, id(5))
  assert.equal(result.concert.timezone, null); assert.equal(result.concert.time, '00:00:07')
  workspace.concerts[0] = result.concert; workspace.venues.push(result.venue)
  assert.deepEqual(decodeWorkspace(JSON.stringify(workspace)), workspace)
})
test('empty lineup and unknown date/timezone remain valid without inventing facts', () => {
  const f = catalogFixture()
  f.event.relationships.artistLinks.data = []
  f.event.relationships.venue.data = null
  f.event.attributes.year = null; f.event.attributes.local_start_time = null
  const result = finishConcertDraft(adoptCatalogEvent(createConcertDraft(), decodeCatalogEvent(f.detail), []))
  assert.deepEqual(result.concert.artists, [])
  assert.deepEqual(result.concert.date, { year: null, month: null, day: null })
  assert.equal(result.concert.venueId, null); assert.equal(result.concert.time, ''); assert.equal(result.concert.timezone, null)
})
test('Venue reuse uses catalog UUID rather than labels and changes only the returned draft', () => {
  const venue = decodeCatalogEvent(catalogFixture().detail).venue
  const locals = [{ id: 'local-a', name: venue.name, city: '', neighborhood: '', lat: null, lng: null }, { id: 'local-b', name: 'Old name', city: '', neighborhood: '', lat: null, lng: null, catalogId: venue.id }]
  const adopted = adoptCatalogVenue(createConcertDraft(), venue, locals)
  assert.equal(adopted.concert.venueId, 'local-b')
  assert.equal(adopted.pendingVenue.id, 'local-b')
  assert.equal(adopted.pendingVenue.lat, 0)
  assert.equal(locals[1].name, 'Old name')
  const separate = adoptCatalogVenue(createConcertDraft(), { ...venue, id: id(99) }, locals)
  assert.notEqual(separate.concert.venueId, 'local-a')
  assert.notEqual(separate.concert.venueId, id(99))
})
test('misaligned or malformed optional references are unreadable rather than partially hydrated', () => {
  const workspace = createDemoWorkspace('2026-10-23')
  workspace.concerts[0].catalog = { eventId: 'not-a-uuid', artistIds: [null], tours: [] }
  assert.equal(decodeWorkspace(JSON.stringify(workspace)), null)
  workspace.concerts[0].catalog = { artistIds: [], tours: [] }
  assert.equal(decodeWorkspace(JSON.stringify(workspace)), null)
  workspace.concerts[0].catalog = { artistIds: [null], tours: [] }
  workspace.concerts[0].setlist = [{ id: 'note', type: 'note', title: 'Encore', artist: '', tape: false, songCatalogId: id(4) }]
  assert.equal(decodeWorkspace(JSON.stringify(workspace)), null)
})
```

Also create `frontend/tests/catalog-ui.test.mjs`:

```js
import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'
let server, ConcertForm, SetlistEditor, CatalogChoices
const catalog = { enabled: false, sessionKey: 'guest', client: { search: async () => { throw new Error('No SSR request') }, event: async () => { throw new Error('No SSR request') } }, onUnauthorized: () => {} }
const noop = () => {}
const render = (component, props) => renderToStaticMarkup(createElement(component, props))
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/catalog-editor-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
  ;({ ConcertForm } = await server.ssrLoadModule('/src/components/ConcertForm.tsx'))
  ;({ SetlistEditor } = await server.ssrLoadModule('/src/components/SetlistEditor.tsx'))
  ;({ CatalogChoices } = await server.ssrLoadModule('/src/components/CatalogChoices.tsx'))
})
after(async () => { await server?.close() })
test('guest concert editor offers optional catalog search while manual save remains available', () => {
  const html = render(ConcertForm, { catalog, venues: [], onClose: noop, onSave: noop })
  for (const label of ['Find an existing Event', 'Search shared Venues', 'Artists', 'Tours (optional)', 'Enter a name manually', 'Save concert']) assert.ok(html.includes(label))
  assert.match(html, /Sign in to search the shared catalog/)
  assert.match(html, /Saving does not create or update shared catalog records/)
  assert.match(html, /type="time" step="1"/)
})
test('duplicate named choices keep separate remove controls and notes have no composition picker', () => {
  const choices = [{ name: 'Same name', catalogId: '00000000-0000-4000-8000-000000000001' }, { name: 'Same name', catalogId: '00000000-0000-4000-8000-000000000002' }]
  const html = render(CatalogChoices, { catalog, kind: 'artists', label: 'Artists', choices, onChange: noop })
  assert.equal((html.match(/aria-label="Remove Same name, item [12]"/g) ?? []).length, 2)
  const note = render(SetlistEditor, { catalog, entry: { id: 'note', type: 'note', title: 'Encore', artist: '', tape: false }, onSave: noop, onCancel: noop })
  assert.doesNotMatch(note, /Find an existing Song|Search a performer/)
  const song = render(SetlistEditor, { catalog, entry: { id: 'song', type: 'song', title: '', artist: '', tape: false }, onSave: noop, onCancel: noop })
  assert.match(song, /Find an existing Song/); assert.match(song, /Search a performer/)
})
```


Run `node --test tests/catalog-draft.test.mjs tests/catalog-ui.test.mjs` and require the expected missing-helper/component failures before implementation.

- [ ] **Step 2: Apply this guarded script to `frontend/src/domain.ts` from the repository root.**

```python
from pathlib import Path
p = Path('frontend/src/domain.ts')
s = p.read_text()
def once(old, new):
    global s
    assert s.count(old) == 1, old[:100]
    s = s.replace(old, new, 1)
once("export type Venue = { id: string; name: string; city: string; neighborhood: string; lat: number | null; lng: number | null }", "export type CatalogChoice = { name: string; catalogId: string | null }\nexport type Venue = { id: string; name: string; city: string; neighborhood: string; lat: number | null; lng: number | null; catalogId?: string; timezone?: string | null }")
once("type: 'song' | 'note'; title: string; artist: string; tape: boolean }", "type: 'song' | 'note'; title: string; artist: string; tape: boolean; songCatalogId?: string; artistCatalogId?: string }")
once('  time: string; timezone: string; interested:', '  eventType?: string; catalog?: { eventId?: string; artistIds: (string | null)[]; tours: CatalogChoice[] };\n  time: string; timezone: string | null; interested:')
once('const record = (value: unknown)', "export const publicUuid = (value: unknown): value is string => typeof value === 'string' && /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i.test(value)\nconst record = (value: unknown)")
once('// Saved demo data is an external input too.', '''function validCatalogRefs(value: unknown, count: number): boolean {
  if (value === undefined) return true
  if (!record(value) || value.eventId !== undefined && !publicUuid(value.eventId) || !Array.isArray(value.artistIds) || value.artistIds.length !== count || !value.artistIds.every(id => id === null || publicUuid(id)) || !Array.isArray(value.tours)) return false
  return value.tours.every(tour => record(tour) && text(tour.name) && tour.name.trim() && (tour.catalogId === null || publicUuid(tour.catalogId)))
}

// Saved demo data is an external input too.''')
once('      venueIds.add(venue.id)', "      if (venue.catalogId !== undefined && !publicUuid(venue.catalogId)) return null\n      if (venue.timezone !== undefined && venue.timezone !== null) { if (!text(venue.timezone)) return null; try { new Intl.DateTimeFormat('en', { timeZone: venue.timezone }) } catch { return null } }\n      venueIds.add(venue.id)")
once('|| !concert.artists.length || !concert.artists.every', '|| !concert.artists.every')
once("!/^([01]\\d|2[0-3]):[0-5]\\d$/.test(concert.time) || !text(concert.timezone)", "!/^([01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/.test(concert.time) || concert.timezone !== null && !text(concert.timezone)")
once("      try { new Intl.DateTimeFormat('en', { timeZone: concert.timezone }) } catch { return null }", "      if (concert.timezone !== null) { try { new Intl.DateTimeFormat('en', { timeZone: concert.timezone }) } catch { return null } }\n      if (!validCatalogRefs(concert.catalog, concert.artists.length) || concert.eventType !== undefined && (!text(concert.eventType) || !concert.eventType.trim())) return null")
once('        entryIds.add(entry.id)', "        if (entry.songCatalogId !== undefined && (entry.type !== 'song' || !publicUuid(entry.songCatalogId))) return null\n        if (entry.artistCatalogId !== undefined && (entry.type !== 'song' || !publicUuid(entry.artistCatalogId))) return null\n        entryIds.add(entry.id)")
p.write_text(s)
```

This preserves the version, storage key, corruption warning, and return-of-validated-data behavior. New optional fields survive reload. No date/countdown/map/reorder helper changes. The only loosened legacy constraints are valid catalog realities: empty lineup, null timezone, and second-precision local time. Names/titles, date validity, broken Venue references, coordinates, IDs, and unknown workspace versions remain guarded.

- [ ] **Step 3: Create `frontend/src/concertDraft.ts`.**

```ts
import { dateKey, TIMEZONE, validDate } from './domain.ts'
import type { CatalogChoice, Concert, EventDate, Venue } from './domain.ts'
import type { CatalogEvent, CatalogVenue } from './catalog.ts'
export type Precision = 'exact' | 'month' | 'year' | 'unknown'
export type ConcertDraft = { concert: Concert; artists: CatalogChoice[]; tours: CatalogChoice[]; eventId?: string; precision: Precision; dateText: string; timezoneText: string; pendingVenue?: Venue; venueName: string; city: string; lat: string; lng: string }
function dateInput(date: EventDate): { precision: Precision; dateText: string } {
  if (date.year === null) return { precision: 'unknown', dateText: '' }
  const year = String(date.year).padStart(4, '0')
  if (date.month === null) return { precision: 'year', dateText: year }
  if (date.day === null) return { precision: 'month', dateText: `${year}-${String(date.month).padStart(2, '0')}` }
  return { precision: 'exact', dateText: dateKey(date)! }
}
export function createConcertDraft(concert?: Concert, initialDate?: string): ConcertDraft {
  const base: Concert = concert ?? { id: crypto.randomUUID(), title: '', artists: [], venueId: null, date: { year: null, month: null, day: null }, time: '', timezone: TIMEZONE, interested: true, ticketsBought: false, notes: '', setlist: [] }
  const date = concert ? dateInput(concert.date) : { precision: 'exact' as const, dateText: initialDate ?? '' }
  return { concert: { ...base }, artists: base.artists.map((name, index) => ({ name, catalogId: base.catalog?.artistIds[index] ?? null })), tours: [...(base.catalog?.tours ?? [])], eventId: base.catalog?.eventId, ...date, timezoneText: base.timezone ?? '', venueName: '', city: 'Buenos Aires', lat: '', lng: '' }
}
export function adoptCatalogVenue(draft: ConcertDraft, venue: CatalogVenue, locals: Venue[]): ConcertDraft {
  const known = locals.find(local => local.catalogId === venue.id)
  const pending: Venue = { id: known?.id ?? crypto.randomUUID(), catalogId: venue.id, name: venue.name, city: venue.city ?? '', neighborhood: '', lat: venue.lat, lng: venue.lng, timezone: venue.timezone }
  return { ...draft, eventId: undefined, concert: { ...draft.concert, venueId: pending.id }, pendingVenue: pending }
}
export function adoptCatalogEvent(draft: ConcertDraft, event: CatalogEvent, locals: Venue[]): ConcertDraft {
  const withVenue = event.venue ? adoptCatalogVenue(draft, event.venue, locals) : { ...draft, concert: { ...draft.concert, venueId: null }, pendingVenue: undefined }
  return { ...withVenue, eventId: event.id, concert: { ...withVenue.concert, title: event.title, eventType: event.type, time: event.time ?? '' }, artists: event.artists.map(a => ({ name: a.name, catalogId: a.id })), tours: event.tours.map(t => ({ name: t.name, catalogId: t.id })), ...dateInput(event.date), timezoneText: event.timezone ?? '' }
}
export function finishConcertDraft(draft: ConcertDraft): { concert: Concert; venue?: Venue } {
  if (draft.artists.some(artist => !artist.name.trim())) throw new Error('Enter each artist name.')
  const artists = draft.artists.map(a => a.catalogId ? a.name : a.name.trim())
  const title = draft.eventId ? draft.concert.title : draft.concert.title.trim() || artists.join(' & ')
  if (!title) throw new Error('Enter a title or add an artist.')
  const parts = draft.dateText.split('-').map(Number)
  const date: EventDate = { year: draft.precision === 'unknown' ? null : parts[0], month: draft.precision === 'exact' || draft.precision === 'month' ? parts[1] : null, day: draft.precision === 'exact' ? parts[2] : null }
  if (!validDate(date)) throw new Error('Enter a valid date at the selected precision.')
  if (draft.concert.time !== '' && !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(draft.concert.time)) throw new Error('Enter a valid local time.')
  const timezone = draft.timezoneText.trim() || null
  if (timezone !== null) { try { new Intl.DateTimeFormat('en', { timeZone: timezone }) } catch { throw new Error('Enter a valid IANA timezone or leave it unknown.') } }
  let venue = draft.pendingVenue
  if (draft.concert.venueId === 'new') {
    if (!draft.venueName.trim()) throw new Error('Enter the venue name.')
    if ((draft.lat === '') !== (draft.lng === '') || draft.lat !== '' && (!Number.isFinite(Number(draft.lat)) || !Number.isFinite(Number(draft.lng)) || Math.abs(Number(draft.lat)) > 90 || Math.abs(Number(draft.lng)) > 180)) throw new Error('Supply both valid coordinates or leave both empty.')
    venue = { id: crypto.randomUUID(), name: draft.venueName.trim(), city: draft.city.trim(), neighborhood: '', lat: draft.lat === '' ? null : Number(draft.lat), lng: draft.lng === '' ? null : Number(draft.lng) }
  }
  return { concert: { ...draft.concert, title, artists, venueId: venue?.id ?? draft.concert.venueId, date, timezone, notes: draft.concert.notes.trim(), catalog: { ...(draft.eventId ? { eventId: draft.eventId } : {}), artistIds: draft.artists.map(a => a.catalogId), tours: draft.tours.map(t => ({ ...t })) } }, venue }
}
```

A confirmed Event fills only shared fields. It never loads someone else's Attendance or setlist, and it retains this draft's personal state. A Venue is staged until Save and reuses only a matching catalog UUID. Address details are selection context; the local map keeps its existing name/city/coordinate projection. New browser IDs are generated independently of catalog UUIDs.

- [ ] **Step 4: Create `frontend/src/components/CatalogChoices.tsx`.**

```tsx
import { useId, useState } from 'react'
import type { CatalogChoice } from '../domain'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
export function CatalogChoices({ kind, label, choices, onChange, catalog }: { kind: 'artists' | 'tours'; label: string; choices: CatalogChoice[]; onChange: (choices: CatalogChoice[]) => void; catalog: CatalogAccess }) {
  const id = useId(), [name, setName] = useState('')
  const add = (choice: CatalogChoice) => {
    if (choice.catalogId && choices.some(current => current.catalogId === choice.catalogId)) return
    onChange([...choices, choice]); setName('')
  }
  return <section className="catalog-choices" aria-label={label}><h3>{label}</h3><ul className="catalog-chips">{choices.map((choice, index) => <li className="catalog-chip" key={choice.catalogId ?? `manual-${index}`}><span>{choice.name}</span><button type="button" aria-label={`Remove ${choice.name}, item ${index + 1}`} onClick={() => onChange(choices.filter((_, position) => position !== index))}>×</button></li>)}</ul>
    <CatalogPicker catalog={catalog} kind={kind} label={`Search ${label.toLowerCase()}`} onPick={row => add({ name: row.name, catalogId: row.id })} />
    <div className="catalog-manual"><label htmlFor={id}>Enter a name manually<input id={id} maxLength={255} value={name} onChange={event => setName(event.target.value)} onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); if (name.trim()) add({ name: name.trim(), catalogId: null }) } }} /></label><button type="button" className="button secondary small" disabled={!name.trim()} onClick={() => add({ name: name.trim(), catalogId: null })}>Add name</button></div>
  </section>
}
```

Artist/Tour names are individual values, so commas do not split a catalog name. The same UUID cannot be added twice, but equal names with different UUIDs remain distinct. Manual names have no inferred identity. Existing comma-separated artist input is replaced by named chips plus explicit manual add; all old saved names are loaded individually.

- [ ] **Step 5: Replace `frontend/src/components/ConcertForm.tsx` with the integrated editor.**

```tsx
import { useState } from 'react'
import type { FormEvent } from 'react'
import type { Concert, Venue } from '../domain'
import { adoptCatalogEvent, adoptCatalogVenue, createConcertDraft, finishConcertDraft } from '../concertDraft'
import type { ConcertDraft, Precision } from '../concertDraft'
import { Dialog } from './Dialog'
import { Icon } from './Icon'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
import { CatalogChoices } from './CatalogChoices'
import { EventCatalogPicker } from './EventCatalogPicker'
export function ConcertForm({ concert, venues, initialDate, catalog, onClose, onSave }: { concert?: Concert; venues: Venue[]; initialDate?: string; catalog: CatalogAccess; onClose: () => void; onSave: (concert: Concert, venue?: Venue) => void }) {
  const [draft, setDraft] = useState(() => createConcertDraft(concert, initialDate))
  const [error, setError] = useState('')
  const shared = (patch: Partial<ConcertDraft>) => setDraft(current => ({ ...current, ...patch, eventId: undefined }))
  const field = <K extends keyof Concert,>(key: K, value: Concert[K], detach = true) => setDraft(current => ({ ...current, concert: { ...current.concert, [key]: value }, eventId: detach ? undefined : current.eventId }))
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    try { const result = finishConcertDraft(draft); onSave(result.concert, result.venue) }
    catch (failure) { setError(failure instanceof Error ? failure.message : 'Check the draft fields.') }
  }
  const pending = draft.pendingVenue
  const stagedVenues = pending ? venues.some(venue => venue.id === pending.id) ? venues.map(venue => venue.id === pending.id ? pending : venue) : [...venues, pending] : venues
  return <Dialog title={concert ? 'Edit concert' : 'Add a concert'} onClose={onClose} drawer><form onSubmit={submit} noValidate className="concert-form"><div className="form-body"><p className="muted">Choose shared details or enter your own.</p>
    <EventCatalogPicker catalog={catalog} onUse={event => setDraft(current => adoptCatalogEvent(current, event, venues))} />
    {draft.eventId && <p className="hint">Using selected Event details. Editing shared fields makes a separate local draft.</p>}
    <label>Concert title<input data-autofocus maxLength={255} value={draft.concert.title} onChange={event => field('title', event.target.value)} placeholder="Title, or use the artist names" /></label>
    <CatalogChoices kind="artists" label="Artists" choices={draft.artists} onChange={artists => shared({ artists })} catalog={catalog} />
    <CatalogPicker catalog={catalog} kind="venues" label="Search shared Venues" onPick={venue => setDraft(current => adoptCatalogVenue(current, venue, venues))} />
    <label>Venue<select value={draft.concert.venueId ?? ''} onChange={event => { shared({ pendingVenue: undefined, lat: '', lng: '' }); field('venueId', event.target.value || null) }}><option value="">Venue to confirm</option>{stagedVenues.map(venue => <option key={venue.id} value={venue.id}>{venue.name} · {venue.city || 'City to confirm'}</option>)}<option value="new">+ Add a venue…</option></select></label>
    {draft.concert.venueId === 'new' && <div className="new-venue-fields"><label>Venue name<input maxLength={150} value={draft.venueName} onChange={event => shared({ venueName: event.target.value })} /></label><label>City<input maxLength={100} value={draft.city} onChange={event => shared({ city: event.target.value })} /></label><details><summary>Map location <span className="optional">optional</span></summary><p className="hint">Leave unknown coordinates empty.</p><div className="form-columns"><label>Latitude<input type="number" step="any" min="-90" max="90" value={draft.lat} onChange={event => shared({ lat: event.target.value })} /></label><label>Longitude<input type="number" step="any" min="-180" max="180" value={draft.lng} onChange={event => shared({ lng: event.target.value })} /></label></div></details></div>}
    <CatalogChoices kind="tours" label="Tours (optional)" choices={draft.tours} onChange={tours => shared({ tours })} catalog={catalog} />
    <label>Date precision<select value={draft.precision} onChange={event => { const precision = event.target.value as Precision; setDraft(current => ({ ...current, precision, eventId: undefined, dateText: precision === 'year' ? current.dateText.slice(0, 4) : precision === 'month' && current.precision === 'exact' ? current.dateText.slice(0, 7) : precision === current.precision ? current.dateText : '' })); setError('') }}><option value="exact">Exact date</option><option value="month">Month only</option><option value="year">Year only</option><option value="unknown">Date to confirm</option></select></label>
    <div className="form-columns">{draft.precision !== 'unknown' && <label>{draft.precision === 'exact' ? 'Date' : draft.precision === 'month' ? 'Month' : 'Year'}<input type={draft.precision === 'exact' ? 'date' : draft.precision === 'month' ? 'month' : 'number'} min={draft.precision === 'year' ? '1' : draft.precision === 'month' ? '0001-01' : '0001-01-01'} max={draft.precision === 'year' ? '9999' : draft.precision === 'month' ? '9999-12' : '9999-12-31'} value={draft.dateText} onChange={event => shared({ dateText: event.target.value })} /></label>}<label>Local time <span className="optional">optional</span><input type="time" step={1} value={draft.concert.time} onChange={event => field('time', event.target.value)} /></label></div>
    <label>Timezone <span className="optional">optional</span><input value={draft.timezoneText} onChange={event => shared({ timezoneText: event.target.value })} placeholder="Unknown, or an IANA timezone" /><small>Leave unknown values empty. Shared Event timezones are kept as returned.</small></label>
    <div className="flag-fields"><label><input type="checkbox" checked={draft.concert.interested} onChange={event => field('interested', event.target.checked, false)} /><Icon name="heart" size={18} />Interested</label><label><input type="checkbox" checked={draft.concert.ticketsBought} onChange={event => field('ticketsBought', event.target.checked, false)} /><Icon name="ticket" size={18} />Tickets bought</label></div>
    <label>Notes <span className="optional">optional</span><textarea rows={3} maxLength={2000} value={draft.concert.notes} onChange={event => field('notes', event.target.value, false)} /></label>
    <p className="hint">Saved in this browser only, shared across sign-ins. Saving does not create or update shared catalog records.</p>{error && <p className="form-error" role="alert">{error}</p>}
  </div><div className="form-footer"><button type="submit" className="button primary"><Icon name="plus" />Save concert</button><button type="button" className="button secondary" onClick={onClose}>Cancel</button></div></form></Dialog>
}
```

Reducing date precision retains known year/month components. Requesting finer unknown precision leaves the input blank rather than inventing January 1. Cancelling closes the draft without writing staged Venues or references. Unknown lineup is valid when a title is provided; no fake Artist is added.

- [ ] **Step 6: Run pure draft tests; finish this task’s App wiring before its final UI gate.**

```sh
node --test tests/catalog-api.test.mjs tests/catalog-draft.test.mjs tests/domain.test.mjs
```

This task includes the remaining editor/App wiring in Steps 7–10. Complete those steps before the UI build or review handoff; do not leave an intermediate unbuildable delivery.

- [ ] **Step 7: Add setlist draft identity handling to the same draft module.**

Use this complete import in `frontend/tests/catalog-draft.test.mjs`:

```js
import { createConcertDraft, adoptCatalogEvent, adoptCatalogVenue, finishConcertDraft, changeSetlistEntry } from '../src/concertDraft.ts'
```

Append this regression:

```js
test('Song selection never infers a performer and manual edits detach only the changed identity', () => {
  const entry = { id: 'local-entry', type: 'song', title: 'Original', artist: 'My performer', tape: false }
  const picked = changeSetlistEntry(entry, { title: 'Canción', songCatalogId: id(4) })
  assert.equal(picked.artist, 'My performer')
  const performer = changeSetlistEntry(picked, { artist: 'Björk', artistCatalogId: id(1) })
  assert.equal(performer.songCatalogId, id(4))
  assert.equal(changeSetlistEntry(performer, { title: 'My version' }).songCatalogId, undefined)
  assert.equal(changeSetlistEntry(performer, { artist: 'Someone else' }).artistCatalogId, undefined)
  const note = changeSetlistEntry(performer, { type: 'note' })
  assert.equal(note.songCatalogId, undefined); assert.equal(note.artistCatalogId, undefined)
})
```

Run `node --test tests/catalog-draft.test.mjs` and observe the missing `changeSetlistEntry` export. Then implement the helper:

Replace the draft module's type import with:

```ts
import type { CatalogChoice, Concert, EventDate, SetlistEntry, Venue } from './domain.ts'
```

Then append:

```ts
export function changeSetlistEntry(entry: SetlistEntry, patch: Partial<SetlistEntry>): SetlistEntry {
  const next = { ...entry, ...patch }
  if (patch.title !== undefined && patch.title !== entry.title && patch.songCatalogId === undefined) delete next.songCatalogId
  if (patch.artist !== undefined && patch.artist !== entry.artist && patch.artistCatalogId === undefined) delete next.artistCatalogId
  if (next.type === 'note') { delete next.songCatalogId; delete next.artistCatalogId }
  return next
}
```

Rerun `node --test tests/catalog-draft.test.mjs` and require the identity-detachment regression to pass.

- [ ] **Step 8: Create `frontend/src/components/SetlistEditor.tsx` and connect it in `SetlistsView.tsx`.**

```tsx
import { useState } from 'react'
import type { FormEvent } from 'react'
import type { SetlistEntry } from '../domain'
import { changeSetlistEntry } from '../concertDraft'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
import { Icon } from './Icon'
export function SetlistEditor({ entry, catalog, onSave, onCancel }: { entry: SetlistEntry; catalog: CatalogAccess; onSave: (entry: SetlistEntry) => void; onCancel: () => void }) {
  const [draft, setDraft] = useState(entry)
  const change = (patch: Partial<SetlistEntry>) => setDraft(current => changeSetlistEntry(current, patch))
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!draft.title.trim()) return
    onSave({ ...draft, title: draft.songCatalogId ? draft.title : draft.title.trim(), artist: draft.artistCatalogId ? draft.artist : draft.artist.trim() })
  }
  return <form className="setlist-editor" onSubmit={submit}><div className="entry-editor-top"><label>Entry type<select value={draft.type} onChange={event => change({ type: event.target.value as 'song' | 'note' })}><option value="song">Song</option><option value="note">Note / section</option></select></label><label className="tape-checkbox"><input type="checkbox" checked={draft.tape} onChange={event => change({ tape: event.target.checked })} /><Icon name="tape" size={17} />Tape / playback</label></div>
    {draft.type === 'song' && <CatalogPicker catalog={catalog} kind="songs" label="Find an existing Song" onPick={song => change({ title: song.title, songCatalogId: song.id })} />}
    <div className="entry-editor-bottom"><label>{draft.type === 'song' ? 'Song title' : 'Note'}<input autoFocus required maxLength={300} value={draft.title} onChange={event => change({ title: event.target.value })} /></label>{draft.type === 'song' && <label>Performer<input maxLength={255} value={draft.artist} onChange={event => change({ artist: event.target.value })} /></label>}<div className="entry-save-actions"><button className="button primary small" type="submit">Save entry</button><button className="button secondary small" type="button" onClick={onCancel}>Cancel</button></div></div>
    {draft.type === 'song' && <CatalogPicker catalog={catalog} kind="artists" label="Search a performer" onPick={artist => change({ artist: artist.name, artistCatalogId: artist.id })} />}
    <p className="hint">Saved in this browser only. A Song selection does not identify its performer.</p>
  </form>
}
```

Apply these guarded edits from the repository root:

```python
from pathlib import Path
p = Path('frontend/src/components/SetlistsView.tsx')
s = p.read_text()
s = s.replace("import type { FormEvent } from 'react'\n", '')
s = s.replace("import { Icon } from './Icon'", "import { Icon } from './Icon'\nimport { SetlistEditor } from './SetlistEditor'\nimport type { CatalogAccess } from './CatalogPicker'")
assert s.count('selectedId, onSelect, onUpdate') == 1
s = s.replace('selectedId, onSelect, onUpdate', 'selectedId, onSelect, onUpdate, catalog', 1)
assert s.count('selectedId: string; onSelect:') == 1
s = s.replace('selectedId: string; onSelect:', 'selectedId: string; catalog: CatalogAccess; onSelect:', 1)
start = s.index('  const save = (event:')
end = s.index('  const add =', start)
s = s[:start] + s[end:]
s = s.replace("type === 'song' ? concert.artists[0] : ''", "type === 'song' ? concert.artists[0] ?? '' : ''")
start = s.index('  const editor = editing &&')
end = s.index('\n  return <section', start)
s = s[:start] + '''  const editor = editing && <SetlistEditor key={`${concert.id}:${editing.id}`} entry={editing} catalog={catalog} onCancel={() => setEditing(null)} onSave={entry => {
    onUpdate({ ...concert, setlist: concert.setlist.some(current => current.id === entry.id) ? concert.setlist.map(current => current.id === entry.id ? entry : current) : [...concert.setlist, entry] }, 'Setlist saved in this browser.')
    setEditing(null)
  }} />''' + s[end:]
p.write_text(s)
```

This keeps the existing single performer slot, repeated songs, notes/tape state, entry IDs, reorder/delete/Undo, and concert selection. It adds catalog identity to that existing performer slot without introducing a new collaborative setlist model.

- [ ] **Step 9: Add draft-preserving expiry handling and wire the App.**

Apply this exact script from the repository root. It extends the existing hook with one stable method and supplies catalog props without remounting a dirty editor on 401:

```python
from pathlib import Path
p = Path('frontend/src/useAccount.ts')
s = p.read_text().replace("import { useEffect, useRef, useState }", "import { useCallback, useEffect, useRef, useState }")
anchor = '  return {\n    ...snapshot, busy,'
assert s.count(anchor) == 1
s = s.replace(anchor, '''  const expireSession = useCallback((): boolean => {
    if (busyRef.current) return false
    ++generation.current; startup.current?.abort()
    setSnapshot({ status: 'guest', user: null, error: 'Your session expired. Sign in again.' })
    return true
  }, [])
  return {
    ...snapshot, busy, expireSession,''', 1)
p.write_text(s)
p = Path('frontend/src/App.tsx')
s = p.read_text()
def once(old, new):
    global s
    assert s.count(old) == 1, old[:100]
    s = s.replace(old, new, 1)
once("import { useEffect, useState }", "import { useCallback, useEffect, useState }")
once("import { useAccount } from './useAccount'", "import { useAccount } from './useAccount'\nimport { createCatalogClient } from './catalogApi'\nimport type { CatalogAccess } from './components/CatalogPicker'")
once("import './account.css'", "import './account.css'\nimport './catalog.css'")
once('const accountClient = createAccountClient(apiBaseUrl)', 'const accountClient = createAccountClient(apiBaseUrl)\nconst catalogClient = createCatalogClient(apiBaseUrl)')
once("  useEffect(() => {\n    const timer = setInterval", '''  const expireSession = account.expireSession
  const expireCatalogSession = useCallback(() => {
    if (!expireSession()) return
    setExploringDemo(true); setAuthView(null)
    setAccountNotice('Your session expired. Catalog search paused. You can still save this browser draft before signing in.')
  }, [expireSession])
  const catalog: CatalogAccess = { client: catalogClient, enabled: account.status === 'authenticated' && Boolean(account.user), sessionKey: account.user?.id ?? 'guest', onUnauthorized: expireCatalogSession }

  useEffect(() => {
    const timer = setInterval''')
once('venues: venue ? [...workspace.venues, venue] : workspace.venues', 'venues: venue ? workspace.venues.some(current => current.id === venue.id) ? workspace.venues.map(current => current.id === venue.id ? venue : current) : [...workspace.venues, venue] : workspace.venues')
once('<SetlistsView key={setlistId}', '<SetlistsView key={setlistId} catalog={catalog}')
once('<ConcertForm concert={form.concert}', '<ConcertForm catalog={catalog} concert={form.concert}')
p.write_text(s)
```

Apply the small display fallbacks required by empty lineup/unknown city data:

```python
from pathlib import Path
p = Path('frontend/src/components/ConcertList.tsx')
s = p.read_text()
assert s.count(": concert.artists.join(' · ')") == 1
s = s.replace(": concert.artists.join(' · ')", ": concert.artists.join(' · ') || 'Lineup to confirm'", 1)
s = s.replace("venue?.city ?? 'Location to confirm'", "venue?.city || 'Location to confirm'")
p.write_text(s)
p = Path('frontend/src/App.tsx')
s = p.read_text()
assert s.count("{detail.artists.join(' · ')}") == 1
s = s.replace("{detail.artists.join(' · ')}", "{detail.artists.join(' · ') || 'Lineup to confirm'}", 1)
s = s.replace("?.city ?? 'Location to confirm'", "?.city || 'Location to confirm'")
p.write_text(s)
p = Path('frontend/src/components/SetlistsView.tsx')
s = p.read_text()
s = s.replace("entry.artist || concert.artists.join(' · ')", "entry.artist || concert.artists.join(' · ') || 'Performer to confirm'")
p.write_text(s)
```

Only the current live picker invokes expiry; its latest-read guard suppresses stale failures. The hook does not invalidate a newer in-flight account mutation. On a real catalog 401 the App becomes guest/Explore without changing the current page, closing the drawer, or clearing the editor. All catalog requests stop and results disappear; manual inputs remain mounted. Existing login/logout/profile behavior remains unchanged. The user can save/cancel the local draft before explicitly entering sign-in. Do not force a login redirect that discards input.

- [ ] **Step 10: Complete the cohesive UI gate.**

```sh
node --test tests/catalog-api.test.mjs tests/catalog-draft.test.mjs tests/domain.test.mjs tests/account-api.test.mjs tests/account-contract.test.mjs
npm run lint
npm run build
npm test
```

Expect all old and new tests to pass. Build precedes `npm test` because production-server tests use `dist/`. Reference fields must survive edit/reopen and existing view mutations, while new drafts remain manual when catalog access is disabled. Use the next task's markup/browser checks before reporting user-visible acceptance.

### Task 4: Browser Acceptance and Documentation

**Files:** Verify the editor markup tests created in Task 3; update account/catalog paragraphs in `frontend/README.md` and `README.md` only. During implementation, record browser evidence in `frontend/design/qa/catalog-integration-verification.md`; do not create a passing QA record during planning.

**Interfaces:** Consumes the integrated editors and actual authenticated read API. Produces regression evidence for manual/guest/selection UI and real-browser acceptance. Static rendering proves markup only; it cannot prove cookies, effects, focus, or request races.

- [ ] **Step 1: Replace outdated frontend catalog boundary copy with this documentation.**

Append to `frontend/README.md` (or replace an existing equivalent section):

```markdown
## Shared catalog selection

Signed-in users can search existing Events, Artists, Venues, Songs, and Tours from the concert/setlist editors. Choosing an Event fetches fresh details and asks before replacing shared draft fields. Personal flags, notes, and setlists remain in the draft. Manual input works for guests and when searches fail.

Concerts and setlists are still saved only in this browser under `spectacles.preview.v1`, shared across sign-ins. Optional public catalog UUID references are saved with selected values; this does not create or update shared records or an Attendance. Older saved workspaces remain supported. Editing copied Event facts detaches its Event reference. Editing a selected song or performer name detaches that field's reference. Duplicate catalog names remain distinct; names containing commas remain single choices.

Tours are optional, with independent manual/catalog selection. A Tour roster does not populate the Event lineup. Song selection does not identify a performer. Unknown dates, timezone, Venue, and lineup remain unknown; local time supports seconds.

Catalog requests use authenticated cookie GETs to `/api/v1`, with no bearer tokens, GET bodies, CSRF mutation, provider calls, or private Attendance data. A session-expiry response stops catalog lookup and exposes the guest context while preserving the current local draft. Save or cancel it before entering sign-in.
```

In the root README's Shared catalog reads section, replace only the now-stale sentence about no frontend Tour control:

```python
from pathlib import Path
p = Path('README.md')
s = p.read_text()
s = s.replace('No create/update/delete catalog endpoints or frontend Tour control are included.', 'No create/update/delete catalog endpoints are included. The frontend offers optional catalog selection, including Tours, into browser-local drafts; see frontend/README.md.')
p.write_text(s)
```

Keep the current API query/auth documentation and unrelated local setup edits.

- [ ] **Step 2: Run the frontend gates.**

```sh
node --test tests/catalog-api.test.mjs tests/catalog-draft.test.mjs tests/catalog-ui.test.mjs
npm run lint
npm run build
npm test
```

- [ ] **Step 3: Start disposable services for real-browser acceptance during implementation.**

Read the current PHP runtime prefixes first. The commands below use the plain `php` prefix currently configured. Use clean browser storage and a new temporary SQLite database, not the shared developer service/data. If these ports are occupied, stop and choose unused temporary ports consistently; do not kill an existing server.

Backend terminal from repository root:

```sh
catalog_qa_dir=$(mktemp -d "${TMPDIR:-/tmp}/spectacles-catalog-qa.XXXXXX")
touch "$catalog_qa_dir/database.sqlite"
export APP_ENV=local APP_DEBUG=false APP_URL=http://localhost:8097
export APP_CONFIG_CACHE="$catalog_qa_dir/config.php" APP_ROUTES_CACHE="$catalog_qa_dir/routes.php"
export DB_URL= DB_CONNECTION=sqlite DB_DATABASE="$catalog_qa_dir/database.sqlite" DB_FOREIGN_KEYS=true
export SESSION_DRIVER=database SESSION_CONNECTION=sqlite SESSION_DOMAIN=null SESSION_SECURE_COOKIE=false SESSION_COOKIE=spectacles_catalog_qa_session
export CACHE_STORE=database DB_CACHE_CONNECTION=sqlite DB_CACHE_LOCK_CONNECTION=sqlite QUEUE_CONNECTION=sync
export SANCTUM_STATEFUL_DOMAINS=localhost:5179 FRONTEND_URLS=http://localhost:5179
cd backend
php artisan migrate --force --no-interaction
php artisan db:seed --class=LiveEventSeeder --no-interaction
php artisan serve --host=localhost --port=8097 --no-interaction
```

The existing seeder supplies Sample Concert (year only, unknown time/timezone), Sample Artist, Sample Venue, Sample Song, and Sample Tour, plus unrelated private fake Attendance data. No new backend fixture code is needed. Read the current runtime instructions again if these commands run on another host.

Frontend terminal from repository root:

```sh
cd frontend
DEV_SERVER_URL= VITE_API_BASE_URL=http://localhost:8097 npm run dev -- --host=localhost --port=5179
```

Use `http://localhost:5179` and create only a disposable test user through the existing registration UI: `catalog-qa@example.com` / `catalog-test-password-123`. Those credentials are fixture constants for the temporary database. Do not use a real user's credentials or record cookie values. Stop temporary servers with Ctrl-C after QA and remove only their verified temporary directory when its evidence is no longer needed.

- [ ] **Step 4: Record each browser case with actual expected/observed UI and requests.**

| Case | Procedure | Required outcome |
| --- | --- | --- |
| Guest/manual | Explore without signing in, add a manual concert, new Venue, Artist/Tour names, and song/note. | No catalog requests; manual saves and existing Undo/reorder work. Sign-in explanations and browser-local labels are visible. |
| All six reads | Register fixture user. Search Sample Concert; choose it, inspect detail, then use details. Search Sample Artist, Sample Venue, Sample Tour, and Sample Song in their controls. | Network shows only the five documented list GETs and Event detail; cookies and Accept included, no bodies/CSRF calls/bearer header. All lookup types have a reachable UI use. |
| Confirmation/cancel | Type local notes/flags and a draft title. Choose a search result, dismiss its preview, then confirm on a second attempt. Cancel the whole editor once. | Dismiss leaves all fields untouched. Confirm replaces only shared fields and retains personal inputs/setlist. Cancel writes no staged Venue/reference/workspace change. |
| Identity/reopen | Save confirmed selections, reload, edit/reopen. Change the title or lineup; separately change a selected Song/performer text. | UUIDs remain separate from local IDs and survive reload. Shared Event edits detach Event ID; Song/performer edits detach only their own IDs. Equal names are not automatically merged. |
| Venue reuse | Select the same shared Venue for two local concerts, and compare with an equal-name manual Venue. | One catalog-referenced local Venue is reused by UUID; the manual Venue remains independent. New coordinates are not invented; missing/out-of-map locations stay in the existing directory. |
| Partial/unknown facts | Adopt Sample Concert, save/reopen; run the pure fixture tests for empty lineup, unknown date, null Venue, zero coordinates and seconds. | Year-only remains year-only, unknown timezone/time stays empty/null. No false Artist, January 1, UTC instant, or zero-to-null conversion. Pure fixture results are recorded as automated evidence, not mislabeled as browser coverage. |
| Independent choices | Select Sample Tour and a Song while a manually chosen performer is present. | Tour does not change lineup; Song does not change performer. A note offers no Song/performer picker or catalog IDs. Existing repeated-song/tape/reorder behavior is retained. |
| Rapid/cancelled reads | Throttle network; type several searches, change page, dismiss results/drawer during a request, and reopen. | Only newest applicable results appear. Aborted/late responses publish nothing; results never replace a draft automatically. No state update after unmount. Latest-read automated tests also pass. |
| Pagination | Use fixture/mock pages in client tests and enough seeded catalog rows if available in the disposable database. Inspect requested page/filter values. | Changing query resets page to 1. Next/Previous retain filters, use fixed API origin and correct disabled bounds. Report UI pagination as unverified if a second real/overridden page is not exercised. |
| Offline/errors | Use browser offline mode for a search, reconnect and retry; use client tests for 404/422/429/HTML/5xx. | Inline safe error and manual fallback; unsaved values survive. No automatic mutation/retry and no raw debug HTML. Event-detail 404 does not apply a stale selection. |
| Session expiry | Keep a dirty concert drawer and separately a dirty setlist editor. Delete only temporary session cookies, then search. | 401 transitions account menu to guest and stops all catalog searches without closing/dropping the draft. Local Save/Cancel remains usable. Explicit sign-in restores lookup after the ordinary account flow. |
| Account races | Start a search, navigate to profile or begin logout before its delayed response. Run existing account tests as well. | An obsolete 401 cannot replace a newer session or invalidate a pending account mutation; no stuck account busy state. Logout failure/success behavior remains truthful. |
| Keyboard/layout | At 1280 px and 390 px, Tab through search/results/chips/editor controls; use Enter/Space/Escape and drawer close. | Enter searches rather than saving the parent form, result selection restores useful focus, first Escape dismisses results, drawer keyboard behavior remains, controls fit and meet 44 px targets. No horizontal overflow or nested forms. |
| Existing collection | Use dashboard/calendar/map/setlists after saves, then logout/login another disposable fixture user without editing concerts. | Data remains clearly browser-local and shared across sign-ins. No catalog/Attendance write request, setlist prefill, provider lookup, or account identity persistence appears. |

Screenshots should show the existing visual language with new inline pickers, Event preview, Artist/Tour chips, Song/performer lookup, guest fallback, and expired-session draft preservation. Keep QA artifacts separate from product source and record actual browser/runtime/viewport, commands, test counts, HTTP statuses, temporary DB path, pass/fail rows, and limitations. Do not include credentials, cookies, raw auth headers, or private fixture setlists in screenshots/logs.

- [ ] **Step 5: Review final scope and report only actual verification.**

```sh
git diff --check
git status --short
git diff -- frontend/src frontend/tests frontend/README.md README.md
```

Inspect new untracked product files explicitly as well. Preserve unrelated configuration and QA/design/planning files. No backend or package/lockfile diff belongs to this frontend integration. End with changes, automated/browser checks actually run, and any remaining acceptance gaps. Do not commit/push/open PRs/deploy without separate authorization.

## Source References and Self-Review

The current frontend components/domain/account transport, existing tests and visual specification, backend routes/request/controller/resources, and the existing `LiveEventSeeder` were inspected. [React effect cleanup guidance](https://react.dev/reference/react/useEffect#fetching-data-with-effects), [Fetch credentials/cancellation](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API/Using_Fetch), and [URLSearchParams](https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams) support the request lifecycle/encoding approach. Installed project source remains authoritative for the current API contract.

- All six reads map to UI controls or Event detail confirmation; Artist selection serves both lineup and the existing performer slot.
- UUID storage and optional Tour control were explicitly selected by the user. Neither changes local record IDs, API contracts, or shared persistence.
- Partial/unknown facts are represented without invention; compatibility changes are optional metadata plus valid empty lineup/null timezone/seconds support.
- Manual entry and personal draft state are retained, including session expiry. Shared Event facts change only on explicit adoption, with reference detachment on later edits.
- Existing account mutation semantics, demo storage protection, navigation, map, calendar, setlist ordering, and Undo remain in scope for preservation, not redesign.
- The five Review Focus risks have decoder/client/latest-read/draft tests plus explicit browser acceptance rows; markup checks are not claimed to prove effects or focus.
- This document is the only product artifact created in this planning turn. There is no remaining blocking product decision. Implementation and real-browser acceptance remain future work.

## Planning Verification

The main-agent self-review checked endpoint/UI coverage, accepted choices, file/interface consistency, placeholders, and all five Review Focus risks. The written source and test examples were extracted into a disposable frontend copy outside this repository: all **38 tests passed**, TypeScript project checks passed, and lint passed with zero warnings for its source/tests. That copy was used only to validate the plan examples. Application source in this checkout was not changed. No API service, database fixture, or real-browser acceptance was run; those checks remain explicit implementation steps.
