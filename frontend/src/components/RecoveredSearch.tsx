export function RecoveredSearch({ value, onUse }: { value: string | null; onUse: () => void }) {
  return value ? <p className="hint">Recovered source search: “{value}” <button type="button" onClick={onUse}>Use recovered search</button></p> : null
}
