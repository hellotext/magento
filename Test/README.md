# Running tests

Use Node.js 22 and PHP 8.2, 8.3 or 8.4. From the module directory:

```sh
composer validate
node --test Test/*.test.js Test/*.test.mjs Test/Unit/Js/*.test.cjs
for test in Test/*.test.php; do php "$test" || exit; done
```

## PHPUnit

Place this repository at `app/code/Hellotext/Tracking` in a Magento development installation with its Composer development dependencies and Inventory Sales API installed. From the Magento directory, run:

```sh
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Hellotext/Tracking/Test/Unit
```

The unit tests use Magento's classes with mocked persistence and do not require a database or running storefront. The `Test` directory is included in the Git repository but excluded from Composer distribution archives.
