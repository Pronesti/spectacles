import { Button } from 'react-aria-components'
export function RecoveredSearch({ value, onUse }: { value: string | null; onUse: () => void }) {
  return value ? <p className="hint">Recovered source search: “{value}” <Button type="button" onPress={onUse}>Use recovered search</Button></p> : null
}
