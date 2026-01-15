(function($){
    'use strict';

    console.log('wpgb-facet-logger: script loaded');

    function getWpgbInstances(){
        var wpgb = window.WP_Grid_Builder;
        console.log('wpgb-facet-logger: checking WP_Grid_Builder presence', !!wpgb);
        if (!wpgb) return null;
        if (Array.isArray(wpgb.instances)) return wpgb.instances;
        if (typeof wpgb.getInstances === 'function') return wpgb.getInstances();
        if (typeof wpgb.instances === 'object') {
            var out = [];
            for (var k in wpgb.instances) {
                if (wpgb.instances.hasOwnProperty(k)) out.push(wpgb.instances[k]);
            }
            return out;
        }
        return null;
    }

    function collectViaAPI(target){
        console.log('wpgb-facet-logger: collectViaAPI target=', target);
        var instances = getWpgbInstances();
        console.log('wpgb-facet-logger: instances found', instances && instances.length ? instances.length : 0);
        if (!instances) return null;
        var output = [];
        instances.forEach(function(inst){
            try {
                // If target is provided, try to match container or grid slug
                if (target) {
                    // if target matches container selector
                    try{
                        var container = document.querySelector(target);
                        if (container && !container.contains(inst.container)) return; // skip this instance
                    }catch(e){}
                }

                var facets = inst.facets;
                if (!facets) return;

                // Try to get all params at once
                var params = null;
                if (typeof facets.getParams === 'function') {
                    try {
                        params = facets.getParams();
                    } catch (e) {
                        params = null;
                    }
                }

                // Fallback: if no params returned, try to enumerate facet slugs
                if (!params && typeof facets.getFacet === 'function') {
                    params = {};
                    // best-effort attempt to read internal list
                    try{
                        if (Array.isArray(facets._facets)){
                            facets._facets.forEach(function(f){
                                try{ params[f.slug] = facets.getParams(f.slug) || []; }catch(e){}
                            });
                        }
                    }catch(e){}
                }

                if (params) {
                    output.push({
                        grid: inst.id || inst.name || null,
                        facets: params
                    });
                }

            } catch (e) {
                // ignore instance errors
            }
        });

        return output.length ? output : null;
    }

    function collectViaDOM(target){
        console.log('wpgb-facet-logger: collectViaDOM target=', target);
        var scope = document;
        if (target) {
            try { var el = document.querySelector(target); if (el) scope = el; } catch(e){}
        }
        var facets = scope.querySelectorAll('.wpgb-facet');
        var result = {};
        Array.prototype.forEach.call(facets, function(f){
            var slug = f.getAttribute('data-slug') || f.dataset.slug || f.getAttribute('data-wpgb-facet') || f.getAttribute('data-name') || null;
            if (!slug) {
                // try to derive from inputs
                var inp = f.querySelector('input[name]');
                if (inp) {
                    slug = inp.name.replace(/\[.*\]$/, '');
                }
            }
            if (!slug) return;

            var values = [];
            // checked inputs
            var checked = f.querySelectorAll('input:checked');
            if (checked && checked.length) {
                Array.prototype.forEach.call(checked, function(ci){ values.push(ci.value); });
            } else {
                // hidden inputs or active buttons
                var hidden = f.querySelectorAll('input[type="hidden"][value]');
                if (hidden && hidden.length) {
                    Array.prototype.forEach.call(hidden, function(h){ if (h.value) values.push(h.value); });
                } else {
                    var active = f.querySelectorAll('.active, .is-active');
                    if (active && active.length) {
                        Array.prototype.forEach.call(active, function(a){ values.push(a.getAttribute('data-value') || a.textContent.trim()); });
                    }
                }
            }

            result[slug] = values;
        });

        // if no .wpgb-facet elements found, try generic inputs inside grid
        if (!Object.keys(result).length) {
            var inputs = scope.querySelectorAll('[data-wpgb-facet], [data-facet]');
            Array.prototype.forEach.call(inputs, function(i){
                var slug = i.getAttribute('data-wpgb-facet') || i.getAttribute('data-facet') || i.name || null;
                if (!slug) return;
                var val = i.value || null;
                if (val) {
                    if (!result[slug]) result[slug] = [];
                    result[slug].push(val);
                }
            });
        }

        return Object.keys(result).length ? [{ grid: null, facets: result }] : null;
    }

    function collectAll(target, useApiPref) {
        var useApi = (typeof useApiPref !== 'undefined') ? !!useApiPref : false;
        var apiData = null;
        console.log('wpgb-facet-logger: collectAll useApi=', useApi);
        if ( useApi && window.WP_Grid_Builder ) {
            apiData = collectViaAPI(target);
            console.log('wpgb-facet-logger: apiData', apiData);
        }
        if (!apiData) {
            var domData = collectViaDOM(target) || [];
            console.log('wpgb-facet-logger: domData', domData);
            return domData;
        }
        return apiData;
    }

    $(document).on('click', '.acf-wpgb-facet-logger', function(e){
        e.preventDefault();
        var $btn = $(this);
        var target = $btn.attr('data-target') || '';
        var useApiAttr = $btn.attr('data-use-api');
        var useApi = (typeof useApiAttr !== 'undefined') ? (useApiAttr === '1' || useApiAttr === 'true') : (window.acfWpgbLogger && window.acfWpgbLogger.use_api_default);

        console.log('wpgb-facet-logger: button clicked', { target: target, useApi: useApi });
        var collected = collectAll(target, useApi);

            // Apply mapping if provided by PHP and print compact rows
            var map = window.acfWpgbFacetMap || {};
            try {
                if (Array.isArray(collected)) {
                    collected.forEach(function(item){
                        var gridId = item.grid || '(no-grid)';
                        var facets = item.facets || {};
                        Object.keys(facets).forEach(function(slug){
                            var acfField = (map && map[slug]) ? map[slug] : '(no mapping)';
                            var values = facets[slug] || [];
                            if (values && values.length) {
                                values.forEach(function(v){
                                    console.log('grid:', gridId, '| facet:', slug, '| acf:', acfField, '| value:', v);
                                });
                            } else {
                                console.log('grid:', gridId, '| facet:', slug, '| acf:', acfField, '| value:', '(none)');
                            }
                        });
                    });
                } else {
                    console.log('wpgb-facet-logger: no facet data found');
                }
            } catch (e) {
                console.error('wpgb-facet-logger: print error', e);
            }
    });

})(jQuery);
