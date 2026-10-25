export const WORKSPACE_KEY = 'spectacles.preview.v1'
export const TIMEZONE = 'America/Argentina/Buenos_Aires'

export type EventDate = { year: number | null; month: number | null; day: number | null }
export type CatalogChoice = { name: string; catalogId: string | null }
export type Venue = { id: string; name: string; city: string; neighborhood: string; lat: number | null; lng: number | null; catalogId?: string; timezone?: string | null }
export type SetlistEntry = { id: string; type: 'song' | 'note'; title: string; artist: string; tape: boolean; songCatalogId?: string; artistCatalogId?: string }
export type Concert = {
  id: string; title: string; artists: string[]; venueId: string | null; date: EventDate;
  eventType?: string; catalog?: { eventId?: string; artistIds: (string | null)[]; tours: CatalogChoice[] };
  time: string; timezone: string | null; interested: boolean; ticketsBought: boolean; notes: string; setlist: SetlistEntry[];
}
export type Workspace = { version: 1; concerts: Concert[]; venues: Venue[] }
export type DateRelation = 'past' | 'upcoming' | 'today' | 'unconfirmed'

export function todayKey(now = new Date(), timezone = TIMEZONE): string {
  const parts = new Intl.DateTimeFormat('en', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now)
  return `${parts.find(p => p.type === 'year')!.value}-${parts.find(p => p.type === 'month')!.value}-${parts.find(p => p.type === 'day')!.value}`
}

export function fromKey(key: string): EventDate {
  const [year, month, day] = key.split('-').map(Number)
  return { year, month, day }
}

export function validDate(date: EventDate): boolean {
  const { year, month, day } = date
  if (year === null) return month === null && day === null
  if (!Number.isInteger(year) || year < 1 || year > 9999) return false
  if (month === null) return day === null
  if (!Number.isInteger(month) || month < 1 || month > 12) return false
  if (day === null) return true
  if (!Number.isInteger(day) || day < 1) return false
  const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)
  return day <= [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month - 1]
}

export function dateKey(date: EventDate): string | null {
  if (!validDate(date) || date.year === null || date.month === null || date.day === null) return null
  return `${String(date.year).padStart(4, '0')}-${String(date.month).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`
}

export function formatDate(date: EventDate, long = false): string {
  if (date.year === null) return 'Date to confirm'
  if (date.month === null) return `${date.year} · date to confirm`
  const month = new Intl.DateTimeFormat('en', { month: long ? 'long' : 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(2000, date.month - 1, 1)))
  if (date.day === null) return `${month} ${date.year} · day to confirm`
  return `${month} ${String(date.day).padStart(2, '0')}, ${date.year}`
}

export function relation(date: EventDate, today: string): DateRelation {
  const key = dateKey(date)
  if (!key) return 'unconfirmed'
  return key < today ? 'past' : key > today ? 'upcoming' : 'today'
}

export function daysUntil(date: EventDate, today: string): number | null {
  const key = dateKey(date)
  if (!key) return null
  // Compare calendar days, independent of daylight-saving transitions and browser timezone.
  return Math.round((Date.parse(`${key}T00:00:00Z`) - Date.parse(`${today}T00:00:00Z`)) / 86_400_000)
}

export function sortConcerts(concerts: Concert[], descending = false): Concert[] {
  return [...concerts].sort((a, b) => {
    const ak = dateKey(a.date), bk = dateKey(b.date)
    if (!ak) return bk ? 1 : a.title.localeCompare(b.title)
    if (!bk) return -1
    return (ak.localeCompare(bk) || a.title.localeCompare(b.title)) * (descending ? -1 : 1)
  })
}

export function groupByDay(concerts: Concert[]): Map<string, Concert[]> {
  const days = new Map<string, Concert[]>()
  for (const concert of concerts) {
    const key = dateKey(concert.date)
    if (key) days.set(key, [...(days.get(key) ?? []), concert])
  }
  return days
}

export function uniqueVenues(workspace: Workspace): Venue[] {
  const ids = new Set(workspace.concerts.map(concert => concert.venueId))
  return workspace.venues.filter(venue => ids.has(venue.id))
}

export function moveEntry(entries: SetlistEntry[], id: string, offset: -1 | 1): SetlistEntry[] {
  const index = entries.findIndex(entry => entry.id === id)
  const destination = index + offset
  if (index < 0 || destination < 0 || destination >= entries.length) return entries
  const next = [...entries]
  ;[next[index], next[destination]] = [next[destination], next[index]]
  return next
}

export const publicUuid = (value: unknown): value is string => typeof value === 'string' && /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i.test(value)
const record = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value)
const text = (value: unknown): value is string => typeof value === 'string'
const nullableNumber = (value: unknown): value is number | null => value === null || typeof value === 'number' && Number.isFinite(value)

function validCatalogRefs(value: unknown, count: number): boolean {
  if (value === undefined) return true
  if (!record(value) || value.eventId !== undefined && !publicUuid(value.eventId) || !Array.isArray(value.artistIds) || value.artistIds.length !== count || !value.artistIds.every(id => id === null || publicUuid(id)) || !Array.isArray(value.tours)) return false
  return value.tours.every(tour => record(tour) && text(tour.name) && tour.name.trim() && (tour.catalogId === null || publicUuid(tour.catalogId)))
}

// Saved demo data is an external input too. Never replace an unreadable saved collection silently.
export function decodeWorkspace(raw: string): Workspace | null {
  try {
    const data: unknown = JSON.parse(raw)
    if (!record(data) || data.version !== 1 || !Array.isArray(data.concerts) || !Array.isArray(data.venues)) return null
    const venueIds = new Set<string>(), concertIds = new Set<string>()
    for (const venue of data.venues) {
      if (!record(venue) || !text(venue.id) || !venue.id || venueIds.has(venue.id) || !text(venue.name) || !venue.name.trim() || !text(venue.city) || !text(venue.neighborhood) || !nullableNumber(venue.lat) || !nullableNumber(venue.lng)) return null
      if ((venue.lat === null) !== (venue.lng === null) || venue.lat !== null && (Math.abs(venue.lat) > 90 || Math.abs(venue.lng!) > 180)) return null
      if (venue.catalogId !== undefined && !publicUuid(venue.catalogId)) return null
      if (venue.timezone !== undefined && venue.timezone !== null) { if (!text(venue.timezone)) return null; try { new Intl.DateTimeFormat('en', { timeZone: venue.timezone }) } catch { return null } }
      venueIds.add(venue.id)
    }
    for (const concert of data.concerts) {
      if (!record(concert) || !text(concert.id) || !concert.id || concertIds.has(concert.id) || !text(concert.title) || !concert.title.trim() || !Array.isArray(concert.artists) || !concert.artists.every(a => text(a) && a.trim()) || !record(concert.date)) return null
      if (![concert.date.year, concert.date.month, concert.date.day].every(nullableNumber) || !validDate(concert.date as EventDate)) return null
      if (concert.venueId !== null && (!text(concert.venueId) || !venueIds.has(concert.venueId))) return null
      if (!text(concert.time) || concert.time !== '' && !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(concert.time) || concert.timezone !== null && !text(concert.timezone) || !text(concert.notes) || typeof concert.interested !== 'boolean' || typeof concert.ticketsBought !== 'boolean' || !Array.isArray(concert.setlist)) return null
      if (concert.timezone !== null) { try { new Intl.DateTimeFormat('en', { timeZone: concert.timezone }) } catch { return null } }
      if (!validCatalogRefs(concert.catalog, concert.artists.length) || concert.eventType !== undefined && (!text(concert.eventType) || !concert.eventType.trim())) return null
      const entryIds = new Set<string>()
      for (const entry of concert.setlist) {
        if (!record(entry) || !text(entry.id) || !entry.id || entryIds.has(entry.id) || !['song', 'note'].includes(String(entry.type)) || !text(entry.title) || !entry.title.trim() || !text(entry.artist) || typeof entry.tape !== 'boolean') return null
        if (entry.songCatalogId !== undefined && (entry.type !== 'song' || !publicUuid(entry.songCatalogId))) return null
        if (entry.artistCatalogId !== undefined && (entry.type !== 'song' || !publicUuid(entry.artistCatalogId))) return null
        entryIds.add(entry.id)
      }
      concertIds.add(concert.id)
    }
    return data as Workspace
  } catch { return null }
}

export function createDemoWorkspace(today = todayKey()): Workspace {
  const venues: Venue[] = [
    { id: 'movistar', name: 'Movistar Arena', city: 'Buenos Aires', neighborhood: 'Villa Crespo', lat: -34.5943, lng: -58.4475 },
    { id: 'obras', name: 'Estadio Obras', city: 'Buenos Aires', neighborhood: 'Núñez', lat: -34.5446, lng: -58.4598 },
    { id: 'niceto', name: 'Niceto Club', city: 'Buenos Aires', neighborhood: 'Palermo', lat: -34.5905, lng: -58.4382 },
    { id: 'gran-rex', name: 'Teatro Gran Rex', city: 'Buenos Aires', neighborhood: 'San Nicolás', lat: -34.6033, lng: -58.3783 },
    { id: 'monumental', name: 'Estadio Monumental', city: 'Buenos Aires', neighborhood: 'Belgrano', lat: -34.5453, lng: -58.4498 },
    { id: 'luna', name: 'Luna Park', city: 'Buenos Aires', neighborhood: 'San Nicolás', lat: -34.5989, lng: -58.3687 },
  ]
  const shows: [string, string, number][] = [
    ['Arctic Monkeys', 'movistar', 15], ['The Strokes', 'obras', 28], ['Khruangbin', 'niceto', 43], ['Parcels', 'niceto', 8],
    ['The National', 'gran-rex', -1], ['Fontaines D.C.', 'obras', -6], ['Radiohead', 'monumental', -10], ['Nick Cave & The Bad Seeds', 'gran-rex', -27],
    ['Jungle', 'movistar', -47], ['Bandalos Chinos', 'luna', -70], ['Tame Impala', 'monumental', -91], ['Air', 'gran-rex', -105],
    ['Massive Attack', 'movistar', -130], ['Björk', 'gran-rex', -158], ['The Smile', 'luna', -178], ['Interpol', 'obras', -202],
    ['Gorillaz', 'monumental', -224], ['The National', 'luna', -245], ['Jungle', 'niceto', -265], ['Arctic Monkeys', 'movistar', -284],
    ['Bandalos Chinos', 'niceto', -300], ['Radiohead', 'monumental', -320], ['The Strokes', 'obras', -340], ['Air', 'gran-rex', -360],
  ]
  const songs: Record<string, string[]> = {
    Radiohead: ['Airbag', '2 + 2 = 5', 'Paranoid Android', 'Weird Fishes / Arpeggi', 'Nude', 'ENCORE', 'No Surprises', 'Karma Police', 'Everything in Its Right Place'],
    'The National': ['Once Upon a Poolside', 'Eucalyptus', 'Tropic Morning News', 'Bloodbuzz Ohio', 'The System Only Dreams in Total Darkness', 'ENCORE', 'About Today', 'Vanderlyle Crybaby Geeks'],
    'Fontaines D.C.': ['Romance', 'Jackie Down the Line', 'Televised Mind', 'A Lucid Dream', 'Starburster', 'Favourite'],
    'Arctic Monkeys': ['Sculptures of Anything Goes', 'Brianstorm', 'Snap Out of It', 'Crying Lightning', 'ENCORE', 'Do I Wanna Know?', 'R U Mine?'],
  }
  const concerts: Concert[] = shows.map(([artist, venueId, offset], i) => {
    const date = new Date(`${today}T12:00:00Z`)
    date.setUTCDate(date.getUTCDate() + offset)
    const setlist: SetlistEntry[] = offset < 0 && songs[artist] ? songs[artist].map((title, n) => ({ id: `entry-${i}-${n}`, type: title === 'ENCORE' ? 'note' : 'song', title, artist: title === 'ENCORE' ? '' : artist, tape: false })) : []
    return { id: `concert-${i}`, title: artist, artists: [artist], venueId, date: fromKey(date.toISOString().slice(0, 10)), time: '21:00', timezone: TIMEZONE, interested: true, ticketsBought: offset < 20, notes: '', setlist }
  })
  return { version: 1, concerts, venues }
}

export function filteredConcerts(concerts: Concert[], venues: Venue[], query: string, filter: string, today: string, venueId?: string): Concert[] {
  const term = query.trim().toLocaleLowerCase()
  return sortConcerts(concerts.filter(concert => {
    const venue = venues.find(item => item.id === concert.venueId)
    const matches = !term || [concert.title, ...concert.artists, venue?.name ?? '', venue?.city ?? ''].join(' ').toLocaleLowerCase().includes(term)
    const when = relation(concert.date, today)
    return matches && (!venueId || concert.venueId === venueId) && (filter === 'all' || filter === 'upcoming' && (when === 'upcoming' || when === 'today') || filter === when)
  }), filter === 'past')
}
