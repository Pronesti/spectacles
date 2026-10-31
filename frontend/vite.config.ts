import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ command, mode, isPreview }) => {
  const env = loadEnv(mode, process.cwd(), ['VITE_', 'DEV_'])
  if (command === 'build' && !env.VITE_API_BASE_URL) {
    throw new Error('Set VITE_API_BASE_URL to the Laravel backend URL before building.')
  }
  if (env.VITE_API_BASE_URL) {
    const url = new URL(env.VITE_API_BASE_URL)
    if (!['http:', 'https:'].includes(url.protocol)) throw new Error('VITE_API_BASE_URL must be an HTTP(S) URL.')
  }
  const devServerUrl = env.DEV_SERVER_URL ? new URL(env.DEV_SERVER_URL) : undefined
  return {
    plugins: [react()],
    define: {
      'import.meta.env.DEV_LOGIN_EMAIL': JSON.stringify(command === 'serve' && !isPreview ? env.DEV_LOGIN_EMAIL || '' : ''),
      'import.meta.env.DEV_LOGIN_PASSWORD': JSON.stringify(command === 'serve' && !isPreview ? env.DEV_LOGIN_PASSWORD || '' : ''),
    },
    server: {
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
      allowedHosts: devServerUrl ? [devServerUrl.hostname] : [],
      ws: devServerUrl ? {
        host: devServerUrl.hostname,
        protocol: devServerUrl.protocol === 'https:' ? 'wss' : 'ws',
        clientPort: Number(devServerUrl.port || (devServerUrl.protocol === 'https:' ? 443 : 80)),
      } : undefined,
    },
  }
})
