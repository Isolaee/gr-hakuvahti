(function($){
    'use strict';

    console.log('wpgb-facet-logger: script loaded');
    console.log('wpgb-facet-logger: localized mapping (acfWpgbFacetMap)=', window.acfWpgbFacetMap || null);

    function getWpgbInstances(){
        var wpgb = window.WP_Grid_Builder;
        console.log('wpgb-facet-logger: checking WP_Grid_Builder presence', !!wpgb);
        if (!wpgb) return null;
        try {
            console.log('wpgb-facet-logger: WP_Grid_Builder info', {
                hasInstances: !!wpgb.instances,
                getInstancesFunc: typeof wpgb.getInstances === 'function'
            });
        } catch (e) { console.error('wpgb-facet-logger: WP_Grid_Builder introspect error', e && e.stack); }
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
                console.log('wpgb-facet-logger: API instance', {
                    id: inst.id || inst.name || null,
                    container: !!inst.container,
                    facetsObj: !!inst.facets
                });
                // If target is provided, try to match container or grid slug
                if (target) {
                    // if target matches container selector
                    try{
                        var container = document.querySelector(target);
                        if (container && !container.contains(inst.container)) return; // skip this instance
                    }catch(e){}
                }

                var facets = inst.facets;
                console.log('wpgb-facet-logger: instance.facets keys', Object.keys(facets || {}));
                if (!facets) return;

                // Try to get all params at once
                var paramsRaw = null;
                if (typeof facets.getParams === 'function') {
                    try {
                        paramsRaw = facets.getParams();
                    } catch (e) {
                        paramsRaw = null;
                        console.error('wpgb-facet-logger: facets.getParams error', e && e.stack);
                    }
                }

                // Fallback: if no params returned, try to enumerate facet slugs
                if (!paramsRaw && typeof facets.getFacet === 'function') {
                    paramsRaw = {};
                    // best-effort attempt to read internal list
                    try{
                        if (Array.isArray(facets._facets)){
                            facets._facets.forEach(function(f){
                                try{ paramsRaw[f.slug] = facets.getParams(f.slug) || []; }catch(e){}
                            });
                        }
                    }catch(e){ console.error('wpgb-facet-logger: facets._facets read error', e && e.stack); }
                }

                // Normalize params into slug -> array
                var params = {};
                function normalizeFacetParams(raw){
                    var out = {};
                    if (!raw) return out;
                    if (Array.isArray(raw)){
                        // array of objects or values
                        raw.forEach(function(item){
                            if (!item || typeof item !== 'object') return;
                            Object.keys(item).forEach(function(k){
                                var v = item[k];
                                if (v == null) return;
                                if (Array.isArray(v)) out[k] = v;
                                else out[k] = [String(v)];
                            });
                        });
                        return out;
                    }
                    if (typeof raw === 'object'){
                        Object.keys(raw).forEach(function(k){
                            var v = raw[k];
                            if (v == null) { out[k] = []; return; }
                            if (Array.isArray(v)) { out[k] = v; return; }
                            if (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean') { out[k] = [String(v)]; return; }
                            if (typeof v === 'object'){
                                if (Array.isArray(v.values)) { out[k] = v.values; return; }
                                if (Array.isArray(v.selected)) { out[k] = v.selected; return; }
                                // flatten primitives inside object
                                var vals = [];
                                Object.keys(v).forEach(function(sub){
                                    var sv = v[sub];
                                    if (Array.isArray(sv)) vals = vals.concat(sv);
                                    else if (typeof sv === 'string' || typeof sv === 'number' || typeof sv === 'boolean') vals.push(String(sv));
                                });
                                out[k] = vals;
                                return;
                            }
                            out[k] = [];
                        });
                        return out;
                    }
                    return out;
                }

                try {
                    params = normalizeFacetParams(paramsRaw);
                } catch(e) { params = {}; console.error('wpgb-facet-logger: normalize error', e && e.stack); }

                if (params && Object.keys(params).length) {
                    output.push({ grid: inst.id || inst.name || null, facets: params });
                }

            } catch (e) {
                console.error('wpgb-facet-logger: instance loop error', e && e.stack);
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
        console.log('wpgb-facet-logger: DOM facets found count=', facets.length);
        var result = {};
        Array.prototype.forEach.call(facets, function(f){
            var slug = f.getAttribute('data-slug') || f.dataset.slug || f.getAttribute('data-wpgb-facet') || f.getAttribute('data-name') || null;
            // log facet element snapshot
            try { console.log('wpgb-facet-logger: facet element', { slugGuess: slug, dataset: f.dataset, htmlSnippet: (f.innerText||f.textContent||'').trim().slice(0,80) }); } catch(e) {}
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
            console.log('wpgb-facet-logger: fallback inputs found count=', inputs.length);
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
        console.log('wpgb-facet-logger: collectAll useApi=', useApi, 'WP_Grid_Builder present=', !!window.WP_Grid_Builder);
        if ( useApi && window.WP_Grid_Builder ) {
            apiData = collectViaAPI(target);
            console.log('wpgb-facet-logger: apiData count=', apiData ? apiData.length : 0);
        }
        if (!apiData) {
            var domData = collectViaDOM(target) || [];
            console.log('wpgb-facet-logger: domData count=', domData ? domData.length : 0);
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
        // snapshot mapping at click time
        try { console.log('wpgb-facet-logger: mapping at click', window.acfWpgbFacetMap || {}); } catch(e) {}
        var collected = collectAll(target, useApi);
        // Log mapping keys vs collected slugs for debugging
        try {
            var mapKeys = Object.keys(window.acfWpgbFacetMap || {});
            var collectedSlugs = [];
            if (Array.isArray(collected)) {
                collected.forEach(function(item){
                    var facets = item && item.facets ? item.facets : {};
                    Object.keys(facets).forEach(function(s){ if (collectedSlugs.indexOf(s) === -1) collectedSlugs.push(s); });
                });
            }
            console.log('wpgb-facet-logger: mapping keys=', mapKeys);
            console.log('wpgb-facet-logger: collected slugs=', collectedSlugs);
            var missing = collectedSlugs.filter(function(s){ return mapKeys.indexOf(s) === -1; });
            if (missing.length) console.log('wpgb-facet-logger: slugs without mapping=', missing);
        } catch (e) { console.error('wpgb-facet-logger: compare map/slug error', e && e.stack); }

            // Apply mapping if provided by PHP and print compact rows
            var map = window.acfWpgbFacetMap || {};
            try {
                if (!Array.isArray(collected) || !collected.length) {
                    console.log('wpgb-facet-logger: no facet data found');
                    return;
                }

                // Print a neat table per grid
                collected.forEach(function(item){
                    var gridId = item.grid || '(no-grid)';
                    var facets = item.facets || {};
                    var rows = [];
                    Object.keys(facets).forEach(function(slug){
                        var acfField = (map && map[slug]) ? map[slug] : '(no mapping)';
                        var values = facets[slug] || [];
                        if (values && values.length) {
                            values.forEach(function(v){ rows.push({ facet: slug, acf_field: acfField, value: v }); });
                        } else {
                            rows.push({ facet: slug, acf_field: acfField, value: '(none)' });
                        }
                    });

                        if (rows.length) {
                            // Build search criteria from mapped rows
                            var criteriaMap = {}; // field -> array of values
                            rows.forEach(function(r){
                                if (!r.acf_field || r.acf_field === '(no mapping)') return;
                                if (!criteriaMap[r.acf_field]) criteriaMap[r.acf_field] = [];
                                criteriaMap[r.acf_field].push(r.value);
                            });

                            console.groupCollapsed('wpgb-facet-logger: criteria build');
                            console.log('mapping used:', map);
                            console.log('initial criteriaMap:', criteriaMap);

                            // Helper: parse numeric-ish strings
                            function parseNumber(v){
                                if (v == null) return null;
                                if (typeof v === 'number') return v;
                                var s = String(v).trim();
                                // remove non digits except dot,comma,minus
                                var cleaned = s.replace(/[^0-9.,\-]/g, '');
                                if (cleaned === '') return null;
                                // normalize comma to dot
                                cleaned = cleaned.replace(/,/g, '.');
                                var n = parseFloat(cleaned);
                                return isNaN(n) ? null : n;
                            }

                            // Build criteria array for AJAX (supports array-of-objects input)
                            var criteriaArray = [];
                            Object.keys(criteriaMap).forEach(function(field){
                                var vals = criteriaMap[field] || [];
                                // clean values: trim and remove common placeholders
                                var valsClean = vals.map(function(v){ return v == null ? '' : String(v).trim(); });
                                valsClean = valsClean.filter(function(v){
                                    if (!v) return false;
                                    var lower = v.toLowerCase().trim();
                                    var placeholders = ['(none)', '(no mapping)', 'none', 'any', 'default', 'valitse', 'valitse...', '-- select --', '-- select field --', 'choose', 'choose one', 'not set'];
                                    if (placeholders.indexOf(lower) !== -1) return false;
                                    if (/^[-\s]*--.*--[-\s]*$/.test(v)) return false;
                                    if (/^[-]+$/.test(v)) return false;
                                    return true;
                                });
                                if (valsClean.length === 0) return; // skip fields with no usable values

                                // parse numbers where possible and also support single "min-max" string
                                var parsed = valsClean.map(function(v){ var n = parseNumber(v); return { raw: v, num: n }; });
                                var numericVals = parsed.map(function(p){ return p.num; }).filter(function(x){ return x !== null; });
                                console.log('field parse', field, parsed, 'numericVals=', numericVals);

                                // If a single value contains a range like "1000-5000" extract numbers
                                if (numericVals.length < 2 && valsClean.length === 1) {
                                    var rangeMatch = String(valsClean[0]).match(/(-?\d+[\.,]?\d*)\D+(-?\d+[\.,]?\d*)/);
                                    if (rangeMatch) {
                                        var n1 = parseNumber(rangeMatch[1]);
                                        var n2 = parseNumber(rangeMatch[2]);
                                        if (n1 !== null && n2 !== null) {
                                            numericVals = [n1, n2];
                                        }
                                    }
                                }

                                if (numericVals.length >= 2) {
                                    // Use min/max (order-agnostic)
                                    var min = Math.min.apply(null, numericVals);
                                    var max = Math.max.apply(null, numericVals);
                                    criteriaArray.push({ field: field + '_min', value: String(min) });
                                    criteriaArray.push({ field: field + '_max', value: String(max) });
                                } else if (numericVals.length === 1) {
                                    // Single numeric value -> exact match
                                    criteriaArray.push({ field: field, value: String(numericVals[0]) });
                                } else {
                                    // Non-numeric values: send each provided value as a separate criterion (OR semantics)
                                    valsClean.forEach(function(v){
                                        criteriaArray.push({ field: field, value: v });
                                    });
                                }
                            });

                            console.log('final criteriaArray:', criteriaArray);
                            console.groupEnd();

                            if (criteriaArray.length === 0) {
                                console.log('wpgb-facet-logger: no valid criteria to search; skipping AJAX');
                            }

                            console.group('wpgb-facet-logger — Grid: ' + gridId + ' (' + rows.length + ' rows)');
                            console.table(rows);

                            if (criteriaArray.length > 0) {
                                if (typeof window.acfWpgbLogger !== 'undefined' && window.acfWpgbLogger.ajaxUrl) {
                                    console.log('wpgb-facet-logger: sending search AJAX', { criteria: criteriaArray });
                                    $.post(window.acfWpgbLogger.ajaxUrl, {
                                        action: 'acf_popup_search',
                                        nonce: window.acfWpgbLogger.nonce || window.acfWpgbLogger.nonce,
                                        category: '',
                                        criteria: criteriaArray,
                                        match_logic: 'AND',
                                        debug: '1'
                                    }).done(function(resp){
                                        if (resp && resp.success) {
                                            console.log('wpgb-facet-logger: search result', resp.data);
                                            if (resp.data && resp.data.posts) {
                                                console.group('wpgb-facet-logger: Matched posts (' + (resp.data.total||0) + ')');
                                                console.table(resp.data.posts);
                                                console.groupEnd();
                                            }
                                        } else {
                                            console.warn('wpgb-facet-logger: search returned error', resp && resp.data);
                                        }
                                    }).fail(function(xhr){
                                        console.error('wpgb-facet-logger: AJAX search failed', xhr && xhr.responseText);
                                    });
                                } else {
                                    console.warn('wpgb-facet-logger: ajaxUrl not available; cannot perform search');
                                }
                            } else {
                                console.log('wpgb-facet-logger: skipping AJAX because no criteria');
                            }

                            console.groupEnd();
                        } else {
                            console.log('wpgb-facet-logger — Grid: ' + gridId + ' (no facets)');
                        }
                });

            } catch (e) {
                console.error('wpgb-facet-logger: print error', e && e.stack ? e.stack : e);
            }
    });

})(jQuery);
