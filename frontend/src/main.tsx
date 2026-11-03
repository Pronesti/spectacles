import { lazy, StrictMode, Suspense } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

const DevAgentation = import.meta.env.DEV
  ? lazy(() => import('./components/DevAgentation').then(module => ({ default: module.DevAgentation })))
  : null

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
    {DevAgentation && <Suspense fallback={null}><DevAgentation /></Suspense>}
  </StrictMode>,
)
