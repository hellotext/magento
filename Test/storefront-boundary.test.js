const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../view/frontend/web/js/bridge.js'), 'utf8');
const generatedSession = 'ae25b1d4-8000-4000-8000-123456789abc';
const context = { businessId: 'public-business', apiRoot: 'http://api.lvh.me:3200/v1', installationId: 'local', storeId: '1', currency: 'USD' };
const settle = () => new Promise(setImmediate);
let realCartTracker;
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../view/frontend/web/js/cart-tracker.js'), 'utf8'), {
    define: (_deps, factory) => { realCartTracker = factory(); }, console, Promise
});

function harness({ cookie = '', query = '', sections, offline = false, rejectReinitialize = false, productReference = 'magento:local:product:1' } = {}) {
    const events = [];
    const carts = [];
    const subscriptions = {};
    let initialize;
    let reloads = 0;
    let loads = 0;
    let initializations = 0;
    let fresh = sections || { cart: { hellotext: { enabled: true, cart_ready: true, context, identity: null, items: [] } }, customer: {} };
    const cookies = new Map(cookie ? cookie.split('; ').map(value => value.split('=')) : []);
    const document = {
        get cookie() { return [...cookies].map(([name, value]) => `${name}=${value}`).join('; '); },
        set cookie(value) { const [name, content] = value.split('='); cookies.set(name, content); },
        querySelector: () => ({ getAttribute: (attribute) => JSON.stringify(attribute === 'data-hellotext-product'
            ? { reference: productReference, name: 'Item', sku: 'ITEM', source: 'magento' } : {}) }),
        querySelectorAll: () => [], addEventListener() {}
    };
    const sdk = {
        initialize: async (_business, config) => {
            initializations++;
            if (rejectReinitialize && initializations > 1) { throw new Error('SDK refresh unavailable'); }
            assert.equal('session' in config, false);
            assert.equal('autoGenerateSession' in config, false);
            sdk.session = new URLSearchParams(query).get('hello_session') || cookies.get('hello_session') || generatedSession;
            document.cookie = `hello_session=${sdk.session}`;
        },
        identify: async (id, options) => {
            cookies.set('hello_user_id', id);
            cookies.set('hello_user_source', options.source);
            events.push(['identify', id, options]);
            return { failed: false };
        },
        forget: () => { cookies.delete('hello_user_id'); cookies.delete('hello_user_source'); },
        track: async (action) => { events.push(action); return { failed: false }; }
    };
    const customerData = {
        getInitCustomerData: () => Promise.resolve(),
        get: (name) => ({ subscribe: (fn) => { subscriptions[name] = fn; } }),
        reload: async (names, force) => {
            reloads++;
            assert.deepEqual(Array.from(names), ['cart']);
            assert.equal(force, true);
            if (offline) { throw new Error('private server unavailable'); }
            const data = JSON.parse(JSON.stringify(fresh));
            // Magento updates observables before resolving reload's deferred.
            subscriptions.cart?.(data.cart);
            return data;
        }
    };
    const cartTracker = () => (items) => { carts.push(items); };
    cartTracker.validItems = realCartTracker.validItems;
    vm.runInNewContext(source, {
        window: { location: { href: `http://localhost/product.html${query}` } }, document,
        Promise, URL, setTimeout, console: { warn() {} },
        define: (_deps, factory) => { initialize = factory(customerData, cartTracker); },
        require: (_deps, callback) => { loads++; callback(sdk); }
    });
    initialize(context);
    return {
        events, carts, document, sdk,
        reloads: () => reloads, loads: () => loads,
        fresh: (value) => { fresh = value; },
        notify: (name, value) => subscriptions[name]?.(value)
    };
}

test('waits for fresh native private content before initialization and product/cart tracking', async () => {
    const h = harness();
    assert.equal(h.loads(), 0);
    await settle();
    assert.equal(h.sdk.session, generatedSession);
    assert.deepEqual(h.events, ['page.viewed', 'product.viewed']);
    assert.equal(h.carts.length, 1);
    assert.ok(h.reloads() >= 2);
    const before = h.reloads();
    h.notify('cart', { hellotext: { enabled: true, session: 'forged-cached-value' } });
    await settle();
    assert.equal(h.reloads(), before + 1);
    assert.equal(h.carts.length, 2);
});

for (const cookie of ['hello_user_id=123', 'x=y; hello_user_source=shopify', 'hello_user_identification_hash=abc', 'hello_user_id=123; hello_user_source=shopify']) {
    test(`tracks without changing existing identity cookies: ${cookie}`, async () => {
        const h = harness({ cookie });
        await settle();
        assert.equal(h.loads(), 1);
        assert.deepEqual(h.events, ['page.viewed', 'product.viewed']);
        assert.equal(h.carts.length, 1);
        assert.ok(cookie.split('; ').every(value => h.document.cookie.split('; ').includes(value)));
    });
}
for (const query of ['?hello_session=other', '?hello_session=', '?hello%5fsession=other']) {
    test(`lets the SDK select the URL session ${query}`, async () => {
        const h = harness({ query });
        await settle();
        assert.equal(h.loads(), 1);
        assert.equal(h.sdk.session, new URLSearchParams(query).get('hello_session') || generatedSession);
        assert.deepEqual(h.events, ['page.viewed', 'product.viewed']);
    });
}
for (const [name, data] of Object.entries({
    disabled: { enabled: false },
    missing: null,
    'missing context': { enabled: true, cart_ready: true, identity: null, items: [] },
    'invalid identity': { enabled: true, cart_ready: true, context, identity: { customer_id: '0' }, items: [] },
    'invalid items': { enabled: true, cart_ready: true, context, identity: null, items: [{}] }
})) {
    test(`does not load SDK for ${name} private state`, async () => {
        const h = harness({ sections: { cart: { hellotext: data } } });
        await settle();
        assert.equal(h.loads(), 0);
    });
}
test('private reload failure stops tracking without impacting native cart', async () => {
    const h = harness({ offline: true });
    await settle();
    assert.equal(h.loads(), 0);
    assert.equal(h.events.length, 0);
});
test('refreshes Magento identity after login while preserving the SDK session', async () => {
    const h = harness();
    await settle();
    h.fresh({ cart: { hellotext: {
        enabled: true, cart_ready: true, context,
        identity: { customer_id: '42' }, items: []
    } } });
    h.notify('cart', {});
    await settle();

    assert.equal(h.sdk.session, generatedSession);
    assert.ok(h.events.some((event) => Array.isArray(event) && event[0] === 'identify' && event[1] === '42'));
    assert.ok(h.events.includes('page.viewed'));
    assert.equal(h.events.filter((event) => event === 'product.viewed').length, 1);
    assert.ok(h.carts.length >= 2);
});
test('does not turn a temporary unbound quote during login into cart removals', async () => {
    const item = { quantity: 1, price: { amount: '12.00', currency: 'USD' }, product: {
        reference: 'magento:local:product:1', source: 'magento', name: 'Item', sku: 'ITEM'
    } };
    const h = harness({ sections: { cart: { hellotext: {
        enabled: true, cart_ready: true, context, identity: null, items: [item]
    } } } });
    await settle();
    assert.equal(h.carts.length, 1);

    h.fresh({ cart: { hellotext: {
        enabled: true, cart_ready: false, context,
        identity: { customer_id: '42' }, items: []
    } } });
    h.notify('cart', {});
    await settle();

    assert.equal(h.carts.length, 1);
    assert.deepEqual(h.carts[0], [item]);
});
test('contains SDK reinitialization failure after its cookie changes', async () => {
    const h = harness({ rejectReinitialize: true });
    await settle();
    h.document.cookie = 'hello_session=AbC123xy';
    h.fresh({ cart: { hellotext: {
        enabled: true, cart_ready: true,
        context,
        identity: { customer_id: '42' }, items: []
    } } });
    h.notify('cart', {});
    await settle();

    assert.equal(h.carts.length, 1);
});
test('keeps tracking cart snapshots when identity cookies change', async () => {
    const h = harness();
    await settle();
    h.document.cookie = 'hello_user_id=other';
    h.document.cookie = 'hello_user_source=shopify';
    h.notify('cart', {});
    await settle();
    assert.equal(h.carts.length, 2);
    assert.equal(h.sdk.session, generatedSession);
});

for (const field of ['businessId', 'apiRoot', 'installationId', 'storeId', 'currency']) {
    test(`rejects stale public/private ${field} bindings before SDK load`, async () => {
        const h = harness({ sections: { cart: { hellotext: {
            enabled: true, cart_ready: true, identity: null, items: [], context: { ...context, [field]: 'different' }
        } }, customer: {} } });
        await settle();
        assert.equal(h.loads(), 0);
        assert.deepEqual(h.events, []);
    });
}

test('lets the SDK reuse an existing session cookie', async () => {
    const cookie = 'hello_session=be25b1d4-8000-4000-8000-123456789abc';
    const h = harness({ cookie });
    await settle();
    assert.equal(h.loads(), 1);
    assert.equal(h.sdk.session, cookie.split('=')[1]);
    assert.equal(h.document.cookie, cookie);
    assert.deepEqual(h.events, ['page.viewed', 'product.viewed']);
});

test('allows its matching session cookie across navigation', async () => {
    const h = harness({ cookie: `hello_session=${generatedSession}` });
    await settle();
    assert.equal(h.loads(), 1);
    assert.deepEqual(h.events, ['page.viewed', 'product.viewed']);
});

for (const [reference, currency] of [
    ['magento:other:product:1', 'USD'], ['magento:local:product:1', 'EUR']
]) {
    test('rejects a cart item outside the bound product namespace or currency before SDK load', async () => {
        const items = [{ quantity: 1, price: { amount: '19.99', currency }, product: { reference, source: 'magento', sku: 'ITEM', name: 'Item' } }];
        const h = harness({ sections: { cart: { hellotext: { enabled: true, cart_ready: true, context, identity: null, items } }, customer: {} } });
        await settle();
        assert.equal(h.loads(), 0);
        assert.deepEqual(h.events, []);
    });
}

test('does not send a stale PDP reference belonging to another store', async () => {
    const h = harness({ productReference: 'magento:other:product:1' });
    await settle();
    assert.equal(h.events.includes('product.viewed'), false);
});
