import { useEffect, useState } from 'react'
import { Agentation } from 'agentation'

/** Keep feedback inside the native modal's focus and interaction boundary. */
export function DevAgentation() {
  const [container, setContainer] = useState<HTMLDialogElement | null>(null)

  useEffect(() => {
    const syncContainer = () => {
      const modals = document.querySelectorAll<HTMLDialogElement>('dialog:modal')
      setContainer(modals.item(modals.length - 1))
    }
    const observer = new MutationObserver(syncContainer)
    observer.observe(document.body, {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ['open'],
    })
    syncContainer()
    return () => observer.disconnect()
  }, [])

  return <Agentation endpoint="http://localhost:4747" portalContainer={container} />
}
