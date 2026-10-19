# Hellotext for Magento

Connect your Magento store to Hellotext to track storefront activity and orders, identify customers, import your catalog, and display your Hellotext popups, webchat, WhatsApp widget and forms.

## Requirements

- Magento Open Source 2.4.7
- PHP 8.2 or 8.3
- Magento Inventory (MSI) is optional; without it, stock checks use Magento's default stock
- An HTTPS storefront and a Magento API reachable by Hellotext

## Install

```sh
composer require hellotext/magento
```

Open **Settings → Integrations → Magento** in Hellotext and follow the commands to enable the module and connect your store. See [Getting started](docs/getting-started.md) for the full instructions.

## Documentation

| Topic | Document |
| --- | --- |
| Connect a store and import records | [Getting started](docs/getting-started.md) |
| Open prepared carts in the storefront | [Checkout links](docs/checkout-links.md) |
| Diagnose problems | [Troubleshooting](docs/troubleshooting.md) |
| Report a security issue | [Security policy](SECURITY.md) |
| Changes between versions | [Changelog](CHANGELOG.md) |

## Support

Contact info@hellotext.com with your Magento and module versions and the output of:

```sh
bin/magento hellotext:diagnose --store=<store-view-code>
```

## Uninstall

```sh
bin/magento module:uninstall Hellotext_Tracking --remove-data
```

This removes the Hellotext integration, its access token, configuration and stored order-session data.

## License

See [LICENSE](LICENSE).
