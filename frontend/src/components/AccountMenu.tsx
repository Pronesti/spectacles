import { useContext, useState } from 'react'
import { Button, Menu, MenuItem, MenuTrigger, Popover, RootMenuTriggerStateContext } from 'react-aria-components'
import type { AccountUser, AuthMode } from '../accountApi'
import { Avatar } from './Avatar'
import { Icon } from './Icon'

function AccountTrigger({ name, busy, open }: { name: string; busy: boolean; open: boolean }) {
  const state = useContext(RootMenuTriggerStateContext)
  return <Button isDisabled={busy} className={`account-trigger ${open ? 'is-open' : ''}`} aria-label={`Account menu for ${name}`}
    onKeyDown={event => {
      if (!busy && (event.key === 'Home' || event.key === 'End')) {
        event.preventDefault(); state?.open(event.key === 'End' ? 'last' : 'first')
      }
    }}><Avatar name={name} /></Button>
}

export function AccountMenu({ user, busy, onProfile, onAuth, onSignOut }: {
  user: AccountUser | null; busy: boolean; onProfile: () => void; onAuth: (mode: AuthMode) => void; onSignOut: () => void
}) {
  const [open, setOpen] = useState(false)
  const name = user?.name ?? 'Guest'
  return <div className="account-menu">
    <MenuTrigger isOpen={open} onOpenChange={setOpen}>
      <AccountTrigger name={name} busy={busy} open={open} />
      <Popover className="account-menu-panel" placement="bottom end" offset={12} isNonModal>
        <div className="account-menu-identity"><Avatar name={name} /><div><strong>{name}</strong>{user && <span>{user.email}</span>}<small>{user ? 'Signed in' : 'Session expired'}</small></div></div>
        {/* Prevent page scrolling from dismissing the popover while Menu handles Home/End focus. */}
        <div onKeyDownCapture={event => { if (event.key === 'Home' || event.key === 'End') event.preventDefault() }}><RootMenuTriggerStateContext.Consumer>{state => <Menu className="account-menu-items" aria-label="Account" aria-labelledby="" autoFocus={state?.focusStrategy ?? 'first'} shouldFocusWrap>
          {user ? <>
            <MenuItem id="profile" textValue="Your profile" isDisabled={busy} onAction={onProfile}><Icon name="user" size={20} /><span>Your profile</span><Icon name="arrow" size={17} /></MenuItem>
            <MenuItem id="sign-out" textValue="Sign out" isDisabled={busy} onAction={onSignOut}><Icon name="logout" size={20} /><span>Sign out</span></MenuItem>
          </> : <>
            <MenuItem id="sign-in" textValue="Sign in" isDisabled={busy} onAction={() => onAuth('login')}><Icon name="user" size={20} /><span>Sign in</span></MenuItem>
            <MenuItem id="register" textValue="Create account" isDisabled={busy} onAction={() => onAuth('register')}><Icon name="plus" size={20} /><span>Create account</span></MenuItem>
          </>}
        </Menu>}</RootMenuTriggerStateContext.Consumer></div>
      </Popover>
    </MenuTrigger>
  </div>
}
