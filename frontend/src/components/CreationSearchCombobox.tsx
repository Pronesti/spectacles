import { useContext, useId, useState } from 'react'
import { ComboBox, ComboBoxStateContext, Input, Label, ListBox, ListBoxItem, PopoverContext, useContextProps } from 'react-aria-components'
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

// This menu stays in document flow. Connect its ref to React Aria's combobox
// rather than using floating-popover positioning/dismiss-on-scroll behavior.
function InlineResults({ children }: { children: ReactNode }) {
  const state = useContext(ComboBoxStateContext)
  const [, ref] = useContextProps({}, null, PopoverContext)
  // The hidden collection pass needs the ListBox even before state exists.
  if (!state) return <>{children}</>
  return state.isOpen ? <section ref={ref} className="creation-search">{children}</section> : null
}

function SearchInput({ label, onComposing, statusId }: Pick<Props, 'label' | 'onComposing'> & { statusId: string }) {
  const state = useContext(ComboBoxStateContext)
  return <Input aria-describedby={state?.isOpen ? statusId : undefined} placeholder={`Search ${label.toLowerCase()}…`} maxLength={200} autoComplete="off"
    onClick={() => state?.open(null, 'focus')}
    onKeyDownCapture={event => {
      if (event.nativeEvent.isComposing) return
      if (event.key === 'Tab' && state?.isOpen) {
        event.stopPropagation(); state.close()
      }
      if (event.key === 'Escape' && state?.isOpen) {
        event.preventDefault(); event.stopPropagation(); state.close()
      }
    }}
    onCompositionStart={() => onComposing(true)} onCompositionEnd={() => onComposing(false)} />
}

export function CreationSearchCombobox({ label, query, rows, status, loading, actions, onQuery, onPick, onOpenChange, onComposing }: Props) {
  const statusId = useId()
  const [selected, setSelected] = useState<string | null>(null)
  const items = [
    ...rows.map(row => describeCreationMatch(row)),
    { key: 'create', label: `+ Create manually${query.trim() ? `: ${query.trim()}` : ''}`, detail: '', source: '' },
  ]
  return <ComboBox className="creation-search" items={items} inputValue={query} onInputChange={value => { setSelected(null); onQuery(value) }}
    value={selected} onChange={key => { if (key !== null) { setSelected(String(key)); onPick(String(key)) } }}
    menuTrigger="focus" allowsCustomValue allowsEmptyCollection shouldFocusWrap={false}
    onOpenChange={open => {
      // A result starts a review; it is not a persistent selection in this field.
      // Clear it so dismissing a review allows choosing the same result again.
      if (!open) setSelected(null)
      onOpenChange(open)
    }}>
    <Label>{label}</Label>
      <SearchInput statusId={statusId} label={label} onComposing={onComposing} />
      <InlineResults>
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
      </InlineResults>
  </ComboBox>
}
