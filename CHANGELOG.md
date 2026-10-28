# Changelog

## 1.0.2 - 2026-10-28

- `bin/magento module:uninstall Hellotext_Tracking --remove-data` now removes the Hellotext integration, its OAuth consumer and token, and the module configuration. Before, Magento skipped the module's uninstall script because the module was not registered in `setup_module`.

## 1.0.1 - 2026-10-19

- Install on stores without Magento Inventory (MSI): stock checks use Magento's default stock when MSI is not enabled.
- Allow the fonts of Hellotext popups, webchat and forms in the Content Security Policy.
- Allow PHP 8.4, used by Magento 2.4.8.

## 1.0.0 - 2026-10-17

- Track page views, products, carts and checkout activity through Hellotext.js.
- Display Hellotext popups, webchat, WhatsApp widgets and forms.
- Identify signed-in customers and preserve browsing attribution through checkout and order updates.
- Report order placement, status changes and refunds, and expose order history for import.
- Import simple and configurable product catalogs and customer profiles.
- Check product and variant availability in batches.
- Open prepared checkout carts, merging existing items for signed-in shoppers.
- Connect stores from Hellotext using `bin/magento hellotext:connect`.
- Provide connection diagnostics through `bin/magento hellotext:diagnose`.
