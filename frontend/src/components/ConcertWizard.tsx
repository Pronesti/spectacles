import type { CatalogProvidersAccess } from '../catalogProvidersApi'
import { useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { creationStepError } from '../creationPolicy'
import { finishWizard, moveWizard, wizardSteps } from '../concertWizard'
import type { PendingCatalog, WizardStep } from '../concertWizard'
import type { Concert, Venue } from '../domain'
import type { CatalogAccess } from './CatalogPicker'
import type { MusicBrainzAccess } from './EntityAutocomplete'
import { openRecovery } from '../concertRecovery'
import { ConcertRecoveryContext } from './ConcertRecovery'
import { Dialog } from './Dialog'
import { WizardArtistStep } from './WizardArtistStep'
import { WizardTourStep } from './WizardTourStep'
import { WizardEventStep } from './WizardEventStep'
import { WizardSetlistStep } from './WizardSetlistStep'
import '../wizard.css'
function OwnedConcertWizard({ owner = '', venues, initialDate, catalog, musicbrainz, providers, busy = false, disabled = false, onSignIn, onReloadCollection, onClose, onSave }: { owner?: string; venues: Venue[]; initialDate?: string; catalog: CatalogAccess; musicbrainz: MusicBrainzAccess; providers?: CatalogProvidersAccess; busy?: boolean; disabled?: boolean; onSignIn?: () => void; onReloadCollection?: () => void; onClose: () => void; onSave: (concert: Concert, venue?: Venue, pendingCatalog?: PendingCatalog) => Promise<void> }) {
  const [recovery] = useState(() => openRecovery(owner, initialDate))
  const [, refreshRecovery] = useState(0)
  const [state, updateState] = useState(() => recovery.snapshot.state)
  const setState = (value: typeof state) => { recovery.changeState(value); updateState(value); refreshRecovery(n => n + 1) }
  const [error, setError] = useState(''), [editing, setEditing] = useState(false)
  const heading = useRef<HTMLHeadingElement>(null), errorMessage = useRef<HTMLParagraphElement>(null)
  useEffect(() => { if (error) errorMessage.current?.focus() }, [error])
  useEffect(() => { heading.current?.focus() }, [state.step])
  const navigate = (step: WizardStep) => {
    if (busy || editing) return
    try { const issue = wizardSteps.indexOf(step) > wizardSteps.indexOf(state.step) ? creationStepError(state, state.step) : null; if (issue) throw new Error(issue); setState(moveWizard(state, step)); setError('') } catch (failure) { setError(failure instanceof Error ? failure.message : 'Check the draft.'); errorMessage.current?.focus() }
  }
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (busy || disabled || editing || state.step !== 'setlist') return
    try { for (const step of wizardSteps) { const issue = creationStepError(state, step); if (issue) throw new Error(issue) } const result = finishWizard(state); await onSave(result.concert, result.venue, result.pendingCatalog); if (!recovery.clear()) window.alert(recovery.notice) } catch (failure) { setError(failure instanceof Error ? failure.message : 'Check the draft fields.') }
  }
  const Step = { artist: WizardArtistStep, tour: WizardTourStep, event: WizardEventStep, setlist: WizardSetlistStep }[state.step]
  const index = wizardSteps.indexOf(state.step)
  return <ConcertRecoveryContext.Provider value={{ controller: recovery, changed: () => refreshRecovery(n => n + 1) }}><Dialog title="Add a concert" drawer onClose={() => { if (!busy) onClose() }}><form className="concert-form concert-creation" noValidate onSubmit={submit}><fieldset disabled={busy} style={{ display: 'contents' }}><div className="form-body"><p role="status" className="hint">{recovery.notice ? recovery.restored && recovery.notice.startsWith('Your concert draft was restored') ? 'Draft restored. Continue where you left off.' : recovery.notice : 'Draft kept on this device when you close.'}</p><ol className="wizard-steps">{wizardSteps.map((step, n) => <li key={step} aria-current={step === state.step ? 'step' : undefined}><button type="button" disabled={editing || n > index || busy} onClick={() => navigate(step)}><span className="step-number" aria-hidden="true">{n < index ? '✓' : n + 1}</span><span>{step[0].toUpperCase() + step.slice(1)}</span></button></li>)}</ol><h3 ref={heading} tabIndex={-1}>{{ artist: 'Who are you seeing?', tour: 'Part of a tour?', event: 'Which concert?', setlist: 'Your setlist' }[state.step]}</h3><Step state={state} onChange={setState} onEditingChange={setEditing} catalog={catalog} musicbrainz={musicbrainz} providers={providers} venues={venues} />{editing && <p className="hint">Confirm or cancel the open editor before continuing.</p>}{error && <p ref={errorMessage} tabIndex={-1} role="alert" className="form-error">{error}</p>}</div><div className="form-footer">{index > 0 && <button type="button" className="button secondary" disabled={editing} onClick={() => navigate(wizardSteps[index - 1])}>Back</button>}{index < 3 ? <button type="button" className="button primary" disabled={editing} onClick={event => { event.preventDefault(); navigate(wizardSteps[index + 1]) }}>Continue</button> : <button type="submit" className="button primary" disabled={disabled || editing}>Save concert</button>}{onReloadCollection && <button type="button" className="button secondary" onClick={() => { setError(''); onReloadCollection() }}>Reload collection</button>}{onSignIn && <button type="button" onClick={onSignIn}>Sign in again</button>}<button type="button" className="button secondary creation-cancel" onClick={onClose}>Cancel</button><button type="button" className="text-button creation-discard" onClick={() => { if (recovery.clear()) onClose(); else { setError(recovery.notice); refreshRecovery(n => n + 1) } }}>Discard draft</button></div></fieldset></form></Dialog></ConcertRecoveryContext.Provider>
}

export function ConcertWizard(props: Parameters<typeof OwnedConcertWizard>[0]) {
  return <OwnedConcertWizard key={props.owner} {...props} />
}
