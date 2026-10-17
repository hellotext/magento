const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.join(__dirname, '../view/frontend/web/js/bridge.js'),
    'utf8'
);

const anonymousSession = 'ae25b1d4-8000-4000-8000-123456789abc';
const emptyTracker = () => () => Promise.resolve();
emptyTracker.validItems = Array.isArray;

function runBridge(context, identity = null, tracking = {}) {
    let configuration;
    context.window.location ||= { href: 'http://localhost/product.html' };
    context.document.cookie ||= '';
    const define = context.define;
    const require = context.require;
    context.require = (deps, callback) => require(deps, (sdk) => {
        sdk.session = anonymousSession;
        callback(sdk);
    });
    context.define = (deps, factory) => define(deps, () => (value) => {
        configuration = { installationId: 'store-one', storeId: '1', currency: 'USD', ...value };
        return factory({
        getInitCustomerData: () => Promise.resolve(),
        reload: async () => ({ cart: { hellotext: { enabled: true, cart_ready: true, context: configuration, identity, items: [], ...tracking } }, customer: {} }),
        get: () => ({ subscribe: () => {} })
        }, emptyTracker)(configuration);
    });
    vm.runInNewContext(source, context);
}

function productElement(payload, variants = {}) {
    return {
        getAttribute: (attribute) => JSON.stringify(
            attribute === 'data-hellotext-product' ? payload : variants
        )
    };
}

test('initializes once and sends the canonical product view payload', async () => {
    const calls = [];
    let initialize;
    const sdk = {
        initialize: async (id, options) => { calls.push(['initialize', id, options]); },
        track: async (action, params) => {
            calls.push(['track', action, params]);
            return { failed: false };
        }
    };
    const context = {
        window: {},
        document: {
            querySelector: () => productElement({
                name: 'Test shirt', reference: 'magento:store-one:product:42', sku: 'TS-42', source: 'magento'
            }),
            querySelectorAll: () => [],
            addEventListener: () => {}
        },
        console,
        Promise,
        URL,
        setTimeout,
        require: (deps, callback) => {
            calls.push(['require', deps[0]]);
            callback(sdk);
        },
        define: (_deps, factory) => { initialize = factory(); }
    };
    runBridge(context);

    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3200/v1' });
    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3200/v1' });
    await new Promise(setImmediate);

    assert.equal(calls.filter(([name]) => name === 'require').length, 1);
    assert.equal(calls[1][1], 'public-business');
    assert.equal(calls[1][2].apiRoot, 'http://api.lvh.me:3200/v1');
    assert.equal(calls[1][2].push, false);
    assert.equal('session' in calls[1][2], false);
    assert.equal('autoGenerateSession' in calls[1][2], false);
    ['popup', 'webchat', 'whatsappWidget'].forEach((widget) => assert.equal(widget in calls[1][2], false));
    assert.equal(calls.find(([name, action]) => name === 'track' && action === 'page.viewed')[2].object_parameters.source, 'magento');
    assert.equal(calls.filter(([name, action]) => name === 'track' && action === 'page.viewed').length, 1);
    const productCall = calls.find(([name, action]) => name === 'track' && action === 'product.viewed');
    assert.deepEqual(JSON.parse(JSON.stringify(productCall[2])), {
        url: 'http://localhost/product.html',
        object_parameters: {
            name: 'Test shirt', reference: 'magento:store-one:product:42', sku: 'TS-42', source: 'magento'
        }
    });
});

test('identifies an authenticated customer before storefront events and keeps tracking active', async () => {
    const calls = [];
    let initialize;
    const identity = { customer_id: '42' };
    const sdk = {
        initialize: async () => {},
        identify: async (id, options) => { calls.push(['identify', id, options]); return { failed: false }; },
        track: async (action) => { calls.push(['track', action]); return { failed: false }; }
    };
    const context = {
        window: {},
        document: {
            querySelector: () => productElement({ name: 'Shirt', reference: 'magento:store-one:product:42', sku: 'TS-42', source: 'magento' }),
            querySelectorAll: () => [],
            addEventListener: () => {}
        },
        console, Promise, URL, setTimeout,
        require: (_deps, callback) => callback(sdk),
        define: (_deps, factory) => { initialize = factory(); }
    };
    runBridge(context, identity);
    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3200/v1' });
    await new Promise(setImmediate);

    assert.equal(calls[0][0], 'identify');
    assert.deepEqual(JSON.parse(JSON.stringify(calls[0])), ['identify', '42', { source: 'magento' }]);
    assert.deepEqual(calls.filter(([name]) => name === 'track').map(([, action]) => action), ['page.viewed', 'product.viewed']);
});

test('product observation URL excludes query parameters and fragments', async () => {
    const calls = [];
    let initialize;
    runBridge({
        window: { location: { href: 'http://localhost/product.html?email=private@example.com#secret' } },
        document: {
            querySelector: () => productElement({
                name: 'Shirt', reference: 'magento:store-one:product:42', sku: 'TS-42', source: 'magento'
            }),
            querySelectorAll: () => [],
            addEventListener: () => {}
        },
        console, Promise, URL, setTimeout,
        require: (_deps, callback) => callback({ initialize: async () => {},
            track: async (action, params) => { calls.push([action, params]); return { failed: false }; } }),
        define: (_deps, factory) => { initialize = factory(); }
    });

    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3200/v1' });
    await new Promise(setImmediate);
    const productCall = calls.find(([action]) => action === 'product.viewed');
    assert.equal(productCall[1].url, 'http://localhost/product.html');
});

test('accepts the explicit production API root', async () => {
    let initialize;
    let loaded = false;
    runBridge({
        window: {},
        document: {
            querySelector: () => productElement({
                name: 'Production shirt', reference: 'magento:store-one:product:42', sku: 'PROD-42', source: 'magento'
            }),
            querySelectorAll: () => [],
            addEventListener: () => {}
        },
        console,
        Promise,
        URL,
        setTimeout,
        require: (_deps, callback) => {
            loaded = true;
            callback({ initialize: async () => {}, track: async () => ({ failed: false }) });
        },
        define: (_deps, factory) => { initialize = factory(); }
    });

    initialize({ businessId: 'public-business', apiRoot: 'https://api.hellotext.com/v1' });
    await new Promise(setImmediate);
    assert.equal(loaded, true);
});

test('rejects the host-only Rails URL because API routes require the api subdomain', async () => {
    let initialize;
    let loaded = false;
    runBridge({
        window: {},
        document: { querySelector: () => null, querySelectorAll: () => [], addEventListener: () => {} },
        console,
        Promise,
        URL,
        setTimeout,
        require: (_deps, callback) => { loaded = true; callback({ initialize: async () => {}, track: async () => ({ failed: false }) }); },
        define: (_deps, factory) => { initialize = factory(); }
    });

    initialize({ businessId: 'public-business', apiRoot: 'http://localhost:3200/v1' });
    await new Promise(setImmediate);
    assert.equal(loaded, false);
});

test('tracks a selected configurable child once after Magento updates its hidden input', async () => {
    const calls = [];
    let initialize;
    let selected = '';
    let onChange;
    let onDirectChange;
    const sdk = {
        initialize: async () => {},
        track: async (action, params) => { if (action === 'product.viewed') { calls.push(params.object_parameters); } return { failed: false }; }
    };
    runBridge({
        window: {},
        document: {
            querySelector: (selector) => selector === '[data-hellotext-product]'
                ? productElement(
                    { name: 'Parent', reference: 'magento:store-one:product:4', sku: 'PARENT', source: 'magento' },
                    { '93:4': { name: 'Red', reference: 'magento:store-one:product:2', sku: 'CHILD-RED', source: 'magento' } }
                )
                : null,
            querySelectorAll: () => [{
                name: 'super_attribute[93]',
                value: selected,
                addEventListener: (_event, callback) => { onDirectChange = callback; }
            }],
            addEventListener: (_event, callback) => { onChange = callback; }
        },
        console,
        Promise,
        URL,
        setTimeout,
        require: (_deps, callback) => callback(sdk),
        define: (_deps, factory) => { initialize = factory(); }
    });

    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3100/v1' });
    await new Promise(setImmediate);
    selected = '4';
    onDirectChange();
    await new Promise((resolve) => setTimeout(resolve, 1));
    onChange();
    await new Promise((resolve) => setTimeout(resolve, 1));

    assert.deepEqual(JSON.parse(JSON.stringify(calls)), [
        { name: 'Parent', reference: 'magento:store-one:product:4', sku: 'PARENT', source: 'magento' },
        { name: 'Red', reference: 'magento:store-one:product:2', sku: 'CHILD-RED', source: 'magento' }
    ]);
});

test('does not load the SDK for an unsupported API root', async () => {
    let initialize;
    let loaded = false;
    runBridge({
        window: {},
        document: {
            querySelector: () => { throw new Error('Must not read product'); },
            querySelectorAll: () => []
        },
        console,
        Promise,
        URL,
        setTimeout,
        require: () => { loaded = true; },
        define: (_deps, factory) => { initialize = factory(); }
    });

    for (const apiRoot of [
        'https://api.hellotext.com./v1',
        'http://user@api.lvh.me:3200/v1',
        'http://user:pass@api.lvh.me:3200/v1',
        'http://api.lvh.me:3200/v1?redirect=production',
        'http://api.lvh.me:3200/v1#fragment',
        'http://api.lvh.me:3201/v1',
        'http://api.lvh.me.:3200/v1',
        'http://api.lvh.me:3200/v1//'
    ]) {
        initialize({ businessId: 'public-business', apiRoot });
    }
    await new Promise(setImmediate);
    assert.equal(loaded, false);
});

test('allows a later change to retry an SDK-rejected product event', async () => {
    const calls = [];
    let initialize;
    let onChange;
    runBridge({
        window: {},
        document: {
            querySelector: () => productElement({
                name: 'Test shirt', reference: 'magento:store-one:product:42', sku: 'TS-42', source: 'magento'
            }),
            querySelectorAll: () => [],
            addEventListener: (_event, callback) => { onChange = callback; }
        },
        console: { warn: () => {} },
        Promise,
        URL,
        setTimeout,
        require: (_deps, callback) => callback({
            initialize: async () => {},
            track: async (action) => {
                if (action === 'page.viewed') { return { failed: false }; }
                calls.push('track');
                return { failed: calls.length === 1, data: 'temporary failure' };
            }
        }),
        define: (_deps, factory) => { initialize = factory(); }
    });

    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3100/v1' });
    await new Promise(setImmediate);
    onChange();
    await new Promise((resolve) => setTimeout(resolve, 1));
    assert.deepEqual(calls, ['track', 'track']);
});

test('contains a synchronous SDK initialization failure and releases the page lock', async () => {
    let initialize;
    const warnings = [];
    const window = {};
    runBridge({
        window,
        document: {
            querySelector: () => { throw new Error('Must not read product'); },
            querySelectorAll: () => []
        },
        console: { warn: (_message, error) => { warnings.push(error.message); } },
        Promise,
        URL,
        setTimeout,
        require: (_deps, callback) => callback({
            initialize: () => { throw new Error('offline'); }
        }),
        define: (_deps, factory) => { initialize = factory(); }
    });

    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3100/v1' });
    await new Promise(setImmediate);
    assert.deepEqual(warnings, ['offline']);
    assert.equal(window.hellotextMagentoStarted, false);
});

for (const businessId of [undefined, 'public-business', 'other-business']) {
    test(`reuses a global SDK with the store configuration after ${businessId || 'no'} initialization`, async () => {
        const calls = [];
        let initialize;
        const sdk = {
            business: businessId ? { id: businessId } : undefined,
            initialize: async (id, options) => {
                calls.push(['initialize', id, options]);
                sdk.session = anonymousSession;
            },
            track: async (action) => { calls.push(['track', action]); }
        };
        runBridge({
            window: { Hellotext: sdk },
            document: { querySelector: () => null, querySelectorAll: () => [] },
            console: { warn: () => { calls.push(['warn']); } },
            Promise,
            URL,
            setTimeout,
            require: () => { throw new Error('Must not load a duplicate SDK'); },
            define: (_deps, factory) => { initialize = factory(); }
        });

        initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3100/v1' });
        await new Promise(setImmediate);
        assert.equal(calls[0][0], 'initialize');
        assert.equal(calls[0][1], 'public-business');
        assert.equal(calls[0][2].apiRoot, 'http://api.lvh.me:3100/v1');
        assert.deepEqual(calls.slice(1), [['track', 'page.viewed']]);
    });
}

test('tracks through an already initialized AMD SDK using the store configuration', async () => {
    let initialize;
    const calls = [];
    runBridge({
        window: {},
        document: { querySelector: () => null, querySelectorAll: () => [] },
        console: { warn: () => { calls.push(['warn']); } },
        Promise,
        URL,
        setTimeout,
        require: (_deps, callback) => callback({
            business: { id: 'public-business' },
            initialize: async (id, options) => { calls.push(['initialize', id, options]); },
            track: async (action) => { calls.push(['track', action]); }
        }),
        define: (_deps, factory) => { initialize = factory(); }
    });

    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3100/v1' });
    await new Promise(setImmediate);
    assert.equal(calls[0][0], 'initialize');
    assert.equal(calls[0][1], 'public-business');
    assert.equal(calls[0][2].apiRoot, 'http://api.lvh.me:3100/v1');
    assert.deepEqual(calls.slice(1), [['track', 'page.viewed']]);
});

test('reports a success-page order by its canonical reference only, once per order', async () => {
    const run = async (reference, storage) => {
        const calls = [];
        let initialize;
        runBridge({
            window: { sessionStorage: storage },
            document: {
                querySelector: (selector) => selector === '[data-hellotext-order]'
                    ? { getAttribute: () => reference }
                    : null,
                querySelectorAll: () => [],
                addEventListener: () => {}
            },
            console, Promise, URL, setTimeout,
            require: (_deps, callback) => callback({
                initialize: async () => {},
                identify: async () => ({ failed: false }),
                track: async (action, payload) => { calls.push([action, payload]); return { failed: false }; }
            }),
            define: (_deps, factory) => { initialize = factory(); }
        });
        initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3200/v1' });
        await new Promise(setImmediate);
        return calls.filter(([action]) => action === 'order.placed');
    };
    const values = { [`hellotext-magento-cart:public-business:${anonymousSession}`]: '[]' };
    const storage = {
        getItem: (key) => values[key] || null,
        setItem: (key, value) => { values[key] = value; },
        removeItem: (key) => { delete values[key]; }
    };

    const first = await run('magento:store-one:order:99', storage);
    assert.deepEqual(JSON.parse(JSON.stringify(first)),
        [['order.placed', { object_parameters: { reference: 'magento:store-one:order:99', source: 'magento' } }]]);
    // The purchased cart's baseline is dropped instead of reporting its lines as removed.
    assert.equal(values[`hellotext-magento-cart:public-business:${anonymousSession}`], undefined);
    assert.equal((await run('magento:store-one:order:99', storage)).length, 0);
    assert.equal((await run('magento:another-store:order:99', storage)).length, 0);
    assert.equal((await run('magento:store-one:order:0', storage)).length, 0);
});

test('reports checkout started once for a non-empty valid quote and retries rejected events', async () => {
    const values = {};
    const storage = {
        getItem: (key) => values[key] || null,
        setItem: (key, value) => { values[key] = value; }
    };
    const item = {
        quantity: 1,
        price: { amount: 25, currency: 'USD' },
        product: { name: 'Shirt', reference: 'magento:store-one:product:42', sku: 'TS-42', source: 'magento' }
    };
    const run = async (failed) => {
        const calls = [];
        let initialize;
        runBridge({
            window: { sessionStorage: storage },
            document: { querySelector: () => null, querySelectorAll: () => [], addEventListener: () => {} },
            console, Promise, URL, setTimeout,
            require: (_deps, callback) => callback({
                initialize: async () => {},
                track: async (action, payload) => { calls.push([action, payload]); return { failed: action === 'checkout.started' && failed }; }
            }),
            define: (_deps, factory) => { initialize = factory(); }
        }, null, { checkout_reference: 'magento:store-one:checkout:17', items: [item] });
        initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3200/v1', checkoutStarted: true });
        await new Promise(setImmediate);
        return calls.filter(([action]) => action === 'checkout.started');
    };

    assert.equal((await run(true)).length, 1);
    assert.equal((await run(false)).length, 1);
    assert.equal((await run(false)).length, 0);
    assert.equal(values[`hellotext-magento-checkout:${anonymousSession}:magento:store-one:checkout:17`], '1');
});

test('does not report checkout started for an empty cart', async () => {
    const calls = [];
    let initialize;
    runBridge({
        window: {},
        document: { querySelector: () => null, querySelectorAll: () => [], addEventListener: () => {} },
        console, Promise, URL, setTimeout,
        require: (_deps, callback) => callback({
            initialize: async () => {},
            track: async (action) => { calls.push(action); return { failed: false }; }
        }),
        define: (_deps, factory) => { initialize = factory(); }
    }, null, { checkout_reference: 'magento:store-one:checkout:17', items: [] });
    initialize({ businessId: 'public-business', apiRoot: 'http://api.lvh.me:3200/v1', checkoutStarted: true });
    await new Promise(setImmediate);
    assert.equal(calls.includes('checkout.started'), false);
});
