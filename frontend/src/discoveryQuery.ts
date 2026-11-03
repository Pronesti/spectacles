import { tourSuggestionKey } from './catalogProviders.ts'
import type { TourSearchSuggestion } from './catalogProviders.ts'
// Older recovery snapshots stored local and source searches separately. Keep both
// until the owner explicitly chooses the recovered source text.
export function discoveryQuery(primary: string, source: string) {
  return { value: primary, alternative: source && primary !== source ? source : null }
}

export function visibleSourceTours(items: TourSearchSuggestion[], visibleLocalIds: string[]) {
  const seen = new Set(visibleLocalIds.map(id => `local:${id}`))
  return items.filter(tour => {
    const key = tour.localId ? `local:${tour.localId}` : tourSuggestionKey(tour)
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}
