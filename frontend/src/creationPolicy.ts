import type { WizardState, WizardStep } from './concertWizard.ts'
/** Creation is deliberately smaller than the shared catalog's saved Event model. */
export function creationStepError(state: WizardState, step: WizardStep): string | null {
  if (step === 'artist' && state.draft.artists.length !== 1) return 'Choose one Artist to continue. Existing draft choices are kept until you decide.'
  if (step === 'tour' && state.draft.tours.length > 1) return 'Choose at most one Tour, or remove the Tours to skip this step.'
  if (step === 'event') {
    if (state.draft.artists.length !== 1 || state.draft.tours.length > 1) return 'This Event has several Artists or Tours. Go back and explicitly choose one Artist and at most one Tour for a new Event.'
    if (!state.draft.concert.venueId) return 'Choose or create one Venue for this concert.'
  }
  return null
}
