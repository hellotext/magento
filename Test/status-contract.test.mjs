import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('status is a signed, store-scoped, safe version handshake', () => {
  const routes = read('etc/webapi.xml');
  const service = read('Model/StatusManagement.php');
  const data = read('Api/Data/StatusInterface.php');
  const version = read('Model/VersionInformation.php');
  const bridge = read('view/frontend/web/js/bridge.js');
  const route = routes.match(/<route url="\/V1\/hellotext\/status" method="([^"]+)">([\s\S]*?)<\/route>/);

  assert.ok(route);
  assert.equal(route[1], 'GET');
  assert.match(route[2], /Magento_Backend::store/);
  assert.doesNotMatch(route[2], /Hellotext_Tracking::(?:delivery|catalog_read)|Magento_Catalog::products/);
  assert.match(service, /Cache-Control.*no-store/);
  assert.match(service, /Reference::build\(\$installation, 'product', 1\)/);
  assert.match(service, /Configuration::isSafeApiRoot/);
  for (const field of [
    'SchemaVersion', 'InstallationId', 'StoreId', 'WebsiteId', 'StoreCodes', 'BusinessId', 'ConfigurationValid',
    'ModuleVersion', 'SdkVersion', 'MagentoVersion', 'MagentoEdition', 'PhpVersion'
  ]) {
    assert.match(data, new RegExp(`get${field}`));
  }
  assert.match(data, /concrete DTO deliberately returns \?string/);
  assert.doesNotMatch(data, /get(?:ApiRoot|Token|Secret|Customer|Order|Session)/);
  const sdkVersion = version.match(/SDK_VERSION = '([^']+)'/)[1];
  // The reported SDK channel matches the major version pinned in the bridge's CDN URL.
  assert.equal(sdkVersion, '2.x');
  assert.match(bridge, /https:\/\/cdn\.jsdelivr\.net\/npm\/@hellotext\/hellotext@2\/dist\/hellotext\.min\.js/);
});
