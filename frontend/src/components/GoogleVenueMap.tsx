import { useEffect, useRef, useState } from 'react'
import { importLibrary, setOptions } from '@googlemaps/js-api-loader'
import type { LocatedVenueVisit } from '../venueVisits'

let configured = false

export function GoogleVenueMap({ visits, selectedId, onSelect, apiKey, mapId }: {
  visits: LocatedVenueVisit[]; selectedId: string; onSelect: (id: string) => void; apiKey: string; mapId: string
}) {
  const root = useRef<HTMLDivElement>(null)
  const markerNodes = useRef<{ id: string; node: HTMLSpanElement; marker: google.maps.marker.AdvancedMarkerElement }[]>([])
  const [map, setMap] = useState<google.maps.Map | null>(null)
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading')

  useEffect(() => {
    let disposed = false
    let markers: google.maps.marker.AdvancedMarkerElement[] = []
    let listeners: google.maps.MapsEventListener[] = []
    if (!configured) {
      setOptions({ key: apiKey, v: 'weekly', authReferrerPolicy: 'origin' })
      configured = true
    }
    const timer = window.setTimeout(() => {
      disposed = true
      setStatus('error')
    }, 15_000)
    void Promise.all([importLibrary('maps'), importLibrary('marker')]).then(([maps, marker]) => {
      if (disposed || !root.current) return
      window.clearTimeout(timer)
      const nextMap = new maps.Map(root.current, {
        mapId,
        center: { lat: visits[0].venue.lat, lng: visits[0].venue.lng },
        zoom: 13,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
        cameraControl: false,
        zoomControl: true,
        gestureHandling: 'cooperative',
      })
      const bounds = new google.maps.LatLngBounds()
      markers = visits.map(({ venue, count }) => {
        const position = { lat: venue.lat, lng: venue.lng }
        bounds.extend(position)
        const pin = document.createElement('span')
        pin.className = 'venue-count-marker'
        pin.textContent = String(count)
        pin.setAttribute('aria-hidden', 'true')
        const item = new marker.AdvancedMarkerElement({ map: nextMap, position, title: `${venue.name}, ${count} ${count === 1 ? 'visit' : 'visits'}` })
        item.append(pin)
        markerNodes.current.push({ id: venue.id, node: pin, marker: item })
        listeners.push(item.addListener('click', () => onSelect(venue.id)))
        return item
      })
      if (visits.length > 1) {
        nextMap.fitBounds(bounds, 48)
        listeners.push(google.maps.event.addListenerOnce(nextMap, 'idle', () => {
          if ((nextMap.getZoom() ?? 0) > 15) nextMap.setZoom(15)
        }))
      }
      setMap(nextMap)
      setStatus('ready')
    }).catch(() => {
      if (!disposed) {
        window.clearTimeout(timer)
        setStatus('error')
      }
    })
    return () => {
      disposed = true
      window.clearTimeout(timer)
      listeners.forEach(listener => listener.remove())
      markers.forEach(marker => { marker.map = null })
      markerNodes.current = []
    }
  }, [visits, apiKey, mapId, onSelect])

  useEffect(() => {
    markerNodes.current.forEach(({ id, node, marker }) => {
      node.classList.toggle('is-selected', id === selectedId)
      marker.zIndex = id === selectedId ? 1000 : undefined
    })
    const visit = visits.find(({ venue }) => venue.id === selectedId)
    if (!map || !visit) return
    map.panTo({ lat: visit.venue.lat, lng: visit.venue.lng })
    map.setZoom(15)
  }, [map, selectedId, visits])

  return <>
    <div ref={root} className="google-venue-map" aria-label="Google map of visited venues" />
    {status !== 'ready' && <div className="map-load-state" role={status === 'error' ? 'alert' : 'status'}>
      {status === 'loading' ? 'Loading your venue map…' : 'Google Maps could not load. Your venues are still available in the list.'}
    </div>}
  </>
}
