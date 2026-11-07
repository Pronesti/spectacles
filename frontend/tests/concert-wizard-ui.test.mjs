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
  assert.match(html, /aria-current="step"/); assert.equal((html.match(/<form\b/g) ?? []).length, 1); assert.match(html, /Continue/); assert.doesNotMatch(html, />Save concert</); assert.doesNotMatch(html, />Cancel<|>Discard draft<|>Create manually</); assert.match(html, /aria-label="Close dialog"/)
})
test('combined autocomplete and explicit entity fields expose accessible non-submit actions', () => {
  const html = render(EntityAutocomplete, { kind: 'artists', label: 'Find Artist', catalog, musicbrainz, onPick: () => {} })
  assert.match(html, /role="combobox"/); assert.match(html, /aria-autocomplete="list"/); assert.match(html, /aria-expanded="false"/); assert.match(html, /Create manually/); assert.doesNotMatch(html, /<form/)
  for (const [Component, initial] of [[ArtistDraftFields, { name: '', type: null, disambiguation: null, selection: null }], [SongDraftFields, { title: '', disambiguation: null, selection: null }]]) {
    const fields = render(Component, { initial, onConfirm: () => {}, onCancel: () => {} }); assert.doesNotMatch(fields, /<form/); assert.match(fields, /type="button"[^>]*disabled/)
  }
})

test('manual Event emphasizes its date and omits technical fields; Setlist remains skippable', () => {
  const props = {state:createWizard(), venues:[], catalog, musicbrainz, onChange:()=>{}, onEditingChange:()=>{}}
  const html = render(WizardEventStep, props)
  assert.match(html, /event-date-label/); assert.match(html, /type="date" required/); assert.match(html, /Search venue…/)
  assert.doesNotMatch(html, /Date precision|Event type|Local time|Timezone|More details|Concert title/)
   assert.doesNotMatch(html, /<form/)
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
  assert.doesNotMatch(html, />Create manually</)
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


test('creation selectors share React Aria and retain their labels and closed initial state', () => {
  const state = createWizard()
  const props = { state, venues: [], catalog, musicbrainz, onChange() {}, onEditingChange() {} }
  const artist = render(WizardArtistStep, props)
  assert.match(artist, /data-rac=""/)
  assert.match(artist, /role="combobox"/)
  assert.match(artist, /placeholder="Search artist…"/)
  assert.match(artist, /aria-expanded="false"/)
  assert.doesNotMatch(artist, />Create manually</)
  state.draft.artists = [{ name: 'Taylor Swift', catalogId: '00000000-0000-4000-8000-000000000001' }]
  const tour = render(WizardTourStep, props)
  assert.match(tour, /placeholder="Search tour…"/)
  assert.match(tour, /data-rac=""/)
  const event = render(WizardEventStep, props)
  for (const label of ['Search venue…', 'Search event or performance…']) assert.ok(event.includes(label))
  assert.equal((event.match(/role="combobox"/g) ?? []).length, 2)
  const searchInputs = (event.match(/<input\b[^>]*>/g) ?? []).filter(input => input.includes('role="combobox"'))
  assert.equal(searchInputs.length, 2)
  for (const input of searchInputs) assert.match(input, /aria-expanded="false"/)
  assert.match(event, /data-rac=""/)
})


test('Artist review is read-only with edit and clear controls and delegates confirmation to Continue', () => {
  const html = render(ArtistDraftFields, { review: true, initial: { name: 'Don Omar', type: 'Person', disambiguation: 'Puerto Rican singer', selection: 'proof' }, onConfirm() {}, onCancel() {} })
  assert.match(html, /class="creation-selection" aria-label="Selected Artist"/); assert.match(html, /<strong>Don Omar<\/strong>/); assert.doesNotMatch(html, /<input/)
  assert.match(html, /aria-label="Edit Artist"/)
  assert.match(html, /aria-label="Clear Artist"/)
  assert.match(html, /Person · Puerto Rican singer/)
  assert.doesNotMatch(html, /<summary|>Use artist<|>Cancel</)
  const empty = render(ArtistDraftFields, { review: true, initial: { name: '', type: null, disambiguation: null, selection: null }, onConfirm() {}, onCancel() {} })
  assert.doesNotMatch(empty, /readOnly=/)
  assert.match(empty, /aria-label="Finish editing Artist"/)
})


test('upstream overwrite reviews appear only beside the affected Tour and Event', () => {
  const state = createWizard()
  state.draft.artists = [{ name: 'New Artist', catalogId: '00000000-0000-4000-8000-000000000011' }]
  state.draft.tours = [{ name: 'Original Tour', catalogId: '00000000-0000-4000-8000-000000000012' }]
  state.draft.eventId = '00000000-0000-4000-8000-000000000013'
  state.draft.concert.title = 'Original Event'
  const originalArtists = [{ name: 'Original Artist', catalogId: '00000000-0000-4000-8000-000000000014' }]
  state.draft.lineupReview = { tour: { artists: originalArtists }, event: { artists: originalArtists, tours: state.draft.tours } }
  const props = { state, venues: [], catalog, musicbrainz, onChange() {}, onEditingChange() {} }
  const artist = render(WizardArtistStep, props)
  assert.match(artist, /New Artist/)
  assert.doesNotMatch(artist, /role="alert"|Create new Event using these details|Clear previous Tour/)
  const tour = render(WizardTourStep, props)
  assert.match(tour, /Original Tour/); assert.match(tour, /Original Artist/)
  assert.match(tour, /Clear previous Tour/)
  assert.doesNotMatch(tour, /Create new Event using these details/)
  const event = render(WizardEventStep, props)
  assert.match(event, /Original Event/); assert.match(event, /Original Artist/)
  assert.match(event, /Create new Event using these details/)
  assert.match(event, /Keep selected Event/)
})


test('fetched and existing Events show their summary and personal fields without manual Venue or concert controls', () => {
  for(const source of ['catalog','provider']) {
    const state = createWizard()
    state.draft.artists = [{name:'Saved Artist', catalogId:'00000000-0000-4000-8000-000000000021'}]
    state.draft.concert.title = 'Selected performance'
    state.draft.dateText = '2026-11-07'
    const venue = {id:'00000000-0000-4000-8000-000000000022',name:'Fetched Venue',city:'City',lat:null,lng:null,neighborhood:''}
    state.draft.concert.venueId=venue.id
    if(source==='catalog') state.draft.eventId='00000000-0000-4000-8000-000000000023'
    else state.draft.providerEvent={key:'00000000-0000-4000-8000-000000000024',ref:{provider:'setlist_fm',resourceType:'setlist',externalId:'abc'},selection:'proof'}
    const html=render(WizardEventStep,{state,venues:[venue],catalog,musicbrainz,onChange(){},onEditingChange(){}})
    assert.match(html,/Selected performance/); assert.match(html,/Fetched Venue/)
    assert.match(html,/Event \/ performance/); assert.match(html,/Notes/); assert.match(html,/Interested/)
    assert.doesNotMatch(html,/aria-label="Selected Venue"|Venue details|Concert details|Add concert details|Search options|Performance period|Concert title|Date precision/)
  }
  const manual=render(WizardEventStep,{state:createWizard(),venues:[],catalog,musicbrainz,onChange(){},onEditingChange(){}})
  assert.match(manual,/Search venue…/);assert.match(manual,/event-date-label/);assert.doesNotMatch(manual,/Concert title|Date precision/)
  assert.doesNotMatch(manual,/Search options|Performance period|Venue details/)
})


test('Event reselection has no keep action, overwrite confirmation, or old manual details', () => {
 const state=createWizard()
 state.draft.eventChoicePending=true
 state.draft.concert.title='Previous event'
 const html=render(WizardEventStep,{state,venues:[],catalog,musicbrainz,onChange(){},onEditingChange(){}})
 assert.match(html,/Search event or performance…/)
 assert.doesNotMatch(html,/Keep selected Event|Changing these details creates a new Event|Add concert details|Search venue…/)
 assert.match(html,/Notes/)
})


test('manual Event replaces search with a selected summary and accessible edit and clear icons', () => {
 const state=createWizard();state.draft.manualEvent=true;state.draft.concert.title='My Event';state.draft.dateText='2026-11-07'
 const html=render(WizardEventStep,{state,venues:[],catalog,musicbrainz,onChange(){},onEditingChange(){}})
 assert.match(html,/aria-label="Selected Event \/ performance"/);assert.match(html,/<strong>My Event<\/strong>/)
 assert.match(html,/aria-label="Edit Event name"/);assert.match(html,/aria-label="Clear Event"/)
 assert.match(html,/event-date-label/);assert.match(html,/value="2026-11-07"/)
 assert.doesNotMatch(html,/Search event or performance…|Concert title|Date precision|More details|Local time|Timezone/)
})
