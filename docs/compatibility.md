# Compatibility

Compatibility is a tested contract, not an assumption from a successful module installation. Record new supported combinations in release notes and extend the matrix before enabling them for merchants. The support statement below applies to storefront analytics, signed-in identification and signed order snapshots only where explicitly called out.

## Supported baseline

| Component | Baseline |
| --- | --- |
| Magento | Open Source 2.4.7-p10 |
| PHP | 8.2–8.3 |
| Theme | Luma |
| Browser runtime | Secure context with RequireJS/AMD support |
| Hellotext.js | Latest release, followed through jsDelivr |
| API root | `https://api.hellotext.com/v1` |

This is the production support baseline for page, product and anonymous cart events. It does not claim support for Adobe Commerce, other Magento versions, custom themes, Varnish or alternate proxy topologies.

## Known constraints

- Hellotext.js requires a secure browser context; plain `lvh.me` HTTP is not a representative storefront runtime.
- The bridge loads the latest SDK from the unversioned jsDelivr URL at runtime, so SDK releases, including new major versions, reach stores without a module release. The status endpoint reports `latest` rather than a fixed SDK version. The public API the bridge uses (`business`, `session`, `identify`, `track`) must remain compatible, and jsDelivr and browsers cache the resolved file (a rollback of a bad release may need a purge). CDN availability and merchant CSP/network policy are release gates: verify the asset loads in browser Network. An approved owned asset is an alternative only after changing the bridge/CSP and revalidating it; this package does not provide one.
- Generated Magento static assets must be rebuilt after changing module JavaScript.
- Product and order references include the Magento installation and entity ID, not store-view IDs; one connection covers every view in its website.
- FPC must be checked at the backend/cache-key level; response headers alone do not prove a HIT.
- Swatches, custom configurable renderers, bundles, grouped/downloadable/virtual products, custom options and decimal quantities (**Qty Uses Decimals**) require explicit mapping before support is claimed.
- `order.shipped` is reported when Magento marks the order complete; partial shipments are not reported separately.
- Customer reads follow the default **Per Website** account sharing; **Global** customer sharing is not supported.
- Adobe Marketplace credentials are not required when the module is installed from the reviewed package or repository checkout.
- Consent-management behavior is merchant-specific and must be accepted before enabling analytics traffic.
- Signed-in identification needs the identity setting Hellotext writes on connection; the private section carries only the customer ID.
- Orders are limited to core physical simple/configurable items. Virtual/mixed-virtual orders and unknown item types are skipped with a numeric-ID diagnostic until their mapping is reviewed.
- Signed order-history reads reuse the delivery permission and core physical simple/configurable mapping across every store view in the connected website, with no 30-day floor. Unsupported snapshots are skipped with an advancing cursor. Native state/status/refunded totals are exposed for Rails history processing; carrier delivery evidence and arbitrary native item types remain outside the acceptance baseline. Real signed REST and serializer validation is required before merchant enablement.
- Catalog reading is a separate, opt-in REST capability for enabled, website- assigned simple/configurable products in the selected store. It deliberately omits price, stock, arbitrary attributes, HTML and unsupported types. A role with `Hellotext_Tracking::catalog_read` must **not** receive the native `Magento_Catalog::products` resource, which also permits catalog writes.
- Stock availability is a separate signed batch route under that same catalog permission. It requires Magento Inventory Sales to be enabled and resolves the current website's stock rather than assuming the default stock. It supports the same simple/configurable product types, including hidden children, and returns salability rather than quantities. See [stock availability](stock-availability.md) for the contract and acceptance checks.
