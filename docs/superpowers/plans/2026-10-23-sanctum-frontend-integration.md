# Sanctum Frontend Account Integration Implementation Plan

> **Execution:** Follow the user-selected execution method and repository requirements. This plan does not require any additional skill. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connect the existing React login, registration, profile, and account menu to the five implemented Sanctum endpoints, including session restoration and real logout.

**Architecture:** Keep React and Laravel separate. Add one small `fetch` client for the existing cookie/CSRF contract and one React hook for in-memory account state; adapt the existing account components and their App callbacks. Keep the concert workspace in its existing browser-local demo storage, with explicit demo labels and no account data or credentials persisted in browser storage.

**Tech Stack:** Node 24 (verified 24.22.1), React 19, TypeScript 6, Vite 8, native Fetch/AbortController, Node's existing test runner, and the installed React server renderer/Vite SSR loader for component markup tests. Existing Laravel 13/Sanctum 4.3.3 backend; no new dependencies.

**Spec:** `docs/superpowers/plans/2026-10-23-sanctum-account-endpoints.md` supplies the accepted backend contract. `frontend/design/account/visual-spec.md` supplies the existing layout, assets, and keyboard behavior. The user's correction explicitly requests the **frontend integration plan**. The original visual document's local-only credential behavior is superseded for authentication; its generated references remain historical design artifacts. The confirmed persisted profile scope remains **name, email, locale only**.

## Global Constraints

- “The browser calls Laravel directly over HTTP.” Preserve the separate React SPA and API; no Inertia, Express auth proxy, new backend endpoints, or backend reimplementation.
- “Photo selection stays local, supports PNG/JPEG/WebP up to 2 MiB, and can be replaced with initials.” This is historical preview behavior, not permission to add uploads: authenticated account UI uses initials and exposes no photo/color/city/bio editors because the approved API cannot persist them.
- “Keyboard controls include opening, item navigation, Escape with focus return, and closing on outside click or focus leaving the menu.” Preserve these controls.
- “Signing out leaves the concert collection intact.” Preserve `spectacles.preview.v1`, saved concerts, unknown saved-data handling, dates, setlists, map, and Undo semantics.
- “No real authentication, account creation, verification, password reset, or backend profile API is introduced.” This boundary belongs to the old preview specification: only its authentication/profile exclusion is superseded by this integration request. Verification/reset/photos remain outside scope.
- Node 24 is required. Use the installed `react`, `react-dom`, `vite`, and Node test runner. Add no package dependency or lockfile changes for this integration.
- New account calls use the configured `VITE_API_BASE_URL` plus `/api/v1`; CSRF initialization uses `/sanctum/csrf-cookie` at the backend root. Keep `/api/health` separate and session-free.
- Every account request includes `credentials: 'include'` and `Accept: application/json`. Before each mutation, get a fresh CSRF cookie and send its decoded `XSRF-TOKEN` as `X-XSRF-TOKEN`. Never issue or store a bearer token.
- Session cookies are the backend's authority. Neither the legacy profile key nor the legacy entry key grants authentication. Leave their stored bytes untouched, stop reading/writing them, and do not migrate a preview identity into an account.
- Keep passwords only in form/request memory. Never put them in URLs, browser storage, errors, logs, analytics, or toast text. Clear them after successful submission and on form unmount/switch.
- Registration requires 12 Unicode characters, confirmation, no NUL, and at most 72 UTF-8 bytes. Login accepts an existing shorter password; it requires a nonempty string within the same byte/NUL ceiling. Match the current backend, not the old preview's 8–128 character rule.
- Use only server-returned UUID/name/email/locale for account identity. The UI does not claim that the demo concert collection is private, account-specific, or stored on the server: show “Demo collection · saved in this browser, shared across sign-ins.”
- A failed logout must not look successful. Treat 204 and already-unauthenticated 401 as signed out; keep the signed-in identity and show an error for network/419/5xx failures. Do not automatically replay credential submissions or profile mutations.
- Preserve the paper/ink/lime/lavender layout, existing concert photograph, responsive behavior, labels, input associations, password visibility, focus handling, and reduced-motion support. No redesign or new image generation.
- This turn writes only this plan. Execution, commits, pushes, additional chats, deployment, environment changes, migrations, paid calls, and new real accounts are not authorized by writing it. At execution, ask before creating an isolated worktree; a worktree does not isolate services or databases.

## Review Focus

- Successful-looking demo login must disappear: invalid credentials cannot enter an authenticated UI; reload must restore only a server-confirmed session. Pin with Task 1's HTTP-client tests, Task 2's startup markup test, and Task 3's browser rows 1–4.
- Encoded/rotated/missing CSRF cookies, 204 bodies, 401/419/422/429 responses, and HTML/network failures must have bounded, truthful outcomes. Pin in Task 1's client tests and Task 3's browser rows 5–8.
- Duplicate submissions and late startup responses must not change identity after a newer session action or unmount. Pin with the hook's request generation/abort guards and Task 3's browser row 9; markup tests alone do not prove this.
- Unsupported profile data and stale local identities must never overwrite server identity; a rejected save retains the draft, while Cancel makes no mutation. Pin in Tasks 1 and 2's allowlist/markup tests and Task 3's browser rows 10–12.
- Offline/failed logout must retain a truthful signed-in state, and signing out or switching accounts must preserve the clearly labelled demo collection. Pin in Task 3's browser rows 6, 8, 12–14; retain the existing domain/server tests.

---

## Repository Evidence and File Structure

Current branch: `feat/live-event-data-layer`; backend account implementation is commit `6ea9030`. `frontend/src/App.tsx:47-60` reads a local profile and entry preference; `:124-153` stores the preview profile and performs a local-only sign-out. `AuthView.tsx` explicitly discards passwords and opens the demo. `ProfileView.tsx` currently edits unsupported city/bio/photo/color values. The backend already exposes all five routes and returns JSON:API `users` with UUID `id` and only name/email/locale attributes.

| Path | Action and responsibility |
| --- | --- |
| `frontend/src/account.ts` | Retain the existing Unicode initials helper; remove unused preview persistence helpers after UI conversion. |
| `frontend/src/accountApi.ts` | Create account protocol types/validation and native cookie/CSRF client; injectable fetch/cookie access for focused tests. |
| `frontend/src/useAccount.ts` | Create React session bootstrap and guarded account mutations. |
| `frontend/src/components/AuthView.tsx` | Adapt the existing forms to asynchronous real login/registration. |
| `frontend/src/components/AccountMenu.tsx` | Real identity/actions, guest entry actions, existing keyboard behavior. |
| `frontend/src/components/Avatar.tsx` | Initials only, based on current server identity or explicit Demo label. |
| `frontend/src/components/ProfileView.tsx` | Supported fields only; server saves, errors, pending and Cancel. |
| `frontend/src/App.tsx` | Wire hook and actions; replace account storage authority, preserve all concert logic. |
| `frontend/src/account.css` | Small additions for pending/gate/error/select states. |
| `frontend/tests/account.test.mjs` | Replace obsolete preview-account tests with static-render account UI regressions using installed Vite/React. |
| `frontend/tests/account-contract.test.mjs` | Create account identity/validation/patch-selection tests. |
| `frontend/tests/account-api.test.mjs` | Create cookie/request/error/allowlist tests with fake responses. |
| `frontend/README.md`, `README.md` | Update account behavior and cookie prerequisites only. |

Run Node commands from `/Users/pronestivm/Projects/spectacles/frontend`. All file lists are repository-relative. Read applicable AGENTS instructions before execution; no root/frontend `.ai/rules` directory was found during planning. Preserve existing `.gitignore`, README, frontend environment/Vite edits, untracked `.env.example`, `docs/`, and `frontend/design/` artifacts.

## API and UI Contract

| User action | API | Result |
| --- | --- | --- |
| App load/retry | `GET /api/v1/me` | Checking gate, then signed-in identity; 401 shows login with Explore demo; network/protocol failure shows Retry/Explore, never a fake account. |
| Create account | CSRF GET, then `POST /api/v1/auth/register` with `name,email,password,password_confirmation` | Accept 201 server identity and enter the existing demo dashboard. |
| Sign in | CSRF GET, then `POST /api/v1/auth/login` with `email,password` | Accept 200 server identity; 422 stays on form with field errors. |
| Save profile | CSRF GET, then `PATCH /api/v1/me` with changed `name,email,locale` fields only | Replace identity with returned canonical values after success. Keep unsaved input on failure. |
| Sign out | CSRF GET, then `POST /api/v1/auth/logout` | 204/401 closes account-related overlays and opens login. Other failures retain identity and display a retryable error. |
| Explore demo | No mutation | Show the existing browser collection with guest menu actions; no authentication claim. |

The current backend locale allowlist is its configured primary/fallback locale, both defaulting to `en`. The profile form offers Application default (`null`) and English (`en`), plus an existing non-English saved value for display/preservation. It submits only changed values. This task adds no translation system or language-discovery endpoint; if backend locale configuration changes, verify the available options before changing this list.

### Task 1: Typed Account Contract and Cookie Client

**Files:** Create `frontend/src/accountApi.ts`, `frontend/tests/account-contract.test.mjs`, and `frontend/tests/account-api.test.mjs`. Keep the current UI and legacy helpers intact until Task 2 so this task remains independently buildable.

**Interfaces:** Produces `AccountUser`, `Credentials`, `ProfileDraft`, `ProfilePatch`, `AccountErrors`, `AuthMode`, `decodeUser(unknown): AccountUser`, `credentialErrors`, `profileErrors`, `profileChanges`, and uses the existing `avatarLetter` from `account.ts`. Produces `createAccountClient(baseUrl, fetcher?, readCookie?)` with `me(signal?)`, `login(credentials)`, `register(credentials)`, `update(patch)`, and `logout()`, plus `AccountApiError(status,message,fields)` and `accountErrorMessage(unknown)`.

- [ ] **Step 1: Create `frontend/tests/account-contract.test.mjs` with these contract tests.**

```js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { avatarLetter } from '../src/account.ts'
import { credentialErrors, decodeUser, profileChanges, profileErrors } from '../src/accountApi.ts'

const user = { id: '00000000-0000-4000-8000-000000000001', name: 'Lucía García', email: 'lucia@example.com', locale: null }
test('account identity uses server UUID and explicit attributes only', () => {
  assert.deepEqual(decodeUser({ data: { type: 'users', id: user.id, attributes: { ...user, password: 'never-copy', city: 'ignored' } } }), user)
  assert.throws(() => decodeUser({ name: 'Legacy preview', email: user.email }))
  assert.throws(() => decodeUser({ data: { type: 'users', id: 1, attributes: user } }))
  assert.equal(avatarLetter('  Élodie Martin '), 'É')
})
test('registration and legacy login obey different minimum lengths and the UTF-8 ceiling', () => {
  const input = { name: user.name, email: user.email, password: 'a-valid-password', confirmation: 'a-valid-password' }
  assert.deepEqual(credentialErrors('register', input), {})
  assert.ok(credentialErrors('register', { ...input, password: 'password', confirmation: 'password' }).password)
  assert.deepEqual(credentialErrors('login', { ...input, name: '', password: 'password', confirmation: '' }), {})
  assert.ok(credentialErrors('register', { ...input, password: 'é'.repeat(37), confirmation: 'é'.repeat(37) }).password)
  assert.ok(credentialErrors('login', { ...input, password: 'a\0password' }).password)
  assert.ok(credentialErrors('register', { ...input, confirmation: 'different' }).confirmation)
})
test('profile patches contain changed supported fields only and preserve absent locale', () => {
  assert.deepEqual(profileChanges({ ...user, name: '  Updated  ', password: 'never-copy', city: 'ignored' }, user), { name: 'Updated' })
  assert.deepEqual(profileChanges({ ...user, locale: 'en' }, user), { locale: 'en' })
  assert.deepEqual(profileChanges({ ...user, locale: null }, { ...user, locale: 'en' }), { locale: null })
  assert.ok(profileErrors({ ...user, name: ' ' }).name)
  assert.ok(profileErrors({ ...user, email: 'invalid' }).email)
  assert.ok(profileErrors({ ...user, name: 'x'.repeat(81) }).name)
})
```

- [ ] **Step 2: Write the client tests. All credentials below are test-only constants.**

```js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { AccountApiError, createAccountClient } from '../src/accountApi.ts'

const user = { id: '00000000-0000-4000-8000-000000000001', name: 'Jamie', email: 'jamie@example.com', locale: null }
const document = { data: { type: 'users', id: user.id, attributes: user } }
const json = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
const credentials = { name: 'Jamie', email: user.email, password: 'test-password-123', confirmation: 'test-password-123' }

test('five endpoints carry cookies; mutations initialize CSRF and map/allowlist payloads', async () => {
  const calls = []
  let cookie = ''; let token = 0
  const client = createAccountClient('https://api.spectacles.test/', async (url, init) => {
    const path = new URL(url).pathname
    calls.push({ path, ...init })
    assert.equal(init.credentials, 'include')
    assert.equal(new Headers(init.headers).get('Accept'), 'application/json')
    if (path === '/sanctum/csrf-cookie') { cookie = `XSRF-TOKEN=token%20${++token}`; return new Response(null, { status: 204 }) }
    if (init.method !== 'GET') assert.equal(new Headers(init.headers).get('X-XSRF-TOKEN'), `token ${token}`)
    return path.endsWith('/logout') ? new Response(null, { status: 204 }) : json(document, path.endsWith('/register') ? 201 : 200)
  }, () => cookie)
  assert.deepEqual(await client.me(), user)
  await client.login(credentials)
  await client.register(credentials)
  await client.update({ name: 'Updated', locale: null, password: 'must-not-send', city: 'ignored' })
  await client.logout()
  assert.equal(token, 4)
  assert.deepEqual(JSON.parse(calls.find(c => c.path.endsWith('/login')).body), { email: user.email, password: credentials.password })
  assert.deepEqual(JSON.parse(calls.find(c => c.path.endsWith('/register')).body), { name: credentials.name, email: user.email, password: credentials.password, password_confirmation: credentials.confirmation })
  assert.deepEqual(JSON.parse(calls.find(c => c.path === '/api/v1/me' && c.method === 'PATCH').body), { name: 'Updated', locale: null })
  assert.equal(calls.some(c => new Headers(c.headers).has('Authorization')), false)
})
test('missing or malformed CSRF cookies prevent the mutation', async () => {
  for (const cookie of ['', 'XSRF-TOKEN=%ZZ']) {
    const calls = []
    const client = createAccountClient('https://api.spectacles.test', async (url) => { calls.push(url); return new Response(null, { status: 204 }) }, () => cookie)
    await assert.rejects(client.login(credentials), error => error instanceof AccountApiError && error.status === 419)
    assert.equal(calls.length, 1)
  }
})
test('422 maps confirmation errors and 419 is not automatically replayed', async () => {
  let calls = 0
  const client = createAccountClient('https://api.spectacles.test', async url => {
    calls++
    return url.endsWith('/csrf-cookie') ? new Response(null, { status: 204 }) : json({ errors: { password_confirmation: ['Confirm the password.'], internal_id: ['ignored'] } }, 422)
  }, () => 'XSRF-TOKEN=token')
  await assert.rejects(client.register(credentials), error => {
    assert.deepEqual(error.fields, { confirmation: 'Confirm the password.' }); return error.status === 422
  })
  assert.equal(calls, 2)
  calls = 0
  const expired = createAccountClient('https://api.spectacles.test', async url => {
    calls++
    return url.endsWith('/csrf-cookie') ? new Response(null, { status: 204 }) : json({}, 419)
  }, () => 'XSRF-TOKEN=token')
  await assert.rejects(expired.login(credentials), error => error.status === 419)
  assert.equal(calls, 2)
})
test('unauthorized, throttled, HTML, and network responses stay distinguishable and safe', async () => {
  for (const status of [401, 429, 503]) {
    const client = createAccountClient('https://api.spectacles.test', async () => json({ message: 'server-debug-do-not-display' }, status))
    await assert.rejects(client.me(), error => error.status === status && !error.message.includes('server-debug'))
  }
  const html = createAccountClient('https://api.spectacles.test', async () => new Response('<html>not an account</html>'))
  await assert.rejects(html.me(), error => error instanceof AccountApiError)
  const offline = createAccountClient('https://api.spectacles.test', async () => { throw new TypeError('secret-debug-detail') })
  await assert.rejects(offline.me(), error => error.status === 0 && !error.message.includes('secret-debug'))
})
test('unexpected successful HTML responses cannot confirm a logout', async () => {
  const client = createAccountClient('https://api.spectacles.test', async url => url.endsWith('/csrf-cookie')
    ? new Response(null, { status: 204 }) : new Response('<html>not a logout</html>'), () => 'XSRF-TOKEN=token')
  await assert.rejects(client.logout(), error => error instanceof AccountApiError && error.status === 200)
})
test('a stalled success body is bounded by the request timeout', async context => {
  context.mock.timers.enable({ apis: ['setTimeout'] })
  const client = createAccountClient('https://api.spectacles.test', async (url, init) => new Response(new ReadableStream({
    start(controller) { init.signal.addEventListener('abort', () => controller.error(new DOMException('Aborted', 'AbortError')), { once: true }) },
  })))
  const read = client.me()
  await Promise.resolve()
  context.mock.timers.tick(10_000)
  await assert.rejects(read, error => error instanceof AccountApiError && error.status === 0 && error.message.includes('timed out'))
})
test('a redirected HTML page cannot initialize CSRF', async () => {
  let calls = 0
  const client = createAccountClient('https://api.spectacles.test', async () => { calls++; return new Response('<html>not CSRF</html>') }, () => 'XSRF-TOKEN=old-token')
  await assert.rejects(client.login(credentials), error => error instanceof AccountApiError)
  assert.equal(calls, 1)
})
test('caller cancellation aborts a startup read without being converted into an auth failure', async () => {
  const controller = new AbortController()
  const client = createAccountClient('https://api.spectacles.test', async (url, init) => new Promise((resolve, reject) => {
    init.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true })
  }))
  const read = client.me(controller.signal)
  controller.abort()
  await assert.rejects(read, { name: 'AbortError' })
})
```

- [ ] **Step 3: Run the tests and observe the missing client/exports.**

```sh
node --test tests/account-contract.test.mjs tests/account-api.test.mjs
```

Expected before implementation: import/export failure. If the frontend integration already exists when execution begins, inspect and reuse it instead of replacing passing behavior.

- [ ] **Step 4: Create the protocol contract at the top of `frontend/src/accountApi.ts`.**

Keep the existing preview module unchanged in this task. This new module will be the authoritative account contract used by the converted UI:

```ts
export type AuthMode = 'login' | 'register'
export type AccountUser = { id: string; name: string; email: string; locale: string | null }
export type Credentials = { name: string; email: string; password: string; confirmation: string }
export type ProfileDraft = Pick<AccountUser, 'name' | 'email' | 'locale'>
export type ProfilePatch = Partial<ProfileDraft>
export type AccountErrors = Partial<Record<'name' | 'email' | 'password' | 'confirmation' | 'locale', string>>

const record = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value)
export function decodeUser(value: unknown): AccountUser {
  if (!record(value) || !record(value.data) || !record(value.data.attributes)) throw new Error('Invalid account response')
  const data = value.data, fields = data.attributes as Record<string, unknown>
  if (data.type !== 'users' || typeof data.id !== 'string' || !/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i.test(data.id)
    || typeof fields.name !== 'string' || !fields.name.trim() || typeof fields.email !== 'string'
    || fields.locale !== null && typeof fields.locale !== 'string') throw new Error('Invalid account response')
  return { id: data.id, name: fields.name, email: fields.email, locale: fields.locale as string | null }
}
export function profileErrors(input: Pick<ProfileDraft, 'name' | 'email'>): AccountErrors {
  const errors: AccountErrors = {}
  if (!input.name.trim() || Array.from(input.name.trim()).length > 80) errors.name = 'Use a display name between 1 and 80 characters.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.email.trim()) || input.email.trim().length > 254) errors.email = 'Enter a valid email address.'
  return errors
}
export function credentialErrors(mode: AuthMode, input: Credentials): AccountErrors {
  const errors = profileErrors({ name: mode === 'register' ? input.name : 'Account', email: input.email })
  if (!input.password || mode === 'register' && Array.from(input.password).length < 12) errors.password = mode === 'register' ? 'Use at least 12 characters.' : 'Enter your password.'
  if (new TextEncoder().encode(input.password).length > 72) errors.password = 'This password is too long. Use a shorter password.'
  if (input.password.includes('\0')) errors.password = 'This password contains an unsupported character.'
  if (mode === 'register' && input.password !== input.confirmation) errors.confirmation = 'The passwords do not match.'
  return errors
}
export function profileChanges(draft: ProfileDraft, current: AccountUser): ProfilePatch {
  const changes: ProfilePatch = {}
  if (draft.name.trim() !== current.name) changes.name = draft.name.trim()
  if (draft.email.trim() !== current.email) changes.email = draft.email.trim()
  if (draft.locale !== current.locale) changes.locale = draft.locale
  return changes
}
```

- [ ] **Step 5: Create the native client. No storage, logging, token issuance, or automatic mutation retry. Buffer each successful response before clearing its timeout, so a stalled response body is also bounded.**

Append this transport implementation to the same `frontend/src/accountApi.ts` file after the contract above:

```ts
export class AccountApiError extends Error {
  readonly status: number
  readonly fields: AccountErrors
  constructor(status: number, message: string, fields: AccountErrors = {}) {
    super(message); this.name = 'AccountApiError'; this.status = status; this.fields = fields
  }
}
export function accountErrorMessage(error: unknown): string {
  return error instanceof AccountApiError ? error.message : 'Unable to reach the account service. Please try again.'
}
function message(status: number): string {
  if (status === 401) return 'Your session expired. Sign in again.'
  if (status === 419) return 'Your session changed. Please try again.'
  if (status === 422) return 'Check the highlighted fields.'
  if (status === 429) return 'Too many attempts. Please wait and try again.'
  return 'The account service is unavailable. Please try again.'
}
function fieldsFrom(body: unknown): AccountErrors {
  const fields: AccountErrors = {}
  const errors = (body as { errors?: unknown } | null)?.errors
  if (!errors || typeof errors !== 'object' || Array.isArray(errors)) return fields
  for (const [raw, values] of Object.entries(errors)) {
    const key = raw === 'password_confirmation' ? 'confirmation' : raw
    if (['name', 'email', 'password', 'confirmation', 'locale'].includes(key) && Array.isArray(values) && typeof values[0] === 'string') fields[key as keyof AccountErrors] = values[0]
  }
  return fields
}
export function createAccountClient(baseUrl: string, fetcher: typeof fetch = fetch, readCookie: () => string = () => document.cookie) {
  const base = baseUrl.replace(/\/+$/, '')
  async function send(path: string, init: RequestInit = {}, signal?: AbortSignal): Promise<Response> {
    const controller = new AbortController()
    const abort = () => controller.abort()
    if (signal?.aborted) controller.abort()
    signal?.addEventListener('abort', abort, { once: true })
    const timer = setTimeout(abort, 10_000)
    const headers = new Headers(init.headers); headers.set('Accept', 'application/json')
    try {
      const response = await fetcher(`${base}${path}`, { method: 'GET', ...init, headers, credentials: 'include', cache: 'no-store', signal: controller.signal })
      if (!response.ok) {
        const body: unknown = await response.json().catch(() => null)
        throw new AccountApiError(response.status, message(response.status), fieldsFrom(body))
      }
      const bytes = await response.arrayBuffer()
      return new Response(bytes.byteLength ? bytes : null, { status: response.status, statusText: response.statusText, headers: response.headers })
    } catch (error) {
      if (error instanceof AccountApiError || signal?.aborted) throw error
      throw new AccountApiError(0, controller.signal.aborted ? 'The account request timed out. Please try again.' : 'Unable to reach the account service. Please try again.')
    } finally { clearTimeout(timer); signal?.removeEventListener('abort', abort) }
  }
  async function identity(response: Response): Promise<AccountUser> {
    try { return decodeUser(await response.json()) }
    catch { throw new AccountApiError(response.status, 'The account service returned an unexpected response. Please try again.') }
  }
  async function write(path: string, method: 'POST' | 'PATCH', body?: unknown): Promise<Response> {
    const csrf = await send('/sanctum/csrf-cookie')
    if (csrf.status !== 204) throw new AccountApiError(csrf.status, 'Unable to start a secure session. Please try again.')
    let token: string
    try {
      const value = readCookie().split(';').map(part => part.trim()).find(part => part.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length)
      if (!value) throw new Error('Missing cookie')
      token = decodeURIComponent(value)
    } catch { throw new AccountApiError(419, 'Cookies are unavailable. Allow cookies and try again.') }
    return send(path, { method, headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token }, body: JSON.stringify(body ?? {}) })
  }
  return {
    async me(signal?: AbortSignal) { return identity(await send('/api/v1/me', {}, signal)) },
    async login(input: Pick<Credentials, 'email' | 'password'>) {
      return identity(await write('/api/v1/auth/login', 'POST', { email: input.email.trim(), password: input.password }))
    },
    async register(input: Credentials) {
      return identity(await write('/api/v1/auth/register', 'POST', { name: input.name.trim(), email: input.email.trim(), password: input.password, password_confirmation: input.confirmation }))
    },
    async update(input: ProfilePatch) {
      const body: ProfilePatch = {}
      if (input.name !== undefined) body.name = input.name
      if (input.email !== undefined) body.email = input.email
      if (input.locale !== undefined) body.locale = input.locale
      return identity(await write('/api/v1/me', 'PATCH', body))
    },
    async logout(): Promise<void> {
      const response = await write('/api/v1/auth/logout', 'POST')
      if (response.status !== 204) throw new AccountApiError(response.status, 'Unable to confirm sign out. Please try again.')
    },
  }
}
export type AccountClient = ReturnType<typeof createAccountClient>
```

- [ ] **Step 6: Run the focused tests; require all to pass.**

```sh
node --test tests/account-contract.test.mjs tests/account-api.test.mjs
```

Expected: passing request order, canonical payloads, fresh CSRF values, no bearer header, field mapping, no automatic replay, safe failures, cancellation, and helper rules. Run `npm run lint` and `npm run build` as well: the old UI still builds because this task has not removed or changed its imports.

### Task 2: Connect the Existing Account Screens to Server Sessions

**Files:** Create `frontend/src/useAccount.ts`; modify `frontend/src/account.ts`, `frontend/src/App.tsx`, `frontend/src/components/AuthView.tsx`, `frontend/src/components/AccountMenu.tsx`, `frontend/src/components/Avatar.tsx`, `frontend/src/components/ProfileView.tsx`, and `frontend/src/account.css`; replace `frontend/tests/account.test.mjs`. Keep the existing component boundaries and concert logic.

**Interfaces:** Consumes Task 1's `AccountClient`, `AccountUser`, `Credentials`, `ProfilePatch`, `AccountErrors`, `AuthMode`, validation helpers, and error class. Produces `useAccount(client)` with `status`, `user`, `error`, `busy`, `refresh()`, `clearError()`, `login(Credentials): Promise<void>`, `register(Credentials): Promise<void>`, `save(ProfilePatch): Promise<void>`, and `logout(): Promise<void>`. `AuthView.onSubmit(mode,credentials)` and `ProfileView.onSave(patch)` resolve only after the server confirms the change. Their rejected promises retain the form and display errors. `AccountMenu` receives a nullable server user and explicit guest/sign-out actions.

This task is one cohesive UI conversion: removing the old account types before updating their callers would leave an unbuildable intermediate state. Its steps are small edits within that single review gate.

- [ ] **Step 1: Replace `frontend/tests/account.test.mjs` with these UI regressions.**

```js
import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'

let server, AuthView, ProfileView, Avatar, App
const user = { id: '00000000-0000-4000-8000-000000000001', name: 'Élodie', email: 'elodie@example.com', locale: null }
const noop = () => {}
const render = (component, props) => renderToStaticMarkup(createElement(component, props))
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/account-ui-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false }, appType: 'custom' })
  ;({ AuthView } = await server.ssrLoadModule('/src/components/AuthView.tsx'))
  ;({ ProfileView } = await server.ssrLoadModule('/src/components/ProfileView.tsx'))
  ;({ Avatar } = await server.ssrLoadModule('/src/components/Avatar.tsx'))
  ;({ default: App } = await server.ssrLoadModule('/src/App.tsx'))
})
after(async () => { await server?.close() })
test('real credential forms retain labels and distinguish login from registration policy', () => {
  const props = { busy: false, onSwitch: noop, onExplore: noop, onSubmit: async () => {} }
  const login = render(AuthView, { ...props, mode: 'login' })
  const register = render(AuthView, { ...props, mode: 'register' })
  assert.match(login, /autoComplete="current-password"/i)
  assert.match(register, /autoComplete="new-password"/i)
  assert.match(register, /id="auth-confirmation"/)
  assert.match(register, /12 characters/)
  assert.match(register, /Create account/)
  assert.doesNotMatch(login + register, /sample password|Create preview profile|never sent|does not create a real account/)
  assert.match(render(AuthView, { ...props, mode: 'register', busy: true }), /<fieldset[^>]*disabled/)
})
test('profile exposes supported fields and labels browser demo statistics', () => {
  const html = render(ProfileView, { user, counts: { concerts: 24, artists: 12, venues: 8 }, busy: false, onSave: async () => {}, onCancel: noop })
  for (const id of ['profile-name', 'profile-email', 'profile-locale']) assert.ok(html.includes(`id="${id}"`))
  assert.match(html, /elodie@example.com/)
  assert.match(html, /Demo concerts/)
  assert.doesNotMatch(html, /profile-city|profile-bio|type="file"|Change photo|Avatar color/)
  assert.match(render(Avatar, { name: user.name }), /É/)
})
test('initial application render checks the session without showing a fabricated identity', () => {
  const html = render(App)
  assert.match(html, /Checking your session/)
  assert.doesNotMatch(html, /Jamie Morgan|jamie@example.com|Local preview profile/)
})
```

These tests exercise markup and the initial gate. They do not simulate effects, browser cookies, keyboard events, or asynchronous submissions; those have explicit acceptance rows in Task 3.

- [ ] **Step 2: Run `node --test tests/account.test.mjs` and observe the obsolete preview contract fail.**

Expected: the current forms do not accept the new props or expose a locale field, and the current App renders the demo immediately. Do not weaken the assertions to preserve the preview-only behavior.

- [ ] **Step 3: Create `frontend/src/useAccount.ts` with one startup request and guarded mutations.**

```ts
import { useEffect, useRef, useState } from 'react'
import { AccountApiError, accountErrorMessage } from './accountApi'
import type { AccountClient, AccountUser, Credentials, ProfilePatch } from './accountApi'

type Snapshot = { status: 'checking' | 'guest' | 'authenticated' | 'unavailable'; user: AccountUser | null; error: string }
export function useAccount(client: AccountClient) {
  const [snapshot, setSnapshot] = useState<Snapshot>({ status: 'checking', user: null, error: '' })
  const [retry, setRetry] = useState(0)
  const [busy, setBusy] = useState(false)
  const busyRef = useRef(false)
  const generation = useRef(0)
  const startup = useRef<AbortController | null>(null)
  useEffect(() => {
    const current = ++generation.current
    const controller = new AbortController()
    startup.current = controller
    void client.me(controller.signal).then(user => {
      if (current === generation.current) setSnapshot({ status: 'authenticated', user, error: '' })
    }).catch(error => {
      if (current !== generation.current || controller.signal.aborted) return
      setSnapshot(error instanceof AccountApiError && error.status === 401
        ? { status: 'guest', user: null, error: '' }
        : { status: 'unavailable', user: null, error: accountErrorMessage(error) })
    })
    // oxlint-disable-next-line react-hooks/exhaustive-deps -- Invalidate request generations on cleanup; this ref is not a rendered DOM node.
    return () => { ++generation.current; controller.abort() }
  }, [client, retry])

  async function run(operation: () => Promise<AccountUser | null>): Promise<void> {
    if (busyRef.current) throw new AccountApiError(0, 'An account request is already in progress.')
    busyRef.current = true; setBusy(true)
    const current = ++generation.current
    startup.current?.abort()
    try {
      const user = await operation()
      if (current === generation.current) setSnapshot({ status: user ? 'authenticated' : 'guest', user, error: '' })
    } catch (error) {
      if (current === generation.current && error instanceof AccountApiError && error.status === 401) {
        setSnapshot({ status: 'guest', user: null, error: 'Your session expired. Sign in again.' })
      }
      throw error
    } finally {
      busyRef.current = false
      if (current === generation.current) setBusy(false)
    }
  }
  return {
    ...snapshot, busy,
    refresh() {
      if (busyRef.current) return
      ++generation.current; startup.current?.abort()
      setSnapshot({ status: 'checking', user: null, error: '' }); setRetry(value => value + 1)
    },
    clearError() { setSnapshot(current => ({ ...current, error: '' })) },
    login(input: Credentials) { return run(() => client.login(input)) },
    register(input: Credentials) { return run(() => client.register(input)) },
    save(input: ProfilePatch) { return run(() => client.update(input)) },
    logout() {
      return run(async () => {
        try { await client.logout() }
        catch (error) { if (!(error instanceof AccountApiError && error.status === 401)) throw error }
        return null
      })
    },
  }
}
```

The generation check protects every resolved request, including unmount and StrictMode cleanup. Only startup reads are aborted. A mutation that reaches Laravel is never treated as rolled back merely because a view changes. The shared synchronous lock prevents a second click before React renders disabled controls.

- [ ] **Step 4: Reduce `frontend/src/account.ts` to the existing initials helper and update `Avatar.tsx`.**

Complete `account.ts`:

```ts
export function avatarLetter(name: string): string {
  return Array.from(name.trim())[0]?.toLocaleUpperCase() ?? 'J'
}
```

Complete `frontend/src/components/Avatar.tsx`:

```tsx
import { avatarLetter } from '../account'
export function Avatar({ name, large = false }: { name: string; large?: boolean }) {
  return <span className={`account-avatar avatar-lime ${large ? 'large' : ''}`} aria-hidden="true">{avatarLetter(name)}</span>
}
```

No call to the old storage keys remains after Step 8. Their existing bytes are deliberately left untouched.

- [ ] **Step 5: Replace `frontend/src/components/AuthView.tsx`, retaining the visual layout and photograph.**

```tsx
import { useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { AccountApiError, accountErrorMessage, credentialErrors } from '../accountApi'
import type { AccountErrors, AuthMode, Credentials } from '../accountApi'
import { Icon, Soundmark } from './Icon'
import stagePhoto from '../assets/concert-stage.png'

function PasswordField({ label, id, value, onChange, error, register }: { label: string; id: string; value: string; onChange: (value: string) => void; error?: string; register: boolean }) {
  const [visible, setVisible] = useState(false)
  return <div className="account-field"><label htmlFor={id}>{label}</label><span className={`password-input ${error ? 'invalid' : ''}`}>
    <input id={id} type={visible ? 'text' : 'password'} autoComplete={register ? 'new-password' : 'current-password'} required minLength={register ? 12 : 1} maxLength={72} placeholder="Enter your password" value={value} onChange={event => onChange(event.target.value)} aria-invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : undefined} />
    <button type="button" className="password-visibility" onClick={() => setVisible(current => !current)} aria-label={`${visible ? 'Hide' : 'Show'} ${label.toLowerCase()}`} aria-pressed={visible}><Icon name={visible ? 'eyeOff' : 'eye'} size={19} /></button>
  </span>{error && <small className="field-error" id={`${id}-error`}>{error}</small>}</div>
}
export function AuthView({ mode, busy, notice = '', onSwitch, onExplore, onSubmit }: {
  mode: AuthMode; busy: boolean; notice?: string; onSwitch: (mode: AuthMode) => void; onExplore: () => void; onSubmit: (mode: AuthMode, input: Credentials) => Promise<void>
}) {
  const register = mode === 'register'
  const [name, setName] = useState(''), [email, setEmail] = useState('')
  const [password, setPassword] = useState(''), [confirmation, setConfirmation] = useState('')
  const [errors, setErrors] = useState<AccountErrors>({}), [message, setMessage] = useState('')
  const locked = useRef(false)
  const resetError = () => { setErrors({}); setMessage('') }
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (busy || locked.current) return
    const form = event.currentTarget
    const input = { name, email, password, confirmation }
    const next = credentialErrors(mode, input)
    setErrors(next); setMessage('')
    const focusError = (fields: AccountErrors) => requestAnimationFrame(() => {
      const field = Object.keys(fields)[0]
      form.querySelector<HTMLElement>(field ? `#auth-${field}` : '[role="alert"]')?.focus()
    })
    if (Object.keys(next).length) { focusError(next); return }
    locked.current = true
    try { await onSubmit(mode, input); setPassword(''); setConfirmation('') }
    catch (error) {
      const fields = error instanceof AccountApiError ? error.fields : {}
      setErrors(fields); setMessage(accountErrorMessage(error)); focusError(fields)
    } finally { locked.current = false }
  }
  return <main className={`auth-layout ${register ? 'registration' : ''}`} id="auth-content">
    <section className="auth-side"><header className="auth-header">
      <button disabled={busy} className="brand" onClick={onExplore} aria-label="Spectacles demo dashboard"><Soundmark /><span>Spectacles<span className="brand-period">.</span></span></button>
      <div className="auth-switch"><span>{register ? 'Already collecting memories?' : 'New here?'}</span><button disabled={busy} onClick={() => onSwitch(register ? 'login' : 'register')}>{register ? 'Sign in' : 'Create an account'}<Icon name="arrow" size={16} /></button></div>
    </header><div className="auth-form-wrap"><p className="eyebrow">{register ? 'START YOUR COLLECTION' : 'YOUR PERSONAL ENCORE'}</p>
      <h1>{register ? <>Make it your own<span className="lime-dot">.</span></> : 'Welcome back.'}</h1><p className="auth-subtitle">{register ? 'Keep the dates, the places, the songs.' : 'Your next great memory is waiting.'}</p>
      <form onSubmit={submit} noValidate className="auth-form" aria-busy={busy}>
        {(message || notice) && <p className="account-error" role="alert" tabIndex={-1}>{message || notice}</p>}
        <fieldset className="account-fields" disabled={busy}><legend className="sr-only">{register ? 'Create account' : 'Sign in'}</legend>
          {register && <div className="account-field"><label htmlFor="auth-name">Your name</label><input autoFocus id="auth-name" autoComplete="name" maxLength={80} required value={name} onChange={event => { setName(event.target.value); resetError() }} aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'auth-name-error' : undefined} />{errors.name && <small className="field-error" id="auth-name-error">{errors.name}</small>}</div>}
          <div className="account-field"><label htmlFor="auth-email">Email</label><input autoFocus={!register} id="auth-email" type="email" autoComplete="email" maxLength={254} required value={email} onChange={event => { setEmail(event.target.value); resetError() }} aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'auth-email-error' : undefined} />{errors.email && <small className="field-error" id="auth-email-error">{errors.email}</small>}</div>
          <PasswordField label="Password" id="auth-password" value={password} onChange={value => { setPassword(value); resetError() }} error={errors.password} register={register} />
          {register && <PasswordField label="Confirm password" id="auth-confirmation" value={confirmation} onChange={value => { setConfirmation(value); resetError() }} error={errors.confirmation} register />}
          <p className="password-hint">{register ? 'At least 12 characters and at most 72 UTF-8 bytes.' : 'Use your account email and password.'}</p>
          <button className="button primary auth-submit" type="submit">{busy ? 'Please wait…' : register ? 'Create account' : 'Sign in'}<Icon name="arrow" size={20} /></button>
        </fieldset>
      </form>
      <div className="auth-divider"><span>or take a look around</span></div><button disabled={busy} className="button secondary auth-explore" onClick={onExplore}>Explore the demo<Icon name="arrow" size={19} /></button>
      <p className="auth-preview-note">Your account profile is saved online. Concerts remain a browser-local demo shared across sign-ins.</p>
    </div><footer className="auth-footer">A personal archive of being there.</footer></section>
    <aside className="auth-photo"><img src={stagePhoto} alt="A live band and crowd beneath lime stage lights" /><div className="auth-photo-shade" /><div className="auth-photo-copy"><span className="auth-photo-label"><i />FOR THE LOVE OF LIVE MUSIC</span><h2>{register ? <>Every night<br />has a story<span>.</span></> : <>Be there.<br />Keep it forever.</>}</h2><p>{register ? 'Start keeping yours.' : 'The shows end. The stories stay.'}</p></div></aside>
  </main>
}
```

The form uses `noValidate` because the shared validator counts Unicode characters and UTF-8 bytes. HTML attributes assist input/autofill; they are not the complete password policy. No reset or verification links are introduced without implemented destinations.

- [ ] **Step 6: Replace `frontend/src/components/ProfileView.tsx` with the server-backed form.**

```tsx
import { useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { AccountApiError, accountErrorMessage, profileChanges, profileErrors } from '../accountApi'
import type { AccountErrors, AccountUser, ProfileDraft, ProfilePatch } from '../accountApi'
import { Avatar } from './Avatar'
import { Icon } from './Icon'

export function ProfileView({ user, counts, busy, onSave, onCancel }: {
  user: AccountUser; counts: { concerts: number; artists: number; venues: number }; busy: boolean; onSave: (patch: ProfilePatch) => Promise<void>; onCancel: () => void
}) {
  const [draft, setDraft] = useState<ProfileDraft>({ name: user.name, email: user.email, locale: user.locale })
  const [errors, setErrors] = useState<AccountErrors>({}), [message, setMessage] = useState('')
  const locked = useRef(false)
  const dirty = Object.keys(profileChanges(draft, user)).length > 0
  const change = <K extends keyof ProfileDraft>(key: K, value: ProfileDraft[K]) => {
    setDraft(current => ({ ...current, [key]: value })); setErrors({}); setMessage('')
  }
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (busy || locked.current || !dirty) return
    const form = event.currentTarget
    const next = profileErrors(draft)
    const focus = (fields: AccountErrors) => requestAnimationFrame(() => {
      const key = Object.keys(fields)[0]
      form.querySelector<HTMLElement>(key ? `#profile-${key}` : '[role="alert"]')?.focus()
    })
    setErrors(next); setMessage('')
    if (Object.keys(next).length) { focus(next); return }
    locked.current = true
    try { await onSave(profileChanges(draft, user)) }
    catch (error) {
      const fields = error instanceof AccountApiError ? error.fields : {}
      setErrors(fields); setMessage(accountErrorMessage(error)); focus(fields)
    } finally { locked.current = false }
  }
  const legacyLocale = user.locale !== null && user.locale !== 'en' ? user.locale : null
  return <div className="profile-layout"><form className="profile-form" noValidate onSubmit={submit} aria-busy={busy}>
    <section className="profile-identity" aria-label="Account identity"><Avatar name={user.name} large /><div className="profile-identity-copy"><strong>{user.name}</strong><span>{user.email}</span><small>Account profile</small></div></section>
    {message && <p className="account-error" role="alert" tabIndex={-1}>{message}</p>}
    <fieldset className="account-fields" disabled={busy}><legend className="sr-only">Edit account profile</legend>
      <div className="profile-fields"><div className="profile-field-row">
        <div className="account-field"><label htmlFor="profile-name">Display name</label><input id="profile-name" required autoComplete="name" maxLength={80} value={draft.name} onChange={event => change('name', event.target.value)} aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'profile-name-error' : undefined} />{errors.name && <small className="field-error" id="profile-name-error">{errors.name}</small>}</div>
        <div className="account-field"><label htmlFor="profile-email">Email</label><input id="profile-email" type="email" required autoComplete="email" maxLength={254} value={draft.email} onChange={event => change('email', event.target.value)} aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'profile-email-error' : 'profile-email-hint'} />{errors.email && <small className="field-error" id="profile-email-error">{errors.email}</small>}<small id="profile-email-hint">Changes take effect immediately. Use the new email next time you sign in.</small></div>
      </div><div className="account-field"><label htmlFor="profile-locale">Preferred language</label><select id="profile-locale" value={draft.locale ?? ''} onChange={event => change('locale', event.target.value || null)} aria-invalid={Boolean(errors.locale)} aria-describedby={errors.locale ? 'profile-locale-error' : 'profile-locale-hint'}>
        <option value="">Application default</option><option value="en">English</option>{legacyLocale && <option value={legacyLocale}>{legacyLocale} (current saved preference)</option>}
      </select>{errors.locale && <small className="field-error" id="profile-locale-error">{errors.locale}</small>}<small id="profile-locale-hint">Saves your preference. The current interface is in English.</small></div></div>
      <p className="profile-local-note"><Icon name="user" size={15} />Name, email, and language are saved to your account.</p>
      <div className="profile-form-actions"><button className="button secondary" type="button" onClick={onCancel}>Cancel</button><button className="button primary" type="submit" disabled={!dirty}>{busy ? 'Saving…' : 'Save changes'}<Icon name="arrow" size={17} /></button></div>
    </fieldset>
  </form><aside className="profile-story"><Icon name="music" size={24} /><p className="eyebrow">DEMO COLLECTION</p><h2>Your story,<br />so far<span>.</span></h2><p>Saved in this browser and shared across sign-ins. These counts are demo data.</p><div className="profile-story-stats">{[[counts.concerts, 'Demo concerts'], [counts.artists, 'Demo artists'], [counts.venues, 'Demo venues']].map(([count, label]) => <div key={label}><strong>{count}</strong><span><b>{label}</b><small>Browser-local collection</small></span></div>)}</div></aside></div>
}
```

Avatar/header identity always uses the confirmed `user`; typing a name does not change global identity. A successful save returns a new canonical user, remounting the form from those values in Step 8. A rejected save keeps the draft.

- [ ] **Step 7: Replace `frontend/src/components/AccountMenu.tsx`, preserving its keyboard contract.**

```tsx
import { useEffect, useRef, useState } from 'react'
import type { KeyboardEvent } from 'react'
import type { AccountUser, AuthMode } from '../accountApi'
import { Avatar } from './Avatar'
import { Icon } from './Icon'

export function AccountMenu({ user, busy, onProfile, onAuth, onSignOut }: {
  user: AccountUser | null; busy: boolean; onProfile: () => void; onAuth: (mode: AuthMode) => void; onSignOut: () => void
}) {
  const [open, setOpen] = useState(false)
  const root = useRef<HTMLDivElement>(null), trigger = useRef<HTMLButtonElement>(null)
  const firstItem = useRef<'first' | 'last'>('first')
  const selector = 'button[role="menuitem"]:not(:disabled)'
  useEffect(() => {
    if (!open) return
    const items = root.current!.querySelectorAll<HTMLButtonElement>(selector)
    items[firstItem.current === 'last' ? items.length - 1 : 0]?.focus()
    const outside = (event: PointerEvent | FocusEvent) => {
      if (event.target instanceof Node && !root.current?.contains(event.target)) setOpen(false)
    }
    document.addEventListener('pointerdown', outside); document.addEventListener('focusin', outside)
    return () => { document.removeEventListener('pointerdown', outside); document.removeEventListener('focusin', outside) }
  }, [open])
  const handleKeys = (event: KeyboardEvent) => {
    if (busy) return
    if (event.key === 'Escape' && open) { event.preventDefault(); setOpen(false); trigger.current?.focus(); return }
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return
    event.preventDefault()
    if (!open) { firstItem.current = event.key === 'ArrowUp' || event.key === 'End' ? 'last' : 'first'; setOpen(true); return }
    const items = Array.from(root.current!.querySelectorAll<HTMLButtonElement>(selector))
    if (!items.length) return
    const current = items.findIndex(item => item === document.activeElement)
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length
    items[next]?.focus()
  }
  const choose = (action: () => void) => { setOpen(false); action() }
  const name = user?.name ?? 'Demo'
  return <div className="account-menu" ref={root} onKeyDown={handleKeys}>
    <button ref={trigger} disabled={busy} className={`account-trigger ${open ? 'is-open' : ''}`} aria-label={`Account menu for ${name}`} aria-haspopup="menu" aria-expanded={open} aria-controls={open ? 'account-menu-panel' : undefined} onClick={() => { firstItem.current = 'first'; setOpen(value => !value) }}><Avatar name={name} /></button>
    {open && <div className="account-menu-panel" id="account-menu-panel"><div className="account-menu-identity"><Avatar name={name} /><div><strong>{name}</strong>{user && <span>{user.email}</span>}<small>{user ? 'Signed in' : 'Exploring the demo'}</small></div></div>
      <div className="account-menu-items" role="menu" aria-label="Account">{user ? <>
        <button disabled={busy} role="menuitem" tabIndex={-1} onClick={() => choose(onProfile)}><Icon name="user" size={20} /><span>Your profile</span><Icon name="arrow" size={17} /></button>
        <button disabled={busy} role="menuitem" tabIndex={-1} onClick={() => choose(onSignOut)}><Icon name="logout" size={20} /><span>Sign out</span></button>
      </> : <>
        <button disabled={busy} role="menuitem" tabIndex={-1} onClick={() => choose(() => onAuth('login'))}><Icon name="user" size={20} /><span>Sign in</span></button>
        <button disabled={busy} role="menuitem" tabIndex={-1} onClick={() => choose(() => onAuth('register'))}><Icon name="plus" size={20} /><span>Create account</span></button>
      </>}</div><p>Demo concerts stay in this browser, shared across sign-ins.</p></div>}
  </div>
}
```

- [ ] **Step 8: Apply these exact, guarded changes to `frontend/src/App.tsx`.**

Run this script from the repository root during implementation. It updates the account integration anchors once and leaves the concert operations untouched. A changed source anchor raises an error before writing; inspect that drift before adapting the patch.

```python
from pathlib import Path
path = Path('frontend/src/App.tsx')
text = path.read_text()
def replace_once(old, new):
    global text
    assert text.count(old) == 1, f'Expected one source anchor: {old[:100]}'
    text = text.replace(old, new, 1)

replace_once("import { DEFAULT_PROFILE, ENTRY_KEY, PROFILE_KEY, decodeProfile, profilePayload } from './account'\nimport type { AuthView as AuthMode, Profile } from './account'", "import { accountErrorMessage, createAccountClient } from './accountApi'\nimport type { AuthMode, Credentials, ProfilePatch } from './accountApi'\nimport { useAccount } from './useAccount'")
replace_once("type Connection =", "const accountClient = createAccountClient(apiBaseUrl)\ntype Connection =")
start = text.index('function initialProfile():')
end = text.index('function App() {', start)
text = text[:start] + text[end:]
replace_once("  const [initialAccount] = useState(initialProfile)\n  const [profile, setProfile] = useState(initialAccount.data)\n  const [profileIssue, setProfileIssue] = useState(initialAccount.issue)\n  const [authView, setAuthView] = useState<AuthMode | null>(initialEntry)", "  const account = useAccount(accountClient)\n  const [authView, setAuthView] = useState<AuthMode | null>('login')\n  const [exploringDemo, setExploringDemo] = useState(false)\n  const [accountNotice, setAccountNotice] = useState('')")
start = text.index('  useEffect(() => {\n    document.title =')
end = text.index('  const commit =', start)
text = text[:start] + '''  const activeAuthView = account.user ? null : authView ?? (!exploringDemo && account.error ? 'login' : null)
  useEffect(() => {
    const title = account.status === 'checking' ? 'Checking your session' : activeAuthView === 'login' ? 'Welcome back' : activeAuthView === 'register' ? 'Make it your own' : page === 'profile' ? 'Your profile' : 'Your life, live.'
    document.title = `Spectacles — ${title}`
  }, [activeAuthView, account.status, page])

  const focusMain = () => requestAnimationFrame(() => document.getElementById('main-content')?.focus({ preventScroll: true }))
  const clearOverlays = () => { setForm(null); setDetailId(null); setHealthOpen(false); setUndo(null); setToast('') }
  const changeEntry = (view: AuthMode | null) => {
    setAuthView(view); setAccountNotice('')
    if (view) { setExploringDemo(false); if (account.status === 'unavailable') account.refresh() }
    window.scrollTo({ top: 0, behavior: 'instant' })
  }
  const enterPreview = () => {
    clearOverlays(); setExploringDemo(true); account.clearError(); changeEntry(null); setPage('dashboard'); focusMain()
  }
  const submitAccount = async (mode: AuthMode, input: Credentials): Promise<void> => {
    if (mode === 'register') await account.register(input)
    else await account.login(input)
    clearOverlays(); setExploringDemo(false); changeEntry('login'); setPage('dashboard'); focusMain()
  }
  const saveProfile = async (patch: ProfilePatch): Promise<void> => {
    await account.save(patch)
    setToast('Account profile saved.')
  }
  const signOut = () => {
    setAccountNotice('')
    void account.logout().then(() => {
      clearOverlays(); setExploringDemo(false); setPage('dashboard'); changeEntry('login')
    }).catch(error => setAccountNotice(accountErrorMessage(error)))
  }

''' + text[end:]
replace_once("  if (authView) return <AuthView key={authView} mode={authView} onSwitch={changeEntry} onEnter={() => enterPreview()} onRegister={(name, email) => { saveProfile({ ...DEFAULT_PROFILE, name, email }); enterPreview('Your preview profile is ready.') }} />", '''  if (account.status === 'checking' || account.status === 'unavailable' && !exploringDemo) return <main className="account-gate"><Soundmark /><h1>Spectacles<span className="lime-dot">.</span></h1><p role={account.status === 'checking' ? 'status' : 'alert'}>{account.status === 'checking' ? 'Checking your session…' : account.error}</p>{account.status === 'unavailable' && <div><button className="button primary" onClick={account.refresh}>Try again</button><button className="button secondary" onClick={enterPreview}>Explore the demo</button></div>}</main>
  if (activeAuthView) return <AuthView key={activeAuthView} mode={activeAuthView} busy={account.busy} notice={account.error || accountNotice} onSwitch={changeEntry} onExplore={enterPreview} onSubmit={submitAccount} />''')
replace_once('Design preview · sample data', 'Demo concert collection')
replace_once('<AccountMenu profile={profile} onProfile={() => navigate(\'profile\')} onSignOut={signOut} />', '<AccountMenu user={account.user} busy={account.busy} onProfile={() => navigate(\'profile\')} onAuth={changeEntry} onSignOut={signOut} />')
replace_once('        {storageIssue && <p className="storage-warning" role="alert">{storageIssue}</p>}', '''        {storageIssue && <p className="storage-warning" role="alert">{storageIssue}</p>}
        {accountNotice && <p className="account-error" role="alert">{accountNotice}</p>}
        <p className="demo-notice">Demo collection · saved in this browser, shared across sign-ins.</p>''')
replace_once("{page === 'profile' && <ProfileView profile={profile} counts={{ concerts: workspace.concerts.length, artists: artistCount, venues: venues.length }} issue={profileIssue} onSave={saveProfile} onCancel={() => navigate('dashboard')} />}", "{page === 'profile' && account.user && <ProfileView key={JSON.stringify(account.user)} user={account.user} counts={{ concerts: workspace.concerts.length, artists: artistCount, venues: venues.length }} busy={account.busy} onSave={saveProfile} onCancel={() => navigate('dashboard')} />}")
path.write_text(text)
```

`accountClient` is created once at module scope so the hook effect cannot loop from a new client each render. All hooks still run before conditional returns. The health check continues to use its existing unauthenticated `/api/health` fetch. The demo remains accessible on explicit Explore; authentication failure never silently enters it.

- [ ] **Step 9: Append these narrow state styles to `frontend/src/account.css`.**

```css
.account-fields { border: 0; margin: 0; padding: 0; min-width: 0; display: grid; gap: 22px; }
.registration .account-fields { gap: 16px; }
.account-fields:disabled { cursor: progress; }
.account-field > select { width: 100%; min-height: 46px; padding: 12px 14px; border: 1px solid #d3d9c9; border-radius: 6px; background: var(--paper); color: var(--ink); font: inherit; font-size: 14px; }
.account-field > select[aria-invalid="true"] { border-color: #b87962; }
.account-field > select:focus-visible { outline: 2px solid #8ba84e; outline-offset: 2px; }
.account-field > small:not(.field-error) { color: var(--muted); line-height: 1.6; }
.password-visibility { width: 44px; height: 44px; }
.account-error { color: #a25d45; font-size: 13px; line-height: 1.6; margin: 12px 0; overflow-wrap: anywhere; }
.demo-notice { color: var(--muted); font-size: 11px; line-height: 1.6; margin: 0 0 20px; }
.account-gate { min-height: 100dvh; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 24px; padding: 32px 20px; text-align: center; }
.account-gate h1 { font-size: clamp(32px, 7vw, 52px); }
.account-gate > p { max-width: 40ch; line-height: 1.7; }
.account-gate > div { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
```

Keep the existing media queries, color variables, menu dimensions, and reduced-motion rule. Do not remove unrelated preview CSS while changing the account behavior.

- [ ] **Step 10: Run the UI and protocol tests, then lint/build and the complete existing suite.**

```sh
node --test tests/account.test.mjs tests/account-contract.test.mjs tests/account-api.test.mjs
npm run lint
npm run build
npm test
```

Expected: all new tests and the existing domain/server tests pass; TypeScript has no stale `Profile`/preview-helper imports. `npm test` follows the build because the production server tests consume `dist/`. A markup test failure is a UI contract regression; an SSR loader error requires fixing the test configuration rather than adding a test dependency.

- [ ] **Step 11: Inspect the integration diff before the browser acceptance gate.**

```sh
git diff -- frontend/src frontend/tests
rg -n 'PROFILE_KEY|ENTRY_KEY|DEFAULT_PROFILE|decodeProfile|profilePayload|onRegister|onEnter' frontend/src
rg -n 'localStorage|sessionStorage|Authorization|console\.' frontend/src/accountApi.ts frontend/src/useAccount.ts frontend/src/components/AuthView.tsx frontend/src/components/ProfileView.tsx
```

Expected: the first search has no stale account authority references; the second has no credential/account persistence, bearer header, or logging. `domain.ts` and the concert operations in `App.tsx` are unchanged. This step authorizes no commit.

### Task 3: Browser Acceptance and Accurate Account Documentation

**Files:** Modify only the account-related paragraphs in `frontend/README.md` and `README.md`. Record execution evidence in `frontend/design/account/qa/auth-integration-verification.md` when implementing; this plan does not create that record or alter the historical visual specification.

**Interfaces:** Consumes the completed React UI and existing five Laravel routes. Produces an acceptance record of real browser cookie/session requests, supported profile saves, truthful failures, accessibility, and demo-data preservation. No backend API changes, account migration, or concert persistence is introduced.

- [ ] **Step 1: Make the frontend account documentation describe the new behavior.**

Replace `frontend/README.md` from its `## Account design preview` heading onward with:

```markdown
## Account authentication

Registration and sign-in use the Laravel Sanctum cookie session. The app checks `GET /api/v1/me` before showing an account identity. Mutations first request `/sanctum/csrf-cookie`, then send the decoded `XSRF-TOKEN` as `X-XSRF-TOKEN` with browser credentials. There are no bearer tokens or browser-stored account identities. Logout ends the current backend session; a failed logout keeps the visible identity and shows an error.

Your profile saves name, email, and preferred language to Laravel. Email changes apply immediately; use the new address on the next sign-in. The current supported preference is English or the application default. Photo uploads, avatar color, city, bio, verification, and password reset are outside this integration.

Concerts remain fictional browser-local demo data under `spectacles.preview.v1`, shared across sign-ins. Account actions leave that collection intact. The old local profile and entry keys are ignored and left untouched. Explore the demo allows anonymous use without claiming an authenticated session.

Set `VITE_API_BASE_URL` to the backend origin and run the Vite development server for HMR. Frontend and API must share a parent domain for cookie authentication. Local Yerd uses `https://spectacles.test` and `https://api.spectacles.test`; backend stateful domains, allowed frontend origins, session domain, secure cookies, and session storage must match. `GET /api/health` remains a separate public health indicator.

Run `npm run lint`, `npm run build`, and `npm test`. Account protocol tests use fake fetch responses; markup tests use the installed Vite/React server renderer. Real cookie/session, interaction, responsive, and keyboard acceptance is recorded in `design/account/qa/auth-integration-verification.md` after browser testing. The historical image references and visual specification remain in `design/account/`.
```

- [ ] **Step 2: Append this integration note to the existing root README without replacing the backend setup or local changes.**

```markdown
### Frontend account integration

The React account screens use the five Sanctum routes documented above. Session restoration, registration, login, name/email/language changes, and logout use cookie authentication. The frontend does not store account credentials or identity in browser storage. Frontend and API must share a parent domain, with credentialed CORS and Sanctum/session settings matching the frontend origin.

The concert workspace remains a browser-local demo, shared across sign-ins; account authentication does not add server concert persistence. See [frontend account behavior](frontend/README.md#account-authentication) and the [frontend integration plan](docs/superpowers/plans/2026-10-23-sanctum-frontend-integration.md).
```

Also replace the two now-stale root README sentences using the exact changes below:

```python
from pathlib import Path
p = Path('README.md')
s = p.read_text()
s = s.replace('The backend supports Sanctum SPA sessions; the existing React account screens remain local previews.', 'The React account screens use Laravel Sanctum SPA sessions.')
s = s.replace('The existing React account screens remain browser-local previews; this backend task does not connect them.', 'The React account screens use these endpoints for session restoration, registration, login, supported profile changes, and logout.')
p.write_text(s)
```

Before appending, check whether a frontend account section already exists at execution time. Update that section in place with this content if it does; do not leave contradictory preview-only authentication claims or create duplicate sections. Preserve the separate current backend endpoint documentation.

- [ ] **Step 3: Start an isolated browser acceptance environment without changing private dotenv files or the shared database.**

At execution, reread `~/.config/agent-skills/runtime.md` before PHP/Composer commands and apply its current prefixes. The commands below use the plain `php` prefix verified during this planning session. Node remains version 24. Use separate terminals; a port conflict must stop startup rather than terminate a pre-existing process.

Backend terminal, from the repository root:

```sh
account_qa_dir=$(mktemp -d "${TMPDIR:-/tmp}/spectacles-account-qa.XXXXXX")
touch "$account_qa_dir/database.sqlite"
export APP_ENV=local APP_DEBUG=false APP_URL=http://localhost:8097
export APP_CONFIG_CACHE="$account_qa_dir/config.php"
export APP_ROUTES_CACHE="$account_qa_dir/routes.php"
export DB_URL= DB_CONNECTION=sqlite DB_DATABASE="$account_qa_dir/database.sqlite" DB_FOREIGN_KEYS=true
export SESSION_DRIVER=database SESSION_DOMAIN=null SESSION_SECURE_COOKIE=false SESSION_COOKIE=spectacles_account_qa_session
export SESSION_CONNECTION=sqlite DB_CACHE_CONNECTION=sqlite DB_CACHE_LOCK_CONNECTION=sqlite
export CACHE_STORE=database QUEUE_CONNECTION=sync
export SANCTUM_STATEFUL_DOMAINS=localhost:5179 FRONTEND_URLS=http://localhost:5179
cd backend
php artisan migrate --force
php artisan serve --host=localhost --port=8097
```

This starts Laravel against a new disposable SQLite database; migrations here do not apply the shared database's pending domain migrations. It reads the existing application key without printing it. Do not run `config:cache` or alter the existing service. Keep this terminal open for the test session.

Frontend terminal, from the repository root:

```sh
cd frontend
DEV_SERVER_URL= VITE_API_BASE_URL=http://localhost:8097 npm run dev -- --host=localhost --port=5179
```

Use `http://localhost:5179` in a clean browser profile, so frontend and API cookies share `localhost` despite different ports. Use only these disposable fixture credentials: `frontend-qa@example.com` and `frontend-second@example.com`, both with password `frontend-test-password-123`. A disposable database and clean browser profile are required because registration and profile edits are real writes. Close the temporary servers with Ctrl-C after testing; leave the temporary path in the acceptance record and remove only that verified temporary directory when its evidence is no longer needed.

- [ ] **Step 4: Run each acceptance case and record its observed result.**

| Row | Procedure | Required result |
| --- | --- | --- |
| 1 | Open a clean browser at `http://localhost:5179`; inspect the first `/api/v1/me` request. | Checking gate first; 401 opens login. No fabricated Jamie name/email appears. `/api/health` remains independent. |
| 2 | Sign in with `missing@example.com` and the fixture password; then submit registration with mismatched confirmation and with 37 `é` characters as both passwords. | Invalid login remains on the form with Laravel 422 errors. Confirmation and UTF-8 byte errors focus the correct input; invalid registration inputs cause no credential POST. No success toast or account identity. |
| 3 | Register `frontend-qa@example.com`, name `Frontend QA`, with the fixture password and matching confirmation. | One credential POST after CSRF GET; 201 enters dashboard. Menu shows returned name/email and true signed-in actions. Concert demo notice remains visible. |
| 4 | Reload; inspect `/me`; close and reopen the page with the same cookies. | Identity is restored only after 200 `/me`. No password or account identity is written to local/session storage. Cookie expiration or a clean browser returns to login. |
| 5 | Inspect every mutation's Network request; remove only the temporary XSRF cookie between two actions and retry. Block `/sanctum/csrf-cookie` for one submission, then unblock. | Cookie initialization precedes each mutation; credentials included, decoded header value matches fresh cookie, JSON body is correct. Failed CSRF initialization sends no credential mutation and shows a recoverable error. No bearer header. |
| 6 | On profile, edit the name without saving. Use DevTools offline mode and submit; restore connectivity. | Failure leaves the draft and confirmed menu identity unchanged. Re-enable network and save once; canonical server values replace identity only after 200. No optimistic success message. |
| 7 | Reach the login throttle with repeated invalid fixture submissions; restore normal operation after the configured window. For a rejected profile edit, use another fixture account's email. | 429 shows a wait-and-retry message; duplicate email 422 stays on the profile with field error and editable draft. There is no automatic mutation replay. |
| 8 | While signed in, go offline and choose Sign out. Reconnect and retry. Also expire/delete the temporary session cookie before a later sign-out attempt. | Failed logout retains the signed-in identity and shows an error. Successful 204 enters login; reload gets 401. Already-unauthenticated 401 also ends local account state. 419 prompts a deliberate retry. |
| 9 | With network throttling, double-click Sign in, Create account, Save changes, and Sign out. Reload during a slow startup `/me`, then reload again and finish authentication. | At most one credential/profile/logout mutation per pending action. Disabled forms cannot switch/Explore during submission. Aborted/late startup results never restore a stale identity or update an unmounted view. StrictMode's startup cleanup produces no visible error. |
| 10 | Save name, then email, then English, then Application default, inspecting each PATCH. Reload after each successful save. | Each PATCH contains only changed `name`, `email`, or `locale`; locale default sends `null`. Email is canonical lowercase from the server and works on next login. No city/bio/photo/color controls or payload fields. |
| 11 | Change name and locale, press Cancel, reopen profile. Reject a later edit with 422, then cancel. | Cancel sends no PATCH and restores the last confirmed values on reopen. Rejected edits remain visible until cancelled or corrected. Name/email/language labels and errors remain associated. |
| 12 | In the clean test profile, seed `spectacles.preview.profile.v1` with a fake preview identity and `spectacles.preview.entry.v1` with `register`. Record those exact strings plus `spectacles.preview.v1` after app initialization. Sign in, update profile, sign out, and reload without editing concerts. | Old profile/entry keys remain byte-for-byte unchanged and do not affect authentication. The initialized concert workspace remains unchanged by account actions. Existing unknown/corrupt workspace preservation continues to pass the domain tests. |
| 13 | Register `frontend-second@example.com`, sign out, then sign in as the first fixture. Compare menu/profile and demo concerts. | Each account gets only its own server-returned identity; no previous account flash. The same browser demo collection remains, explicitly labelled shared across sign-ins. No concert writes reach Laravel. |
| 14 | At 1280 px and 390 px widths, use keyboard only through login, register, profile, and menu; test Arrow keys, Home/End, Escape, outside click/focus leaving, password visibility, error focus, and Cancel. | Controls fit without horizontal overflow; disabled controls cannot submit; errors are announced; menu navigation skips disabled items, Escape returns focus, and focus order remains useful. Layout/photo/colors follow existing references; no unsupported profile editors reappear. |
| 15 | With DevTools request overrides or a temporary browser fetch interception confined to this test profile, return 200 HTML for `/me` and logout, 503 for a profile mutation, and 419 for a mutation after a valid CSRF response. Remove overrides afterwards. | `/me` shows unavailable Retry/Explore rather than a fake identity. HTML cannot confirm logout. 503 preserves the profile draft; 419 asks for a deliberate retry and sends exactly one mutation. No debug response text appears in UI. |

Rows 5/8/15 cover browser-only cookie and response failures beyond fake-fetch tests. Rows 9/14 are interaction acceptance gates: SSR markup passing does not waive them. For startup timeout, block the response until the client's 10-second limit and require the unavailable gate. If DevTools cannot reproduce a failure reliably, record it as unverified and complete it using the available browser automation request controls before marking acceptance complete; do not claim a passed row from inspection alone.

- [ ] **Step 5: Record the exact acceptance evidence without secrets.**

Use this initial content for `frontend/design/account/qa/auth-integration-verification.md` during execution, replacing each unchecked row with its observed result as it is tested:

```markdown
# Frontend account integration verification

- Implementation branch and commit (or uncommitted state): recorded from `git status` and `git log -1`.
- Runtime: Node version and PHP runtime prefix from current machine configuration.
- Browser and viewport: actual browser name/version; 1280 px and 390 px checks.
- API/UI origins: http://localhost:8097 and http://localhost:5179.
- Database: actual mktemp path; disposable SQLite only.
- Account fixtures: frontend-qa@example.com and frontend-second@example.com.
- Automated gates: exact commands, exit codes, and test counts.
- Browser acceptance: rows 1 through 15, each with action, observed HTTP status/UI outcome, and pass/fail.
- Screenshots: login, registration, profile, signed-in menu, guest menu, and failure state at desktop/mobile sizes, saved under this QA directory.
- Remaining limitations: list observed gaps; use “None” only after all rows pass.

Do not record passwords, cookie values, CSRF values, headers containing secrets, or raw credential request bodies.
```

- [ ] **Step 6: Run the final gates and inspect only intended changes.**

```sh
cd frontend
npm run lint
npm run build
npm test
cd ..
git diff --check
git status --short
git diff -- frontend/src frontend/tests frontend/README.md README.md
```

Require frontend gates to pass and all browser rows to have evidence. Report only checks actually run. Do not rerun the backend suite merely for frontend documentation; if browser testing reveals a backend contract mismatch, report it and resolve scope before changing server behavior.

- [ ] **Step 7: Hand off the verified frontend result.**

Report changed behavior, automated gates, browser acceptance, and any remaining failure. Commit/push/PR creation requires separate user authorization; this plan does not grant it. If later authorized, stage only intended frontend files and the agreed documentation, inspect the staged diff, and keep unrelated existing modifications and design artifacts out of the commit.

## Planning Self-Review

- Contract coverage: all five existing endpoints have a caller; CSRF bootstrapping, startup identity, registration/login, supported profile changes, and real logout have implementation steps and acceptance checks.
- Scope: frontend integration only. No new backend endpoints/columns, bearer tokens, upload mechanism, translations, password reset, verification, server concert storage, dependency, or runtime change.
- Type consistency: client, hook, form callbacks, nullable locale, and App props share the exact Task 1 types. The legacy profile module is removed only with all UI callers converted.
- Failure coverage: protocol tests pin CSRF/204/401/419/422/429/HTML/network failures; browser rows pin duplicate submissions, late responses, draft retention, failed logout, keyboard behavior, and old storage preservation.
- Visual coverage: the existing layouts, photo, colors, focus controls, and responsive CSS are retained; unsupported account editors are removed to match the approved API.
- Execution boundary: this document is the only artifact created by this planning request. No unresolved product decision blocks the described integration. Reconfirm actual locale configuration and source anchors if implementation begins after code drift.

Planning validation: extracted the proposed source and tests into a disposable copy, applied the guarded App patch, and passed 14 focused tests, TypeScript checking, and lint. The Python patch blocks compile and the document passes the fence/reference/placeholder scan. These checks validate the plan examples; product implementation and real-browser acceptance remain future execution work.
