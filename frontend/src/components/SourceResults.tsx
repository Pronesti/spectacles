import { useContext } from 'react'
import { InteractionDisabledContext } from './InteractionDisabledContext'
import { ListBox, ListBoxItem } from 'react-aria-components'
import type { ComponentPropsWithRef } from 'react'

// Preserve the provider list's existing ul/li/button styling while React Aria
// owns active-option navigation, typeahead, and explicit preview activation.
export function SourceResults<T>({ label, items, getKey, getText, onAction }: { label: string; items: T[]; getKey: (item: T) => string; getText: (item: T) => string; onAction: (item: T) => void }) {
  const interactionDisabled = useContext(InteractionDisabledContext)
  if (!items.length) return null
  return <ListBox aria-label={label} selectionMode="none" className="source-results" render={props => <ul {...props as ComponentPropsWithRef<'ul'>} />}>
    {items.map(item => <ListBoxItem isDisabled={interactionDisabled} key={getKey(item)} id={getKey(item)} textValue={getText(item)} onAction={() => onAction(item)}
      render={props => <li role="presentation"><button {...props as ComponentPropsWithRef<'button'>} type="button" /></li>}>{getText(item)}</ListBoxItem>)}
  </ListBox>
}
