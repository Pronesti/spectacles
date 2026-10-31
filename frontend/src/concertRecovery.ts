import { catalogUuid } from './catalog.ts'
import { decodeStoredSnapshot } from './concertRecoverySchema.ts'
import type { ProviderRef, PerformanceSummary, PerformanceFilters, ArtistScope, VenueValues } from './catalogProviders.ts'
import { createWizard } from './concertWizard.ts'
import type { ArtistDraftInput, SongDraftInput, TourDraftInput, WizardArtistChoice, WizardEntry, WizardState, WizardTourChoice } from './concertWizard.ts'
export type ArtistEditor = { input: ArtistDraftInput; mbid?: string }
export type TourEditor = { input: TourDraftInput; mbid?: string; ref?: ProviderRef; context?: string | null }
export type ArtistDecision = { add: WizardArtistChoice } | { remove: number }
export type TourDecision = { add: WizardTourChoice } | { remove: number }
export type RecoveryFields = {
  artistEditor: ArtistEditor | null; artistDecision: ArtistDecision | null;
  tourEditor: TourEditor | null; tourDecision: TourDecision | null;
  row: WizardEntry | null; songEditor: SongDraftInput | null; performerEditor: ArtistDraftInput | null;
  placeQuery: string; tourProviderQuery: string;
  artistQuery: string; tourQuery: string; eventQuery: string; venueQuery: string; songQuery: string; performerQuery: string;
  historyPerformanceFilters: Omit<PerformanceFilters, 'cursor'>; historyPerformancePreview: PerformanceSummary | null; historyPerformanceDecision: 'apply' | 'detach' | null;
  performanceFilters: Omit<PerformanceFilters, 'cursor'>; performancePreview: PerformanceSummary | null; performanceDecision: 'apply' | 'detach' | null;
  venuePreviewRef: ProviderRef | null; venueHistoryTab: 'past' | 'upcoming'; venueHistorySource: ProviderRef | null; activeProviderArtist: ArtistScope | null;
  venueEditor: { name: string; city: string; address: string; aliasesText: string; lat: string; lng: string; selection: string | null; ref?: ProviderRef; metadata?: VenueValues; linkedRefs?: ProviderRef[]; url?: string; venueUrl?: string; venueSources?: import('./catalogProviders.ts').VenueSource[] } | null;
  eventPreviewId: string | null;
}
export type RecoverySnapshot = { version: 2; owner: string; state: WizardState; fields: Partial<RecoveryFields> }
export type DraftStorage = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>
export const recoveryKey = (owner: string) => `spectacles.concert-creation.v1:${owner}`
export const decodeRecovery = decodeStoredSnapshot
export function browserDraftStorage(): DraftStorage | undefined { try { return typeof window === 'undefined' ? undefined : window.localStorage } catch { return undefined } }
export function openRecovery(owner: string, initialDate?: string, storage = browserDraftStorage()) {
  let current: RecoverySnapshot = { version: 2, owner, state: createWizard(initialDate), fields: {} }
  let blocked = false, closed = false
  let notice = '', restored = false
  const key = recoveryKey(owner)
  try {
    if (!storage || !catalogUuid(owner)) throw new Error('Storage unavailable')
    const raw = storage.getItem(key)
    if (raw !== null) {
      const decoded = decodeRecovery(raw, owner)
      if (decoded) { current = decoded; restored = true; notice = 'Your concert draft was restored on this device. It takes priority over a newly selected calendar date.' }
      else { blocked = true; notice = 'The stored concert draft could not be restored. It has been left untouched. Discard draft to start over, or save this new concert to replace it.' }
    }
  } catch { notice = 'Local draft storage is unavailable. Keep this form open to avoid losing changes.' }
  const persist = () => {
    if (blocked || closed) return
    try {
      const raw = JSON.stringify(current)
      if (!storage || !decodeRecovery(raw, owner)) throw new Error('Cannot retain draft')
      storage.setItem(key, raw)
      if (!restored) notice = ''
    } catch { notice = 'The latest changes could not be saved on this device. Keep this form open to avoid losing them.' }
  }
  return {
    get snapshot() { return current }, get notice() { return notice }, get restored() { return restored },
    changeState(value: WizardState) { if (!closed) { current = { ...current, state: value }; persist() } },
    changeField<K extends keyof RecoveryFields>(name: K, value: RecoveryFields[K]) { if (!closed) { current = { ...current, fields: { ...current.fields, [name]: value } }; persist() } },
    clear() {
      // No effect/unmount writes: a confirmed save cannot resurrect a cleared snapshot.
      try { if (!storage) throw new Error('Storage unavailable'); storage.removeItem(key); closed = true; return true } catch { notice = 'The saved draft could not be removed from this device. Discard it before starting another concert.'; return false }
    },
  }
}
export type RecoveryController = ReturnType<typeof openRecovery>
