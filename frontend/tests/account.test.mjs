import assert from 'node:assert/strict'
import { test } from 'node:test'
import { AVATAR_COLORS, DEFAULT_PROFILE, ENTRY_KEY, MAX_AVATAR_BYTES, PROFILE_KEY, avatarFileError, avatarLetter, credentialErrors, decodeProfile, profileErrors, profilePayload } from '../src/account.ts'
import { WORKSPACE_KEY } from '../src/domain.ts'

test('account preferences have separate versioned keys from the concert workspace', () => {
  assert.equal(new Set([PROFILE_KEY, ENTRY_KEY, WORKSPACE_KEY]).size, 3)
  assert.deepEqual(decodeProfile(JSON.stringify(DEFAULT_PROFILE)), DEFAULT_PROFILE)
})

test('profile serialization trims known fields and excludes credentials and foreign data', () => {
  const profile = { ...DEFAULT_PROFILE, name: '  Lucía García  ', email: '  lucia@example.com  ', password: 'sample-secret', confirmation: 'sample-secret', concerts: ['do-not-copy'] }
  const payload = profilePayload(profile)
  assert.equal(payload.name, 'Lucía García')
  assert.equal(payload.email, 'lucia@example.com')
  assert.equal('password' in payload, false)
  assert.equal('confirmation' in payload, false)
  assert.equal('concerts' in payload, false)
  assert.equal(JSON.stringify(payload).includes('sample-secret'), false)
  assert.deepEqual(decodeProfile(JSON.stringify(profile)), payload)
})

test('profile validation handles blank names, email format, and exact bio boundaries', () => {
  assert.deepEqual(profileErrors(DEFAULT_PROFILE), {})
  assert.ok(profileErrors({ ...DEFAULT_PROFILE, name: '   ' }).name)
  assert.ok(profileErrors({ ...DEFAULT_PROFILE, email: 'not-an-email' }).email)
  assert.deepEqual(profileErrors({ ...DEFAULT_PROFILE, bio: 'x'.repeat(80) }), {})
  assert.ok(profileErrors({ ...DEFAULT_PROFILE, bio: 'x'.repeat(81) }).bio)
  assert.ok(profileErrors({ ...DEFAULT_PROFILE, city: 'x'.repeat(101) }).city)
})

test('credential validation checks preview format and confirmation without returning secret values', () => {
  const input = { mode: 'register', name: 'Jamie', email: 'jamie@example.com', password: 'DemoPassword9', confirmation: 'different' }
  const errors = credentialErrors(input)
  assert.equal(errors.confirmation, 'The passwords do not match.')
  assert.equal(JSON.stringify(errors).includes(input.password), false)
  assert.deepEqual(credentialErrors({ ...input, confirmation: input.password }), {})
  assert.ok(credentialErrors({ ...input, password: 'short' }).password)
  assert.deepEqual(credentialErrors({ ...input, mode: 'login', name: '', confirmation: '' }), {})
})

test('avatar selections preserve Unicode names and admit only bounded local image formats', () => {
  assert.equal(avatarLetter('  Élodie Martin  '), 'É')
  assert.equal(avatarLetter('Jamie Morgan'), 'J')
  assert.equal(avatarLetter(''), 'J')
  for (const color of AVATAR_COLORS) assert.ok(decodeProfile(JSON.stringify({ ...DEFAULT_PROFILE, color })))
  assert.equal(avatarFileError('image/png', MAX_AVATAR_BYTES), null)
  assert.ok(avatarFileError('image/png', MAX_AVATAR_BYTES + 1))
  assert.ok(avatarFileError('image/svg+xml', 100))
  assert.ok(avatarFileError('image/jpeg', 0))
  assert.ok(decodeProfile(JSON.stringify({ ...DEFAULT_PROFILE, photo: 'data:image/png;base64,iVBORw0KGgo=' })))
})

test('malformed saved profiles, external avatar URLs, and unsupported versions are rejected', () => {
  for (const value of ['broken json', 'null', '[]', JSON.stringify({ ...DEFAULT_PROFILE, version: 2 }), JSON.stringify({ ...DEFAULT_PROFILE, color: 'unknown' }), JSON.stringify({ ...DEFAULT_PROFILE, name: '' }), JSON.stringify({ ...DEFAULT_PROFILE, photo: 'https://example.com/tracker.png' }), JSON.stringify({ ...DEFAULT_PROFILE, photo: 'data:image/svg+xml;base64,AAAA' })]) {
    assert.equal(decodeProfile(value), null)
  }
})
