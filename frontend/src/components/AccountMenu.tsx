import { useEffect, useRef, useState } from 'react'
import type { KeyboardEvent } from 'react'
import type { AccountUser, AuthMode } from '../accountApi'
import { Avatar } from './Avatar'
import { Icon } from './Icon'

export function AccountMenu({ user, busy, onProfile, onAuth, onSignOut }: {
  user: AccountUser | null; busy: boolean; onProfile: () => void; onAuth: (mode: AuthMode) => void; onSignOut: () => void
}) {
  const [open, setOpen] = useState(false)
  const root = useRef<HTMLDivElement>(null), trigger = useRef<HTMLButtonElement>(null)
  const firstItem = useRef<'first' | 'last'>('first')
  const selector = 'button[role="menuitem"]:not(:disabled)'
  useEffect(() => {
    if (!open) return
    const items = root.current!.querySelectorAll<HTMLButtonElement>(selector)
    items[firstItem.current === 'last' ? items.length - 1 : 0]?.focus()
    const outside = (event: PointerEvent | FocusEvent) => {
      if (event.target instanceof Node && !root.current?.contains(event.target)) setOpen(false)
    }
    document.addEventListener('pointerdown', outside); document.addEventListener('focusin', outside)
    return () => { document.removeEventListener('pointerdown', outside); document.removeEventListener('focusin', outside) }
  }, [open])
  const handleKeys = (event: KeyboardEvent) => {
    if (busy) return
    if (event.key === 'Escape' && open) { event.preventDefault(); setOpen(false); trigger.current?.focus(); return }
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return
    event.preventDefault()
    if (!open) { firstItem.current = event.key === 'ArrowUp' || event.key === 'End' ? 'last' : 'first'; setOpen(true); return }
    const items = Array.from(root.current!.querySelectorAll<HTMLButtonElement>(selector))
    if (!items.length) return
    const current = items.findIndex(item => item === document.activeElement)
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length
    items[next]?.focus()
  }
  const choose = (action: () => void) => { setOpen(false); action() }
  const name = user?.name ?? 'Guest'
  return <div className="account-menu" ref={root} onKeyDown={handleKeys}>
    <button ref={trigger} disabled={busy} className={`account-trigger ${open ? 'is-open' : ''}`} aria-label={`Account menu for ${name}`} aria-haspopup="menu" aria-expanded={open} aria-controls={open ? 'account-menu-panel' : undefined} onClick={() => { firstItem.current = 'first'; setOpen(value => !value) }}><Avatar name={name} /></button>
    {open && <div className="account-menu-panel" id="account-menu-panel"><div className="account-menu-identity"><Avatar name={name} /><div><strong>{name}</strong>{user && <span>{user.email}</span>}<small>{user ? 'Signed in' : 'Session expired'}</small></div></div>
      <div className="account-menu-items" role="menu" aria-label="Account">{user ? <>
        <button disabled={busy} role="menuitem" tabIndex={-1} onClick={() => choose(onProfile)}><Icon name="user" size={20} /><span>Your profile</span><Icon name="arrow" size={17} /></button>
        <button disabled={busy} role="menuitem" tabIndex={-1} onClick={() => choose(onSignOut)}><Icon name="logout" size={20} /><span>Sign out</span></button>
      </> : <>
        <button disabled={busy} role="menuitem" tabIndex={-1} onClick={() => choose(() => onAuth('login'))}><Icon name="user" size={20} /><span>Sign in</span></button>
        <button disabled={busy} role="menuitem" tabIndex={-1} onClick={() => choose(() => onAuth('register'))}><Icon name="plus" size={20} /><span>Create account</span></button>
      </>}</div><p>Concerts and setlists are saved to your account.</p></div>}
  </div>
}
