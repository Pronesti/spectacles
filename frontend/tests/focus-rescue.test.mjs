import assert from 'node:assert/strict'
import { test } from 'node:test'
import { isFocusLost } from '../src/focusRescue.ts'

const body = { isConnected: true }
test('focus counts as lost only when the last focused control left the document and focus fell to the body', () => {
  assert.equal(isFocusLost(body, { isConnected: false }, body), true)
  assert.equal(isFocusLost(null, { isConnected: false }, body), true)
  assert.equal(isFocusLost(body, { isConnected: true }, body), false, 'a click on blank space keeps the last control connected')
  assert.equal(isFocusLost({ isConnected: true }, { isConnected: false }, body), false, 'focus already moved somewhere else')
  assert.equal(isFocusLost(body, null, body), false)
})
