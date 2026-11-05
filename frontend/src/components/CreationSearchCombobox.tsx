import { InteractionDisabledContext } from './InteractionDisabledContext'
import { useContext, useId, useState } from 'react'
import { ComboBox, Label, ListBox, ListBoxItem } from 'react-aria-components'
import { InlineComboboxInput, InlineComboboxResults } from './InlineCombobox'
import type { ReactNode } from 'react'
import { describeCreationMatch } from '../creationSearch'
import type { CreationMatch } from '../creationSearch'

type Props = {
  label: string
  query: string
  rows: CreationMatch[]
  status: string
  loading: boolean
  actions: ReactNode
  onQuery: (query: string) => void
  onPick: (key: string) => void
  onOpenChange: (open: boolean) => void
  onComposing: (composing: boolean) => void
}

export function CreationSearchCombobox({ label, query, rows, status, loading, actions, onQuery, onPick, onOpenChange, onComposing }: Props) {
  const interactionDisabled = useContext(InteractionDisabledContext)
  const statusId = useId()
  const [selected, setSelected] = useState<string | null>(null)
  const items = [
    ...rows.map(row => describeCreationMatch(row)),
    { key: 'create', label: `+ Create manually${query.trim() ? `: ${query.trim()}` : ''}`, detail: '', source: '' },
  ]
  return <ComboBox isDisabled={interactionDisabled} className="creation-search" items={items} inputValue={query} onInputChange={value => { setSelected(null); onQuery(value) }}
    value={selected} onChange={key => { if (key !== null) { setSelected(String(key)); onPick(String(key)) } }}
    menuTrigger="focus" allowsCustomValue allowsEmptyCollection shouldFocusWrap={false}
    onOpenChange={open => {
      // A result starts a review; it is not a persistent selection in this field.
      // Clear it so dismissing a review allows choosing the same result again.
      if (!open) setSelected(null)
      onOpenChange(open)
    }}>
    <Label>{label}</Label>
      <InlineComboboxInput statusId={statusId} placeholder={`Search ${label.toLowerCase()}…`} onComposing={onComposing} />
      <InlineComboboxResults>
        <div className="creation-results creation-aria-results">
          <div id={statusId} className="creation-status creation-placeholder" role="status" aria-live="polite">{status}</div>
          <ListBox items={items} aria-label={`${label} results`} aria-busy={loading} className="creation-aria-options">
            {item => <ListBoxItem id={item.key} textValue={item.label} className={item.key === 'create' ? 'creation-manual' : ''}>
              {item.key === 'create' ? item.label : <>
                <span><strong>{item.label}</strong>{item.detail && <small>{item.detail}</small>}</span>
                <small className="creation-source">{item.source}</small>
              </>}
            </ListBoxItem>}
          </ListBox>
        </div>
        {actions}
      </InlineComboboxResults>
  </ComboBox>
}
