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
