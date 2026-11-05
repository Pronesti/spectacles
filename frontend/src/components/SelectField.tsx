import { useContext, useRef, useState } from 'react'
import { Button, FieldError, Label, ListBox, ListBoxItem, Popover, Select, SelectValue, Text } from 'react-aria-components'
import { InteractionDisabledContext } from './InteractionDisabledContext'
import { Icon } from './Icon'
import '../select.css'

type Option = { id: string; label: string }
export function SelectField({ id, label, hiddenLabel = false, className, options, value, onChange, isDisabled = false, error, description }: {
  id?: string; label: string; hiddenLabel?: boolean; className: string; options: Option[]; value: string; onChange: (value: string) => void; isDisabled?: boolean; error?: string; description?: string
}) {
  const inheritedDisabled = useContext(InteractionDisabledContext)
  const trigger = useRef<HTMLButtonElement>(null)
  const [open, setOpen] = useState(false)
  const [portal, setPortal] = useState<Element | undefined>()
  const disabled = isDisabled || inheritedDisabled
  if (disabled && open) setOpen(false)
  return <Select id={id} className={className} value={value} onChange={key => { if (typeof key === 'string') onChange(key) }}
    isDisabled={disabled} isInvalid={Boolean(error)} validationBehavior="aria" isOpen={open && !disabled} onOpenChange={value => {
      // Keep dropdowns inside a native modal's top layer when used in a drawer.
      if (value) setPortal(trigger.current?.closest('dialog') ?? undefined)
      setOpen(value)
    }}>
    <Label className={hiddenLabel ? 'sr-only' : undefined}>{label}</Label>
    <Button ref={trigger} type="button" className="select-trigger" render={props => <button {...props} aria-invalid={Boolean(error)} />}><SelectValue className="select-value">{({ selectedText }) => selectedText}</SelectValue><Icon name="down" size={14} /></Button>
    {error && <FieldError elementType="small" className="field-error">{error}</FieldError>}
    {description && <Text elementType="small" slot="description">{description}</Text>}
    <Popover className="select-popover" placement="bottom start" offset={4} UNSTABLE_portalContainer={portal}>
      <ListBox aria-label={`${label} options`} aria-labelledby="" items={options} className="select-options">{option => <ListBoxItem id={option.id} textValue={option.label} className="select-option"><span className="select-option-label">{option.label}</span><span className="select-option-check" aria-hidden="true"><Icon name="check" size={14} /></span></ListBoxItem>}</ListBox>
    </Popover>
  </Select>
}
