import { useEffect, useRef, useState } from 'react'
import { AccountApiError, accountErrorMessage } from './accountApi'
import type { AccountClient, AccountUser, Credentials, ProfilePatch } from './accountApi'

type Snapshot = { status: 'checking' | 'guest' | 'authenticated' | 'unavailable'; user: AccountUser | null; error: string }
export function useAccount(client: AccountClient) {
  const [snapshot, setSnapshot] = useState<Snapshot>({ status: 'checking', user: null, error: '' })
  const [retry, setRetry] = useState(0)
  const [busy, setBusy] = useState(false)
  const busyRef = useRef(false)
  const generation = useRef(0)
  const startup = useRef<AbortController | null>(null)
  useEffect(() => {
    const current = ++generation.current
    const controller = new AbortController()
    startup.current = controller
    void client.me(controller.signal).then(user => {
      if (current === generation.current) setSnapshot({ status: 'authenticated', user, error: '' })
    }).catch(error => {
      if (current !== generation.current || controller.signal.aborted) return
      setSnapshot(error instanceof AccountApiError && error.status === 401
        ? { status: 'guest', user: null, error: '' }
        : { status: 'unavailable', user: null, error: accountErrorMessage(error) })
    })
    // oxlint-disable-next-line react-hooks/exhaustive-deps -- Invalidate request generations on cleanup; this ref is not a rendered DOM node.
    return () => { ++generation.current; controller.abort() }
  }, [client, retry])

  async function run(operation: () => Promise<AccountUser | null>): Promise<void> {
    if (busyRef.current) throw new AccountApiError(0, 'An account request is already in progress.')
    busyRef.current = true; setBusy(true)
    const current = ++generation.current
    startup.current?.abort()
    try {
      const user = await operation()
      if (current === generation.current) setSnapshot({ status: user ? 'authenticated' : 'guest', user, error: '' })
    } catch (error) {
      if (current === generation.current && error instanceof AccountApiError && error.status === 401) {
        setSnapshot({ status: 'guest', user: null, error: 'Your session expired. Sign in again.' })
      }
      throw error
    } finally {
      busyRef.current = false
      if (current === generation.current) setBusy(false)
    }
  }
  return {
    ...snapshot, busy,
    refresh() {
      if (busyRef.current) return
      ++generation.current; startup.current?.abort()
      setSnapshot({ status: 'checking', user: null, error: '' }); setRetry(value => value + 1)
    },
    clearError() { setSnapshot(current => ({ ...current, error: '' })) },
    login(input: Credentials) { return run(() => client.login(input)) },
    register(input: Credentials) { return run(() => client.register(input)) },
    save(input: ProfilePatch) { return run(() => client.update(input)) },
    logout() {
      return run(async () => {
        try { await client.logout() }
        catch (error) { if (!(error instanceof AccountApiError && error.status === 401)) throw error }
        return null
      })
    },
  }
}
