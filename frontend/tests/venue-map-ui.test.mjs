import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'
let server, VenueMap
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/venue-map-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' })
  ;({ VenueMap } = await server.ssrLoadModule('/src/components/VenueMap.tsx'))
})
after(async () => { await server?.close() })
test('directory includes visited venues across cities, counts repeat visits, and preserves unlocated venues', () => {
  const venue = (id, city, lat, lng) => ({ id, name: id, city, lat, lng, neighborhood: '' })
  const show = (id, venueId, day = 2) => ({ id, venueId, date: { year: 2026, month: 11, day } })
  const html = renderToStaticMarkup(createElement(VenueMap, { today: '2026-11-03', onViewConcerts() {}, workspace: { venues: [venue('London Hall', 'London', 51.5, -.1), venue('Buenos Aires Hall', 'Buenos Aires', null, null), venue('Future Hall', 'Paris', 48, 2)], concerts: [show('a', 'London Hall'), show('b', 'London Hall'), show('c', 'Buenos Aires Hall'), show('d', 'Future Hall', 4)] } }))
  for (const content of ['London Hall', 'Buenos Aires Hall', '2 visits', '1 visit', 'Location unavailable', 'Visited venues']) assert.ok(html.includes(content), content)
  assert.ok(!html.includes('Future Hall'))
  assert.ok(!html.includes('illustrat'))
  assert.ok(!html.includes('City</span><select'))
})
