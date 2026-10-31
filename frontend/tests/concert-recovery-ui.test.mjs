import assert from 'node:assert/strict'
import { before, after, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'
let server, context, openRecovery, Artist, Tour, Event, Setlist, Wizard
const owner = '00000000-0000-4000-8000-000000000001'
const id = n => `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`
const access = { enabled: false, sessionKey: owner, client: { search() { throw Error('No SSR requests') } }, onUnauthorized() {} }
before(async () => {
 server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/recovery-ui-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
 ;({ ConcertRecoveryContext: context } = await server.ssrLoadModule('/src/components/ConcertRecovery.tsx'))
 ;({ openRecovery } = await server.ssrLoadModule('/src/concertRecovery.ts'))
 ;({ WizardArtistStep: Artist } = await server.ssrLoadModule('/src/components/WizardArtistStep.tsx'))
 ;({ WizardTourStep: Tour } = await server.ssrLoadModule('/src/components/WizardTourStep.tsx'))
 ;({ WizardEventStep: Event } = await server.ssrLoadModule('/src/components/WizardEventStep.tsx'))
 ;({ WizardSetlistStep: Setlist } = await server.ssrLoadModule('/src/components/WizardSetlistStep.tsx'))
 ;({ ConcertWizard: Wizard } = await server.ssrLoadModule('/src/components/ConcertWizard.tsx'))
})
after(async () => { await server?.close() })
function fixture(fields, step = 'artist') {
 const map = new Map(), storage = { getItem: k => map.get(k) ?? null, setItem: (k,v) => map.set(k,v), removeItem: k => map.delete(k) }
 const initial = openRecovery(owner, undefined, storage)
 for(const [name,value] of Object.entries(fields)) initial.changeField(name, value)
 initial.changeState({ ...initial.snapshot.state, step })
 return openRecovery(owner, undefined, storage)
}
function render(Component, recovery) {
 return renderToStaticMarkup(createElement(context.Provider, { value: { controller: recovery, changed() {} } }, createElement(Component, { state: recovery.snapshot.state, onChange() {}, onEditingChange() {}, venues: [], catalog: access, musicbrainz: access })))
}
const artist = { name: 'Unconfirmed Artist', type: 'group', disambiguation: 'Keep details', selection: 'public-proof' }
test('restored Artist/Tour editors render actual typed values and explicit pending Event decisions', () => {
 const a = render(Artist, fixture({ artistEditor: { input: artist }, artistDecision: { remove: 0 } }))
 for(const value of ['Unconfirmed Artist','group','Keep details','MusicBrainz match selected','Keep selected Event']) assert.ok(a.includes(value))
 const t = render(Tour, fixture({ tourEditor: { input: { name: 'Unconfirmed Tour', selection: 'proof' }, mbid: id(2), context: 'Context' }, tourDecision: { add: { name: 'Local', catalogId: id(3) } } }, 'tour'))
 for(const value of ['Unconfirmed Tour','Context','Keep selected Event']) assert.ok(t.includes(value))
})
test('restored row and both nested child editors use recovered fields rather than original initial props', () => {
 for(const nested of [{ songEditor: { title: 'Unconfirmed Song', disambiguation: 'Song detail', selection: 'proof' } }, { performerEditor: artist }]) {
  const html = render(Setlist, fixture({ row: { id: id(4), type: 'song', title: 'Row partial', artist: 'Performer partial', tape: true }, ...nested }, 'setlist'))
  assert.match(html, /value="Row partial"/); assert.match(html, /value="Performer partial"/); assert.match(html, /checked=""/)
  assert.ok(html.includes(nested.songEditor ? 'Unconfirmed Song' : 'Unconfirmed Artist'))
  assert.ok(html.includes(nested.songEditor ? 'Song detail' : 'Keep details'))
  assert.match(html, /disabled=""[^>]*>Add row/)
 }
})
test('query text restores with results closed; a pending Event preview has an explicit recovery/dismiss path', () => {
 const html = render(Event, fixture({ eventQuery: 'Partial Event query', venueQuery: 'Partial Venue query', eventPreviewId: id(5) }, 'event'))
 assert.match(html, /value="Partial Event query"/); assert.match(html, /value="Partial Venue query"/)
 assert.match(html, /aria-expanded="false"/); assert.doesNotMatch(html, /role="listbox"/)
 assert.match(html, /Sign in to reload the selected Event preview/); assert.match(html, /Dismiss preview/)
})
test('creation exposes local-recovery and explicit-discard guidance without changing the single-form contract', () => {
 const html = renderToStaticMarkup(createElement(Wizard, { owner, venues: [], catalog: access, musicbrainz: access, onClose() {}, onSave: async () => {} }))
 assert.match(html, /Local draft storage is unavailable/); assert.match(html, /Discard draft/)
 assert.equal((html.match(/<form\b/g) ?? []).length, 1)
})
