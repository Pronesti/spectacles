import assert from 'node:assert/strict'
import { test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { createServer, resolveConfig } from 'vite'

const root = fileURLToPath(new URL('..', import.meta.url))
const email = 'prefill-check@example.com'
const password = 'prefill-check-password'
const props = { busy: false, onSwitch: () => {}, onExplore: () => {}, onSubmit: async () => { throw new Error('Prefill must not submit') } }

async function withCredentials(values, check) {
  const saved = Object.fromEntries(Object.keys(values).map(key => [key, process.env[key]]))
  Object.assign(process.env, values)
  try { await check() }
  finally {
    for (const [key, value] of Object.entries(saved)) {
      if (value === undefined) delete process.env[key]
      else process.env[key] = value
    }
  }
}

for (const configured of [true, false]) {
  test(`local login ${configured ? 'prefills configured values' : 'stays blank without values'} and registration stays blank`, async () => {
    await withCredentials({ DEV_LOGIN_EMAIL: configured ? email : '', DEV_LOGIN_PASSWORD: configured ? password : '' }, async () => {
      const server = await createServer({ root, logLevel: 'silent', server: { middlewareMode: true, hmr: false }, appType: 'custom' })
      try {
        const { AuthView } = await server.ssrLoadModule('/src/components/AuthView.tsx')
        const login = renderToStaticMarkup(createElement(AuthView, { ...props, mode: 'login' }))
        const register = renderToStaticMarkup(createElement(AuthView, { ...props, mode: 'register' }))
        assert.match(login, new RegExp(`id="auth-email"[^>]*value="${configured ? email : ''}"`))
        assert.match(login, new RegExp(`id="auth-password"[^>]*value="${configured ? password : ''}"`))
        for (const field of ['name', 'email', 'password', 'confirmation']) assert.match(register, new RegExp(`id="auth-${field}"[^>]*value=""`))
      } finally { await server.close() }
    })
  })
}

test('build and preview config exclude local credentials, even in development mode', async () => {
  await withCredentials({ DEV_LOGIN_EMAIL: email, DEV_LOGIN_PASSWORD: password, VITE_API_BASE_URL: 'http://localhost:8000' }, async () => {
    for (const [command, isPreview] of [['build', false], ['serve', true]]) {
      const config = await resolveConfig({ root, mode: 'development', logLevel: 'silent' }, command, 'development', 'development', isPreview)
      assert.equal(config.define['import.meta.env.DEV_LOGIN_EMAIL'], '""')
      assert.equal(config.define['import.meta.env.DEV_LOGIN_PASSWORD'], '""')
      assert.equal(config.env.DEV_LOGIN_EMAIL, undefined)
      assert.equal(config.env.DEV_LOGIN_PASSWORD, undefined)
    }
  })
})
