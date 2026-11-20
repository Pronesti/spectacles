import { useLayoutEffect } from 'react'

let lastFocused: Element | null = null
if (typeof document !== 'undefined') document.addEventListener('focusin', event => { lastFocused = event.target instanceof Element ? event.target : null }, true)

/** Focus counts as lost when the last focused element left the document and focus fell back to the body. A click on blank space keeps the last focused element connected, so it never counts. */
export function isFocusLost(active: Element | null, previous: Element | null, body: Element | null): boolean {
  return (!active || active === body) && !!previous && !previous.isConnected
}

export function focusWasLost(): boolean {
  return typeof document !== 'undefined' && isFocusLost(document.activeElement, lastFocused, document.body)
}

/**
 * After each render, moves focus to the target only when the control that had it was just removed
 * (a picked search result, a confirmed form). It never takes focus from a control that still exists.
 */
export function useRescueFocus(target: () => HTMLElement | null | undefined) {
  useLayoutEffect(() => { if (focusWasLost()) target()?.focus() })
}
