import { useEffect, useState } from 'react'
import './App.css'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000').replace(/\/$/, '')

type Connection = { status: 'loading' | 'connected' | 'error'; message: string }

function App() {
  const [attempt, setAttempt] = useState(0)
  const [connection, setConnection] = useState<Connection>({ status: 'loading', message: 'Checking the API…' })

  useEffect(() => {
    let active = true
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 10000)

    async function checkConnection() {
      setConnection({ status: 'loading', message: 'Checking the API…' })
      try {
        const response = await fetch(`${apiBaseUrl}/api/health`, {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
        })
        if (!response.ok) throw new Error(`API returned HTTP ${response.status}.`)
        const health = await response.json()
        if (health.status !== 'ok') throw new Error('The API returned an unexpected response.')
        if (!active) return
        setConnection({ status: 'connected', message: 'The Laravel API is connected.' })
      } catch (error) {
        if (!active) return
        if (controller.signal.aborted) {
          setConnection({ status: 'error', message: 'The API request timed out. Check that the backend is running.' })
        } else {
          setConnection({ status: 'error', message: error instanceof Error ? error.message : 'Unable to reach the API.' })
        }
      } finally {
        clearTimeout(timeout)
      }
    }

    void checkConnection()
    return () => { active = false; clearTimeout(timeout); controller.abort() }
  }, [attempt])

  return (
    <main>
      <p className="eyebrow">SPECTACLES</p>
      <h1>Two apps.<br />One starting point.</h1>
      <p className="intro">A React frontend connected to an independent Laravel API.</p>
      <section className={`connection ${connection.status}`} aria-labelledby="connection-title">
        <div className="connection-heading">
          <h2 id="connection-title">API connection</h2>
          <span className="status">{connection.status}</span>
        </div>
        <p role="status" aria-live="polite">{connection.message}</p>
        <code>{apiBaseUrl}/api/health</code>
        {connection.status === 'error' && <p className="hint">Check the API URL and the backend’s allowed frontend origin.</p>}
        <button onClick={() => setAttempt(value => value + 1)} disabled={connection.status === 'loading'}>
          {connection.status === 'loading' ? 'Checking…' : 'Check again'}
        </button>
      </section>
    </main>
  )
}

export default App
