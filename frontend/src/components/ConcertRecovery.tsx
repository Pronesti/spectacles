import { createContext, useContext, useState } from 'react'
import type { Dispatch, SetStateAction } from 'react'
import type { RecoveryController, RecoveryFields } from '../concertRecovery'
export const ConcertRecoveryContext = createContext<{ controller: RecoveryController; changed: () => void } | null>(null)
export function useRecoveryField<K extends keyof RecoveryFields>(name: K, initial: RecoveryFields[K]): [RecoveryFields[K], Dispatch<SetStateAction<RecoveryFields[K]>>] {
  const recovery = useContext(ConcertRecoveryContext)
  const [value, setValue] = useState<RecoveryFields[K]>(() => recovery?.controller.snapshot.fields[name] ?? initial)
  const change: Dispatch<SetStateAction<RecoveryFields[K]>> = next => {
    const result = typeof next === 'function' ? (next as (v: RecoveryFields[K]) => RecoveryFields[K])(value) : next
    recovery?.controller.changeField(name, result)
    setValue(result); recovery?.changed()
  }
  return [value, change]
}
