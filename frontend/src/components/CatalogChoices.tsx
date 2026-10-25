import { useId, useState } from 'react'
import type { CatalogChoice } from '../domain'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
export function CatalogChoices({ kind, label, choices, onChange, catalog }: { kind: 'artists' | 'tours'; label: string; choices: CatalogChoice[]; onChange: (choices: CatalogChoice[]) => void; catalog: CatalogAccess }) {
  const id = useId(), [name, setName] = useState('')
  const add = (choice: CatalogChoice) => {
    if (choice.catalogId && choices.some(current => current.catalogId === choice.catalogId)) return
    onChange([...choices, choice]); setName('')
  }
  return <section className="catalog-choices" aria-label={label}><h3>{label}</h3><ul className="catalog-chips">{choices.map((choice, index) => <li className="catalog-chip" key={choice.catalogId ?? `manual-${index}`}><span>{choice.name}</span><button type="button" aria-label={`Remove ${choice.name}, item ${index + 1}`} onClick={() => onChange(choices.filter((_, position) => position !== index))}>×</button></li>)}</ul>
    <CatalogPicker catalog={catalog} kind={kind} label={`Search ${label.toLowerCase()}`} onPick={row => add({ name: row.name, catalogId: row.id })} />
    <div className="catalog-manual"><label htmlFor={id}>Enter a name manually<input id={id} maxLength={255} value={name} onChange={event => setName(event.target.value)} onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); if (name.trim()) add({ name: name.trim(), catalogId: null }) } }} /></label><button type="button" className="button secondary small" disabled={!name.trim()} onClick={() => add({ name: name.trim(), catalogId: null })}>Add name</button></div>
  </section>
}
