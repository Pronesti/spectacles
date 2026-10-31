import assert from 'node:assert/strict'
import { test } from 'node:test'
import { avatarLetter } from '../src/account.ts'
import { credentialErrors, decodeUser, profileChanges, profileErrors } from '../src/accountApi.ts'

const user = { id: '00000000-0000-4000-8000-000000000001', name: 'Lucía García', email: 'lucia@example.com', locale: null }
test('account identity uses server UUID and explicit attributes only', () => {
  assert.deepEqual(decodeUser({ data: { type: 'users', id: user.id, attributes: { ...user, password: 'never-copy', city: 'ignored' } } }), user)
  assert.throws(() => decodeUser({ name: 'Legacy preview', email: user.email }))
  assert.throws(() => decodeUser({ data: { type: 'users', id: 1, attributes: user } }))
  assert.equal(avatarLetter('  Élodie Martin '), 'É')
})
test('registration and legacy login obey different minimum lengths and the UTF-8 ceiling', () => {
  const input = { name: user.name, email: user.email, password: 'a-valid-password', confirmation: 'a-valid-password' }
  assert.deepEqual(credentialErrors('register', input), {})
  assert.ok(credentialErrors('register', { ...input, password: 'password', confirmation: 'password' }).password)
  assert.deepEqual(credentialErrors('login', { ...input, name: '', password: 'password', confirmation: '' }), {})
  assert.ok(credentialErrors('register', { ...input, password: 'é'.repeat(37), confirmation: 'é'.repeat(37) }).password)
  assert.ok(credentialErrors('login', { ...input, password: 'a\0password' }).password)
  assert.ok(credentialErrors('register', { ...input, confirmation: 'different' }).confirmation)
})
test('profile patches contain changed supported fields only and preserve absent locale', () => {
  assert.deepEqual(profileChanges({ ...user, name: '  Updated  ', password: 'never-copy', city: 'ignored' }, user), { name: 'Updated' })
  assert.deepEqual(profileChanges({ ...user, locale: 'en' }, user), { locale: 'en' })
  assert.deepEqual(profileChanges({ ...user, locale: null }, { ...user, locale: 'en' }), { locale: null })
  assert.ok(profileErrors({ ...user, name: ' ' }).name)
  assert.ok(profileErrors({ ...user, email: 'invalid' }).email)
  assert.ok(profileErrors({ ...user, name: 'x'.repeat(81) }).name)
})
