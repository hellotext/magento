import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = path.resolve(import.meta.dirname, '..')
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8')

test('order reads reuse the signed delivery permission without native Sales access', () => {
  const routes = read('etc/webapi.xml')

  for (const url of ['/V1/hellotext/orders', '/V1/hellotext/orders/:orderId']) {
    const matches = [...routes.matchAll(/<route url="([^"]+)" method="([^"]+)">([\s\S]*?)<\/route>/g)]
      .filter((match) => match[1] === url)

    assert.equal(matches.length, 1)
    assert.equal(matches[0][2], 'GET')
    assert.match(matches[0][3], /Hellotext_Tracking::delivery/)
    assert.match(matches[0][3], /Hellotext\\Tracking\\Api\\OrderManagementInterface/)
  }

  assert.doesNotMatch(routes + read('etc/integration.xml'), /Magento_Sales::/)
  assert.match(read('etc/di.xml'), /for="Hellotext\\Tracking\\Api\\OrderManagementInterface" type="Hellotext\\Tracking\\Model\\OrderManagement"/)
})
