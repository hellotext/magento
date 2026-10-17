# Stock availability

Check up to 100 products in one OAuth-signed request:

```text
GET /rest/{store_code}/V1/hellotext/catalog/stock?productIds[0]=11&productIds[1]=12
```

The integration needs the `Hellotext_Tracking::catalog_read` permission, and Magento Inventory Sales must be enabled.

Pass positive Magento product IDs for simple products, configurable parents or individual variants. Duplicate IDs produce one result.

```json
{
  "schema_version": 1,
  "installation_id": "12345678-1234-4234-8234-123456789012",
  "store_id": 4,
  "business_id": "business123",
  "items": [
    { "id": 11, "salable": true },
    { "id": 12, "salable": false }
  ]
}
```

`salable` uses the stock assigned to the store's website and Magento's inventory rules, including reservations and backorders. Each variant is checked independently of its parent. Missing, disabled, unsupported or other-website products return `false`.

The endpoint returns availability, not quantities, and does not reserve stock. Magento checks the requested quantity and product options when adding items to a cart. Inventory failures return an error rather than an out-of-stock result.
