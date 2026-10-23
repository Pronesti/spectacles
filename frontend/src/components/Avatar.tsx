import { avatarLetter } from '../account'
export function Avatar({ name, large = false }: { name: string; large?: boolean }) {
  return <span className={`account-avatar avatar-lime ${large ? 'large' : ''}`} aria-hidden="true">{avatarLetter(name)}</span>
}
