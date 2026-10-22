import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { createApp } from '../server.mjs'

let server
let baseUrl
before(async () => {
  server = createApp().listen(0)
  await new Promise(resolve => server.once('listening', resolve))
  baseUrl = `http://localhost:${server.address().port}`
})
after(() => new Promise(resolve => server.close(resolve)))

test('serves the built SPA and client-side deep links', async () => {
  for (const path of ['/', '/projects/example']) {
    const response = await fetch(`${baseUrl}${path}`, { headers: { Accept: 'text/html' } })
    assert.equal(response.status, 200)
    assert.match(response.headers.get('content-type'), /text\/html/)
    assert.match(await response.text(), /<div id="root">/)
  }
})

test('serves built assets with immutable caching', async () => {
  const html = await (await fetch(baseUrl)).text()
  const script = html.match(/src="([^" ]+\.js)"/)[1]
  const response = await fetch(`${baseUrl}${script}`)
  assert.equal(response.status, 200)
  assert.match(response.headers.get('cache-control'), /immutable/)
  assert.match(response.headers.get('content-type'), /javascript/)
})

test('missing assets and API calls never return the SPA document', async () => {
  for (const path of ['/assets/missing.js', '/assets/missing', '/favicon-missing.svg', '/api/health']) {
    const response = await fetch(`${baseUrl}${path}`)
    assert.equal(response.status, 404)
    assert.doesNotMatch(await response.text(), /<div id="root">/)
  }
})
