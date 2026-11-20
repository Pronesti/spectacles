import { Button } from 'react-aria-components'
import { useId, useLayoutEffect, useRef } from 'react'
import type { ReactNode } from 'react'
import { Icon } from './Icon'

export function Dialog({ title, onClose, children, drawer = false }: { title: string; onClose: () => void; children: ReactNode; drawer?: boolean }) {
  const titleId = useId()
  const ref = useRef<HTMLDialogElement>(null)
  // Closing returns focus to the control that opened the dialog, when it still exists and nothing else took focus.
  useLayoutEffect(() => {
    const dialog = ref.current!, opener = document.activeElement
    dialog.showModal(); dialog.querySelector<HTMLElement>('[data-autofocus]')?.focus()
    return () => {
      const active = document.activeElement, focusInside = !active || active === document.body || dialog.contains(active)
      dialog.close()
      if (!focusInside || !(opener instanceof HTMLElement) || opener === document.body) return
      // The same commit can re-enable the opener (a save ends), so wait until it finishes.
      requestAnimationFrame(() => { const now = document.activeElement; if (opener.isConnected && (!now || now === document.body)) opener.focus() })
    }
  }, [])
  return <dialog ref={ref} className={`dialog ${drawer ? 'drawer' : ''}`} aria-labelledby={titleId} onCancel={e => { e.preventDefault(); onClose() }} onClick={e => { if (e.target === e.currentTarget) { const box = e.currentTarget.getBoundingClientRect(); if (e.clientX < box.left || e.clientX > box.right || e.clientY < box.top || e.clientY > box.bottom) onClose() } }}><div className="dialog-header"><div><div className="eyebrow">YOUR COLLECTION</div><h2 id={titleId}>{title}</h2></div><Button type="button" className="icon-button" onPress={onClose} aria-label="Close dialog"><Icon name="close" /></Button></div>{children}</dialog>
}
