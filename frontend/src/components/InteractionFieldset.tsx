import { useContext } from 'react'
import type { ComponentProps } from 'react'

// Native fieldsets disable buttons/inputs automatically. Composite collection
// items also need the same state because their focusable elements are divs.
import { InteractionDisabledContext } from './InteractionDisabledContext'
export function InteractionFieldset({ disabled, children, ...props }: ComponentProps<'fieldset'>) {
  const inherited = useContext(InteractionDisabledContext)
  const blocked = inherited || Boolean(disabled)
  return <InteractionDisabledContext.Provider value={blocked}><fieldset disabled={blocked} {...props}>{children}</fieldset></InteractionDisabledContext.Provider>
}
