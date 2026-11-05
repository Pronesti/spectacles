import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'
let server, Heatmap, CatalogChoices, InteractionFieldset, AccountTextField, SourceResults
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/aria-collections-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
  ;({ Heatmap } = await server.ssrLoadModule('/src/components/Heatmap.tsx'))
  ;({ CatalogChoices } = await server.ssrLoadModule('/src/components/CatalogChoices.tsx'))
  ;({ InteractionFieldset } = await server.ssrLoadModule('/src/components/InteractionFieldset.tsx'))
  ;({ AccountTextField } = await server.ssrLoadModule('/src/components/AccountTextField.tsx'))
  ;({ SourceResults } = await server.ssrLoadModule('/src/components/SourceResults.tsx'))
})
after(async () => { await server?.close() })
const render = (component, props) => renderToStaticMarkup(createElement(component, props))
test('annual activity includes every real date and keeps leap days and weekday placement', () => {
  const normal = render(Heatmap, { concerts: [], today: '2026-11-04', onOpen() {} })
  const leap = render(Heatmap, { concerts: [], today: '2024-11-04', onOpen() {} })
  assert.equal((normal.match(/role="option"/g) ?? []).length, 365)
  assert.equal((leap.match(/role="option"/g) ?? []).length, 366)
  assert.match(normal, /aria-label="Concert activity in 2026"/)
  assert.match(normal, /grid-column:1;grid-row:4/)
  assert.match(leap, /February 29, 2024: No concerts/)
  assert.match(leap, /December 31, 2024: No concerts/)
  assert.doesNotMatch(normal, /February 29, 2026/)
})
test('busy composite collections inherit the native fieldset interaction lock', () => {
  const catalog = { enabled: true, sessionKey: 'qa', client: {}, onUnauthorized() {} }
  const choices = [{ name: 'Same', catalogId: null }, { name: 'Same', catalogId: null }]
  const html = renderToStaticMarkup(createElement(InteractionFieldset, { disabled: true }, createElement(CatalogChoices, { kind: 'artists', label: 'Artists', choices, catalog, onChange() {} })))
  assert.equal((html.match(/role="row"/g) ?? []).length, 2)
  assert.ok((html.match(/aria-disabled="true"/g) ?? []).length >= 2)
  assert.match(html, /<input[^>]*disabled/)
  assert.doesNotMatch(html, /Sign in to search/)
})
test('account field keeps error and description associated with the focusable input', () => {
  const html = render(AccountTextField, { id: 'profile-email', label: 'Email', value: 'qa@example.test', type: 'email', autoComplete: 'email', maxLength: 254, onChange() {}, error: 'Already used', description: 'Use this email next time.' })
  assert.match(html, /<input[^>]*id="profile-email"/)
  assert.match(html, /aria-invalid="true"/)
  const descriptions = html.match(/aria-describedby="([^"]+)"/)?.[1].split(' ')
  assert.equal(descriptions?.length, 2)
  for (const id of descriptions) assert.ok(html.includes(`id="${id}"`), id)
  assert.match(html, /for="profile-email"/)
  assert.match(html, /Already used/)
})
test('provider result lists do not add a blank tab stop before a search', () => {
  assert.equal(render(SourceResults, { label: 'Results', items: [], getKey: item => item.id, getText: item => item.name, onAction() {} }), '')
  const html = render(SourceResults, { label: 'Results', items: [{ id: 'a', name: 'First' }], getKey: item => item.id, getText: item => item.name, onAction() {} })
  assert.match(html, /role="listbox"/)
  assert.match(html, /role="option"/)
  assert.match(html, /type="button"/)
})
