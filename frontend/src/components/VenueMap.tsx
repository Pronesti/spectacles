import { useState } from 'react'
import { uniqueVenues } from '../domain'
import type { Workspace, Venue } from '../domain'
import { Icon } from './Icon'

function project(venue: Venue): [number, number] {
  return [90 + ((venue.lng! + 58.48) / .15) * 660, 75 + ((-venue.lat! - 34.52) / .13) * 590]
}

export function VenueMap({ workspace, onViewConcerts }: { workspace: Workspace; onViewConcerts: (venue: Venue) => void }) {
  const venues = uniqueVenues(workspace)
  const cities = [...new Set(venues.map(v => v.city || 'City to confirm'))]
  const [city, setCity] = useState('Buenos Aires')
  const currentCity = cities.includes(city) ? city : cities[0] ?? 'Buenos Aires'
  const cityVenues = venues.filter(v => (v.city || 'City to confirm') === currentCity)
  const [selectedId, setSelectedId] = useState('movistar')
  const selected = cityVenues.find(v => v.id === selectedId) ?? cityVenues[0]
  const [zoom, setZoom] = useState(1)
  const located = cityVenues.filter(v => v.lat !== null && v.lng !== null)
  const isBuenosAires = currentCity === 'Buenos Aires'
  const count = (id: string) => workspace.concerts.filter(c => c.venueId === id).length
  // Demo coordinates are approximate. Off-city venues stay listed without fabricated pins.
  const mapped = isBuenosAires ? located.filter(v => v.lat! >= -34.66 && v.lat! <= -34.51 && v.lng! >= -58.49 && v.lng! <= -58.32) : []
  const selectedPoint = selected && mapped.some(v => v.id === selected.id) ? project(selected) : [400, 300]
  const centerX = Math.min(560, Math.max(240, selectedPoint[0])), centerY = Math.min(430, Math.max(250, selectedPoint[1]))
  return <><div className="map-toolbar"><p><strong>{venues.length}</strong> unique venues. A whole lot of memories.</p><label className="city-select"><Icon name="pin" size={18} /><span className="sr-only">City</span><select value={currentCity} onChange={e => { setCity(e.target.value); setZoom(1) }}>{(cities.length ? cities : ['Buenos Aires']).map(name => <option key={name}>{name}</option>)}</select></label></div>
    <div className="venue-layout"><section className="map-frame" aria-label="Illustrative venue map">
      <svg className="city-map" preserveAspectRatio="xMidYMid slice" viewBox={`${Math.min(800 - 800 / zoom, Math.max(0, centerX - 400 / zoom))} ${Math.min(700 - 700 / zoom, Math.max(0, centerY - 350 / zoom))} ${800 / zoom} ${700 / zoom}`} role="group" aria-label="Buenos Aires illustrated street map with selectable venue pins">
        <defs><pattern id="street-grid" width="28" height="36" patternUnits="userSpaceOnUse" patternTransform="rotate(-22)"><rect width="28" height="36" fill="#e8e9e0" /><path d="M0 0h28v36H0Z" fill="none" stroke="#fafbf6" strokeWidth="3" /></pattern><filter id="pin-shadow"><feDropShadow dx="0" dy="3" stdDeviation="3" floodOpacity=".16" /></filter></defs>
        <rect width="800" height="700" fill="url(#street-grid)" />
        <path d="M360-30C450 60 470 125 550 170S630 285 680 335 720 440 800 470V-30Z" fill="#c4d6d7" /><path d="M360-30C450 60 470 125 550 170S630 285 680 335 720 440 800 470" fill="none" stroke="#f8f8ef" strokeWidth="12" />
        <g fill="#cbd8b6"><path d="m270 95 100-25 76 93-98 60-65-45Z" /><path d="m365 210 85-25 70 92-85 22Z" /><path d="m110 475 84-33 63 90-67 50-70-30Z" /><path d="m465 395 73-20 21 66-65 20Z" /></g>
        <g fill="none" stroke="#fafbf6" strokeWidth="10"><path d="M-10 200 840 635M80-20 600 735M-30 530 685 110M-20 365 755 230" /></g><g fill="none" stroke="#cbd0bf" strokeWidth="1"><path d="M-10 200 840 635M80-20 600 735M-30 530 685 110M-20 365 755 230" /></g>
        <g fontFamily="Arial, sans-serif" fontSize="12" fill="#858b7d" letterSpacing="2"><text x="172" y="174">BELGRANO</text><text x="340" y="310">PALERMO</text><text x="112" y="390">VILLA CRESPO</text><text x="520" y="395">RECOLETA</text><text x="555" y="545">CENTRO</text><text x="620" y="100" fill="#76979b" fontSize="13">RÍO DE</text><text x="612" y="124" fill="#76979b" fontSize="13">LA PLATA</text></g>
        <g fontFamily="Arial, sans-serif" fontSize="10" fill="#969b8e"><text x="260" y="265" transform="rotate(29 260 265)">Av. Santa Fe</text><text x="306" y="480" transform="rotate(29 306 480)">Av. Corrientes</text><text x="335" y="165" transform="rotate(29 335 165)">Av. del Libertador</text></g>
        {mapped.map(venue => {
          const [x, y] = project(venue); const active = selected?.id === venue.id
          return <g key={venue.id} className={`map-pin ${active ? 'selected' : ''}`} role="button" tabIndex={0} aria-label={`${venue.name}, ${count(venue.id)} concerts`} aria-pressed={active} onClick={() => setSelectedId(venue.id)} onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setSelectedId(venue.id) } }} transform={`translate(${x} ${y})`} filter="url(#pin-shadow)"><title>{venue.name}</title><path d="M0 0C-3-8-17-17-17-30a17 17 0 1 1 34 0C17-17 3-8 0 0Z" fill={active ? '#d5f76a' : '#1d211e'} stroke="#f7f7f2" strokeWidth="2" /><circle cy="-29" r="5" fill={active ? '#1d211e' : '#d5f76a'} /></g>
        })}
      </svg>
      {!isBuenosAires && <div className="map-no-location"><Icon name="globe" size={32} /><h3>Another city, another stage.</h3><p>This concept includes an illustrative Buenos Aires map. Your venues are listed alongside it.</p></div>}
      <div className="map-zoom"><button aria-label="Zoom in" disabled={zoom >= 1.75} onClick={() => setZoom(v => Math.min(1.75, v + .25))}><Icon name="plus" /></button><button aria-label="Zoom out" disabled={zoom <= 1} onClick={() => setZoom(v => Math.max(1, v - .25))}><Icon name="minus" /></button></div>
      {selected && <div className="map-venue-detail"><span className="venue-symbol"><Icon name="music" size={28} /></span><div><h3>{selected.name}</h3><p>{selected.neighborhood ? `${selected.neighborhood}, ` : ''}{selected.city || 'City to confirm'}</p><small>{count(selected.id)} concerts in your collection{!mapped.some(v => v.id === selected.id) && ' · No map pin'}</small></div><button className="button primary small" onClick={() => onViewConcerts(selected)}>View concerts<Icon name="arrow" size={17} /></button></div>}
      <div className="map-caption">Illustrative city map · Approximate sample locations</div>
    </section><aside className="venue-directory" aria-label="Unique venues">{cityVenues.length ? cityVenues.map((venue, i) => <button className={`venue-item ${selected?.id === venue.id ? 'selected' : ''}`} key={venue.id} onClick={() => setSelectedId(venue.id)} aria-pressed={selected?.id === venue.id}><span className={`venue-art venue-art-${i % 4}`}><Icon name={i % 2 ? 'ticket' : 'music'} size={25} /></span><span><strong>{venue.name}</strong><small><Icon name="pin" size={13} />{count(venue.id)} concerts{!mapped.some(v => v.id === venue.id) ? ' · Unmapped' : ''}</small></span><Icon name="right" size={18} /></button>) : <div className="empty-state"><p>Add a concert with a venue to put a pin on your map.</p></div>}</aside></div></>
}
