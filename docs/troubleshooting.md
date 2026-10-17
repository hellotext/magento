# Troubleshooting

## Diagnose the connection

Run this from your Magento installation directory:

```sh
bin/magento hellotext:diagnose --store=<store-view-code>
```

It reports the module version, integration status and configuration. Add `--ping` to check whether the Magento server can reach the Hellotext API.

## Hellotext is missing from Magento Integrations

Run `bin/magento setup:upgrade`. In production mode, also run `bin/magento setup:di:compile`.

If the integration is marked **Recreated**, choose **Reauthorize** under **System → Extensions → Integrations**.

## Connection or authorization fails

| Problem | Action |
| --- | --- |
| Link code refused or expired | Reload the Magento setup page in Hellotext and run the new `hellotext:connect` command. |
| Hellotext window does not open | Allow popups for Magento Admin and choose **Activate** or **Reauthorize** again. |
| Magento cannot be reached | Check the store's public HTTPS URL, certificate and firewall access to its API. |
| Hellotext cannot apply settings | Update the module, run `setup:upgrade`, then choose **Apply settings again** in Hellotext. |

## No storefront activity

Check the browser console and Network panel for blocked requests to jsDelivr or the Hellotext API. Confirm that the page uses HTTPS and the store's CSP permits those requests.

When Magento Cookie Restriction Mode is enabled, tracking waits for consent. Custom storefronts must retain Magento's RequireJS initialization and `Magento_Customer/js/customer-data` support for the tracking bridge to load.

## Old JavaScript after an update

Redeploy Magento's static content and clear its cache:

```sh
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

## Wrong product variant

The bridge reads `super_attribute[...]` controls to identify the selected configurable variant. Custom selectors must update those fields when the shopper changes an option.

## Missing orders or imports

Confirm that the products are supported simple or configurable types and that Hellotext can reach the Magento API. Check the connection page for an import warning.

If the problem continues, contact info@hellotext.com with the warning, affected store view, approximate time and diagnostic output.
