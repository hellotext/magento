# Security policy

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability. Use GitHub's private vulnerability reporting for this repository, or contact the Hellotext security owners through the team's established private channel. Include the affected revision, Magento/PHP versions, reproduction steps and the impact, but never include credentials, tokens or customer data.

## Scope

The module runs in a merchant Magento storefront and handles public product context plus anonymous private-cart snapshots. Reports involving API-root validation, CSP, cache leakage, anonymous-session rotation, credential exposure or checkout availability are especially important.

Test and validation tooling must never be pointed at a merchant or production environment.
