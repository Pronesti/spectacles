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
