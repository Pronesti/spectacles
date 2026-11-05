import assert from 'node:assert/strict'
import { test } from 'node:test'
import { keyCatalogChoices } from '../src/catalogChoiceKeys.ts'

test('removing or reordering equal manual choices preserves surviving tag identities', () => {
  const a = { name: 'Same', catalogId: null }, b = { name: 'Same', catalogId: null }, c = { name: 'Other', catalogId: null }
  const first = keyCatalogChoices(null, [a, b, c])
  const removed = keyCatalogChoices(first, [b, c])
  assert.deepEqual(removed.items.map(item => item.id), first.items.slice(1).map(item => item.id))
  const reordered = keyCatalogChoices(removed, [c, b])
  assert.deepEqual(reordered.items.map(item => item.id), [...removed.items].reverse().map(item => item.id))
  assert.equal(first.items.length, 3)
})
test('restoration and inserting equal names reserve existing object identities first', () => {
  const a = { name: 'Same', catalogId: null }, b = { name: 'Same', catalogId: null }
  const first = keyCatalogChoices(null, [a, b])
  const inserted = keyCatalogChoices(first, [{ ...a }, a, b])
  assert.deepEqual(inserted.items.slice(1).map(item => item.id), first.items.map(item => item.id))
  assert.ok(!first.items.some(item => item.id === inserted.items[0].id))
  const restored = keyCatalogChoices(first, [{ ...a }, { ...b }])
  assert.deepEqual(restored.items.map(item => item.id), first.items.map(item => item.id))
  const replaced = keyCatalogChoices(first, [{ name: 'Different', catalogId: null }])
  assert.ok(!first.items.some(item => item.id === replaced.items[0].id))
})
