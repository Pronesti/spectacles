import { useMemo, useState } from 'react'
import type { Workspace, Venue } from '../domain'
import { hasVenueCoordinates, visitedVenues } from '../venueVisits'
import { GoogleVenueMap } from './GoogleVenueMap'
import { Icon } from './Icon'

export function VenueMap({ workspace, today, onViewConcerts }: { workspace: Workspace; today: string; onViewConcerts: (venue: Venue) => void }) {
  const visits = useMemo(() => visitedVenues(workspace, today), [workspace, today])
  const located = useMemo(() => visits.filter(hasVenueCoordinates), [visits])
  const [selectedId, setSelectedId] = useState('')
  const selected = visits.find(({ venue }) => venue.id === selectedId)
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY?.trim() ?? ''
  const mapId = import.meta.env.VITE_GOOGLE_MAPS_MAP_ID?.trim() ?? ''
  const configured = Boolean(apiKey && mapId)
  const unlocated = visits.length - located.length

  return <>
    <p className="venue-total">{visits.length} venues visited</p>
    {unlocated > 0 && <div className="map-toolbar"><p>{unlocated} {unlocated === 1 ? 'venue needs' : 'venues need'} a saved location</p></div>}
    <div className="venue-layout">
      <section className="map-frame venue-google-frame" aria-label="Visited venue map">
        {located.length > 0 && configured ? <GoogleVenueMap visits={located} selectedId={selectedId} onSelect={setSelectedId} apiKey={apiKey} mapId={mapId} /> : <div className="map-empty-state">
          <Icon name="globe" size={32} />
          <h3>{!visits.length ? 'Your stages, on the map.' : !located.length ? 'Venue locations are not available yet.' : 'Your venue map is not available yet.'}</h3>
          <p>{!visits.length ? 'Venues from past concerts with confirmed dates will appear here.' : !located.length ? 'Your visited venues are listed alongside the map. Add their locations to show pins.' : 'Your visited venues are still available in the list.'}</p>
        </div>}
      </section>
      <aside className="venue-directory" aria-label="Visited venues">
        {visits.length ? visits.map(({ venue, count }, index) => <button className={`venue-item ${selected?.venue.id === venue.id ? 'selected' : ''}`} key={venue.id} onClick={() => setSelectedId(venue.id)} aria-pressed={selected?.venue.id === venue.id}>
          <span className={`venue-art venue-art-${index % 4}`}><Icon name="pin" size={25} /></span>
          <span><strong>{venue.name}</strong><small>{count} {count === 1 ? 'visit' : 'visits'}{venue.city && ` · ${venue.city}`}</small>{!hasVenueCoordinates({ venue, count }) && <small>Location unavailable</small>}</span>
          <Icon name="right" size={18} />
        </button>) : <div className="empty-state"><p>No visited venues yet.</p></div>}
      </aside>
    </div>
    {selected && <section className="selected-venue-detail" aria-label="Selected venue" aria-live="polite">
      <div><h3>{selected.venue.name}</h3><p>{[selected.venue.address, selected.venue.city].filter(Boolean).join(' · ') || 'Address to confirm'}</p><p>{selected.count} {selected.count === 1 ? 'visit' : 'visits'}{!hasVenueCoordinates(selected) && ' · Location unavailable; no map pin'}</p></div>
      <button className="button primary small" onClick={() => onViewConcerts(selected.venue)}>View concerts<Icon name="arrow" size={17} /></button>
    </section>}
  </>
}
