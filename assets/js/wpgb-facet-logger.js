(function($){
    'use strict';

    function getWpgbInstances(){
        var wpgb = window.WP_Grid_Builder;
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
        var instances = getWpgbInstances();
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
                var paramsRaw = null;
                if (typeof facets.getParams === 'function') {
                    try {
                        paramsRaw = facets.getParams();
                    } catch (e) {
                        paramsRaw = null;
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
                    }catch(e){ }
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
                } catch(e) { params = {}; }

                if (params && Object.keys(params).length) {
                    output.push({ grid: inst.id || inst.name || null, facets: params });
                }

            } catch (e) {
            }
        });

        return output.length ? output : null;
    }

    function collectViaDOM(target){
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
        if ( useApi && window.WP_Grid_Builder ) {
            apiData = collectViaAPI(target);
        }
        if (!apiData) {
            var domData = collectViaDOM(target) || [];
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

        var collected = collectAll(target, useApi);

            // Apply mapping if provided by PHP and print compact rows
            var map = window.acfWpgbFacetMap || {};
            try {
                if (!Array.isArray(collected) || !collected.length) {
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
                            var skippedUnmapped = [];
                            rows.forEach(function(r){
                                if (!r.acf_field || r.acf_field === '(no mapping)') {
                                    skippedUnmapped.push(r.facet);
                                    return;
                                }
                                if (!criteriaMap[r.acf_field]) criteriaMap[r.acf_field] = [];
                                criteriaMap[r.acf_field].push(r.value);
                            });


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

                            // Build simplified criteria array: name, label, values
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

                                // Detect if values are numeric
                                var parsed = valsClean.map(function(v){ var n = parseNumber(v); return { raw: v, num: n }; });
                                var numericVals = parsed.map(function(p){ return p.num; }).filter(function(x){ return x !== null; });

                                // Detect range format in single value
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

                                // Determine label type
                                var label = 'multiple_choice';
                                if (numericVals.length >= 2) {
                                    label = 'range';
                                }

                                criteriaArray.push({
                                    name: field,
                                    label: label,
                                    values: valsClean
                                });
                            });

                            // Determine category based on current page URL
                            var category = '';
                            var path = window.location.pathname.toLowerCase();
                            if (path.indexOf('/osakeannit') !== -1) {
                                category = 'Osakeannit';
                            } else if (path.indexOf('/velkakirjat') !== -1) {
                                category = 'Velkakirjat';
                            } else if (path.indexOf('/osaketori') !== -1) {
                                category = 'Osaketori';
                            }

                            // Always perform search - empty criteria will return all posts in category
                            if (typeof window.acfWpgbLogger !== 'undefined' && window.acfWpgbLogger.ajaxUrl) {
                                var searchPayload = {
                                    action: 'acf_popup_search',
                                    nonce: window.acfWpgbLogger.nonce || window.acfWpgbLogger.nonce,
                                    category: category,
                                    criteria: criteriaArray,
                                    match_logic: criteriaArray.length > 0 ? 'AND' : 'ALL',
                                    debug: '1'
                                };
                                $.post(window.acfWpgbLogger.ajaxUrl, searchPayload)
                                    .done(function(resp){
                                        // Search completed
                                    }).fail(function(xhr){
                                        // Search request failed
                                    });
                            }
                        }
                });

            } catch (e) {
            }
    });

    // ============================================
    // HAKUVAHTI SAVE FUNCTIONALITY
    // ============================================

    // Store the last collected criteria for saving
    var lastCollectedCriteria = null;
    var lastCollectedCategory = '';

    // Function to get current criteria (reusable)
    function getCurrentCriteria(target, useApi) {
        var collected = collectAll(target, useApi);
        var map = window.acfWpgbFacetMap || {};
        var criteriaArray = [];

        if (!Array.isArray(collected) || !collected.length) {
            return { criteria: [], category: '' };
        }

        // Determine category based on current page URL
        var category = '';
        var path = window.location.pathname.toLowerCase();
        if (path.indexOf('/osakeannit') !== -1) {
            category = 'Osakeannit';
        } else if (path.indexOf('/velkakirjat') !== -1) {
            category = 'Velkakirjat';
        } else if (path.indexOf('/osaketori') !== -1) {
            category = 'Osaketori';
        }

        collected.forEach(function(item) {
            var facets = item.facets || {};
            var rows = [];

            Object.keys(facets).forEach(function(slug) {
                var acfField = (map && map[slug]) ? map[slug] : null;
                var values = facets[slug] || [];
                if (values && values.length && acfField) {
                    rows.push({ facet: slug, acf_field: acfField, values: values });
                }
            });

            // Build criteria from rows
            var criteriaMap = {};
            rows.forEach(function(r) {
                if (!r.acf_field) return;
                if (!criteriaMap[r.acf_field]) criteriaMap[r.acf_field] = [];
                r.values.forEach(function(v) {
                    criteriaMap[r.acf_field].push(v);
                });
            });

            // Helper: parse numeric-ish strings
            function parseNumber(v) {
                if (v == null) return null;
                if (typeof v === 'number') return v;
                var s = String(v).trim();
                var cleaned = s.replace(/[^0-9.,\-]/g, '');
                if (cleaned === '') return null;
                cleaned = cleaned.replace(/,/g, '.');
                var n = parseFloat(cleaned);
                return isNaN(n) ? null : n;
            }

            // Build criteria array
            Object.keys(criteriaMap).forEach(function(field) {
                var vals = criteriaMap[field] || [];
                var valsClean = vals.map(function(v) { return v == null ? '' : String(v).trim(); });
                valsClean = valsClean.filter(function(v) {
                    if (!v) return false;
                    var lower = v.toLowerCase().trim();
                    var placeholders = ['(none)', '(no mapping)', 'none', 'any', 'default', 'valitse', 'valitse...', '-- select --', '-- select field --', 'choose', 'choose one', 'not set'];
                    if (placeholders.indexOf(lower) !== -1) return false;
                    if (/^[-\s]*--.*--[-\s]*$/.test(v)) return false;
                    if (/^[-]+$/.test(v)) return false;
                    return true;
                });

                if (valsClean.length === 0) return;

                // Detect if values are numeric range
                var parsed = valsClean.map(function(v) { var n = parseNumber(v); return { raw: v, num: n }; });
                var numericVals = parsed.map(function(p) { return p.num; }).filter(function(x) { return x !== null; });

                // Detect range format in single value
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

                var label = 'multiple_choice';
                if (numericVals.length >= 2) {
                    label = 'range';
                }

                criteriaArray.push({
                    name: field,
                    label: label,
                    values: valsClean
                });
            });
        });

        return { criteria: criteriaArray, category: category };
    }

    // Format criteria for display
    function formatCriteriaPreview(criteria) {
        if (!criteria || !criteria.length) {
            return '<p>Ei hakuehtoja valittu</p>';
        }

        var html = '<ul class="hakuvahti-criteria-list">';
        criteria.forEach(function(c) {
            html += '<li><strong>' + c.name + ':</strong> ' + c.values.join(', ') + '</li>';
        });
        html += '</ul>';
        return html;
    }

    // Open modal handler
    $(document).on('click', '.acf-hakuvahti-save', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var target = $btn.attr('data-target') || '';
        var useApiAttr = $('.acf-wpgb-facet-logger').attr('data-use-api');
        var useApi = (typeof useApiAttr !== 'undefined') ? (useApiAttr === '1' || useApiAttr === 'true') : (window.acfWpgbLogger && window.acfWpgbLogger.use_api_default);

        // Get current criteria
        var result = getCurrentCriteria(target, useApi);
        lastCollectedCriteria = result.criteria;
        lastCollectedCategory = result.category;

        // Show criteria preview
        $('#hakuvahti-criteria-preview').html(formatCriteriaPreview(lastCollectedCriteria));

        // Clear previous messages and input
        $('#hakuvahti-save-message').html('');
        $('#hakuvahti-name').val('');

        // Move modal to body to avoid parent container issues
        var $modal = $('#hakuvahti-save-modal');
        if (!$modal.parent().is('body')) {
            $modal.appendTo('body');
        }

        // Show modal (use css to set flex display for proper centering)
        $modal.css('display', 'flex');

        // Focus on input field
        setTimeout(function() {
            $('#hakuvahti-name').focus();
        }, 100);
    });

    // Close modal
    $(document).on('click', '.hakuvahti-modal-close', function() {
        $('#hakuvahti-save-modal').css('display', 'none');
    });

    // Close modal on outside click
    $(document).on('click', '.hakuvahti-modal', function(e) {
        if ($(e.target).hasClass('hakuvahti-modal')) {
            $('#hakuvahti-save-modal').css('display', 'none');
        }
    });

    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#hakuvahti-save-modal').is(':visible')) {
            $('#hakuvahti-save-modal').css('display', 'none');
        }
    });

    // Save form submit
    $(document).on('submit', '#hakuvahti-save-form', function(e) {
        e.preventDefault();

        var name = $('#hakuvahti-name').val().trim();
        if (!name) {
            $('#hakuvahti-save-message').html('<p class="error">Anna hakuvahdille nimi.</p>');
            return;
        }

        if (!lastCollectedCategory) {
            $('#hakuvahti-save-message').html('<p class="error">Kategoriaa ei voitu tunnistaa.</p>');
            return;
        }

        var $submitBtn = $(this).find('.hakuvahti-submit');
        $submitBtn.prop('disabled', true).text('Tallennetaan...');

        $.post(window.acfWpgbLogger.ajaxUrl, {
            action: 'hakuvahti_save',
            nonce: window.acfWpgbLogger.hakuvahtiNonce,
            name: name,
            category: lastCollectedCategory,
            criteria: lastCollectedCriteria
        }).done(function(resp) {
            if (resp && resp.success) {
                $('#hakuvahti-save-message').html('<p class="success">' + (resp.data.message || 'Hakuvahti tallennettu!') + '</p>');
                setTimeout(function() {
                    $('#hakuvahti-save-modal').css('display', 'none');
                }, 1500);
            } else {
                $('#hakuvahti-save-message').html('<p class="error">' + (resp.data && resp.data.message ? resp.data.message : 'Tallennus epäonnistui.') + '</p>');
            }
        }).fail(function() {
            $('#hakuvahti-save-message').html('<p class="error">Verkkovirhe. Yritä uudelleen.</p>');
        }).always(function() {
            $submitBtn.prop('disabled', false).text('Tallenna');
        });
    });

})(jQuery);
