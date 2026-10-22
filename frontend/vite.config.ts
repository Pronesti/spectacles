import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_')
  if (command === 'build' && !env.VITE_API_BASE_URL) {
    throw new Error('Set VITE_API_BASE_URL to the Laravel backend URL before building.')
  }
  if (env.VITE_API_BASE_URL) {
    const url = new URL(env.VITE_API_BASE_URL)
    if (!['http:', 'https:'].includes(url.protocol)) throw new Error('VITE_API_BASE_URL must be an HTTP(S) URL.')
  }
  return { plugins: [react()], server: { port: 5173, strictPort: true } }
})
