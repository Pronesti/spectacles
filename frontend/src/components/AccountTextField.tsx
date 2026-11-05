import { FieldError, Input, Label, Text, TextField } from 'react-aria-components'

export function AccountTextField({ id, label, value, onChange, error, description, type = 'text', autoComplete, maxLength, autoFocus }: {
  id: string; label: string; value: string; onChange: (value: string) => void; error?: string; description?: string; type?: 'text' | 'email'; autoComplete: string; maxLength: number; autoFocus?: boolean
}) {
  return <TextField id={id} className="account-field" name={id.split('-').at(-1)} value={value} onChange={onChange} type={type} autoComplete={autoComplete} maxLength={maxLength} isRequired isInvalid={Boolean(error)} validationBehavior="aria" autoFocus={autoFocus}>
    <Label>{label}</Label><Input />
    {error && <FieldError elementType="small" className="field-error">{error}</FieldError>}
    {description && <Text elementType="small" slot="description">{description}</Text>}
  </TextField>
}
