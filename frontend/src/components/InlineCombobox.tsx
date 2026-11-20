import { useContext, useEffect } from 'react'
import type { ReactNode, Ref } from 'react'
import { ComboBoxStateContext, Input, PopoverContext, useContextProps } from 'react-aria-components'

/**
 * React Aria's ComboBox treats its open list as a floating popover and sets
 * aria-hidden on everything outside the input and list. These results sit in
 * document flow, so the surrounding form (headings, Back/Continue, Close)
 * must stay in the accessibility tree while the list is open. Elements that
 * were already aria-hidden when the list opened (decorative icons) keep it.
 */
function useKeepSurroundingsExposed(isOpen: boolean) {
  useEffect(() => {
    if (!isOpen || typeof MutationObserver === 'undefined') return
    const authored = new Set(document.querySelectorAll('[aria-hidden="true"]'))
    const observer = new MutationObserver(records => {
      for (const { target } of records) {
        if (target instanceof Element && !authored.has(target) && target.getAttribute('aria-hidden') === 'true') target.removeAttribute('aria-hidden')
      }
    })
    observer.observe(document.body, { attributes: true, attributeFilter: ['aria-hidden'], subtree: true })
    return () => observer.disconnect()
  }, [isOpen])
}

// This menu stays in document flow. Connect its ref to React Aria's combobox
// rather than using floating-popover positioning/dismiss-on-scroll behavior.
export function InlineComboboxResults({ children, persistent, onDismiss, className = "creation-search" }: { children: ReactNode; persistent?: ReactNode; onDismiss?: () => void; className?: string }) {
  const state = useContext(ComboBoxStateContext)
  const [, ref] = useContextProps({}, null, PopoverContext)
  useKeepSurroundingsExposed(!!state?.isOpen)
  // The hidden collection pass needs the ListBox even before state exists.
  if (!state) return <>{children}</>
  return state.isOpen || persistent ? <section ref={ref} className={className} onKeyDownCapture={event => {
    if (event.key === 'Escape' && state.isOpen && !event.nativeEvent.isComposing) {
      event.preventDefault(); event.stopPropagation(); onDismiss?.(); state.close()
    }
  }}>{state.isOpen && children}{persistent}</section> : null
}

export function InlineComboboxInput({ placeholder, onComposing, statusId, inputRef, type, onSearch }: { placeholder?: string; onComposing: (composing: boolean) => void; statusId?: string; inputRef?: Ref<HTMLInputElement>; type?: 'text' | 'search'; onSearch?: () => void }) {
  const state = useContext(ComboBoxStateContext)
  return <Input ref={inputRef} type={type} aria-describedby={state?.isOpen ? statusId : undefined} placeholder={placeholder} maxLength={200} autoComplete="off"
    onClick={() => state?.open(null, 'focus')}
    onKeyDownCapture={event => {
      if (event.nativeEvent.isComposing) return
      if (event.key === 'Enter' && onSearch && (!state?.isOpen || !state.selectionManager.focusedKey)) {
        event.preventDefault(); event.stopPropagation(); state?.open(null, 'manual'); onSearch(); return
      }
      if (event.key === 'Tab' && state?.isOpen) {
        event.stopPropagation(); state.close()
      }
      if (event.key === 'Escape' && state?.isOpen) {
        event.preventDefault(); event.stopPropagation(); state.close()
      }
    }}
    onCompositionStart={() => onComposing(true)} onCompositionEnd={() => onComposing(false)} />
}
