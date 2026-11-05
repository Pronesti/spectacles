import { InteractionDisabledContext } from './InteractionDisabledContext'
import { Button, TagGroup, TagList, Tag } from 'react-aria-components'
import { useContext, useId, useState } from 'react'
import { keyCatalogChoices } from '../catalogChoiceKeys'
import type { CatalogChoice } from '../domain'
import { CatalogPicker } from './CatalogPicker'
import type { CatalogAccess } from './CatalogPicker'
export function CatalogChoices({ kind, label, choices, onChange, catalog }: { kind: 'artists' | 'tours'; label: string; choices: CatalogChoice[]; onChange: (choices: CatalogChoice[]) => void; catalog: CatalogAccess }) {
  const interactionDisabled = useContext(InteractionDisabledContext)
  const id = useId(), [name, setName] = useState('')
  const [keyed, setKeyed] = useState(() => keyCatalogChoices(null, choices))
  const current = keyed.source === choices ? keyed : keyCatalogChoices(keyed, choices)
  if (current !== keyed) setKeyed(current)
  const add = (choice: CatalogChoice) => {
    if (choice.catalogId && choices.some(current => current.catalogId === choice.catalogId)) return
    onChange([...choices, choice]); setName('')
  }
  return <section className="catalog-choices" aria-label={label}><h3>{label}</h3><TagGroup aria-label={`Selected ${label.toLowerCase()}`} onRemove={interactionDisabled ? undefined : keys => onChange(current.items.filter(item => !keys.has(item.id)).map(item => item.choice))}>
      <TagList dependencies={[interactionDisabled]} className="catalog-chips" items={current.items}>{item => <Tag isDisabled={interactionDisabled} id={item.id} className="catalog-chip" textValue={item.choice.name}><span>{item.choice.name}</span><Button slot="remove" type="button" aria-labelledby="" aria-label={`Remove ${item.choice.name}, item ${current.items.indexOf(item) + 1}`}>×</Button></Tag>}</TagList>
    </TagGroup>
    <CatalogPicker catalog={catalog} kind={kind} label={`Search ${label.toLowerCase()}`} onPick={row => add({ name: row.name, catalogId: row.id })} />
    <div className="catalog-manual"><label htmlFor={id}>Enter a name manually<input id={id} maxLength={255} value={name} onChange={event => setName(event.target.value)} onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); if (name.trim()) add({ name: name.trim(), catalogId: null }) } }} /></label><Button type="button" className="button secondary small" isDisabled={!name.trim()} onPress={() => add({ name: name.trim(), catalogId: null })}>Add name</Button></div>
  </section>
}
