# Connect Magento to Hellotext

You need access to the Magento server and permission to manage integrations in Hellotext.

## Install and connect

1. In Hellotext, open **Settings → Integrations → Magento**.
2. Run the commands shown there from your Magento installation directory:

   ```sh
   composer require hellotext/module-tracking
   bin/magento module:enable Hellotext_Tracking
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy -f
   bin/magento cache:flush
   bin/magento hellotext:connect '<code from Hellotext>'
   ```

3. When the command prints **Store linked**, return to Hellotext and choose **Continue**.
4. Check the store address and business, then choose **Confirm connection**.
5. If prompted, select a **Store view** and choose **Connect**.
6. Choose whether to import existing customers.

If the link code expires, reload the Hellotext page and run the new `hellotext:connect` command. Hellotext applies the module settings automatically.

### Connect through Magento Admin

After installing the module, you can also open **System → Extensions → Integrations** in Magento Admin and choose **Activate → Allow** for Hellotext. Sign in to Hellotext in the window that opens and confirm the connection. For a previously authorized integration, choose **Reauthorize**.

## Websites and store views

One Hellotext business connects to one Magento website, including all of its store views. The selected view determines the catalog's language, currency and links. To connect another website, use a separate Hellotext business.

## Products and customers

Products import automatically after connection. Hellotext imports simple and configurable products and their variants, then fetches prices, descriptions, categories and stock availability. Reimporting updates existing products without creating duplicates.

Customer import is optional and includes customers across the connected website. It imports names, email and newsletter status. If newsletter subscriptions differ between store views, unsubscribed takes precedence over subscribed. Existing contacts retain their subscription preferences.

Signed-in shoppers are identified automatically, even if you skip customer import. The module also displays the widgets configured for your business in Hellotext.

## Orders

The module reports order placement, status changes and refunds. Hellotext reads the order details from Magento. Order import supports physical simple and configurable products; virtual products and other product types are not supported. Magento's `complete` state is reported as `order.shipped`.

## Disconnect

Choose **Disconnect** on the Magento connection in Hellotext. This stops tracking and removes Hellotext's access credentials without deleting imported contacts or products.

If Magento was unreachable during disconnection and tracking continues, clear the Public Business ID in the module's Magento Admin settings. To reconnect, run `hellotext:connect` again or choose **Reauthorize** in Magento Admin.

For connection and tracking problems, see [Troubleshooting](troubleshooting.md).
