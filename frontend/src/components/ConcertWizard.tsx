import { useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { createWizard, finishWizard, moveWizard, wizardSteps } from '../concertWizard'
import type { PendingCatalog, WizardStep } from '../concertWizard'
import type { Concert, Venue } from '../domain'
import type { CatalogAccess } from './CatalogPicker'
import type { MusicBrainzAccess } from './EntityAutocomplete'
import { Dialog } from './Dialog'
import { WizardArtistStep } from './WizardArtistStep'
import { WizardTourStep } from './WizardTourStep'
import { WizardEventStep } from './WizardEventStep'
import { WizardSetlistStep } from './WizardSetlistStep'
import '../wizard.css'
export function ConcertWizard({ venues, initialDate, catalog, musicbrainz, busy = false, disabled = false, onSignIn, onReloadCollection, onClose, onSave }: { venues: Venue[]; initialDate?: string; catalog: CatalogAccess; musicbrainz: MusicBrainzAccess; busy?: boolean; disabled?: boolean; onSignIn?: () => void; onReloadCollection?: () => void; onClose: () => void; onSave: (concert: Concert, venue?: Venue, pendingCatalog?: PendingCatalog) => Promise<void> }) {
  const [state, setState] = useState(() => createWizard(initialDate)), [error, setError] = useState(''), [editing, setEditing] = useState(false)
  const heading = useRef<HTMLHeadingElement>(null), form = useRef<HTMLFormElement>(null)
  useEffect(() => { heading.current?.focus() }, [state.step])
  const navigate = (step: WizardStep) => {
    if (busy || editing) return
    try { setState(moveWizard(state, step)); setError('') } catch (failure) { setError(failure instanceof Error ? failure.message : 'Check the draft.'); form.current?.querySelector<HTMLInputElement>('input:not([disabled])')?.focus() }
  }
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (busy || disabled || editing || state.step !== 'setlist') return
    try { const result = finishWizard(state); await onSave(result.concert, result.venue, result.pendingCatalog) } catch (failure) { setError(failure instanceof Error ? failure.message : 'Check the draft fields.') }
  }
  const Step = { artist: WizardArtistStep, tour: WizardTourStep, event: WizardEventStep, setlist: WizardSetlistStep }[state.step]
  const index = wizardSteps.indexOf(state.step)
  return <Dialog title="Add a concert" drawer onClose={() => { if (!busy) onClose() }}><form ref={form} className="concert-form" noValidate onSubmit={submit}><fieldset disabled={busy} style={{ display: 'contents' }}><div className="form-body"><ol className="wizard-steps">{wizardSteps.map((step, n) => <li key={step} aria-current={step === state.step ? 'step' : undefined}><button type="button" disabled={editing || n > index || busy} onClick={() => navigate(step)}>{step[0].toUpperCase() + step.slice(1)}</button></li>)}</ol><h3 ref={heading} tabIndex={-1}>{state.step[0].toUpperCase() + state.step.slice(1)}</h3><Step state={state} onChange={setState} onEditingChange={setEditing} catalog={catalog} musicbrainz={musicbrainz} venues={venues} />{editing && <p className="hint">Confirm or cancel the open editor before continuing.</p>}{error && <p role="alert" className="form-error">{error}</p>}</div><div className="form-footer">{index > 0 && <button type="button" className="button secondary" disabled={editing} onClick={() => navigate(wizardSteps[index - 1])}>Back</button>}{index < 3 ? <button type="button" className="button primary" disabled={editing} onClick={event => { event.preventDefault(); navigate(wizardSteps[index + 1]) }}>Next</button> : <button type="submit" className="button primary" disabled={disabled || editing}>Save concert</button>}{onReloadCollection && <button type="button" className="button secondary" onClick={onReloadCollection}>Reload collection</button>}{onSignIn && <button type="button" onClick={onSignIn}>Sign in again</button>}<button type="button" className="button secondary" onClick={onClose}>Cancel</button></div></fieldset></form></Dialog>
}
