## Summary

<!-- What changed and why? Link the issue or contract. -->

## Supported-scope checklist

- [ ] The change is within the documented anonymous storefront analytics scope.
- [ ] Public product data and private cart data remain separated.
- [ ] API-root validation, CSP and browser checks were updated together when relevant.
- [ ] Magento native checkout remains fail-open and no synchronous remote call was added.
- [ ] Tests cover the changed behavior and important negative cases.
- [ ] Documentation and the changelog match the shipped behavior.
- [ ] Manual validation was run for storefront, cart and cache behavior when relevant.

## Verification

<!-- List exact commands, CI checks and any manual Network/worker evidence. -->

## Rollout and rollback

<!-- Note configuration, static asset, cache and rollback considerations. -->
