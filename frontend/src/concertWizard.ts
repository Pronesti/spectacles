import { adoptCatalogEvent, changeSetlistEntry, createConcertDraft, finishConcertDraft } from './concertDraft.ts'
import type { ConcertDraft } from './concertDraft.ts'
import type { CatalogChoice, SetlistEntry, Venue } from './domain.ts'
import type { CatalogEvent } from './catalog.ts'
import { catalogUuid } from './catalog.ts'
export type ArtistDraftInput = { name: string; type: string | null; disambiguation: string | null; selection: string | null }
export type SongDraftInput = { title: string; disambiguation: string | null; selection: string | null }
export type TourDraftInput = { name: string; selection: string | null }
export type PendingTour = { key: string; input: TourDraftInput; mbid?: string }
export type WizardTourChoice = CatalogChoice & { draft?: PendingTour }
export type PendingArtist = { key: string; input: ArtistDraftInput; mbid?: string }
export type PendingSong = { key: string; input: SongDraftInput }
export type WizardArtistChoice = CatalogChoice & { draft?: PendingArtist }
export type WizardEntry = SetlistEntry & { songDraft?: PendingSong; performerDraft?: PendingArtist }
export type PendingCatalog = { tours?: Record<string, TourDraftInput>; tourChoices?: Record<string, string>; artists: Record<string, ArtistDraftInput>; songs: Record<string, SongDraftInput>; lineup: Record<string, string>; entries: Record<string, { song?: string; artist?: string }> }
export type WizardStep = 'artist' | 'tour' | 'event' | 'setlist'
export const wizardSteps: WizardStep[] = ['artist', 'tour', 'event', 'setlist']
export type WizardDraft = Omit<ConcertDraft, 'artists' | 'tours' | 'concert'> & { artists: WizardArtistChoice[]; tours: WizardTourChoice[]; concert: Omit<ConcertDraft['concert'], 'setlist'> & { setlist: WizardEntry[] } }
export type WizardState = { step: WizardStep; draft: WizardDraft }
export type WizardSubmission = { concert: ConcertDraft['concert']; venue?: Venue; pendingCatalog?: PendingCatalog }
export function createWizard(initialDate?: string): WizardState { return { step: 'artist', draft: createConcertDraft(undefined, initialDate) } }
export function adoptWizardEvent(state: WizardState, event: CatalogEvent, venues: Venue[]): WizardState { return { ...state, draft: adoptCatalogEvent(state.draft, event, venues) } }
export function detachWizardEvent(state: WizardState): WizardState { return { ...state, draft: { ...state.draft, eventId: undefined } } }
export function changeTourInput(input: TourDraftInput, patch: Partial<TourDraftInput>): TourDraftInput { return { ...input, ...patch, ...(patch.name !== undefined && patch.name !== input.name ? { selection: null } : {}) } }
export function changeArtistInput(input: ArtistDraftInput, patch: Partial<ArtistDraftInput>): ArtistDraftInput { return { ...input, ...patch, ...(patch.name !== undefined && patch.name !== input.name ? { selection: null } : {}) } }
export function changeSongInput(input: SongDraftInput, patch: Partial<SongDraftInput>): SongDraftInput { return { ...input, ...patch, ...(patch.title !== undefined && patch.title !== input.title ? { selection: null } : {}) } }
export function changeWizardEntry(entry: WizardEntry, patch: Partial<WizardEntry>): WizardEntry {
  const next: WizardEntry = { ...changeSetlistEntry(entry, patch) }
  if (patch.title !== undefined && patch.title !== entry.title && patch.songDraft === undefined) delete next.songDraft
  if (patch.artist !== undefined && patch.artist !== entry.artist && patch.performerDraft === undefined) delete next.performerDraft
  if (next.type === 'note') { next.artist = ''; next.tape = false; delete next.songDraft; delete next.performerDraft; delete next.songCatalogId; delete next.artistCatalogId }
  return next
}
export function wizardStepError(state: WizardState, step: WizardStep): string | null {
  try {
    if (step === 'artist' || step === 'tour') {
      const choices = step === 'artist' ? state.draft.artists : state.draft.tours
      if (choices.length > 100 || choices.some(c => !c.name.trim() || c.name.length > 255)) throw new Error('Enter valid names (up to 100 choices).')
      const keys = choices.map(choice => choice.catalogId ? `local:${choice.catalogId}` : choice.draft ? choice.draft.input.selection && choice.draft.mbid ? `musicbrainz:${choice.draft.mbid}` : `draft:${choice.draft.key}` : null).filter(Boolean)
      if (new Set(keys).size !== keys.length) throw new Error('Choose each identity only once.')
    }
    if (step === 'event') finishConcertDraft(state.draft)
    if (step === 'setlist') validateRows(state.draft.concert.setlist)
    return null
  } catch (error) { return error instanceof Error ? error.message : 'Check the draft.' }
}
function validateRows(rows: WizardEntry[]) {
  if (rows.length > 1000 || new Set(rows.map(r => r.id)).size !== rows.length) throw new Error('Check setlist entry identities.')
  for (const row of rows) if (!catalogUuid(row.id) || !row.title.trim() || row.title.length > 2000 || row.artist.length > 2000 || row.type === 'note' && (row.tape || row.songCatalogId || row.artistCatalogId || row.songDraft || row.performerDraft)) throw new Error('Check each setlist row.')
}
export function moveWizard(state: WizardState, step: WizardStep): WizardState {
  const error = wizardSteps.indexOf(step) > wizardSteps.indexOf(state.step) ? wizardStepError(state, state.step) : null
  if (error) throw new Error(error)
  return { ...state, step }
}
export function finishWizard(state: WizardState): WizardSubmission {
  for (const step of wizardSteps) { const error = wizardStepError(state, step); if (error) throw new Error(error) }
  const pending: PendingCatalog = { artists: {}, songs: {}, lineup: {}, entries: {} }
  const register = <T extends ArtistDraftInput | SongDraftInput,>(map: Record<string, T>, draft: { key: string; input: T }) => {
    if (!catalogUuid(draft.key) || map[draft.key] && JSON.stringify(map[draft.key]) !== JSON.stringify(draft.input)) throw new Error('Check catalog draft identity.')
    const label = 'name' in draft.input ? draft.input.name : draft.input.title
    if (!label.trim() || label.length > 255 || draft.input.disambiguation && draft.input.disambiguation.length > 255 || 'type' in draft.input && draft.input.type && draft.input.type.length > 255) throw new Error('Check new catalog fields.')
    map[draft.key] = draft.input
  }
  state.draft.artists.forEach((choice, index) => { if (choice.draft) {
    if (choice.catalogId || state.draft.eventId || choice.name !== choice.draft.input.name) throw new Error('Choose one Artist identity.')
    register(pending.artists, choice.draft); pending.lineup[String(index)] = choice.draft.key
  } })
  const setlist = state.draft.concert.setlist.map(entry => {
    const { songDraft, performerDraft, ...saved } = entry
    const refs: { song?: string; artist?: string } = {}
    if (songDraft) { if (saved.songCatalogId || saved.type !== 'song' || saved.title !== songDraft.input.title) throw new Error('Choose one Song identity.'); register(pending.songs, songDraft); refs.song = songDraft.key }
    if (performerDraft) { if (saved.artistCatalogId || saved.type !== 'song' || saved.artist !== performerDraft.input.name) throw new Error('Choose one performer identity.'); register(pending.artists, performerDraft); refs.artist = performerDraft.key }
    if (refs.song || refs.artist) pending.entries[entry.id] = refs
    return saved
  })
  for (const key of Object.keys(pending.artists)) if (pending.songs[key]) throw new Error('Draft identities must be distinct.')
  state.draft.tours.forEach((choice, index) => {
    const draft = choice.draft
    if (!draft) return
    if (choice.catalogId || state.draft.eventId || choice.name !== draft.input.name) throw new Error('Choose one Tour identity.')
    if (!catalogUuid(draft.key) || !draft.input.name.trim() || draft.input.name.length > 255 || draft.input.selection !== null && (typeof draft.input.selection !== 'string' || !draft.input.selection.trim() || draft.input.selection.length > 4096)) throw new Error('Check new Tour fields.')
    if (pending.artists[draft.key] || pending.songs[draft.key]) throw new Error('Draft identities must be distinct.')
    pending.tours ??= {}; pending.tourChoices ??= {}
    const previous = pending.tours[draft.key]
    if (previous && JSON.stringify(previous) !== JSON.stringify(draft.input)) throw new Error('Check catalog draft identity.')
    pending.tours[draft.key] = { name: draft.input.name, selection: draft.input.selection }
    pending.tourChoices[String(index)] = draft.key
  })
  const result = finishConcertDraft(state.draft)
  const catalog = result.concert.catalog!
  const tours = catalog.tours.map(choice => ({ name: choice.name, catalogId: choice.catalogId }))
  return { ...result, concert: { ...result.concert, catalog: { ...catalog, tours }, setlist }, ...(Object.keys(pending.artists).length || Object.keys(pending.songs).length || Object.keys(pending.tours ?? {}).length ? { pendingCatalog: pending } : {}) }
}
