import express from 'express'
import { existsSync } from 'node:fs'
import { extname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const dist = fileURLToPath(new URL('./dist/', import.meta.url))

export function createApp() {
  if (!existsSync(join(dist, 'index.html'))) throw new Error('Build the SPA with npm run build before starting the server.')
  const app = express()
  app.disable('x-powered-by')
  app.use('/assets', express.static(join(dist, 'assets'), { immutable: true, maxAge: '1y' }))
  app.use(express.static(dist, { index: false, maxAge: 0 }))
  app.get('/{*path}', (req, res) => {
    if (req.path.startsWith('/assets/') || req.path === '/api' || req.path.startsWith('/api/') || extname(req.path) || !req.accepts('html')) {
      return res.sendStatus(404)
    }
    res.set('Cache-Control', 'no-cache')
    return res.sendFile(join(dist, 'index.html'))
  })
  return app
}

if (import.meta.main) {
  const server = createApp().listen(Number(process.env.PORT || 3000), () => {
    console.log(`Spectacles frontend listening on port ${server.address().port}`)
  })
  for (const signal of ['SIGTERM', 'SIGINT']) {
    process.on(signal, () => {
      server.close(() => process.exit(0))
      setTimeout(() => process.exit(1), 25000).unref()
    })
  }
}
