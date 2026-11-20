import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'
let server, ConcertForm, SetlistEditor, CatalogChoices, CalendarView, ConcertList
const catalog = { enabled: false, sessionKey: 'guest', client: { search: async () => { throw new Error('No SSR request') }, event: async () => { throw new Error('No SSR request') } }, onUnauthorized: () => {} }
const noop = () => {}
const render = (component, props) => renderToStaticMarkup(createElement(component, props))
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/catalog-editor-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
  ;({ ConcertForm } = await server.ssrLoadModule('/src/components/ConcertForm.tsx'))
  ;({ SetlistEditor } = await server.ssrLoadModule('/src/components/SetlistEditor.tsx'))
  ;({ CatalogChoices } = await server.ssrLoadModule('/src/components/CatalogChoices.tsx'))
  ;({ CalendarView } = await server.ssrLoadModule('/src/components/CalendarView.tsx'))
  ;({ ConcertList } = await server.ssrLoadModule('/src/components/ConcertList.tsx'))
})
after(async () => { await server?.close() })
test('guest concert editor offers optional catalog search while manual save remains available', () => {
  const html = render(ConcertForm, { catalog, venues: [], onClose: noop, onSave: noop })
  for (const label of ['Find an existing Event', 'Search shared Venues', 'Artists', 'Tours (optional)', 'Enter a name manually', 'Save concert']) assert.ok(html.includes(label))
  assert.match(html, /Sign in to search the shared catalog/)
  assert.match(html, /Saved to your account in the database/)
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

test('a pending concert save disables the editable fields and actions together', () => {
  const html = render(ConcertForm, { catalog, busy: true, venues: [], onClose: noop, onSave: noop })
  assert.match(html, /<fieldset disabled=""/)
  assert.match(html, /Concert title/)
  assert.match(html, /Save concert/)
})

test('calendar excludes unknown dates without inventing a day or adding a confirmation panel', () => {
  const concert = { id: 'unknown', title: 'Unknown date QA', artists: [], venueId: null, date: { year: null, month: null, day: null }, time: '', timezone: null, interested: false, ticketsBought: false, notes: '', setlist: [] }
  const html = render(CalendarView, { concerts: [concert], venues: [], today: '2026-10-23', onOpen: noop, onAdd: noop })
  assert.doesNotMatch(html, /Dates still to be confirmed/)
  assert.doesNotMatch(html, /Unknown date QA/)
  assert.doesNotMatch(html, /class="calendar-event/)
})

test('calendar days are named with a spoken date and the empty agenda nests under its heading', () => {
  const html = render(CalendarView, { concerts: [], venues: [], today: '2026-11-20', onOpen: noop, onAdd: noop })
  assert.match(html, /aria-label="Add concert on Sunday, November 1, 2026"/)
  assert.doesNotMatch(html, /aria-label="(Add concert on )?2026-11-01"/)
  assert.match(html, /<aside class="calendar-agenda" aria-label="Month at a glance">/)
  assert.match(html, /<h4>No concerts here, yet\.<\/h4>/)
})

test('concert rows read as one date, one name and one View action', () => {
  const concert = { id: 'c1', title: 'Don Omar', artists: ['Don Omar'], venueId: null, date: { year: 2027, month: 10, day: 23 }, time: '', timezone: null, interested: false, ticketsBought: false, notes: '', setlist: [] }
  const html = render(ConcertList, { concerts: [concert], venues: [], today: '2026-11-20', onOpen: noop, onEdit: noop })
  assert.match(html, /<span class="sr-only">Oct 23, 2027<\/span><span aria-hidden="true">Oct<\/span>/)
  assert.match(html, /class="concert-table-head" aria-hidden="true"/)
  assert.match(html, /<strong>Don Omar<\/strong><small aria-hidden="true">Don Omar<\/small>/)
  assert.match(html, /<span aria-hidden="true" style="display:contents"><button[^>]*tabindex="-1"/)
  assert.match(html, /aria-label="Edit Don Omar on Oct 23, 2027"/)
  assert.match(render(ConcertList, { concerts: [], venues: [], today: '2026-11-20', onOpen: noop }), /<h2>No concerts here, yet\.<\/h2>/)
})
