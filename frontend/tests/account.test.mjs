import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import { fileURLToPath } from 'node:url'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import react from '@vitejs/plugin-react'
import { createServer } from 'vite'

let server, AuthView, ProfileView, Avatar, App
const user = { id: '00000000-0000-4000-8000-000000000001', name: 'Élodie', email: 'elodie@example.com', locale: null }
const noop = () => {}
const render = (component, props) => renderToStaticMarkup(createElement(component, props))
before(async () => {
  server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), configFile: false, cacheDir: fileURLToPath(new URL('../node_modules/.cache/account-ui-tests', import.meta.url)), plugins: [react()], server: { middlewareMode: true, hmr: false }, appType: 'custom' })
  ;({ AuthView } = await server.ssrLoadModule('/src/components/AuthView.tsx'))
  ;({ ProfileView } = await server.ssrLoadModule('/src/components/ProfileView.tsx'))
  ;({ Avatar } = await server.ssrLoadModule('/src/components/Avatar.tsx'))
  ;({ default: App } = await server.ssrLoadModule('/src/App.tsx'))
})
after(async () => { await server?.close() })
test('real credential forms retain labels and distinguish login from registration policy', () => {
  const props = { busy: false, onSwitch: noop, onExplore: noop, onSubmit: async () => {} }
  const login = render(AuthView, { ...props, mode: 'login' })
  const register = render(AuthView, { ...props, mode: 'register' })
  assert.match(login, /autoComplete="current-password"/i)
  assert.match(register, /autoComplete="new-password"/i)
  assert.match(register, /id="auth-confirmation"/)
  assert.match(register, /12 characters/)
  assert.match(register, /Create account/)
  assert.doesNotMatch(login + register, /sample password|Create preview profile|never sent|does not create a real account/)
  assert.match(render(AuthView, { ...props, mode: 'register', busy: true }), /<fieldset[^>]*disabled/)
})
test('profile exposes supported fields and account collection statistics', () => {
  const html = render(ProfileView, { user, counts: { concerts: 24, artists: 12, venues: 8 }, busy: false, onSave: async () => {}, onCancel: noop })
  for (const id of ['profile-name', 'profile-email', 'profile-locale']) assert.ok(html.includes(`id="${id}"`))
  assert.match(html, /elodie@example.com/)
  assert.match(html, /Concerts/)
  assert.doesNotMatch(html, /profile-city|profile-bio|type="file"|Change photo|Avatar color/)
  assert.match(render(Avatar, { name: user.name }), /É/)
})
test('initial application render checks the session without showing a fabricated identity', () => {
  const html = render(App)
  assert.match(html, /Checking your session/)
  assert.doesNotMatch(html, /Jamie Morgan|jamie@example.com|Local preview profile/)
})

test('account buttons retain form semantics, visibility labels, and initial disabled saves', () => {
  const auth = render(AuthView, { mode: 'register', busy: false, onSwitch: noop, onExplore: noop, onSubmit: async () => {} })
  const buttons = auth.match(/<button\b[^>]*>/g) ?? []
  const submit = buttons.filter(button => /type="submit"/.test(button))
  assert.equal(submit.length, 1)
  assert.match(submit[0], /auth-submit/)
  const visibility = buttons.filter(button => /password-visibility/.test(button))
  assert.equal(visibility.length, 2)
  for (const button of visibility) {
    assert.match(button, /type="button"/)
    assert.match(button, /aria-label="Show (?:password|confirm password)"/)
    assert.match(button, /aria-pressed="false"/)
  }
  const profile = render(ProfileView, { user, counts: { concerts: 0, artists: 0, venues: 0 }, busy: false, onSave: async () => user, onCancel: noop })
  const save = (profile.match(/<button\b[^>]*>/g) ?? []).find(button => /type="submit"/.test(button))
  assert.ok(save)
  assert.match(save, /\bdisabled=""/)
  assert.match(profile, /<button\b[^>]*type="button"[^>]*>Cancel<\/button>/)
})
