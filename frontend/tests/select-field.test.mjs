import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { fileURLToPath } from 'node:url'
import { createServer } from 'vite'
import react from '@vitejs/plugin-react'
let server, SelectField, ProfileView, SetlistsView, InteractionFieldset
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/select-field-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
  ;({ SelectField } = await server.ssrLoadModule('/src/components/SelectField.tsx'))
  ;({ ProfileView } = await server.ssrLoadModule('/src/components/ProfileView.tsx'))
  ;({ SetlistsView } = await server.ssrLoadModule('/src/components/SetlistsView.tsx'))
  ;({ InteractionFieldset } = await server.ssrLoadModule('/src/components/InteractionFieldset.tsx'))
})
after(async () => { await server?.close() })
const render = (Component, props) => renderToStaticMarkup(createElement(Component, props))
const selectedText = html => html.match(/class="select-value"[^>]*>([^<]*)<\/span>/)?.[1]
const user = { id: 'qa', name: 'QA', email: 'qa@example.test', locale: null }
const profileProps = { user, busy: false, counts: { concerts: 0, artists: 0, venues: 0 }, onSave: async () => user, onCancel() {} }
test('profile language retains application default, English, and a saved legacy locale', () => {
  const defaultHtml = render(ProfileView, profileProps)
  assert.equal(selectedText(defaultHtml), 'Application default')
  assert.match(defaultHtml, /English/)
  assert.doesNotMatch(defaultHtml, /current saved preference/)
  const legacy = render(ProfileView, { ...profileProps, user: { ...user, locale: 'es' } })
  assert.equal(selectedText(legacy), 'es (current saved preference)')
  assert.match(legacy, /Application default/)
  assert.match(legacy, /English/)
})
test('select trigger exposes invalid state and links its hint and field error', () => {
  const html = render(SelectField, { id: 'review-language', className: 'account-field', label: 'Language', value: 'en', options: [{ id: 'en', label: 'English' }], onChange() {}, error: 'Language rejected', description: 'Saved preference' })
  const trigger = (html.match(/<button\b[^>]*>/g) ?? []).find(button => button.includes('select-trigger'))
  assert.match(trigger, /id="review-language"/)
  assert.match(trigger, /aria-invalid="true"/)
  assert.match(trigger, /type="button"/)
  const descriptions = trigger.match(/aria-describedby="([^"]+)"/)?.[1].split(' ')
  assert.equal(descriptions?.length, 2)
  for (const id of descriptions) assert.ok(html.includes(`id="${id}"`), id)
})
test('selects disable for a busy profile or an inherited saving fieldset', () => {
  const busy = render(ProfileView, { ...profileProps, busy: true })
  const trigger = (busy.match(/<button\b[^>]*>/g) ?? []).find(button => button.includes('select-trigger'))
  assert.match(trigger, /disabled=""/)
  const inherited = renderToStaticMarkup(createElement(InteractionFieldset, { disabled: true }, createElement(SelectField, { className: 'setlist-select', label: 'Concert', value: 'a', options: [{ id: 'a', label: 'Alpha' }], onChange() {} })))
  assert.match(inherited, /<button[^>]*disabled=""/)
})
test('setlist selector retains date labels and the current newest-concert fallback', () => {
  const show = (id, title, month) => ({ id, title, date: { year: 2026, month, day: 2 }, artists: [], venueId: null, setlist: [] })
  const html = render(SetlistsView, { concerts: [show('a', 'Alpha', 1), show('b', 'Beta', 2)], venues: [], selectedId: 'removed', catalog: { enabled: false, client: {}, sessionKey: 'qa', onUnauthorized() {} }, onSelect() {}, onUpdate: async () => false })
  assert.equal(selectedText(html), 'Beta — Feb 02, 2026')
  assert.match(html, /Alpha — Jan 02, 2026/)
  assert.match(html, /Concert setlist/)
})
