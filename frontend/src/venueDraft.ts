import type { RecoveryFields } from './concertRecovery.ts'
import type { VenueSuggestion, VenueValues } from './catalogProviders.ts'
import type { WizardState } from './concertWizard.ts'
import { applyProviderVenue } from './providerDraft.ts'

type VenueEditor = NonNullable<RecoveryFields['venueEditor']>
export type PreparedVenueDraft = { values: VenueValues; selection: string | null; source?: VenueSuggestion }
export function prepareVenueDraft(editor: VenueEditor): PreparedVenueDraft {
  if (!editor.name.trim() || (editor.lat === '') !== (editor.lng === '') || editor.lat !== '' && (!Number.isFinite(Number(editor.lat)) || !Number.isFinite(Number(editor.lng)) || Math.abs(Number(editor.lat)) > 90 || Math.abs(Number(editor.lng)) > 180)) throw new Error('Enter a Venue name and both valid coordinates, or leave both coordinates blank.')
  const values: VenueValues = { region: null, countryCode: null, timezone: null, image: null, ...editor.metadata, name: editor.name.trim(), city: editor.city.trim(), address: editor.address.trim() || null, aliases: [...new Set(editor.aliasesText.split('\n').map(a => a.trim()).filter(Boolean))], lat: editor.lat === '' ? null : Number(editor.lat), lng: editor.lng === '' ? null : Number(editor.lng) }
  if (values.aliases.length > 100 || values.aliases.some(a => a.length > 255)) throw new Error('Use at most 100 aliases of 255 characters each.')
  const source = editor.ref && editor.selection && editor.url ? { ref: editor.ref, selection: editor.selection, values, linkedRefs: editor.linkedRefs ?? [], url: editor.url, venueUrl: editor.venueUrl, venueSources: editor.venueSources, localId: null } : undefined
  return { values, selection: editor.selection, source }
}
export function validVenueDraft(editor: VenueEditor) { try { prepareVenueDraft(editor); return true } catch { return false } }
export function applyVenueDraft(state: WizardState, { values, selection, source }: PreparedVenueDraft): WizardState {
  if (selection && source) return applyProviderVenue(state, source)
  const pending = { ...values, id: crypto.randomUUID(), neighborhood: '' }
  const draft = { ...state.draft, pendingVenue: pending, concert: { ...state.draft.concert, venueId: pending.id } }; delete draft.providerVenue
  return { ...state, draft }
}
