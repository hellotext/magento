# Troubleshooting

## Installation or delivery status is unclear

Run `php bin/magento hellotext:diagnose --store=<store-view-code>` in the Magento root. Use `--ping` only when PHP-to-API reachability matters; it does not test the browser-to-API or Rails-to-Magento paths. A loopback-bound local API can be unreachable from PHP without breaking either.

A reachable API root is only a transport check, not proof that OAuth, the Rails worker or storefront tracking works.

## The Hellotext integration is missing or reset

`hellotext:diagnose` prints `integration: missing` until `setup:upgrade` runs. On stores with compiled DI, also run `setup:di:compile`; otherwise the development URL override is ignored. `recreated` means a module release or an `env.php` override changed the integration: choose **Reauthorize** in System → Integrations. The old OAuth consumer row stays in the database without an integration and cannot authenticate.

## The connection stays in "Verifying" with a settings warning

Rails could not call `POST /V1/hellotext/configuration`: the module is missing, outdated or unreachable. Update the module, run `setup:upgrade`, then use **Apply settings again** on the connection page. Do not copy values into Magento Admin to work around it.

## The browser runs old JavaScript

Magento may serve generated static assets after source changes. Remove only the affected generated module asset or rerun static-content deployment, then verify the browser Network response and its content hash against the source file.

## The SDK does not initialize

Check, in order:

1. the page is a secure context;
2. the API root passed validation and matches the CSP/CORS configuration;
3. the page is not checkout, payment or success;
4. the browser can load the SDK asset from jsDelivr when it is not already loaded.

The bridge reuses an existing `window.Hellotext` and initializes it with the store's configured business and API root.

Unavailable or invalid cart data skips cart tracking without stopping page, product or success-page order events. Later cart refreshes can recover without reloading the page. An explicitly disabled section still stops tracking, including when Magento's Cookie Restriction Mode denies consent.

## No cart event is persisted

A tracking `2xx` only means accepted or enqueued. Check the Rails worker, the private anonymous event store and the exact scoped UUID/reference. Repeat the runbook with a fresh disposable browser session rather than copying a UUID into a URL.

## Configurable child is wrong

Verify that all `super_attribute[...]` controls describe a complete, enabled child and that the child SKU/reference matches Magento. Swatches and custom theme renderers are not automatically covered by the Luma mapping.

## FPC behavior is unclear

Inspect the cache backend or cache-key instrumentation. `Cache-Control` or a `no-store` header alone does not establish whether a response was a MISS or HIT.
