import { avatarLetter } from '../account'
import type { Profile } from '../account'

export function Avatar({ profile, large = false }: { profile: Profile; large?: boolean }) {
  return <span className={`account-avatar avatar-${profile.color} ${large ? 'large' : ''}`} aria-hidden="true">
    {profile.photo ? <img src={profile.photo} alt="" /> : avatarLetter(profile.name)}
  </span>
}
