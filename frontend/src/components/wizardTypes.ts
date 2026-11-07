import type { CatalogProvidersAccess } from '../catalogProvidersApi'
import type { RefObject } from 'react'
import type { WizardState } from '../concertWizard'
import type { Venue } from '../domain'
import type { CatalogAccess } from './CatalogPicker'
import type { MusicBrainzAccess } from './EntityAutocomplete'
export type WizardStepProps = { continueAction?: RefObject<(() => WizardState | null) | null>; providers?: CatalogProvidersAccess; state: WizardState; onChange: (state: WizardState) => void; onEditingChange: (editing: boolean) => void; catalog: CatalogAccess; musicbrainz: MusicBrainzAccess; venues: Venue[] }
