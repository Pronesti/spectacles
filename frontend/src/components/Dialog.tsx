import { useEffect, useRef } from 'react'
import type { ReactNode } from 'react'
import { Icon } from './Icon'

export function Dialog({ title, onClose, children, drawer = false }: { title: string; onClose: () => void; children: ReactNode; drawer?: boolean }) {
  const ref = useRef<HTMLDialogElement>(null)
  useEffect(() => { const dialog = ref.current!; dialog.showModal(); dialog.querySelector<HTMLElement>('[data-autofocus]')?.focus(); return () => { dialog.close() } }, [])
  return <dialog ref={ref} className={`dialog ${drawer ? 'drawer' : ''}`} aria-labelledby="dialog-title" onCancel={e => { e.preventDefault(); onClose() }} onClick={e => { if (e.target === e.currentTarget) { const box = e.currentTarget.getBoundingClientRect(); if (e.clientX < box.left || e.clientX > box.right || e.clientY < box.top || e.clientY > box.bottom) onClose() } }}><div className="dialog-header"><div><div className="eyebrow">YOUR COLLECTION</div><h2 id="dialog-title">{title}</h2></div><button className="icon-button" onClick={onClose} aria-label="Close dialog"><Icon name="close" /></button></div>{children}</dialog>
}
