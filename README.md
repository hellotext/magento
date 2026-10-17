# Hellotext for Magento 2

Hellotext for Magento 2 connects one Magento website to one Hellotext business. Once connected, the store sends storefront activity, customer identification and order events to Hellotext, shows the popups, webchat, WhatsApp widget and forms configured in Hellotext, and lets Hellotext import the store's products and, if you choose, its customers.

## Requirements

- Magento Open Source 2.4.7 (tested on 2.4.7-p10)
- PHP 8.2 or 8.3
- An HTTPS storefront with a valid certificate, and a Magento REST API that Hellotext can reach

Other Magento versions and editions are supported only after they have been tested.

## Install

In Hellotext, open **Settings → Integrations → Magento**. The page shows the commands for your business. The package is installed with Composer:

```sh
composer require hellotext/module-tracking
```

Then follow [Connect Magento to Hellotext](docs/getting-started.md).

## Documentation

| Topic | Document |
| --- | --- |
| Connect a store and import records | [Getting started](docs/getting-started.md) |
| Open prepared carts in the storefront | [Checkout links](docs/checkout-links.md) |
| Check product and variant availability | [Stock availability](docs/stock-availability.md) |
| Diagnose problems | [Troubleshooting](docs/troubleshooting.md) |
| Report a security issue | [Security policy](SECURITY.md) |
| Changes between versions | [Changelog](CHANGELOG.md) |

## Support

Contact info@hellotext.com. When you write, include the output of `bin/magento hellotext:diagnose --store=<store-view-code>`. The command never prints credentials or customer data.

## Privacy and consent

This module does not replace a consent-management platform. Tracking is optional and fail-open: if Hellotext is unreachable, Magento checkout keeps working.

## Uninstall

Run `bin/magento module:uninstall Hellotext_Tracking --remove-data`. It removes the Hellotext integration, its access token, the module configuration and the module's database column.

## License

See [LICENSE](LICENSE).
