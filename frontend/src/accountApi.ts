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
