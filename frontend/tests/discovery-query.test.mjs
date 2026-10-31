import assert from 'node:assert/strict'
import { test } from 'node:test'
import { discoveryQuery, visibleSourceTours } from '../src/discoveryQuery.ts'
test('recovered independent query text requires an explicit choice and never prevents clearing', () => {
  assert.deepEqual(discoveryQuery('Drama', 'Luz'), { value: 'Drama', alternative: 'Luz' })
  assert.deepEqual(discoveryQuery('', 'Luz'), { value: '', alternative: 'Luz' })
  assert.deepEqual(discoveryQuery('Drama', 'Drama'), { value: 'Drama', alternative: null })
  assert.deepEqual(discoveryQuery('Drama', ''), { value: 'Drama', alternative: null })
})

test('source local identities remain selectable once when artist-scoped local search is empty', () => {
  const tour = (externalId, localId, name = 'Drama y luz') => ({ ref: { provider: 'setlist_fm', resourceType: 'tour', externalId }, localId, values: { name }, url: '', selection: 'proof' })
  const existing = tour('source-1', 'local-1')
  const sameLocalIdentity = tour('source-2', 'local-1')
  const sameNameDifferentIdentity = tour('source-3', 'local-2')
  assert.deepEqual(visibleSourceTours([existing, sameLocalIdentity, sameNameDifferentIdentity], []), [existing, sameNameDifferentIdentity])
  assert.deepEqual(visibleSourceTours([existing, sameLocalIdentity, sameNameDifferentIdentity], ['local-1']), [sameNameDifferentIdentity])
  const external = tour('source-4', null)
  assert.deepEqual(visibleSourceTours([external, external], []), [external])
})
