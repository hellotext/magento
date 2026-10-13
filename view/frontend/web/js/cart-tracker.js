define([], function () {
    'use strict';

    function validItems(items) {
        var references = Object.create(null);
        return Array.isArray(items) && items.every(function (item) {
            var product = item && item.product;
            if (!product || product.source !== 'magento'
                || !/^magento:[a-z0-9][a-z0-9_-]{2,63}:product:[1-9]\d*$/.test(product.reference)
                || typeof product.name !== 'string' || !product.name.trim()
                || typeof product.sku !== 'string' || !product.sku.trim()
                || !Number.isInteger(item.quantity) || item.quantity < 1 || item.quantity > 2147483647
                || !item.price || typeof item.price.amount !== 'string'
                || !/^\d+(?:\.\d+)?$/.test(item.price.amount) || !Number.isFinite(Number(item.price.amount))
                || !/^[A-Z]{3}$/.test(item.price.currency) || references[product.reference]) { return false; }
            references[product.reference] = true;
            return true;
        });
    }

    // CartService uses absolute quantities; cart.removed deletes entire product lines.
    function createTracker(sdk, isActive, storage, key) {
        var previous = null;
        var queue = Promise.resolve();
        if (storage && key) {
            try {
                var saved = JSON.parse(storage.getItem(key));
                if (validItems(saved)) { previous = saved; }
            } catch (error) {
                console.warn('Hellotext Magento: cart baseline unavailable');
            }
        }

        function byReference(items) {
            var result = Object.create(null);
            items.forEach(function (item) { result[item.product.reference] = item; });
            return result;
        }

        function persistBaseline() {
            if (storage && key) {
                try { storage.setItem(key, JSON.stringify(previous)); }
                catch (error) { console.warn('Hellotext Magento: cart baseline could not be saved'); }
            }
        }

        function acceptEvent(action, items) {
            var accepted = byReference(previous || []);
            items.forEach(function (item) {
                if (action === 'cart.removed') { delete accepted[item.product.reference]; }
                else { accepted[item.product.reference] = item; }
            });
            previous = Object.keys(accepted).map(function (reference) { return accepted[reference]; });
            persistBaseline();
        }

        return function (items) {
            if (!validItems(items)) { return Promise.resolve(); }
            var snapshot = JSON.parse(JSON.stringify(items));
            queue = queue.then(async function () {
                if (!isActive()) { return; }
                var before = byReference(previous || []);
                var after = byReference(snapshot);
                var events = [];
                if (previous === null) {
                    if (snapshot.length) { events.push(['cart.viewed', snapshot]); }
                } else {
                    events.push(['cart.removed', previous.filter(function (item) { return !after[item.product.reference]; })]);
                    events.push(['cart.added', snapshot.filter(function (item) {
                        var old = before[item.product.reference];
                        return !old || item.quantity > old.quantity;
                    })]);
                    events.push(['cart.viewed', snapshot.filter(function (item) {
                        var old = before[item.product.reference];
                        return old && (item.quantity < old.quantity
                            || (item.quantity === old.quantity && (Number(item.price.amount) !== Number(old.price.amount)
                                || item.price.currency !== old.price.currency)));
                    })]);
                }
                for (var event of events) {
                    if (!event[1].length) { continue; }
                    if (!isActive()) { return; }
                    var response = await sdk.track(event[0], {
                        object_parameters: { source: 'magento', items: event[1] }
                    });
                    if (!response || response.failed) { throw new Error('Cart event was not accepted'); }
                    acceptEvent(event[0], event[1]);
                }
                if (previous === null && snapshot.length === 0) {
                    previous = snapshot;
                    persistBaseline();
                }
            }).catch(function () {
                // Keep the last fully accepted event baseline; a later refresh retries only pending changes.
                console.warn('Hellotext Magento: cart update failed; awaiting a fresh snapshot');
            });
            return queue;
        };
    }
    createTracker.validItems = validItems;
    return createTracker;
});
