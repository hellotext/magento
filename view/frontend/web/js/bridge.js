define(['Magento_Customer/js/customer-data', 'Hellotext_Tracking/js/cart-tracker'], function (customerData, createCartTracker) {
    'use strict';

    var sdkUrl = 'https://cdn.jsdelivr.net/npm/@hellotext/hellotext@2/dist/hellotext.min.js';

    return function (config) {
        if (!hasSafeConfig(config) || window.hellotextMagentoStarted) {
            return;
        }
        window.hellotextMagentoStarted = true;
        var stopped = false;
        var session;
        var sdk;
        var refresh;
        var cartTracker;
        var refreshProductTracking;

        function stop() {
            stopped = true;
        }

        function cookieValue(name) {
            var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
            if (!match) { return null; }
            try { return decodeURIComponent(match[1]); } catch (error) { return null; }
        }

        // Only the signed-in customer's ID: Hellotext verifies it against the store and reads the profile there.
        function validIdentity(identity) {
            return identity === null || Boolean(identity
                && typeof identity === 'object'
                && Object.keys(identity).length === 1
                && /^[1-9]\d*$/.test(identity.customer_id));
        }

        function safeSection(sections) {
            var data = sections && sections.cart && sections.cart.hellotext;
            if (stopped || !data || data.enabled !== true
                || !data.context || !['businessId', 'apiRoot', 'installationId', 'storeId', 'currency'].every(function (key) {
                    return typeof data.context[key] === 'string' && data.context[key] === config[key];
                })
                || typeof data.cart_ready !== 'boolean'
                || !createCartTracker.validItems(data.items)
                || !data.items.every(function (item) {
                    return matchesProductContext(item.product) && item.price.currency === config.currency;
                })
                || !validIdentity(data.identity)
                || !(data.checkout_reference == null || (typeof data.checkout_reference === 'string'
                    && new RegExp('^magento:' + config.installationId + ':checkout:[1-9]\\d*$').test(data.checkout_reference)))) {
                stop();
                return null;
            }
            return data;
        }

        function matchesProductContext(product) {
            return product && product.source === 'magento' && typeof product.reference === 'string'
                && product.reference.startsWith('magento:' + config.installationId + ':product:');
        }

        function hasExpectedSessionCookie(expected) {
            var cookie = document.cookie.match(/(?:^|;\s*)hello_session=([^;]*)/);
            return !cookie || cookie[1] === expected;
        }

        function isActive() {
            if (sdk && (sdk.session !== session || !hasExpectedSessionCookie(session))) { stop(); }
            return !stopped && sdk && sdk.session === session;
        }

        function initializeSession(Hellotext) {
            return Promise.resolve(Hellotext.initialize(config.businessId, {
                apiRoot: config.apiRoot,
                // Web push needs a service worker served from the store's own origin.
                push: false
            })).then(function () { session = Hellotext.session; });
        }

        function synchronizeSession(Hellotext) {
            if (sdk.session === session && hasExpectedSessionCookie(session)) {
                return Promise.resolve(false);
            }
            var previousSession = session;
            return initializeSession(Hellotext).then(function () {
                if (!isActive()) { throw new Error('Magento session could not be synchronized'); }
                if (previousSession !== session && cartTracker) {
                    var storage;
                    try { storage = window.sessionStorage; } catch (error) { /* Optional storage may be disabled. */ }
                    cartTracker = createCartTracker(Hellotext, isActive, storage,
                        'hellotext-magento-cart:' + config.businessId + ':' + session);
                }
                return previousSession !== session;
            });
        }

        function readCurrentCart() {
            if (stopped) { return Promise.resolve(null); }
            if (refresh) { return refresh; }
            // Do not trust Magento's localStorage snapshot as current authentication state.
            refresh = Promise.resolve().then(function () {
                return customerData.reload(['cart'], true);
            }).then(function (sections) {
                return safeSection(sections);
            }).catch(function () {
                stop();
                console.warn('Hellotext Magento: private cart unavailable; tracking stopped');
                return null;
            }).finally(function () { refresh = null; });
            return refresh;
        }

        function hasSafeConfig(value) {
            return Boolean(value && typeof value.businessId === 'string' && value.businessId.trim())
                && /^[a-z0-9][a-z0-9_-]{2,63}$/.test(value.installationId)
                && /^[1-9]\d*$/.test(value.storeId)
                && /^[A-Z]{3}$/.test(value.currency)
                && [
                    'http://api.lvh.me:3100/v1',
                    'http://api.lvh.me:3100/v1/',
                    'http://api.lvh.me:3200/v1',
                    'http://api.lvh.me:3200/v1/',
                    'https://api.hellotext.com/v1',
                    'https://api.hellotext.com/v1/'
                ].indexOf(value.apiRoot) !== -1;
        }

        function readJson(element, attribute, fallback) {
            try {
                return JSON.parse(element.getAttribute(attribute));
            } catch (error) {
                console.warn('Hellotext Magento: invalid product data');
                return fallback;
            }
        }

        // The success page exposes only the canonical order reference: Hellotext reads totals, lines and
        // buyer from the store, so nothing about the order is trusted from the browser.
        function trackPlacedOrder(Hellotext) {
            var element = document.querySelector('[data-hellotext-order]');
            var reference = element && typeof element.getAttribute === 'function' && element.getAttribute('data-hellotext-order');
            var prefix = 'magento:' + config.installationId + ':order:';
            if (typeof reference !== 'string' || reference.indexOf(prefix) !== 0 || !/^[1-9]\d*$/.test(reference.slice(prefix.length))) {
                return false;
            }
            var storage;
            var key = 'hellotext-magento-order:' + reference;
            try { storage = window.sessionStorage; if (storage && storage.getItem(key)) { return true; } } catch (error) { storage = null; }
            Promise.resolve(Hellotext.track('order.placed', { object_parameters: { reference: reference, source: 'magento' } }))
                .then(function (response) {
                    if (response && response.failed) { console.warn('Hellotext Magento: order event rejected'); return; }
                    try { if (storage) { storage.setItem(key, '1'); } } catch (error) { /* Optional storage may be disabled. */ }
                })
                .catch(function () { console.warn('Hellotext Magento: order event failed'); });
            return true;
        }

        function trackCheckoutStarted(Hellotext, data) {
            if (!config.checkoutStarted || !data.cart_ready || !data.items.length || !data.checkout_reference) {
                return false;
            }
            var storage;
            var key = 'hellotext-magento-checkout:' + session + ':' + data.checkout_reference;
            try { storage = window.sessionStorage; if (storage && storage.getItem(key)) { return true; } } catch (error) { storage = null; }
            Promise.resolve(Hellotext.track('checkout.started', {
                object_parameters: {
                    reference: data.checkout_reference,
                    source: 'magento',
                    items: data.items
                }
            })).then(function (response) {
                if (response && response.failed) { console.warn('Hellotext Magento: checkout event rejected'); return; }
                try { if (storage) { storage.setItem(key, '1'); } } catch (error) { /* Optional storage may be disabled. */ }
            }).catch(function () { console.warn('Hellotext Magento: checkout event failed'); });
            return true;
        }

        function start(Hellotext) {
            var trackedReferences = {};

            if (stopped) { return; }
            sdk = Hellotext;

            var ready = Promise.resolve().then(function () {
                if (stopped) { return; }
                return initializeSession(Hellotext);
            });

            ready.then(readCurrentCart).then(function (current) {
                if (!current) { return; }
                return synchronizeSession(Hellotext).then(function () { return current; });
            }).then(function (current) {
                if (!current) { return; }
                return identifyCurrent(Hellotext, current).then(function () { return current; });
            }).then(function (current) {
                if (!current || !isActive()) { return; }
                // The SDK does not automatically emit page.viewed.
                Promise.resolve().then(function () {
                    if (isActive()) {
                        return Hellotext.track('page.viewed', { object_parameters: { source: 'magento' } });
                    }
                }).then(function (response) {
                    if (response && response.failed) { console.warn('Hellotext Magento: page event rejected'); }
                }).catch(function () { console.warn('Hellotext Magento: page event failed'); });
                var storage;
                try { storage = window.sessionStorage; } catch (error) { /* Optional storage may be disabled. */ }
                if (trackPlacedOrder(Hellotext)) {
                    // The purchased quote is gone: forget its baseline instead of reporting the lines as removed.
                    try { if (storage) { storage.removeItem('hellotext-magento-cart:' + config.businessId + ':' + session); } } catch (error) { /* Optional storage may be disabled. */ }
                    return;
                }
                trackCheckoutStarted(Hellotext, current);
                cartTracker = createCartTracker(Hellotext, isActive, storage, 'hellotext-magento-cart:' + config.businessId + ':' + session);
                if (current.cart_ready) { cartTracker(current.items); }
                customerData.get('cart').subscribe(function () {
                    if (!refresh) {
                        readCurrentCart().then(function (data) {
                            if (!data) { return; }
                            var sessionChanged;
                            return synchronizeSession(Hellotext).then(function (changed) {
                                sessionChanged = changed;
                                return identifyCurrent(Hellotext, data);
                            }).then(function () {
                                if (sessionChanged && isActive()) {
                                    return Hellotext.track('page.viewed', { object_parameters: { source: 'magento' } });
                                }
                            }).then(function () {
                                if (isActive() && data.cart_ready) { cartTracker(data.items); }
                                if (sessionChanged && refreshProductTracking) { refreshProductTracking(); }
                            }).catch(function () {
                                console.warn('Hellotext Magento: private cart refresh failed');
                            });
                        });
                    }
                });
                var product = document.querySelector('[data-hellotext-product]');
                var parent;
                var variants;

                if (!product) {
                    return;
                }

                parent = readJson(product, 'data-hellotext-product', null);
                variants = readJson(product, 'data-hellotext-variants', {});
                if (!parent) {
                    return;
                }

                function trackCurrentProduct() {
                    var inputs = document.querySelectorAll('[name^="super_attribute["]');
                    var selection = [];
                    var selected;
                    var payload;

                    Array.prototype.forEach.call(inputs, function (input) {
                        var attribute = input.name.match(/^super_attribute\[(\d+)]$/);
                        if (attribute && input.value) {
                            selection.push(attribute[1] + ':' + input.value);
                        }
                    });
                    selection.sort();
                    selected = inputs.length && selection.length === inputs.length
                        ? variants[selection.join('|')]
                        : null;
                    payload = selected || parent;

                    if (!matchesProductContext(payload)) {
                        stop();
                        return;
                    }

                    var trackingKey = session + ':' + payload.reference;
                    if (trackedReferences[trackingKey]) {
                        return;
                    }
                    trackedReferences[trackingKey] = true;

                    return Promise.resolve().then(function () {
                        return readCurrentCart();
                    }).then(function (data) {
                        if (!data) { return; }
                        var sessionChanged;
                        return synchronizeSession(Hellotext).then(function (changed) {
                            sessionChanged = changed;
                            return identifyCurrent(Hellotext, data);
                        }).then(function () {
                            if (!isActive()) { return; }
                            var trackProduct = function () {
                                if (!isActive()) { return; }
                                return Hellotext.track('product.viewed', {
                                    url: new URL(window.location.href).origin + new URL(window.location.href).pathname,
                                    object_parameters: payload
                                });
                            };
                            if (sessionChanged) {
                                return Hellotext.track('page.viewed', { object_parameters: { source: 'magento' } })
                                    .then(trackProduct);
                            }
                            return trackProduct();
                        });
                    }).then(function (response) {
                        if (response && response.failed) {
                            delete trackedReferences[trackingKey];
                            console.warn('Hellotext Magento: product event rejected');
                        }
                    }).catch(function (error) {
                        delete trackedReferences[trackingKey];
                        console.warn('Hellotext Magento event failed:', error);
                    });
                }

                refreshProductTracking = trackCurrentProduct;

                function scheduleProductTracking() {
                    setTimeout(trackCurrentProduct, 0);
                }

                Array.prototype.forEach.call(
                    document.querySelectorAll('[name^="super_attribute["]'),
                    function (input) { input.addEventListener('change', scheduleProductTracking); }
                );
                document.addEventListener('change', function () {
                    scheduleProductTracking();
                });
                trackCurrentProduct();
            }).catch(function (error) {
                window.hellotextMagentoStarted = false;
                console.warn('Hellotext Magento failed:', error);
            });
        }

        function identifyCurrent(Hellotext, current) {
            var identity = current.identity;
            var currentId = cookieValue('hello_user_id');
            var currentSource = cookieValue('hello_user_source');
            if (currentId && currentSource === 'magento'
                && (!identity || String(identity.customer_id) !== currentId)) {
                Hellotext.forget();
            }
            if (!identity) { return Promise.resolve(); }
            return Promise.resolve(Hellotext.identify(identity.customer_id, { source: 'magento' })).then(function (response) {
                if (response && response.failed) { console.warn('Hellotext Magento: customer identification rejected'); }
            }).catch(function () { console.warn('Hellotext Magento: customer identification failed'); });
        }

        Promise.resolve(customerData.getInitCustomerData()).then(readCurrentCart).then(function (data) {
            if (!data) { return; }
            if (window.Hellotext) {
                start(window.Hellotext);
            } else {
                require([sdkUrl], function (Hellotext) { start(Hellotext); }, function () {
                    window.hellotextMagentoStarted = false;
                    console.warn('Hellotext Magento could not load the SDK');
                });
            }
        });
    };
});
