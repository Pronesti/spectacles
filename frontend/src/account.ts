export const PROFILE_KEY = 'spectacles.preview.profile.v1'
export const ENTRY_KEY = 'spectacles.preview.entry.v1'
export const MAX_AVATAR_BYTES = 2 * 1024 * 1024
export const AVATAR_COLORS = ['lime', 'lavender', 'sand', 'ink'] as const
export type AvatarColor = typeof AVATAR_COLORS[number]
export type AuthView = 'login' | 'register'
export type Profile = {
  version: 1
  name: string
  email: string
  city: string
  bio: string
  color: AvatarColor
  photo: string | null
}
export type AccountErrors = Partial<Record<'name' | 'email' | 'password' | 'confirmation' | 'city' | 'bio', string>>
export const DEFAULT_PROFILE: Profile = {
  version: 1, name: 'Jamie Morgan', email: 'jamie@example.com', city: 'Buenos Aires',
  bio: 'Chasing good music and the next great encore.', color: 'lime', photo: null,
}

export function avatarLetter(name: string): string {
  return Array.from(name.trim())[0]?.toLocaleUpperCase() ?? 'J'
}

export function profileErrors(profile: Pick<Profile, 'name' | 'email' | 'city' | 'bio'>): AccountErrors {
  const errors: AccountErrors = {}
  if (!profile.name.trim() || profile.name.trim().length > 80) errors.name = 'Use a display name between 1 and 80 characters.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(profile.email.trim()) || profile.email.trim().length > 254) errors.email = 'Enter a valid email address.'
  if (profile.city.trim().length > 100) errors.city = 'Keep the city name to 100 characters or fewer.'
  if (profile.bio.length > 80) errors.bio = 'Keep your introduction to 80 characters or fewer.'
  return errors
}

export function credentialErrors(input: { mode: AuthView; name: string; email: string; password: string; confirmation: string }): AccountErrors {
  const errors = profileErrors({ name: input.mode === 'register' ? input.name : DEFAULT_PROFILE.name, email: input.email, city: '', bio: '' })
  if (input.password.length < 8 || input.password.length > 128) errors.password = 'Use 8–128 characters for this preview.'
  if (input.mode === 'register' && input.password !== input.confirmation) errors.confirmation = 'The passwords do not match.'
  return errors
}

export function avatarFileError(type: string, size: number): string | null {
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(type)) return 'Choose a JPG, PNG, or WebP image.'
  if (size === 0 || size > MAX_AVATAR_BYTES) return 'Choose an image of 2 MB or smaller.'
  return null
}

function safePhoto(photo: unknown): photo is string | null {
  return photo === null || typeof photo === 'string' && photo.length <= Math.ceil(MAX_AVATAR_BYTES * 4 / 3) + 100 && /^data:image\/(?:jpeg|png|webp);base64,[A-Za-z0-9+/]+={0,2}$/.test(photo)
}

// Construct an explicit profile payload: form-only fields such as passwords cannot leak into storage.
export function profilePayload(profile: Profile): Profile {
  return { version: 1, name: profile.name.trim(), email: profile.email.trim(), city: profile.city.trim(), bio: profile.bio.trim(), color: profile.color, photo: profile.photo }
}

export function decodeProfile(raw: string): Profile | null {
  try {
    const data: unknown = JSON.parse(raw)
    if (!data || typeof data !== 'object' || Array.isArray(data)) return null
    const record = data as Record<string, unknown>
    if (record.version !== 1 || !['name', 'email', 'city', 'bio'].every(key => typeof record[key] === 'string') || !AVATAR_COLORS.includes(record.color as AvatarColor) || !safePhoto(record.photo)) return null
    const profile = profilePayload(record as Profile)
    return Object.keys(profileErrors(profile)).length ? null : profile
  } catch { return null }
}
