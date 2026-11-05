import { useEffect, useState } from 'react'
import { Button, Text, UNSTABLE_Toast as Toast, UNSTABLE_ToastContent as ToastContent, UNSTABLE_ToastQueue as ToastQueue, UNSTABLE_ToastRegion as ToastRegion } from 'react-aria-components'
import { Icon } from './Icon'

// Keep the installed preview Toast API behind one adapter. App owns replacement,
// the five-second status timeout, and the persistent Undo transaction.
export function NotificationToast({ message, onUndo, onDismiss }: { message: string; onUndo?: () => void; onDismiss: () => void }) {
  const [queue] = useState(() => new ToastQueue<string>({ maxVisibleToasts: 1 }))
  useEffect(() => {
    if (!message) return
    const key = queue.add(message)
    return () => queue.close(key)
  }, [message, queue])
  return <ToastRegion queue={queue} aria-label="Notifications" className="notification-region">{({ toast }) => <Toast toast={toast} className="toast">
    <ToastContent role="status" aria-live="polite" style={{ display: 'contents' }}><span className="toast-check"><Icon name="check" size={17} /></span><Text slot="title">{toast.content}</Text></ToastContent>
    {onUndo && <Button slot={null} type="button" onPress={onUndo}>Undo</Button>}
    <Button slot="close" type="button" className="icon-button" onPress={onDismiss} aria-label="Dismiss notification"><Icon name="close" size={17} /></Button>
  </Toast>}</ToastRegion>
}
