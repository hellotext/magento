# Connect Magento to Hellotext

This guide walks a store owner and their developer through connecting one Magento website to one Hellotext business. Menu names use the English Magento Admin interface. Your administrator's permissions and language may change what you see.

The owner runs the install commands shown in Hellotext on the Magento server; the last one links the store to the business. Hellotext detects it, the owner confirms and chooses what to enable, and Hellotext applies its settings to the module. Nobody copies URLs, keys or IDs between both systems, edits the module configuration or flushes caches by hand.

## 1. Before you start

You need:

- Access to the intended Hellotext business and permission to manage its integrations (owner, administrator or manager).
- A Magento administrator who can manage integrations.
- Shell access to the Magento server (or a developer who has it) to run Composer and `bin/magento`, and deploy static assets.
- An HTTPS storefront and Magento API reachable by Hellotext, with a valid trusted certificate.
- A confirmed Magento version, PHP version, theme and product types from the [compatibility guide](compatibility.md). The reviewed baseline is Magento Open Source 2.4.7-p10, PHP 8.2–8.3 and Luma; other combinations need review before rollout.
- Approval of the store's analytics, personal-data and consent policies. The extension does not replace a consent-management platform.

The developer should first install and validate the extension in a staging store with a separate Hellotext business. They must check that the browser can load the Hellotext.js asset from jsDelivr and reach the Hellotext API without disabling security protections.

### What the connection enables

Everything below is on as soon as the store is connected; there is nothing to choose, as in the other Hellotext integrations.

| Feature | What it does |
| --- | --- |
| Storefront analytics | Records eligible page, product, cart and checkout activity, before and after login. |
| Widgets | Shows the popups, webchat, WhatsApp widget and forms configured for the business in Hellotext. |
| Customer identification | Identifies signed-in customers and links their earlier browsing. |
| Orders | Imports the order history after connecting and reports each order's later state changes and refunds. |
| Product import | Imports existing supported product identities and configurable families, automatically after connecting. |
| Customer import | Optional last step of the setup: imports existing customers from the selected store's website, with explicit newsletter status. |

The Magento integration always grants the same permissions: **Stores → Settings → All Stores** and the four Hellotext resources (configuration, delivery, catalog read, customer read). A feature that is switched off in Hellotext does not use its permission. The grant is fixed so that a future module update does not force every store to authorize again.

## 2. Install the module and link the store

1. In Hellotext, open **Settings → Integrations → Magento**. The page shows the commands to run on your Magento server, already filled in for this business:

   ```sh
   composer require hellotext/module-tracking
   bin/magento module:enable Hellotext_Tracking
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy -f
   bin/magento cache:flush
   bin/magento hellotext:connect '<code from Hellotext>'
   ```

2. Copy them and run them from the Magento root, following the store's normal release process on production (backups, maintenance window). On Adobe Commerce Cloud, add the package to the project with Composer and deploy; then run the last command in an SSH session.
3. The last command prints **Store linked**. It performs the same steps as **Activate** in Magento Admin and sends Hellotext the one-time code that identifies this business. The code expires after one hour; reload the Hellotext page for a new one.
4. Keep the Hellotext page open: it shows **Store detected** by itself. Choose **Continue**.

If the command says the code was refused, reload the Hellotext page and run the new last command. If it cannot reach Hellotext, the server needs outbound HTTPS access.

## 3. Confirm the connection in Hellotext

1. Check the **Magento store** address and the **Hellotext business**, then choose **Confirm connection**.
2. Hellotext completes the authorization with the store; the page updates on its own after a few seconds.
3. If the store has a single store view, Hellotext connects it right away. If it has several, choose a **Store view** and choose **Connect**. This view provides the default localized catalog context; the connection covers all store views under its website.
4. The connection page opens. Choose **Next** to decide whether to import your existing customers, then **Continue**.

Expected result: **Magento is connected**, and Magento Admin → **System → Extensions → Integrations** shows the **Hellotext** integration as **Active**. In the background Hellotext writes the installation key, Public Business ID and delivery switches for the selected website, clears view-level overrides, cleans configuration and full-page caches, and verifies the store.

### Without the link command

If the module was installed another way (for example from the Adobe Commerce Marketplace), open Magento Admin → **System → Extensions → Integrations**, choose **Activate** on the **Hellotext** row, then **Allow**. Magento opens a Hellotext window: sign in, choose the business if you manage several, and continue from step 3 above. If the grid shows **Reauthorize**, the integration was already authorized or reset by a module update; use it the same way.

The authorization expires 30 minutes after Magento sends it. If Hellotext says it is no longer available, run the link command again or choose **Activate**/**Reauthorize** again.

If Hellotext says another user already confirmed the store and you did not expect it, choose **Reauthorize** in Magento (or run the link command): Magento revokes the previous access.

### Multiple websites or store views

One Hellotext business can have one active Magento website connection. If the website has English and Spanish views, selecting either one does not exclude the other: storefront activity, customers and orders cover the whole website. The selected view supplies localized catalog names and links. If one Magento installation has several websites, connect the website you want to this business; use a separate Hellotext business for another website.

## 4. Before real traffic

Before the store receives real orders through Hellotext, the developer should validate the order flow on a staging store first. The module reports order changes as they happen; nothing needs Magento cron. Obtain the store owner's approval for the data being transferred.

- **Signed-in customer identification** identifies a signed-in shopper by their Magento customer ID, which Hellotext checks against the website's customers. Their browsing before signing in stays linked. This does not verify a guest's email or subscribe a customer to marketing.
- **Orders** are read from the store: past orders when the connection completes, then each new order and each later change, including confirmation, shipment, cancellation and refunds. Each order keeps the browsing session that placed it. It does not depend on the browser reaching a thank-you page. The mapping covers core physical simple/configurable items, not virtual, mixed-virtual or other unsupported order types.

## 5. Imports

Products are imported automatically once the connection is verified. Customers are imported only if you choose so in the last step of the setup; the connection page shows the progress of both in **Status**.

### Products

The import reads enabled, website-assigned simple/configurable products for the selected store. It imports identities and relationships, not prices, stock, descriptions or arbitrary attributes. New imported products remain drafts until a separate stock signal establishes availability; existing stock/archive states are preserved. Reconnecting imports them again and updates the same identities instead of creating duplicates.

### Customers

Confirm with the store owner that the import should include customers belonging to the selected view's **website**, not just customers created through that one view. Magento websites and store views are different scopes.

Only identity, first/last name, email and newsletter status are imported. Addresses, telephone numbers, passwords and payment details are not. Newsletter status is combined across the connected website's views: if any view says unsubscribed, the customer is unsubscribed; otherwise any subscribed view means subscribed; if neither applies, consent is unconfirmed. Reimport fills blank names without erasing existing profile data. The completion count is the number of customer records processed, not necessarily new contacts.

## 6. Verify the result before rollout

Use staging, a separate test business, fictitious customers and an approved test payment method. Do not submit a real paid order solely to test the integration.

| Check | What to do | Expected result |
| --- | --- | --- |
| Connection | Open the Magento connection in Hellotext and run **Verify connection**, then **Refresh status**. | Connected with no module warning. |
| Product visit | Open a supported product in the intended storefront, following its consent flow. | Activity arrives in the correct Hellotext business and identifies the intended product. A product-view timestamp alone is not proof of all tracking. |
| Cart changes | Add a product, change its quantity and remove it. Repeat with a configurable variant if used. | Native cart remains usable; activity reflects the selected product/variant without mixing another visitor's cart. |
| Registered login, if enabled | Browse anonymously, then sign in with a test customer through the storefront, not Admin. | After background processing, activity is associated with the expected customer where a preceding session exists. |
| Order, if enabled | Place a supported test order and note its Magento order number. | One matching order appears in Hellotext after processing; refreshing the thank-you page does not create another. |
| Catalog import, if enabled | Inspect a known product and rerun the import. | Completion is reported; identities remain stable. |
| Customer import, if enabled | Import subscribed, unsubscribed and unconfirmed examples and rerun. | Expected contacts and consent states, without duplicates or reversal of an existing unsubscribe. |

A successful browser request, an initialized script and a saved customer/order record are different levels of evidence. Developers should also check browser, cache and background processing on a staging store before rollout.

### Common problems

| Symptom | Check and next action |
| --- | --- |
| No **Hellotext** integration in Magento | The developer must run `setup:upgrade` (and `setup:di:compile` on production-mode stores) after installing the module. |
| Magento says an integration was reset | A module update changed the integration. Choose **Reauthorize** on the Hellotext row; the connection resumes. |
| The Hellotext window does not open | Allow popups for Magento Admin, then choose **Activate** or **Reauthorize** again. Do not bypass certificate or security warnings. |
| Authorization no longer available | It expired or was already completed. Choose **Activate** or **Reauthorize** in Magento again. |
| "Magento could not be reached" | Have the developer verify the public HTTPS base URL, certificate and API reachability. Do not disable TLS verification. |
| "Hellotext could not write its settings" | The module is missing or outdated, or the store is unreachable. Update the module, then use **Apply settings again** in Hellotext. |
| Module scope/configuration warning | Run `bin/magento hellotext:diagnose --store=<code>` and compare installation key, business and API root. Use **Apply settings again** instead of editing Magento by hand. |
| Connected but no storefront activity | Check consent, deployed static assets, browser blocking, CSP and jsDelivr/API reachability. |
| Orders missing | Check product types, that the store's API is reachable from Hellotext, and Hellotext processing. |
| Import stays running or stops with a warning | Record the specific warning and ask support to check background jobs; a started import is not a completed import. |

For support, provide the affected feature, exact warning text, Magento/module versions, intended store view and approximate time of the test. Remove customer data, tokens and passwords from any screenshot. Developer diagnostics are in [troubleshooting](troubleshooting.md).

## Disconnecting

**Disconnect** in Hellotext first turns the module off for the connected website (it clears the Public Business ID and both delivery switches across its views, so the storefront stops sending activity) and then removes Hellotext's credentials, in the background. If Magento cannot be reached at that moment, Hellotext still disconnects; if the storefront keeps sending activity afterwards, clear the Public Business ID in Magento Admin. Disconnecting does not delete contacts already imported. The **Hellotext** integration stays in Magento Admin; choose **Reauthorize** to connect again, which keeps the same installation key and product references.
