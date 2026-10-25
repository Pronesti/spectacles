import { useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { AccountApiError, accountErrorMessage, credentialErrors } from '../accountApi'
import type { AccountErrors, AuthMode, Credentials } from '../accountApi'
import { Icon, Soundmark } from './Icon'
import stagePhoto from '../assets/concert-stage.png'

function PasswordField({ label, id, value, onChange, error, register }: { label: string; id: string; value: string; onChange: (value: string) => void; error?: string; register: boolean }) {
  const [visible, setVisible] = useState(false)
  return <div className="account-field"><label htmlFor={id}>{label}</label><span className={`password-input ${error ? 'invalid' : ''}`}>
    <input id={id} type={visible ? 'text' : 'password'} autoComplete={register ? 'new-password' : 'current-password'} required minLength={register ? 12 : 1} maxLength={72} placeholder="Enter your password" value={value} onChange={event => onChange(event.target.value)} aria-invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : undefined} />
    <button type="button" className="password-visibility" onClick={() => setVisible(current => !current)} aria-label={`${visible ? 'Hide' : 'Show'} ${label.toLowerCase()}`} aria-pressed={visible}><Icon name={visible ? 'eyeOff' : 'eye'} size={19} /></button>
  </span>{error && <small className="field-error" id={`${id}-error`}>{error}</small>}</div>
}
export function AuthView({ mode, busy, notice = '', onSwitch, onExplore, onSubmit }: {
  mode: AuthMode; busy: boolean; notice?: string; onSwitch: (mode: AuthMode) => void; onExplore: () => void; onSubmit: (mode: AuthMode, input: Credentials) => Promise<void>
}) {
  const register = mode === 'register'
  const [name, setName] = useState(''), [email, setEmail] = useState('')
  const [password, setPassword] = useState(''), [confirmation, setConfirmation] = useState('')
  const [errors, setErrors] = useState<AccountErrors>({}), [message, setMessage] = useState('')
  const locked = useRef(false)
  const resetError = () => { setErrors({}); setMessage('') }
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (busy || locked.current) return
    const form = event.currentTarget
    const input = { name, email, password, confirmation }
    const next = credentialErrors(mode, input)
    setErrors(next); setMessage('')
    const focusError = (fields: AccountErrors) => requestAnimationFrame(() => {
      const field = Object.keys(fields)[0]
      form.querySelector<HTMLElement>(field ? `#auth-${field}` : '[role="alert"]')?.focus()
    })
    if (Object.keys(next).length) { focusError(next); return }
    locked.current = true
    try { await onSubmit(mode, input); setPassword(''); setConfirmation('') }
    catch (error) {
      const fields = error instanceof AccountApiError ? error.fields : {}
      setErrors(fields); setMessage(accountErrorMessage(error)); focusError(fields)
    } finally { locked.current = false }
  }
  return <main className={`auth-layout ${register ? 'registration' : ''}`} id="auth-content">
    <section className="auth-side"><header className="auth-header">
      <button disabled={busy} className="brand" onClick={onExplore} aria-label="Spectacles"><Soundmark /><span>Spectacles<span className="brand-period">.</span></span></button>
      <div className="auth-switch"><span>{register ? 'Already collecting memories?' : 'New here?'}</span><button disabled={busy} onClick={() => onSwitch(register ? 'login' : 'register')}>{register ? 'Sign in' : 'Create an account'}<Icon name="arrow" size={16} /></button></div>
    </header><div className="auth-form-wrap"><p className="eyebrow">{register ? 'START YOUR COLLECTION' : 'YOUR PERSONAL ENCORE'}</p>
      <h1>{register ? <>Make it your own<span className="lime-dot">.</span></> : 'Welcome back.'}</h1><p className="auth-subtitle">{register ? 'Keep the dates, the places, the songs.' : 'Your next great memory is waiting.'}</p>
      <form onSubmit={submit} noValidate className="auth-form" aria-busy={busy}>
        {(message || notice) && <p className="account-error" role="alert" tabIndex={-1}>{message || notice}</p>}
        <fieldset className="account-fields" disabled={busy}><legend className="sr-only">{register ? 'Create account' : 'Sign in'}</legend>
          {register && <div className="account-field"><label htmlFor="auth-name">Your name</label><input autoFocus id="auth-name" autoComplete="name" maxLength={80} required value={name} onChange={event => { setName(event.target.value); resetError() }} aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'auth-name-error' : undefined} />{errors.name && <small className="field-error" id="auth-name-error">{errors.name}</small>}</div>}
          <div className="account-field"><label htmlFor="auth-email">Email</label><input autoFocus={!register} id="auth-email" type="email" autoComplete="email" maxLength={254} required value={email} onChange={event => { setEmail(event.target.value); resetError() }} aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'auth-email-error' : undefined} />{errors.email && <small className="field-error" id="auth-email-error">{errors.email}</small>}</div>
          <PasswordField label="Password" id="auth-password" value={password} onChange={value => { setPassword(value); resetError() }} error={errors.password} register={register} />
          {register && <PasswordField label="Confirm password" id="auth-confirmation" value={confirmation} onChange={value => { setConfirmation(value); resetError() }} error={errors.confirmation} register />}
          <p className="password-hint">{register ? 'At least 12 characters and at most 72 UTF-8 bytes.' : 'Use your account email and password.'}</p>
          <button className="button primary auth-submit" type="submit">{busy ? 'Please wait…' : register ? 'Create account' : 'Sign in'}<Icon name="arrow" size={20} /></button>
        </fieldset>
      </form>

      <p className="auth-preview-note">Your concerts, notes, flags, and setlists are saved to your account.</p>
    </div><footer className="auth-footer">A personal archive of being there.</footer></section>
    <aside className="auth-photo"><img src={stagePhoto} alt="A live band and crowd beneath lime stage lights" /><div className="auth-photo-shade" /><div className="auth-photo-copy"><span className="auth-photo-label"><i />FOR THE LOVE OF LIVE MUSIC</span><h2>{register ? <>Every night<br />has a story<span>.</span></> : <>Be there.<br />Keep it forever.</>}</h2><p>{register ? 'Start keeping yours.' : 'The shows end. The stories stay.'}</p></div></aside>
  </main>
}
