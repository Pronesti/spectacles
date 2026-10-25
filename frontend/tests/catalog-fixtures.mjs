export const id = number => `00000000-0000-4000-8000-${String(number).padStart(12, '0')}`
const ref = (type, number) => ({ type, id: id(number) })
const obj = (type, number, attributes, relationships) => ({ ...ref(type, number), attributes, ...(relationships ? { relationships } : {}) })
const rel = data => ({ data })
export function catalogFixture() {
  const a = obj('artists', 1, { name: 'Björk, guest', type: 'person', disambiguation: 'First artist' })
  const b = obj('artists', 2, { name: 'Björk, guest', type: 'person', disambiguation: 'Another artist' })
  const venue = obj('venues', 3, { name: 'Sala Ñ', street: null, house_number: '26-35', postal_code: 'C001', city: null, region: null, country_code: 'AR', lat: '0.0000000', lng: '0.0000000', timezone: null })
  const song = obj('songs', 4, { title: 'Canción', disambiguation: 'Composition context' })
  const tour = obj('tours', 5, { name: 'Gira del sur' }, { artistLinks: rel([ref('tour_artists', 6)]) })
  const tourArtist = obj('tour_artists', 6, {}, { artist: rel(ref('artists', 2)) })
  const event = obj('events', 7, { title: 'Noche de música', type: 'concert', year: 2024, month: null, day: null, local_start_time: '00:00:07', timezone: null }, { venue: rel(ref('venues', 3)), artistLinks: rel([ref('event_artists', 8), ref('event_artists', 9)]), tourLinks: rel([ref('event_tours', 10)]) })
  const first = obj('event_artists', 8, { position: 1 }, { artist: rel(ref('artists', 1)) })
  const last = obj('event_artists', 9, { position: null }, { artist: rel(ref('artists', 2)) })
  const eventTour = obj('event_tours', 10, {}, { tour: rel(ref('tours', 5)) })
  const included = [a, b, venue, tour, tourArtist, first, last, eventTour]
  const page = rows => ({ data: rows, included, meta: { current_page: 1, per_page: 20, last_page: 1, total: rows.length }, links: { next: 'https://untrusted.invalid/never-follow' } })
  return { a, b, venue, song, tour, event, detail: { data: event, included }, page }
}
