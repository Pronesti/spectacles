import { Input, SearchField } from 'react-aria-components'
import type { ComponentPropsWithRef } from 'react'

// Keep the existing wrapping-label layout for explicit provider searches.
export function SourceSearchField({ label, value, onChange, onSubmit }: { label: string; value: string; onChange: (value: string) => void; onSubmit?: () => void }) {
  return <SearchField aria-label={label} value={value} onChange={onChange} onSubmit={onSubmit} type="text" maxLength={200}
    render={props => <label {...props as ComponentPropsWithRef<'label'>} />}>
    <span>{label}</span><Input onKeyDownCapture={event => { if (event.key === 'Escape') event.stopPropagation() }} />
  </SearchField>
}
