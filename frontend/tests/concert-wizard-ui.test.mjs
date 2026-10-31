import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'
let server, WizardArtistStep, WizardTourStep, ConcertWizard, EntityAutocomplete, ArtistDraftFields, SongDraftFields, WizardEventStep, WizardSetlistStep, createWizard
const catalog = { enabled: false, sessionKey: 'guest', client: { search: async () => { throw new Error('No SSR fetch') }, event: async () => { throw new Error('No SSR fetch') } }, onUnauthorized: () => {} }
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/wizard-ui-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
  ;({ createWizard } = await server.ssrLoadModule('/src/concertWizard.ts'))
  ;({ WizardArtistStep } = await server.ssrLoadModule('/src/components/WizardArtistStep.tsx'))
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
test('wizard starts with four named steps, one form, Continue and no early Save', () => {
  const html = render(ConcertWizard, { venues: [], catalog, musicbrainz, onClose: () => {}, onSave: async () => {} })
  for (const label of ['Artist', 'Tour', 'Event', 'Setlist']) assert.ok(html.includes(label))
  assert.match(html, /aria-current="step"/); assert.equal((html.match(/<form\b/g) ?? []).length, 1); assert.match(html, /Continue/); assert.doesNotMatch(html, />Save concert</)
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

test('manual Venue with unknown city does not display the legacy default city', () => {
  const state = createWizard()
  state.draft.pendingVenue = { id: 'venue', name: 'Unknown City Hall', city: '', lat: null, lng: null, neighborhood: '' }
  state.draft.concert.venueId = 'venue'
  state.draft.city = 'Buenos Aires'
  const html = render(WizardEventStep, { state, venues: [], catalog, musicbrainz, onChange() {}, onEditingChange() {} })
  assert.match(html, /Unknown City Hall/); assert.doesNotMatch(html, /Buenos Aires/)
})

test('Tour keeps manual/optional entry and scopes search to selected Artists', () => {
  const state = createWizard()
  state.draft.artists = [{ name: 'Taylor Swift', catalogId: '00000000-0000-4000-8000-000000000001' }]
  const html = render(WizardTourStep, { state, venues: [], catalog, musicbrainz,
    onChange: () => {}, onEditingChange: () => {} })
  assert.match(html, /Search tour/)
  assert.match(html, /Tour for Taylor Swift/); assert.doesNotMatch(html, /type="checkbox"/)
  assert.match(html, /Create manually/)
  assert.match(html, /Skip this step/)
  assert.doesNotMatch(html, /<form/)
})


test('creation hides selected Tour search and unifies Event/Venue providers without source actions', () => {
  const state = createWizard()
  state.draft.artists = [{ name: 'Taylor Swift', catalogId: '00000000-0000-4000-8000-000000000001' }]
  state.draft.tours = [{ name: 'Selected Tour', catalogId: '00000000-0000-4000-8000-000000000002' }]
  const props = { state, venues: [], catalog, musicbrainz, onChange() {}, onEditingChange() {} }
  const tour = render(WizardTourStep, props)
  assert.equal((tour.match(/role="combobox"/g) ?? []).length, 0)
  assert.doesNotMatch(tour, /<select|Tour name on setlist.fm/)
  assert.match(tour, /Selected Tour/); assert.match(tour, /Change/)
  const event = render(WizardEventStep, props)
  assert.equal((event.match(/role="combobox"/g) ?? []).length, 2)
  assert.doesNotMatch(event, /Performance search|Search MusicBrainz Places<input|<label>Venue<select/)
  assert.doesNotMatch(event, /Search setlist.fm|Search MusicBrainz Places|Search MusicBrainz/)
})


test('legacy multi-selection drafts remain visible with explicit resolution actions', () => {
  const state = createWizard()
  state.draft.artists = [{ name: 'First Artist', catalogId: null }, { name: 'Second Artist', catalogId: null }]
  state.draft.tours = [{ name: 'First Tour', catalogId: null }, { name: 'Second Tour', catalogId: null }]
  const props = { state, venues: [], catalog, musicbrainz, onChange() {}, onEditingChange() {} }
  const artist = render(WizardArtistStep, props), tour = render(WizardTourStep, props)
  for (const name of ['First Artist', 'Second Artist']) assert.ok(artist.includes(`Keep only ${name}`))
  for (const name of ['First Tour', 'Second Tour']) assert.ok(tour.includes(`Keep only ${name}`))
  assert.equal(state.draft.artists.length, 2); assert.equal(state.draft.tours.length, 2)
  assert.doesNotMatch(artist + tour, /Multiple Artists or an unknown lineup|Search MusicBrainz|Search setlist.fm/)
})
