import { providerKey } from './catalogProviders.ts'
import type { TourSuggestion } from './catalogProviders.ts'
// Older recovery snapshots stored local and source searches separately. Keep both
// until the owner explicitly chooses the recovered source text.
export function discoveryQuery(primary: string, source: string) {
  return { value: primary, alternative: source && primary !== source ? source : null }
}

export function visibleSourceTours(items: TourSuggestion[], visibleLocalIds: string[]) {
  const seen = new Set(visibleLocalIds.map(id => `local:${id}`))
  return items.filter(tour => {
    const key = tour.localId ? `local:${tour.localId}` : providerKey(tour.ref)
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}
