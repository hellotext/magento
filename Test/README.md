# Module unit tests

Run these tests from a Magento Open Source 2.4.7-p10 development installation with its Composer development dependencies installed:

```sh
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/hellotext/module-tracking/Test/Unit
```

For an installation under `app/code`, use `app/code/Hellotext/Tracking/Test/Unit` as the final path instead.

The PHP tests cover session handoff, delegation to Magento's customer-cart merge, rejected links, failed persistence, redirects, browser-cache invalidation and attribution from the SDK cookie through order lifecycle events. Stock tests cover store/website scoping, native bulk salability, exact child IDs, unavailable products, batch bounds and incomplete/failed inventory responses. They use Magento's real class and interface definitions with mocked persistence and sessions; Inventory Sales API must be installed for the stock tests.

Run the bridge behavior tests from this module's directory with Node.js 18 or newer:

```sh
node --test Test/Unit/Js/bridge.test.cjs
```

These load the module's bridge and cart tracker with a stub of the SDK's URL/configuration/cookie precedence. A live storefront smoke test is still needed for DI compilation, browser cookies and the merchant's checkout configuration; see [checkout links](../docs/checkout-links.md).
