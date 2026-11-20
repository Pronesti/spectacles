import { Button } from 'react-aria-components'
import { useId, useLayoutEffect, useRef } from 'react'
import type { ReactNode } from 'react'
import { focusWasLost } from '../focusRescue'
/** A selection replaces the search that produced it, so it takes focus when that search's input left with focus in it. */
export function CreationSelection({ label, name, detail, onChange, onRemove, actions }: { label: string; name: string; detail?: ReactNode; actions?: ReactNode; onChange?: () => void; onRemove?: () => void }) {
  const summaryId = useId()
  const section = useRef<HTMLElement>(null), change = useRef<HTMLButtonElement>(null)
  // Only on mount: when several selections render, the one that just replaced the search takes focus.
  useLayoutEffect(() => { if (focusWasLost()) (change.current ?? section.current)?.focus() }, [])
  return <section ref={section} tabIndex={-1} className="creation-selection" aria-label={`Selected ${label}`}><div id={summaryId}><span className="selection-label">{label}</span><strong>{name}</strong>{detail && <small>{detail}</small>}</div><div className="selection-actions">{actions ?? <>{onChange && <Button ref={change} type="button" className="text-button" aria-describedby={summaryId} onPress={onChange}>Change <span className="sr-only">{label}</span></Button>}{onRemove && <Button type="button" className="text-button" aria-label={`Remove ${label} ${name}`} onPress={onRemove}>Remove</Button>}</>}</div></section>
}
