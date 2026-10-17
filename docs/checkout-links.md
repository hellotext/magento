# Checkout links

Hellotext creates a guest cart and adds products through Magento's API. Open that cart in the shopper's browser with:

```text
GET {storefront_url}/hellotext/checkout/resume/cart/{masked_cart_id}
```

Use the masked guest cart ID returned by Magento. The storefront URL must use the same store view as the cart, including any subdirectory or store-code path. The route redirects to checkout with the prepared cart.

- **Guests:** the prepared cart replaces the browser's current basket.
- **Signed-in shoppers:** Magento merges their existing basket into the prepared cart. Matching products and options have their quantities added together.
- **Invalid or unavailable carts:** the shopper returns to the cart page with an error, leaving their current basket unchanged.

The link grants access to the guest cart. Once assigned to a signed-in customer or converted into an order, the guest link can no longer be used. Magento's checkout settings, stock checks and minimum-order requirements still apply.

## Tracking attribution

Append a Hellotext session identifier to preserve attribution:

```text
{storefront_url}/hellotext/checkout/resume/cart/{masked_cart_id}?hello_session={session_identifier}
```

The module preserves `hello_session` when redirecting to checkout or back to the cart. Hellotext.js reads it from the URL and maintains the session cookie. The order observer reads that cookie, subject to Magento's cookie consent settings, to associate the order with the browsing session.
