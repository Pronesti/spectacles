import { Button } from 'react-aria-components'
import type { ReactNode } from 'react'
export function CreationSelection({ label, name, detail, onChange, onRemove, actions }: { label: string; name: string; detail?: ReactNode; actions?: ReactNode; onChange?: () => void; onRemove?: () => void }) {
  return <section className="creation-selection" aria-label={`Selected ${label}`}><div><span className="selection-label">{label}</span><strong>{name}</strong>{detail && <small>{detail}</small>}</div><div className="selection-actions">{actions ?? <>{onChange && <Button type="button" className="text-button" onPress={onChange}>Change <span className="sr-only">{label}</span></Button>}{onRemove && <Button type="button" className="text-button" aria-label={`Remove ${label} ${name}`} onPress={onRemove}>Remove</Button>}</>}</div></section>
}
