const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

const source = resolve(__dirname, '../../../view/frontend/web/js');
const anonymous = '12345678-1234-4234-8234-123456789012';
const linked = 'AbC123xyAbC123xyAbC123xyAbC123xy';
const flush = () => new Promise(resolve => setImmediate(resolve));

async function storefront({ urlSession, cookieSession, identity = null, enabled = true, order = null } = {}) {
    const config = {
        businessId: 'business-one', installationId: 'store-one', storeId: '1', currency: 'USD',
        apiRoot: 'https://api.hellotext.com/v1', checkoutStarted: true
    };
    const cookies = new Map(cookieSession ? [['hello_session', cookieSession]] : []);
    const storage = new Map();
    const events = [];
    const initializations = [];
    const warnings = [];
    let subscription;
    const data = {
        enabled, anonymous: true, session: anonymous, context: config, cart_ready: true, identity,
        checkout_reference: 'magento:store-one:checkout:42',
        items: [{
            product: { source: 'magento', reference: 'magento:store-one:product:11', name: 'Shirt', sku: 'SHIRT' },
            quantity: 1, price: { amount: '25.00', currency: 'USD' }
        }]
    };
    const window = {
        location: { href: 'https://shop.example/checkout/' + (urlSession ? '?hello_session=' + urlSession : '') },
        sessionStorage: {
            getItem: key => storage.get(key) || null,
            setItem: (key, value) => storage.set(key, value),
            removeItem: key => storage.delete(key)
        }
    };
    const document = {
        get cookie() { return [...cookies].map(([key, value]) => key + '=' + value).join('; '); },
        querySelector: selector => selector === '[data-hellotext-order]' && order ? { getAttribute: () => order } : null,
        querySelectorAll: () => [],
        addEventListener() {}
    };
    // The SDK contract: URL wins, then explicit configuration, then its cookie.
    const sdk = {
        async initialize(business, options) {
            initializations.push(options);
            this.session = new URL(window.location.href).searchParams.get('hello_session')
                || options.session || cookies.get('hello_session');
            cookies.set('hello_session', this.session);
        },
        async track(action) { events.push({ action, session: this.session }); return { succeeded: true }; },
        async identify(id) { cookies.set('hello_user_id', id); cookies.set('hello_user_source', 'magento'); },
        forget() { cookies.delete('hello_user_id'); cookies.delete('hello_user_source'); }
    };
    const customerData = {
        getInitCustomerData: () => Promise.resolve(),
        reload: () => Promise.resolve({ cart: { hellotext: data } }),
        get: () => ({ subscribe(callback) { subscription = callback; } })
    };
    let createCartTracker;
    vm.runInNewContext(readFileSync(resolve(source, 'cart-tracker.js'), 'utf8'), {
        define: (_, factory) => { createCartTracker = factory(); }, console
    });
    vm.runInNewContext(readFileSync(resolve(source, 'bridge.js'), 'utf8'), {
        define: (_, factory) => factory(customerData, createCartTracker)(config),
        require: (_, loaded) => loaded(sdk), window, document, URL, setTimeout,
        console: { warn: (...args) => warnings.push(args) }
    });
    await flush();
    return { sdk, data, cookies, storage, events, initializations, warnings, refresh: async () => { subscription(); await flush(); } };
}

test('the URL session overrides an existing cookie and reaches checkout/cart tracking', async () => {
    const page = await storefront({ urlSession: linked, cookieSession: 'previous-session' });
    assert.deepEqual(page.events.map(event => event.action).sort(), ['cart.viewed', 'checkout.started', 'page.viewed']);
    assert.ok(page.events.every(event => event.session === linked));
    assert.equal(page.cookies.get('hello_session'), linked);
    assert.ok(page.storage.has('hellotext-magento-cart:business-one:' + linked));
    assert.equal(page.initializations.length, 1);
    assert.deepEqual(page.warnings, []);
});

test('navigation without the query keeps the SDK cookie instead of the private Magento session', async () => {
    const page = await storefront({ cookieSession: linked });
    assert.equal(page.sdk.session, linked);
    assert.ok(page.events.length > 0);
    assert.ok(page.events.every(event => event.session === linked));
});

test('a fresh visitor still uses the generated anonymous fallback', async () => {
    const page = await storefront();
    assert.equal(page.sdk.session, anonymous);
    assert.ok(page.events.length > 0);
    assert.ok(page.events.every(event => event.session === anonymous));
});

test('cart refresh and customer login keep the SDK-selected session', async () => {
    const page = await storefront({ urlSession: linked });
    page.data.session = '22222222-2222-4222-8222-222222222222';
    page.data.identity = { customer_id: '7' };
    page.data.items[0].quantity = 2;
    await page.refresh();
    assert.equal(page.cookies.get('hello_user_id'), '7');
    assert.equal(page.events.at(-1).action, 'cart.added');
    assert.ok(page.events.every(event => event.session === linked));
    assert.equal(page.initializations.length, 1);
});

test('the success-page event uses the SDK cookie', async () => {
    const page = await storefront({ cookieSession: linked, order: 'magento:store-one:order:99' });
    assert.deepEqual(page.events.find(event => event.action === 'order.placed'), { action: 'order.placed', session: linked });
});

test('a disabled private section still prevents SDK initialization', async () => {
    const page = await storefront({ urlSession: linked, enabled: false });
    assert.equal(page.initializations.length, 0);
    assert.deepEqual(page.events, []);
});
