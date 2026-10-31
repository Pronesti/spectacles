import type { ReactNode } from 'react'
export function CreationSelection({ label, name, detail, onChange, onRemove }: { label: string; name: string; detail?: ReactNode; onChange?: () => void; onRemove?: () => void }) {
  return <section className="creation-selection" aria-label={`Selected ${label}`}><div><span className="selection-label">{label}</span><strong>{name}</strong>{detail && <small>{detail}</small>}</div><div className="selection-actions">{onChange && <button type="button" className="text-button" onClick={onChange}>Change <span className="sr-only">{label}</span></button>}{onRemove && <button type="button" className="text-button" aria-label={`Remove ${label} ${name}`} onClick={onRemove}>Remove</button>}</div></section>
}
