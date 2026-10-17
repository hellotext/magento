# Contributing

This repository is the Magento module of the Hellotext integration. Keep changes small, explicit and reviewable.

## Pull requests

Every PR should state the user-visible behavior, the security and cache implications, the tests run and any manual check that remains. Do not add production credentials, local certificates, customer data, quote IDs or generated Magento assets.

## Local checks

```bash
node --test Test/*.test.js Test/*.test.mjs Test/Unit/Js/*.test.cjs
for test in Test/*.test.php; do php "$test"; done
composer validate --no-check-publish
```

The standalone PHP checks use small Magento stubs for mapping and guard logic. Also run the PHPUnit suite against Magento's real classes as described in [Test/README.md](Test/README.md). CI runs both suites on PHP 8.2 and 8.3, plus all JavaScript tests. These checks do not replace an installation smoke test on a real Magento store.

## Commit and review expectations

- Use the repository convention: `magento-integration: ...`.
- Regenerate Magento static assets after JavaScript or template changes.
- Do not weaken privacy, SSRF, CSRF, cache or session guards to make a local check pass.
- Do not introduce synchronous network calls into checkout.
- Changes that affect tracking payloads, identity or orders need a matching review on the Hellotext side.
