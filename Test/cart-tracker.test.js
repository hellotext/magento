const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

let createTracker;
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../view/frontend/web/js/cart-tracker.js'), 'utf8'), {
    define: (_deps, factory) => { createTracker = factory(); },
    console: { warn() {} }, Promise
});
const item = (id, quantity = 1) => ({
    quantity, price: { amount: '24.99', currency: 'USD' },
    product: { reference: `magento:store-one:product:${id}`, sku: `SKU-${id}`, name: `Product ${id}`, source: 'magento' }
});
const plain = (value) => JSON.parse(JSON.stringify(value));

test('initial cart is viewed, absolute increases added, decreases viewed, removals explicit', async () => {
    const events = [];
    const track = createTracker({ track: async (action, payload) => {
        events.push([action, plain(payload)]); return { failed: false };
    } }, () => true);
    await track([item(1)]);
    await track([item(1)]);
    await track([item(1, 3), item(2)]);
    await track([item(1, 2)]);
    await track([]);
    await track([]);
    await track([item(1)]);
    assert.deepEqual(events.map(([action, params]) => [action, params.object_parameters.items.map((row) => row.quantity)]), [
        ['cart.viewed', [1]], ['cart.added', [3, 1]], ['cart.removed', [1]],
        ['cart.viewed', [2]], ['cart.removed', [2]], ['cart.added', [1]]
    ]);
    assert.ok(events.every(([, params]) => params.object_parameters.source === 'magento'));
    assert.ok(events.every(([, params]) => !('reference' in params.object_parameters)));
});

test('empty initial snapshots do not create empty carts', async () => {
    const events = [];
    const track = createTracker({ track: async (action) => { events.push(action); return { failed: false }; } }, () => true);
    await track([]);
    await track([item(1)]);
    assert.deepEqual(events, ['cart.added']);
});

test('price-only changes update the cart without claiming a new addition', async () => {
    const events = [];
    const track = createTracker({ track: async (action, params) => {
        events.push([action, params.object_parameters.items[0].price.amount]); return { failed: false };
    } }, () => true);
    await track([item(1)]);
    await track([{ ...item(1), price: { amount: '19.99', currency: 'USD' } }]);
    await track([{ ...item(1), price: { amount: '19.9900', currency: 'USD' } }]);
    await track([{ ...item(1), price: { amount: '29.99', currency: 'USD' } }]);
    assert.deepEqual(events, [['cart.viewed', '24.99'], ['cart.viewed', '19.99'], ['cart.viewed', '29.99']]);
});

test('serializes rapid snapshots without incrementing or dropping the final quantity', async () => {
    const quantities = [];
    let inFlight = 0;
    const track = createTracker({ track: async (_action, payload) => {
        assert.equal(++inFlight, 1);
        await new Promise(setImmediate);
        quantities.push(payload.object_parameters.items[0].quantity);
        inFlight--;
        return { failed: false };
    } }, () => true);
    await Promise.all([track([]), track([item(1)]), track([item(1, 4)]), track([item(1, 2)])]);
    assert.deepEqual(quantities, [1, 4, 2]);
});

for (const fail of [() => ({ failed: true }), () => { throw new Error('offline'); }]) {
    test('retains accepted baseline after network/rejection failure for a fresh retry', async () => {
        const events = [];
        const track = createTracker({ track: async (action, params) => {
            events.push([action, plain(params)]);
            if (events.length === 1) { return fail(); }
            return { failed: false };
        } }, () => true);
        await track([]);
        await track([item(1)]);
        await track([item(1)]);
        await track([item(1)]);
        assert.equal(events.length, 2);
        assert.deepEqual(events[0], events[1]);
    });
}

test('does not replay an accepted event after a later event fails', async () => {
    const actions = [];
    let attempts = 0;
    const tracker = createTracker({ track: async (action) => {
        actions.push(action);
        attempts++;
        return attempts === 3 ? { failed: true } : { failed: false };
    } }, () => true);

    await tracker([item(1)]);
    await tracker([item(2, 2)]);
    await tracker([item(2, 2)]);

    assert.deepEqual(actions, ['cart.viewed', 'cart.removed', 'cart.added', 'cart.added']);
});

test('does not send queued events after an authentication/session boundary', async () => {
    let active = true;
    const events = [];
    const track = createTracker({ track: async (action) => {
        events.push(action); active = false; return { failed: false };
    } }, () => active);
    await Promise.all([track([item(1)]), track([item(1, 2)])]);
    assert.deepEqual(events, ['cart.viewed']);
});

test('restores the accepted per-tab baseline after navigation and removes the last item', async () => {
    const values = new Map();
    const storage = { getItem: (key) => values.get(key) || null, setItem: (key, value) => values.set(key, value) };
    const actions = [];
    const sdk = { track: async (action) => { actions.push(action); return { failed: false }; } };
    await createTracker(sdk, () => true, storage, 'session-one')([item(1)]);
    await createTracker(sdk, () => true, storage, 'session-one')([]);
    await createTracker(sdk, () => true, storage, 'session-one')([]);
    await createTracker(sdk, () => true, storage, 'session-two')([]);
    assert.deepEqual(actions, ['cart.viewed', 'cart.removed']);
});

test('rejects malformed or duplicate items without throwing or sending partial carts', async () => {
    const actions = [];
    const tracker = createTracker({ track: async (action) => { actions.push(action); return { failed: false }; } }, () => true);
    for (const invalid of [[{}], [item(1), item(1)], [{ ...item(1), quantity: 1.5 }], [{ ...item(1), price: { amount: 'NaN', currency: 'USD' } }]]) {
        assert.equal(createTracker.validItems(invalid), false);
        await tracker(invalid);
    }
    assert.deepEqual(actions, []);
});

test('corrupt or blocked optional browser storage does not break native cart tracking', async () => {
    for (const storage of [
        { getItem: () => '{broken', setItem() { throw new Error('quota'); } },
        { getItem: () => '[{}]', setItem() {} }
    ]) {
        const actions = [];
        const tracker = createTracker({ track: async (action) => { actions.push(action); return { failed: false }; } }, () => true, storage, 'key');
        await tracker([item(1)]);
        assert.deepEqual(actions, ['cart.viewed']);
    }
});
