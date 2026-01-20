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

    // Collect via API when available and desired, otherwise fallback to DOM collection.
    function collectAll(target, useApi) {
        if (typeof useApi === 'undefined') useApi = true;
        if (useApi && window.WP_Grid_Builder) {
            return collectViaAPI(target);
        }
        // Fallback to DOM-based collection to support pages without the WP Grid Builder JS API
        return collectViaDOM(target);
    }

    // Simplified logger: only collect via WP Grid Builder API and map facets
    $(document).on('click', '.acf-wpgb-facet-logger', function(e){
        e.preventDefault();
        var $btn = $(this);
        var target = $btn.attr('data-target') || '';

        var collected = collectAll(target, useApi) || [];
        var map = window.acfWpgbFacetMap || {};

        // Debug: log what we collected and the current mapping
        try { console.info('acfWpgbLogger: collected facets ->', collected); } catch(e) {}
        try { console.info('acfWpgbLogger: acfWpgbFacetMap ->', map); } catch(e) {}

        if (!Array.isArray(collected) || !collected.length) {
            console.log('acfWpgbLogger: no WP Grid Builder data available.');
            return;
        }

        var mapped = [];
        collected.forEach(function(item){
            var facets = item.facets || {};
            Object.keys(facets).forEach(function(slug){
                var acfField = map[slug] || null;
                if (!acfField) return; // only keep mapped facets
                var values = facets[slug] || [];
                mapped.push({ facet: slug, acf_field: acfField, values: values });
            });
        });

        if (!mapped.length) {
            console.log('acfWpgbLogger: no mapped facets found.');
            return;
        }

        // Emit a browser event and log the mapped criteria for downstream use
        try {
            var ev = new CustomEvent('acfWpgbLogger.mapped', { detail: mapped });
            window.dispatchEvent(ev);
        } catch (err) {}
        console.log('acfWpgbLogger mapped:', mapped);
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

    // Minimal modal setup: keep modal hidden and attach to body
    $(function() {
        var $modalInit = $('#hakuvahti-save-modal');
        if ($modalInit.length && !$modalInit.parent().is('body')) {
            $modalInit.appendTo('body');
        }
        // Hide with jQuery to avoid flicker
        $modalInit.hide();
    });

    // ============================================
    // UNRESTRICTED SEARCH MODE
    // ============================================

    /**
     * Detect current category from URL path
     */
    function detectCategory() {
        var path = window.location.pathname.toLowerCase();
        if (path.indexOf('/osakeannit') !== -1) return 'Osakeannit';
        if (path.indexOf('/velkakirjat') !== -1) return 'Velkakirjat';
        if (path.indexOf('/osaketori') !== -1) return 'Osaketori';
        return '';
    }

    /**
     * Get field definitions for current category
     */
    function getFieldsForCategory(category) {
        var defs = window.acfWpgbLogger && window.acfWpgbLogger.fieldDefinitions || {};
        return defs[category] || [];
    }

    /**
     * Build the unrestricted search builder UI
     */
    function buildUnrestrictedModal(category) {
        var fields = getFieldsForCategory(category);
        var $preview = $('#hakuvahti-criteria-preview');

        // Clear and rebuild preview area as search builder
        $preview.empty().addClass('hakuvahti-unrestricted-builder');

        // Container for criteria rows
        var $criteriaContainer = $('<div id="unrestricted-criteria-rows"></div>');

        // Add field dropdown + "Add Criterion" button
        var $addRow = $('<div class="hakuvahti-add-criterion"></div>');
        var $fieldSelect = $('<select id="unrestricted-field-select"></select>');
        $fieldSelect.append('<option value="">Valitse kenttä...</option>');

        fields.forEach(function(field) {
            $fieldSelect.append('<option value="' + field.acf_key + '" data-type="' + field.type + '">' + field.name + '</option>');
        });

        var $addBtn = $('<button type="button" class="button hakuvahti-add-btn">+ Lisää hakuehto</button>');

        $addRow.append($fieldSelect).append(' ').append($addBtn);

        $preview.append($criteriaContainer).append($addRow);

        // Store field definitions for building rows
        $preview.data('fields', fields);
        $preview.data('category', category);

        // Add criterion click handler
        $addBtn.on('click', function() {
            var selectedKey = $fieldSelect.val();
            if (!selectedKey) return;

            var fieldDef = null;
            for (var i = 0; i < fields.length; i++) {
                if (fields[i].acf_key === selectedKey) {
                    fieldDef = fields[i];
                    break;
                }
            }
            if (!fieldDef) return;

            addCriterionRow($criteriaContainer, fieldDef);

            // Reset select
            $fieldSelect.val('');
        });
    }

    /**
     * Add a criterion row to the builder
     */
    function addCriterionRow($container, fieldDef) {
        var $row = $('<div class="hakuvahti-criterion-row" data-field="' + fieldDef.acf_key + '" data-type="' + fieldDef.type + '"></div>');

        // Field label
        var $label = $('<span class="criterion-label">' + fieldDef.name + ':</span>');

        // Input based on type
        var $input;
        if (fieldDef.type === 'range') {
            $input = $('<div class="criterion-range-inputs"></div>');
            $input.append('<input type="number" class="criterion-min" placeholder="Min">');
            $input.append('<span class="range-separator"> - </span>');
            $input.append('<input type="number" class="criterion-max" placeholder="Max">');
        } else {
            // Multiple choice - checkbox list
            $input = $('<div class="criterion-checkboxes"></div>');
            var options = fieldDef.options || {};
            Object.keys(options).forEach(function(key) {
                var $checkbox = $('<label class="criterion-checkbox"><input type="checkbox" value="' + key + '"> ' + options[key] + '</label>');
                $input.append($checkbox);
            });
        }

        // Remove button
        var $removeBtn = $('<button type="button" class="button criterion-remove">&times;</button>');
        $removeBtn.on('click', function() {
            $row.remove();
        });

        $row.append($label).append($input).append($removeBtn);
        $container.append($row);
    }

    /**
     * Collect criteria from unrestricted builder
     */
    function collectUnrestrictedCriteria() {
        var criteria = [];

        $('#unrestricted-criteria-rows .hakuvahti-criterion-row').each(function() {
            var $row = $(this);
            var fieldKey = $row.data('field');
            var fieldType = $row.data('type');
            var values = [];

            if (fieldType === 'range') {
                var min = $row.find('.criterion-min').val();
                var max = $row.find('.criterion-max').val();
                if (min) values.push(min);
                if (max) values.push(max);
            } else {
                // Multiple choice - get checked boxes
                $row.find('.criterion-checkboxes input:checked').each(function() {
                    values.push($(this).val());
                });
            }

            if (values.length > 0) {
                criteria.push({
                    name: fieldKey,
                    label: fieldType,
                    values: values
                });
            }
        });

        return criteria;
    }

    // Open modal handler
    $(document).on('click', '.acf-hakuvahti-save', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var target = $btn.attr('data-target') || '';

        // Check if unrestricted mode is enabled
        var isUnrestricted = window.acfWpgbLogger && window.acfWpgbLogger.unrestrictedMode;

        // Detect category from URL
        var category = detectCategory();

        if (!category) {
            alert('Kategoriaa ei voitu tunnistaa.');
            return;
        }

        var $modal = $('#hakuvahti-save-modal');
        $('#hakuvahti-save-message').html('');
        $('#hakuvahti-name').val('');

        if (isUnrestricted) {
            // UNRESTRICTED MODE: Show manual builder
            lastCollectedCategory = category;
            lastCollectedCriteria = []; // Will be collected on save

            // Build the unrestricted modal UI
            buildUnrestrictedModal(category);

            // Show modal
            $modal.stop(true,true).fadeIn(150);
            setTimeout(function() { $('#hakuvahti-name').focus(); }, 120);

        } else {
            // RESTRICTED MODE: Use existing WP Grid Builder collection
            var useApiAttr = $btn.attr('data-use-api');
            var useApi = (typeof useApiAttr !== 'undefined' && useApiAttr !== null) ? (useApiAttr === '1' || useApiAttr === 'true') : (window.acfWpgbLogger && window.acfWpgbLogger.use_api_default);

            // Get current criteria
            var result = getCurrentCriteria(target, useApi);
            try { console.log('hakuvahti: open modal target=' + target + ' useApi=' + useApi); } catch(ex){}
            try { console.log('hakuvahti: getCurrentCriteria ->', result); } catch(ex){}
            lastCollectedCriteria = result.criteria;
            lastCollectedCategory = result.category || category;

            // Show criteria preview
            $('#hakuvahti-criteria-preview')
                .removeClass('hakuvahti-unrestricted-builder')
                .html(formatCriteriaPreview(lastCollectedCriteria));

            $modal.stop(true,true).fadeIn(150);
            setTimeout(function() { $('#hakuvahti-name').focus(); }, 120);
        }
    });
    // Close modal handlers
    $(document).on('click', '.hakuvahti-modal-close', function() {
        $('#hakuvahti-save-modal').stop(true,true).fadeOut(120);
    });

    // Close modal on outside click
    $(document).on('click', '.hakuvahti-modal', function(e) {
        var $modal = $('#hakuvahti-save-modal');
        if (e.target === $modal[0]) {
            $modal.stop(true,true).fadeOut(120);
        }
    });

    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#hakuvahti-save-modal').is(':visible')) {
            $('#hakuvahti-save-modal').stop(true,true).fadeOut(120);
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

        // Check if we're in unrestricted mode
        var isUnrestricted = $('#hakuvahti-criteria-preview').hasClass('hakuvahti-unrestricted-builder');

        if (isUnrestricted) {
            // Collect criteria from the builder
            lastCollectedCriteria = collectUnrestrictedCriteria();
        }

        if (!lastCollectedCriteria || lastCollectedCriteria.length === 0) {
            $('#hakuvahti-save-message').html('<p class="error">Valitse vähintään yksi hakuehto.</p>');
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
                    $('#hakuvahti-save-modal').stop(true,true).fadeOut(120);
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
