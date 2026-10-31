import { useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { AccountApiError, accountErrorMessage, profileChanges, profileErrors } from '../accountApi'
import type { AccountErrors, AccountUser, ProfileDraft, ProfilePatch } from '../accountApi'
import { Avatar } from './Avatar'
import { Icon } from './Icon'

export function ProfileView({ user, counts, busy, onSave, onCancel }: {
  user: AccountUser; counts: { concerts: number; artists: number; venues: number }; busy: boolean; onSave: (patch: ProfilePatch) => Promise<AccountUser>; onCancel: () => void
}) {
  const [draft, setDraft] = useState<ProfileDraft>({ name: user.name, email: user.email, locale: user.locale })
  const [errors, setErrors] = useState<AccountErrors>({}), [message, setMessage] = useState('')
  const locked = useRef(false)
  const dirty = Object.keys(profileChanges(draft, user)).length > 0
  const change = <K extends keyof ProfileDraft>(key: K, value: ProfileDraft[K]) => {
    setDraft(current => ({ ...current, [key]: value })); setErrors({}); setMessage('')
  }
  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (busy || locked.current || !dirty) return
    const form = event.currentTarget
    const next = profileErrors(draft)
    const focus = (fields: AccountErrors) => requestAnimationFrame(() => {
      const key = Object.keys(fields)[0]
      form.querySelector<HTMLElement>(key ? `#profile-${key}` : '[role="alert"]')?.focus()
    })
    setErrors(next); setMessage('')
    if (Object.keys(next).length) { focus(next); return }
    locked.current = true
    try {
      const saved = await onSave(profileChanges(draft, user))
      if (!form.isConnected) return
      setDraft({ name: saved.name, email: saved.email, locale: saved.locale })
      requestAnimationFrame(() => { if (form.isConnected) form.querySelector<HTMLInputElement>('#profile-name')?.focus() })
    }
    catch (error) {
      const fields = error instanceof AccountApiError ? error.fields : {}
      setErrors(fields); setMessage(accountErrorMessage(error)); focus(fields)
    } finally { locked.current = false }
  }
  const legacyLocale = user.locale !== null && user.locale !== 'en' ? user.locale : null
  return <div className="profile-layout"><form className="profile-form" noValidate onSubmit={submit} aria-busy={busy}>
    <section className="profile-identity" aria-label="Account identity"><Avatar name={user.name} large /><div className="profile-identity-copy"><strong>{user.name}</strong><span>{user.email}</span><small>Account profile</small></div></section>
    {message && <p className="account-error" role="alert" tabIndex={-1}>{message}</p>}
    <fieldset className="account-fields" disabled={busy}><legend className="sr-only">Edit account profile</legend>
      <div className="profile-fields"><div className="profile-field-row">
        <div className="account-field"><label htmlFor="profile-name">Display name</label><input id="profile-name" required autoComplete="name" maxLength={80} value={draft.name} onChange={event => change('name', event.target.value)} aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'profile-name-error' : undefined} />{errors.name && <small className="field-error" id="profile-name-error">{errors.name}</small>}</div>
        <div className="account-field"><label htmlFor="profile-email">Email</label><input id="profile-email" type="email" required autoComplete="email" maxLength={254} value={draft.email} onChange={event => change('email', event.target.value)} aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'profile-email-error' : 'profile-email-hint'} />{errors.email && <small className="field-error" id="profile-email-error">{errors.email}</small>}<small id="profile-email-hint">Changes take effect immediately. Use the new email next time you sign in.</small></div>
      </div><div className="account-field"><label htmlFor="profile-locale">Preferred language</label><select id="profile-locale" value={draft.locale ?? ''} onChange={event => change('locale', event.target.value || null)} aria-invalid={Boolean(errors.locale)} aria-describedby={errors.locale ? 'profile-locale-error' : 'profile-locale-hint'}>
        <option value="">Application default</option><option value="en">English</option>{legacyLocale && <option value={legacyLocale}>{legacyLocale} (current saved preference)</option>}
      </select>{errors.locale && <small className="field-error" id="profile-locale-error">{errors.locale}</small>}<small id="profile-locale-hint">Saves your preference. The current interface is in English.</small></div></div>
      <p className="profile-local-note"><Icon name="user" size={15} />Name, email, and language are saved to your account.</p>
      <div className="profile-form-actions"><button className="button secondary" type="button" onClick={onCancel}>Cancel</button><button className="button primary" type="submit" disabled={!dirty}>{busy ? 'Saving…' : 'Save changes'}<Icon name="arrow" size={17} /></button></div>
    </fieldset>
  </form><aside className="profile-story"><Icon name="music" size={24} /><p className="eyebrow">YOUR COLLECTION</p><h2>Your story,<br />so far<span>.</span></h2><p>Your concerts, artists, and venues from the database.</p><div className="profile-story-stats">{[[counts.concerts, 'Concerts'], [counts.artists, 'Artists'], [counts.venues, 'Venues']].map(([count, label]) => <div key={label}><strong>{count}</strong><span><b>{label}</b><small>Your account collection</small></span></div>)}</div></aside></div>
}
