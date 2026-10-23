import { useEffect, useRef, useState } from 'react'
import type { ChangeEvent, FormEvent } from 'react'
import { AVATAR_COLORS, avatarFileError, profileErrors, profilePayload } from '../account'
import type { AccountErrors, Profile } from '../account'
import { Avatar } from './Avatar'
import { Icon } from './Icon'

export function ProfileView({ profile, counts, issue, onSave, onCancel }: { profile: Profile; counts: { concerts: number; artists: number; venues: number }; issue: string; onSave: (profile: Profile) => void; onCancel: () => void }) {
  const [draft, setDraft] = useState(profile)
  const [errors, setErrors] = useState<AccountErrors>({})
  const [photoError, setPhotoError] = useState('')
  const [readingPhoto, setReadingPhoto] = useState(false)
  const fileInput = useRef<HTMLInputElement>(null)
  const fileReader = useRef<FileReader | null>(null)
  useEffect(() => () => fileReader.current?.abort(), [])
  const change = (field: 'name' | 'email' | 'city' | 'bio', value: string) => { setDraft(current => ({ ...current, [field]: value })); setErrors({}) }
  const dirty = draft.name !== profile.name || draft.email !== profile.email || draft.city !== profile.city || draft.bio !== profile.bio || draft.color !== profile.color || draft.photo !== profile.photo
  const choosePhoto = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    fileReader.current?.abort()
    setReadingPhoto(false)
    const error = avatarFileError(file.type, file.size)
    if (error) { setPhotoError(error); return }
    setPhotoError(''); setReadingPhoto(true)
    const reader = new FileReader()
    fileReader.current = reader
    reader.onload = () => { if (typeof reader.result === 'string') setDraft(current => ({ ...current, photo: reader.result as string })); setReadingPhoto(false) }
    reader.onerror = () => { setPhotoError('This image could not be read. Choose another image.'); setReadingPhoto(false) }
    reader.readAsDataURL(file)
  }
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const nextErrors = profileErrors(draft)
    setErrors(nextErrors)
    if (Object.keys(nextErrors).length) { event.currentTarget.querySelector<HTMLInputElement>(`#profile-${Object.keys(nextErrors)[0]}`)?.focus(); return }
    const next = profilePayload(draft)
    setDraft(next); onSave(next)
  }
  return <div className="profile-layout"><form className="profile-form" noValidate onSubmit={submit}>
    <section className="profile-identity" aria-label="Profile avatar"><Avatar profile={draft} large /><div className="profile-identity-copy"><strong>{draft.name || 'Your name'}</strong><span>{draft.email || 'Your email'}</span><button type="button" className="button secondary small" onClick={() => fileInput.current?.click()} disabled={readingPhoto}>{readingPhoto ? 'Reading image…' : 'Change photo'}</button><small>JPG, PNG or WebP · up to 2 MB</small><input className="sr-only" tabIndex={-1} ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" aria-label="Choose profile photo" onChange={choosePhoto} /></div><div className="avatar-color-picker"><span>Choose a color</span><div role="group" aria-label="Avatar color">{AVATAR_COLORS.map(color => <button key={color} type="button" className={`color-swatch avatar-${color} ${draft.color === color ? 'selected' : ''}`} aria-label={`${color.charAt(0).toUpperCase() + color.slice(1)} avatar color`} aria-pressed={draft.color === color} onClick={() => { fileReader.current?.abort(); setReadingPhoto(false); setDraft(current => ({ ...current, color, photo: null })); setPhotoError('') }} />)}</div><button className="text-button" type="button" disabled={!draft.photo} onClick={() => { fileReader.current?.abort(); setReadingPhoto(false); setDraft(current => ({ ...current, photo: null })); setPhotoError('') }}>Use initials</button></div></section>
    {photoError && <p className="field-error profile-photo-error" role="alert">{photoError}</p>}
    <div className="profile-fields"><div className="profile-field-row"><div className="account-field"><label htmlFor="profile-name">Display name</label><input id="profile-name" required autoComplete="name" maxLength={80} value={draft.name} onChange={e => change('name', e.target.value)} aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'profile-name-error' : undefined} />{errors.name && <small className="field-error" id="profile-name-error">{errors.name}</small>}</div><div className="account-field"><label htmlFor="profile-email">Email</label><input id="profile-email" type="email" required autoComplete="email" maxLength={254} value={draft.email} onChange={e => change('email', e.target.value)} aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'profile-email-error' : undefined} />{errors.email && <small className="field-error" id="profile-email-error">{errors.email}</small>}</div></div>
      <div className="account-field"><label htmlFor="profile-city">Home city</label><input id="profile-city" autoComplete="address-level2" maxLength={100} placeholder="Your city" value={draft.city} onChange={e => change('city', e.target.value)} aria-invalid={Boolean(errors.city)} />{errors.city && <small className="field-error">{errors.city}</small>}</div>
      <div className="account-field"><label htmlFor="profile-bio">A few words about you</label><textarea id="profile-bio" rows={4} maxLength={80} placeholder="Your kind of live music…" value={draft.bio} onChange={e => change('bio', e.target.value)} aria-invalid={Boolean(errors.bio)} aria-describedby="profile-bio-hint" /><span className="bio-caption" id="profile-bio-hint"><span>A short introduction. Make it yours.</span><span>{draft.bio.length} / 80</span></span>{errors.bio && <small className="field-error">{errors.bio}</small>}</div>
    </div><p className="profile-local-note"><Icon name="layers" size={15} />{issue || 'Your local preview profile. Changes stay in this browser.'}</p><div className="profile-form-actions"><button className="button secondary" type="button" onClick={onCancel}>Cancel</button><button className="button primary" type="submit" disabled={!dirty || readingPhoto}>Save changes<Icon name="arrow" size={17} /></button></div>
  </form><aside className="profile-story"><Icon name="music" size={24} /><p className="eyebrow">YOUR JOURNEY</p><h2>Your story,<br /> so far<span>.</span></h2><p>A collection only you could make.</p><div className="profile-story-stats">{[[counts.concerts, 'Concerts', 'Nights in your collection.'], [counts.artists, 'Artists', 'A soundtrack of your own.'], [counts.venues, 'Venues', 'Places that feel like home.']].map(([count, label, text]) => <div key={label}><strong>{count}</strong><span><b>{label}</b><small>{text}</small></span></div>)}</div></aside></div>
}
