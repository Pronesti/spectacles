import { useEffect, useRef, useState } from 'react'
import type { KeyboardEvent } from 'react'
import type { Profile } from '../account'
import { Avatar } from './Avatar'
import { Icon } from './Icon'

export function AccountMenu({ profile, onProfile, onSignOut }: { profile: Profile; onProfile: () => void; onSignOut: () => void }) {
  const [open, setOpen] = useState(false)
  const root = useRef<HTMLDivElement>(null)
  const trigger = useRef<HTMLButtonElement>(null)
  const firstItem = useRef<'first' | 'last'>('first')

  useEffect(() => {
    if (!open) return
    const items = root.current!.querySelectorAll<HTMLButtonElement>('[role="menuitem"]')
    items[firstItem.current === 'last' ? items.length - 1 : 0]?.focus()
    const outside = (event: PointerEvent | FocusEvent) => {
      if (event.target instanceof Node && !root.current?.contains(event.target)) setOpen(false)
    }
    document.addEventListener('pointerdown', outside)
    document.addEventListener('focusin', outside)
    return () => { document.removeEventListener('pointerdown', outside); document.removeEventListener('focusin', outside) }
  }, [open])

  const close = () => { setOpen(false); trigger.current?.focus() }
  const handleKeys = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && open) { event.preventDefault(); close(); return }
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return
    event.preventDefault()
    if (!open) { firstItem.current = event.key === 'ArrowUp' || event.key === 'End' ? 'last' : 'first'; setOpen(true); return }
    const items = Array.from(root.current!.querySelectorAll<HTMLButtonElement>('[role="menuitem"]'))
    const current = items.findIndex(item => item === document.activeElement)
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length
    items[next]?.focus()
  }

  return <div className="account-menu" ref={root} onKeyDown={handleKeys}>
    <button ref={trigger} className={`account-trigger ${open ? 'is-open' : ''}`} aria-label={`Account menu for ${profile.name}`} aria-haspopup="menu" aria-expanded={open} aria-controls={open ? 'account-menu-panel' : undefined} onClick={() => { firstItem.current = 'first'; setOpen(value => !value) }}><Avatar profile={profile} /></button>
    {open && <div className="account-menu-panel" id="account-menu-panel">
      <div className="account-menu-identity"><Avatar profile={profile} /><div><strong>{profile.name}</strong><span>{profile.email}</span><small>Local preview profile</small></div></div>
      <div className="account-menu-items" role="menu" aria-label="Account"><button role="menuitem" tabIndex={-1} onClick={() => { setOpen(false); onProfile() }}><Icon name="user" size={20} /><span>Your profile</span><Icon name="arrow" size={17} /></button><button role="menuitem" tabIndex={-1} onClick={() => { setOpen(false); onSignOut() }}><Icon name="logout" size={20} /><span>Sign out</span></button></div>
      <p>Concert data stays in this browser.</p>
    </div>}
  </div>
}
