import { InteractionFieldset } from './InteractionFieldset'
import { Button } from 'react-aria-components'
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
  return <ConcertRecoveryContext.Provider value={{ controller: recovery, changed: () => refreshRecovery(n => n + 1) }}><Dialog title="Add a concert" drawer onClose={() => { if (!busy) onClose() }}><form className="concert-form concert-creation" noValidate onSubmit={submit}><InteractionFieldset disabled={busy} style={{ display: 'contents' }}><div className="form-body">{!(recovery.restored && recovery.notice.startsWith('Your concert draft was restored')) && <p role="status" className="hint">{recovery.notice || 'Draft kept on this device when you close.'}</p>}<ol className="wizard-steps">{wizardSteps.map((step, n) => <li key={step} aria-current={step === state.step ? 'step' : undefined}><Button type="button" isDisabled={editing || n > index || busy} onPress={() => navigate(step)}><span className="step-number" aria-hidden="true">{n < index ? '✓' : n + 1}</span><span>{step[0].toUpperCase() + step.slice(1)}</span></Button></li>)}</ol><h3 ref={heading} tabIndex={-1}>{{ artist: 'Who are you seeing?', tour: 'Part of a tour?', event: 'Which concert?', setlist: 'Your setlist' }[state.step]}</h3><Step state={state} onChange={setState} onEditingChange={setEditing} catalog={catalog} musicbrainz={musicbrainz} providers={providers} venues={venues} />{error && <p ref={errorMessage} tabIndex={-1} role="alert" className="form-error">{error}</p>}</div><div className="form-footer">{index > 0 && <Button type="button" className="button secondary" isDisabled={editing} onPress={() => navigate(wizardSteps[index - 1])}>Back</Button>}{index < 3 ? <Button key="continue" type="button" className="button primary" isDisabled={editing} onPress={() => navigate(wizardSteps[index + 1])}>Continue</Button> : <Button key="save" type="submit" className="button primary" isDisabled={disabled || editing}>Save concert</Button>}{onReloadCollection && <Button type="button" className="button secondary" onPress={() => { setError(''); onReloadCollection() }}>Reload collection</Button>}{onSignIn && <Button type="button" onPress={onSignIn}>Sign in again</Button>}</div></InteractionFieldset></form></Dialog></ConcertRecoveryContext.Provider>
}

export function ConcertWizard(props: Parameters<typeof OwnedConcertWizard>[0]) {
  return <OwnedConcertWizard key={props.owner} {...props} />
}
