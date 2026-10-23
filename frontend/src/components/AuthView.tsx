import { useState } from 'react'
import type { FormEvent } from 'react'
import { credentialErrors } from '../account'
import type { AccountErrors, AuthView as AuthMode } from '../account'
import { Icon, Soundmark } from './Icon'
import stagePhoto from '../assets/concert-stage.png'

function PasswordField({ label, id, value, onChange, error }: { label: string; id: string; value: string; onChange: (value: string) => void; error?: string }) {
  const [visible, setVisible] = useState(false)
  return <div className="account-field"><label htmlFor={id}>{label}</label><span className={`password-input ${error ? 'invalid' : ''}`}><input id={id} type={visible ? 'text' : 'password'} autoComplete="off" required minLength={8} maxLength={128} placeholder="Enter a sample password" value={value} onChange={e => onChange(e.target.value)} aria-invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : undefined} /><button type="button" className="password-visibility" onClick={() => setVisible(value => !value)} aria-label={`${visible ? 'Hide' : 'Show'} ${label.toLowerCase()}`} aria-pressed={visible}><Icon name={visible ? 'eyeOff' : 'eye'} size={19} /></button></span>{error && <small className="field-error" id={`${id}-error`}>{error}</small>}</div>
}

export function AuthView({ mode, onSwitch, onEnter, onRegister }: { mode: AuthMode; onSwitch: (mode: AuthMode) => void; onEnter: () => void; onRegister: (name: string, email: string) => void }) {
  const register = mode === 'register'
  const [name, setName] = useState(''), [email, setEmail] = useState('')
  const [password, setPassword] = useState(''), [confirmation, setConfirmation] = useState('')
  const [errors, setErrors] = useState<AccountErrors>({})
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const nextErrors = credentialErrors({ mode, name, email, password, confirmation })
    setErrors(nextErrors)
    if (Object.keys(nextErrors).length) {
      const first = Object.keys(nextErrors)[0]
      event.currentTarget.querySelector<HTMLInputElement>(`#auth-${first}`)?.focus()
      return
    }
    // Credentials are only a visual form exercise. Never pass passwords outside this component.
    setPassword(''); setConfirmation('')
    if (register) onRegister(name.trim(), email.trim())
    else onEnter()
  }

  return <main className={`auth-layout ${register ? 'registration' : ''}`} id="auth-content">
    <section className="auth-side"><header className="auth-header"><button className="brand" onClick={onEnter} aria-label="Spectacles demo dashboard"><Soundmark /><span>Spectacles<span className="brand-period">.</span></span></button><div className="auth-switch"><span>{register ? 'Already collecting memories?' : 'New here?'}</span><button onClick={() => onSwitch(register ? 'login' : 'register')}>{register ? 'Sign in' : 'Create an account'}<Icon name="arrow" size={16} /></button></div></header>
      <div className="auth-form-wrap"><p className="eyebrow">{register ? 'START YOUR COLLECTION' : 'YOUR PERSONAL ENCORE'}</p><h1>{register ? <>Make it your own<span className="lime-dot">.</span></> : 'Welcome back.'}</h1><p className="auth-subtitle">{register ? 'Keep the dates, the places, the songs.' : 'Your next great memory is waiting.'}</p>
        <form onSubmit={submit} noValidate className="auth-form">
          {register && <div className="account-field"><label htmlFor="auth-name">Your name</label><input autoFocus id="auth-name" autoComplete="name" maxLength={80} required placeholder="Jamie Morgan" value={name} onChange={e => { setName(e.target.value); setErrors({}) }} aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'auth-name-error' : undefined} />{errors.name && <small className="field-error" id="auth-name-error">{errors.name}</small>}</div>}
          <div className="account-field"><label htmlFor="auth-email">Email</label><input autoFocus={!register} id="auth-email" type="email" autoComplete="email" maxLength={254} required placeholder="jamie@example.com" value={email} onChange={e => { setEmail(e.target.value); setErrors({}) }} aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'auth-email-error' : undefined} />{errors.email && <small className="field-error" id="auth-email-error">{errors.email}</small>}</div>
          <PasswordField label="Password" id="auth-password" value={password} onChange={value => { setPassword(value); setErrors({}) }} error={errors.password} />
          {register && <PasswordField label="Confirm password" id="auth-confirmation" value={confirmation} onChange={value => { setConfirmation(value); setErrors({}) }} error={errors.confirmation} />}
          <p className="password-hint">{register ? 'At least 8 characters for this preview.' : 'Use sample details. This opens your existing local demo profile.'}</p>
          <button className="button primary auth-submit" type="submit">{register ? 'Create preview profile' : 'Sign in'}<Icon name="arrow" size={20} /></button>
        </form>
        {register ? <><p className="auth-preview-note">This preview does not create a real account. Your profile stays in this browser. Passwords are never sent or saved.</p><div className="registration-explore"><button className="text-button" onClick={onEnter}>Explore the demo<Icon name="arrow" size={17} /></button></div></> : <><div className="auth-divider"><span>or take a look around</span></div><button className="button secondary auth-explore" onClick={onEnter}>Explore the demo<Icon name="arrow" size={19} /></button><p className="auth-preview-note">Design preview · Passwords are never sent or saved.</p></>}
      </div><footer className="auth-footer">A personal archive of being there.</footer>
    </section>
    <aside className="auth-photo"><img src={stagePhoto} alt="A live band and crowd beneath lime stage lights" /><div className="auth-photo-shade" /><div className="auth-photo-copy"><span className="auth-photo-label"><i />FOR THE LOVE OF LIVE MUSIC</span><h2>{register ? <>Every night<br />has a story<span>.</span></> : <>Be there.<br />Keep it forever.</>}</h2><p>{register ? 'Start keeping yours.' : 'The shows end. The stories stay.'}</p></div></aside>
  </main>
}
