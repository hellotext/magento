# Changelog

## Unreleased

- Let Hellotext.js select or generate the storefront session. Removed Magento's anonymous-session generator, private cart session fields and logout reset hook; order attribution continues to read the SDK's `hello_session` cookie.

- Added signed single and bulk stock availability reads under the existing catalog permission, using the selected website's Magento Inventory stock and native salability for standalone products, configurable parents and exact child variants.

- Added a storefront handoff for carts prepared by Hellotext. Guest shoppers open the prepared basket directly; signed-in shoppers retain their existing items through Magento's native cart merge before being redirected to checkout.
- Preserved tracked-link `hello_session` values through checkout redirects, storefront tracking, order placement and later order updates. The SDK cookie is the order's session source, and hashed session identifiers are retained alongside anonymous UUIDs.

## 0.9.0 - 2026-10-13

First public release.

- Storefront tracking through Hellotext.js, loaded from jsDelivr at the latest 2.x release so SDK fixes reach stores without a module release: page, product (including the selected configurable child), cart, checkout-start and order events, with fail-open behavior and scoped anonymous sessions that stay stable across login and checkout.
- The popups, webchat, WhatsApp widget and forms configured for the business in Hellotext load on the storefront automatically. Web push stays off.
- Private Magento customer-data cart snapshot; cacheable HTML carries only public data. Honors Magento's Cookie Restriction Mode.
- Signed-in customers are identified through `Hellotext.identify` by their website-scoped Magento ID, and keep being tracked after login.
- Orders: signed, website-scoped order history and single-order snapshots (state, refunded total, lines and the Hellotext session that placed the order), plus a commit observer that reports later state changes and refunds through the tracking API without blocking checkout. The only data stored in Magento is `sales_order.hellotext_session`.
- Optional read-only catalog and customer routes under their own permissions, for Hellotext's imports.
- Connection through the declared `Hellotext` integration (`etc/integration.xml`) and `bin/magento hellotext:connect <code>`; Hellotext applies its settings through a `Hellotext_Tracking::configure` Web API and verifies the store through a signed status route.
- `bin/magento hellotext:diagnose` reports versions, configuration validity and the integration state.
- `bin/magento module:uninstall Hellotext_Tracking --remove-data` removes the integration, its OAuth consumer, the module configuration and its schema.
- Supported: Magento Open Source 2.4.7 with PHP 8.2 and 8.3 on the documented Luma baseline. See [compatibility](docs/compatibility.md).
