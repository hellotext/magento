# Checkout links

Hellotext creates a guest cart and adds its products through Magento's native API. The module then connects that prepared cart to the shopper's browser through:

```text
GET {storefront_url}/hellotext/checkout/resume/cart/{masked_cart_id}
```

Use the native masked cart ID, not the numeric quote ID. The storefront URL must belong to the same store view used to create the cart, including any subdirectory, `index.php` or store-code path. The route redirects to Magento's secure checkout; it does not create another cart or add the products again.

- **Guests:** the prepared cart replaces the browser's current basket.
- **Signed-in shoppers:** Magento assigns the prepared cart to the current customer and merges their existing basket into it. Existing items are kept; matching products and options have their quantities added together. Magento retires the previous customer cart and removes the guest mask during assignment.
- **Unavailable links:** malformed, unknown, inactive, empty, customer-owned or other-store carts are rejected without switching the shopper's cart. The shopper returns to the cart page with an error message.

Treat the link as private: its masked ID grants access to the guest cart. Once assigned to a customer, the original guest link is no longer usable; the customer's browser continues with the assigned cart. Completed orders are never reopened. Native Magento checkout still enforces guest-checkout settings, stock and minimum-order requirements.

The redirect is not cacheable, does not forward its URL as a referrer, and invalidates Magento's browser cart/checkout cache so the storefront shows the selected basket. No OAuth permission changes or reauthorization are required for this route.

Hellotext may append `?hello_session={session_identifier}` to this link. The handoff preserves it on the checkout redirect (or cart redirect on failure). Hellotext.js owns the storefront session: it prefers the URL value, otherwise reuses its cookie, and generates a UUID for a fresh visitor. The bridge supplies no session override, and Magento's private cart payload carries no tracking session. Later pages, cart refreshes and customer login/logout retain the SDK's session. At order placement, the observer copies the `hello_session` cookie to the order, subject to Magento's cookie consent setting. Signed snapshots and later lifecycle events retain that same identifier, including the hashed IDs used by tracked links.

After deploying the module update, run the store's normal DI compilation and cache-cleaning steps. Validate a prepared link in the supported Luma store with an empty guest session, an existing guest basket, and a signed-in customer with an existing basket. Also confirm that an invalid link preserves the existing cart and a link for another store view is refused. With a tracked link, confirm the same identifier in the checkout URL, SDK cookie, order snapshot and subsequent lifecycle event. Deploy the Rails importer update that resolves hashed session IDs alongside the module update.

The handoff uses Magento's native [customer assignment and merge](https://github.com/magento/magento2/blob/2.4.7-p10/app/code/Magento/Quote/Model/QuoteManagement.php) and [checkout session](https://github.com/magento/magento2/blob/2.4.7-p10/app/code/Magento/Checkout/Model/Session.php).
