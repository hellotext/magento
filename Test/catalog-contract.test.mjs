import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = path.resolve(import.meta.dirname, '..')
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8')

test('catalog permission is separate from delivery and never grants Magento product writes', () => {
  const routes = read('etc/webapi.xml')
  const acl = read('etc/acl.xml')
  const catalog = routes.match(/<route url="\/V1\/hellotext\/catalog\/products" method="([^"]+)">([\s\S]*?)<\/route>/)

  assert.ok(catalog)
  assert.equal(catalog[1], 'GET')
  assert.match(catalog[2], /Hellotext_Tracking::catalog_read/)
  assert.doesNotMatch(catalog[2], /Magento_Catalog::products|Hellotext_Tracking::delivery/)
  assert.match(acl, /id="Hellotext_Tracking::catalog_read"/)
  assert.doesNotMatch(routes, /Magento_Catalog::products/)
  assert.doesNotMatch(routes, /<route url="\/V1\/hellotext\/catalog\/products" method="(?:POST|PUT|DELETE)"/)
})

test('catalog API response uses Magento data interfaces instead of associative mixed arrays', () => {
  const service = read('Api/CatalogManagementInterface.php')
  const page = read('Api/Data/CatalogPageInterface.php')
  const item = read('Api/Data/CatalogItemInterface.php')

  assert.match(service, /@return \\Hellotext\\Tracking\\Api\\Data\\CatalogPageInterface/)
  assert.match(page, /@return \\Hellotext\\Tracking\\Api\\Data\\CatalogItemInterface\[\]/)
  assert.doesNotMatch(page + item, /getCustomAttributes|getExtensionAttributes|getCost|getDescription/)
})

test('customer import uses a separate bounded read-only permission and narrow response fields', () => {
  const routes = read('etc/webapi.xml')
  const acl = read('etc/acl.xml')
  const route = routes.match(/<route url="\/V1\/hellotext\/customers" method="([^"]+)">([\s\S]*?)<\/route>/)
  const service = read('Model/CustomerManagement.php')
  const item = read('Api/Data/CustomerItemInterface.php')

  assert.ok(route)
  assert.equal(route[1], 'GET')
  assert.match(route[2], /Hellotext_Tracking::customer_read/)
  assert.doesNotMatch(route[2], /Magento_Customer::customer|Hellotext_Tracking::delivery|catalog_read/)
  assert.match(acl, /id="Hellotext_Tracking::customer_read"/)
  assert.match(service, /website_id/)
  assert.match(service, /Subscriber::STATUS_SUBSCRIBED/)
  assert.match(service, /Subscriber::STATUS_UNSUBSCRIBED/)
  assert.match(service, /default => 'unconfirmed'/)
  assert.match(service, /setPageSize\(\$limit \+ 1\)/)
  assert.match(item, /getNewsletterStatus\(\): string/)
  assert.doesNotMatch(item, /getPhone|getAddress|getCustomAttributes|getExtensionAttributes/)
})
