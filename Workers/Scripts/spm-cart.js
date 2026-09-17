/**
 * ShopiMind — cart sync for Thelia 2.x.
 *
 * Reads cart data from the module's JSON endpoint (bypassing any FPC) and
 * POSTs it as JSON to the ShopiMind /cs route, enriched with spmWorkflow.*
 * identifiers when available. Triggers on DOM ready; also listens to
 * Thelia standard cart-update ajax events when jQuery is present.
 *
 * Requires window.SpmCartConfig = { dataUrl, csUrl } to be set before this
 * script loads.
 *
 * ES5 only.
 */
(function() {
    var config = window.SpmCartConfig;
    if (!config || !config.dataUrl || !config.csUrl) {
        return;
    }

    function spmDomReady(fn) {
        if (document.readyState === 'loading') {
            if (document.addEventListener) {
                document.addEventListener('DOMContentLoaded', fn);
            } else {
                document.attachEvent('onreadystatechange', function() {
                    if (document.readyState === 'complete') { fn(); }
                });
            }
        } else {
            fn();
        }
    }

    function spmFetchAndSendCart() {
        var fetchXhr = new XMLHttpRequest();
        fetchXhr.open('GET', config.dataUrl, true);
        fetchXhr.withCredentials = true;
        fetchXhr.onreadystatechange = function() {
            if (fetchXhr.readyState !== 4 || fetchXhr.status !== 200) return;

            var payload;
            try {
                payload = JSON.parse(fetchXhr.responseText);
            } catch (e) {
                return;
            }

            if (!payload || !payload.cart || !payload.cart.id_cart) {
                return;
            }

            var sw = window.spmWorkflow || {};
            if (typeof sw.spmVisitorId !== 'undefined') {
                payload.spm_visitor_id = sw.spmVisitorId;
            }
            if (typeof sw.spmIdShopCustomer !== 'undefined') {
                payload.spm_customer_id = sw.spmIdShopCustomer;
            }
            if (typeof sw.spmVisitorSessionId !== 'undefined') {
                payload.spm_visitor_session_id = sw.spmVisitorSessionId;
            }

            payload.url = window.location.href;

            var sendXhr = new XMLHttpRequest();
            sendXhr.open('POST', config.csUrl, true);
            sendXhr.withCredentials = true;
            sendXhr.setRequestHeader('Content-Type', 'application/json');
            sendXhr.send(JSON.stringify(payload));
        };
        fetchXhr.send();
    }

    spmDomReady(function() {
        spmFetchAndSendCart();

        // Thelia front-end fires ajax completions on cart pages; reuse jQuery
        // if available to rerun the sync after cart mutations.
        if (typeof window.jQuery === 'function') {
            window.jQuery(document).ajaxComplete(function(event, xhr, settings) {
                try {
                    var url = settings && settings.url ? settings.url : '';
                    if (url && /\/cart(\/|$|\?)/.test(url)) {
                        spmFetchAndSendCart();
                    }
                } catch (e) { /* noop */ }
            });
        }
    });
})();
