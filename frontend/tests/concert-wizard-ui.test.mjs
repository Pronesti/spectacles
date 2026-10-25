import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'
let server, WizardTourStep, ConcertWizard, EntityAutocomplete, ArtistDraftFields, SongDraftFields, WizardEventStep, WizardSetlistStep, createWizard
const catalog = { enabled: false, sessionKey: 'guest', client: { search: async () => { throw new Error('No SSR fetch') }, event: async () => { throw new Error('No SSR fetch') } }, onUnauthorized: () => {} }
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/wizard-ui-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
  ;({ createWizard } = await server.ssrLoadModule('/src/concertWizard.ts'))
  ;({ WizardTourStep } = await server.ssrLoadModule('/src/components/WizardTourStep.tsx'))
  ;({ WizardEventStep } = await server.ssrLoadModule('/src/components/WizardEventStep.tsx'))
  ;({ WizardSetlistStep } = await server.ssrLoadModule('/src/components/WizardSetlistStep.tsx'))
  ;({ ConcertWizard } = await server.ssrLoadModule('/src/components/ConcertWizard.tsx'))
  ;({ EntityAutocomplete } = await server.ssrLoadModule('/src/components/EntityAutocomplete.tsx'))
  ;({ ArtistDraftFields } = await server.ssrLoadModule('/src/components/ArtistDraftFields.tsx'))
  ;({ SongDraftFields } = await server.ssrLoadModule('/src/components/SongDraftFields.tsx'))
})
after(async () => { await server?.close() })

const musicbrainz = { ...catalog, client: { search: async () => { throw new Error('No SSR fetch') } } }
const render = (Component, props) => renderToStaticMarkup(createElement(Component, props))
test('wizard starts with four named steps, one form, Next and no early Save', () => {
  const html = render(ConcertWizard, { venues: [], catalog, musicbrainz, onClose: () => {}, onSave: async () => {} })
  for (const label of ['Artist', 'Tour', 'Event', 'Setlist']) assert.ok(html.includes(label))
  assert.match(html, /aria-current="step"/); assert.equal((html.match(/<form\b/g) ?? []).length, 1); assert.match(html, /Next/); assert.doesNotMatch(html, />Save concert</)
})
test('combined autocomplete and explicit entity fields expose accessible non-submit actions', () => {
  const html = render(EntityAutocomplete, { kind: 'artists', label: 'Find Artist', catalog, musicbrainz, onPick: () => {} })
  assert.match(html, /role="combobox"/); assert.match(html, /aria-autocomplete="list"/); assert.match(html, /aria-expanded="false"/); assert.match(html, /Create manually/); assert.doesNotMatch(html, /<form/)
  for (const [Component, initial] of [[ArtistDraftFields, { name: '', type: null, disambiguation: null, selection: null }], [SongDraftFields, { title: '', disambiguation: null, selection: null }]]) {
    const fields = render(Component, { initial, onConfirm: () => {}, onCancel: () => {} }); assert.doesNotMatch(fields, /<form/); assert.match(fields, /type="button"[^>]*disabled/)
  }
})

test('Event exposes nonmusic and unknown-fact paths; Setlist remains skippable with draft-only actions', () => {
  const props = {state:createWizard(), venues:[], catalog, musicbrainz, onChange:()=>{}, onEditingChange:()=>{}}
  const html = render(WizardEventStep, props)
  for (const label of ['Event type', 'theatre', 'Date precision', 'Month only', 'Year only', 'Date to confirm', 'Local time', 'Timezone', 'Venue']) assert.ok(html.includes(label))
  assert.match(html, /step="1"/); assert.doesNotMatch(html, /<form/)
  const setlist = render(WizardSetlistStep, props)
  assert.match(setlist, /Add song/); assert.match(setlist, /Add note/); assert.match(setlist, /empty setlist/); assert.doesNotMatch(setlist, /<form/)
})

test('Tour keeps manual/optional entry and scopes search to selected Artists', () => {
  const state = createWizard()
  state.draft.artists = [{ name: 'Taylor Swift', catalogId: '00000000-0000-4000-8000-000000000001' }]
  const html = render(WizardTourStep, { state, venues: [], catalog, musicbrainz,
    onChange: () => {}, onEditingChange: () => {} })
  assert.match(html, /Find Tours/)
  assert.match(html, /Tours match any selected Artist/); assert.doesNotMatch(html, /type="checkbox"/)
  assert.match(html, /Create manually/)
  assert.match(html, /Skip tour/)
  assert.doesNotMatch(html, /<form/)
})
