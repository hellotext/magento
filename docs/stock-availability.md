# Stock availability

Hellotext checks availability through an OAuth-signed read using the integration's existing `Hellotext_Tracking::catalog_read` permission:

```text
GET /rest/{store_code}/V1/hellotext/catalog/stock?productIds[0]=11&productIds[1]=12
```

Pass up to 100 positive native product IDs. IDs can identify standalone simple products, configurable parents or exact simple children. Duplicate IDs produce one result. The response has the selected store's configuration scope and one boolean verdict per requested ID:

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

The module filters products by the selected store and website, enabled status, and supported type. Hidden configurable children are included so checking a variant cannot accidentally return its parent's availability. Missing, disabled, unsupported and foreign-website products return `false` without exposing another website's SKU.

The website's stock comes from `StockResolverInterface`; the module sends eligible SKUs to native `AreProductsSalableInterface` in one call. Magento owns salability, including its inventory configuration and reservations. The route never changes stock or reserves products. This is an availability verdict; quantities and required product options are still checked when Magento adds items to a cart. See [Adobe's inventory API documentation](https://developer.adobe.com/commerce/webapi/rest/inventory/check-salable-quantity/).

Magento Inventory Sales must be enabled. The package declares its Inventory Sales API dependency. An incomplete native result or inventory exception fails the request instead of manufacturing a stockout. Successful responses are marked `no-store`. The integration's permission set is unchanged, so existing connections do not need reauthorization after upgrading the module.

## Acceptance in a disposable store

After installing the updated module, run the normal Magento upgrade and DI compilation. With a signed integration token that has only the existing Hellotext resources:

1. Query an in-stock simple product, a sold-out simple product, a configurable parent and its sold-out child together. Verify the child retains its own verdict.
2. Query a disabled product, a nonexistent ID and a product assigned only to another website; each must return `false`.
3. Assign the website to a non-default stock and verify availability follows that stock. Change reservations and backorder/manage-stock settings and compare with Magento's native salability service.
4. Request 101 IDs and verify rejection. Remove catalog-read permission and verify authorization fails. Restore the existing permission afterwards.
5. In Rails, verify single lookup and a recommendation batch return the same statuses; use `cache: false` to bypass the shared 30-minute cache during this check.

Unit tests use Magento 2.4.7-p10 core and Inventory 1.2.7 interfaces with mocked data. They do not replace this signed REST, DI and inventory-index acceptance on a running store.
