(function($){
    'use strict';

    function renderEditor(mapping){
        // single-line criteria-style row
        var $row = $('<div class="criteria-row search-option-row" data-index="' + index + '"></div>');

        var $acf = $('<select class="option-acf-field criteria-field"><option value="">-- Select ACF field --</option></select>');
        var $name = $('<input type="text" class="option-name regular-text" placeholder="Display name" />').val(opt.name || '');
        var $valuesContainer = $('<span class="option-values" style="vertical-align:middle; margin-left:10px;"></span>');
        var $remove = $('<button type="button" class="button remove-criteria" style="margin-left:8px;">&times;</button>');

        $remove.on('click', function(){
            // remove this option from the stored array
            // find by matching name+acf+category (best-effort)
            userSearchOptions = userSearchOptions.filter(function(o){
                return !(o.name === opt.name && o.acf_field === opt.acf_field && (o.category || currentOptionsTab) === (opt.category || currentOptionsTab));
            });
            renderSearchOptionsEditor();
        });

        // when acf selection changes
        $acf.on('change', function() { renderValuesAreaFor($(this), $valuesContainer, opt); });

        $row.append($acf).append($name).append($valuesContainer).append($remove);

        // populate acf options for the current tab
        var category = opt.category || currentOptionsTab;
        fetchFieldsForCategory(category, function(fields){
            fieldMetaCache[category] = fieldMetaCache[category] || {};
            $acf.empty();
            $acf.append($('<option value=""></option>').text('-- Select ACF field --'));
            fields.forEach(function(f){ fieldMetaCache[category][f.key] = f; $acf.append($('<option></option>').attr('value', f.key).text(f.label || f.key)); });
            if (opt.acf_field) { $acf.val(opt.acf_field); }
            renderValuesAreaFor($acf, $valuesContainer, opt);
        });

        return $row;
    }

    function renderValuesAreaFor($acfSelect, $container, opt) {
        $container.empty();
        var $row = $acfSelect.closest('.search-option-row');
        var category = $row.find('.option-category').val();
        var fieldKey = $acfSelect.val();
        if (!fieldKey) {
            $container.append($('<span class="muted">Select an ACF field</span>'));
            return;
        }

        var meta = (fieldMetaCache[category] && fieldMetaCache[category][fieldKey]) ? fieldMetaCache[category][fieldKey] : null;
        if (!meta) {
            // fetch and retry
            fetchFieldsForCategory(category, function(fields){
                fieldMetaCache[category] = fieldMetaCache[category] || {};
                fields.forEach(function(f){ fieldMetaCache[category][f.key] = f; });
                renderValuesAreaFor($acfSelect, $container, opt);
            });
            return;
        }

        if (meta.has_choices && meta.choices && Object.keys(meta.choices).length > 0) {
            var $select = $('<select multiple class="option-values-choices" style="min-width:200px; max-width:400px; height:120px;"></select>');
            Object.keys(meta.choices).forEach(function(k){
                var label = meta.choices[k];
                var $o = $('<option></option>').attr('value', k).text(label);
                if (opt && opt.values && Array.isArray(opt.values) && opt.values.indexOf(k) !== -1) $o.attr('selected','selected');
                $select.append($o);
            });
            $container.append($select);
        } else {
            var min = (opt && opt.values && typeof opt.values === 'object') ? (opt.values.min || '') : '';
            var max = (opt && opt.values && typeof opt.values === 'object') ? (opt.values.max || '') : '';
            var $min = $('<input type="text" class="option-values-min small-text" placeholder="min" />').val(min);
            var $max = $('<input type="text" class="option-values-max small-text" placeholder="max" />').val(max);
            $container.append($('<div></div>').append($min).append(' – ').append($max));
        }
    }

    $(document).ready(function(){
        if (typeof acfAnalyzerAdmin === 'undefined') return;
        renderEditor(acfAnalyzerAdmin.mapping || {});
        initUnrestrictedFieldsEditor();
        initSearchOptionsEditor();
    });

    // ============================================
    // USER-DEFINED SEARCH OPTIONS EDITOR
    // ============================================

    var userSearchOptions = [];
    var fieldMetaCache = {}; // category -> { key: meta }
    var currentOptionsTab = (acfAnalyzerAdmin && acfAnalyzerAdmin.categories && acfAnalyzerAdmin.categories[0]) || 'Osakeannit';

    function initSearchOptionsEditor() {
        var $editor = $('#search-options-editor');
        if (!$editor.length) return;

        userSearchOptions = acfAnalyzerAdmin.userSearchOptions || [];

        renderSearchOptionsEditor();
    }

    function renderSearchOptionsEditor() {
        var $editor = $('#search-options-editor');
        $editor.empty();

        // Tabs: wire handlers
        $('.user-search-tabs .tab-btn').off('click').on('click', function(){
            $('.user-search-tabs .tab-btn').removeClass('active');
            $(this).addClass('active');
            currentOptionsTab = $(this).data('category');
            renderSearchOptionsEditor();
        });

        var $list = $('<div class="search-options-list"></div>');
        // render only options for current tab
        userSearchOptions.forEach(function(opt, index) {
            if ((opt.category || '') === currentOptionsTab) {
                $list.append(renderSearchOptionRow(opt, index));
            }
        });

        var $addBtn = $('<button type="button" class="button">+ Add Option</button>');
        $addBtn.on('click', function() {
            userSearchOptions.push({ name: '', category: currentOptionsTab, acf_field: '', values: null });
            renderSearchOptionsEditor();
        });

        var $saveBtn = $('<button type="button" class="button button-primary">Save Options</button>');
        $saveBtn.on('click', function() {
            collectUserOptions();
            saveUserOptions();
        });

        $editor.append($list).append($('<p></p>').append($addBtn).append(' ').append($saveBtn));
    }

    function renderSearchOptionRow(opt, index) {
        var $row = $('<div class="search-option-row" data-index="' + index + '"></div>');

        var $name = $('<input type="text" class="option-name regular-text" placeholder="Display name" />').val(opt.name || '');

        var $cat = $('<select class="option-category"></select>');
        (acfAnalyzerAdmin.categories || []).forEach(function(c) {
            var $o = $('<option></option>').attr('value', c).text(c);
            if (opt.category === c) $o.attr('selected', 'selected');
            $cat.append($o);
        });

        var $acf = $('<select class="option-acf-field"><option value="">-- Select ACF field --</option></select>');
        var $valuesContainer = $('<div class="option-values"></div>');

        // When category changes, fetch fields
        $cat.on('change', function() {
            var category = $(this).val();
            fetchFieldsForCategory(category, function(fields) {
                // cache
                fieldMetaCache[category] = fieldMetaCache[category] || {};
                fields.forEach(function(f){ fieldMetaCache[category][f.key] = f; });

                $acf.empty();
                $acf.append($('<option value=""></option>').text('-- Select ACF field --'));
                fields.forEach(function(f) { $acf.append($('<option></option>').attr('value', f.key).text(f.label || f.key)); });
                if (opt.acf_field) { $acf.val(opt.acf_field); }
                renderValuesAreaFor($acf, $valuesContainer, opt);
            });
        });

        // when acf selection changes
        $acf.on('change', function() { renderValuesAreaFor($(this), $valuesContainer); });

        var $remove = $('<button type="button" class="button-link">Remove</button>');
        $remove.on('click', function() {
            userSearchOptions.splice(index, 1);
            renderSearchOptionsEditor();
        });

        $row.append($('<div class="field-group"></div>').append($('<label>Display Name:</label>')).append($name));
        $row.append($('<div class="field-group"></div>').append($('<label>Key:</label>')).append($key));
        $row.append($('<div class="field-group"></div>').append($('<label>Category:</label>')).append($cat));
        $row.append($('<div class="field-group"></div>').append($('<label>ACF Field:</label>')).append($acf));
        $row.append($('<div class="field-group"></div>').append($('<label>Values:</label>')).append($valuesContainer));
        $row.append($('<div class="field-group field-delete-group"></div>').append($remove));

        // Trigger initial population of ACF select for this category
        (function initPopulate(){
            var category = $cat.val();
            fetchFieldsForCategory(category, function(fields){
                fieldMetaCache[category] = fieldMetaCache[category] || {};
                fields.forEach(function(f){ fieldMetaCache[category][f.key] = f; });

                $acf.empty();
                $acf.append($('<option value=""></option>').text('-- Select ACF field --'));
                fields.forEach(function(f){ $acf.append($('<option></option>').attr('value', f.key).text(f.label || f.key)); });
                if (opt.acf_field) { $acf.val(opt.acf_field); }
                renderValuesAreaFor($acf, $valuesContainer, opt);
            });
        })();

        return $row;
    }

    function collectUserOptions() {
        // Collect rows visible for the current tab and replace that category in userSearchOptions
        var $rows = $('#search-options-editor .search-option-row');
        var collected = [];
        $rows.each(function() {
            var $r = $(this);
            var name = $r.find('.option-name').val().trim();
            var acf  = $r.find('.option-acf-field').val();
            var values = null;
            var $choices = $r.find('.option-values-choices');
            if ($choices.length) {
                values = $choices.val() || [];
            } else {
                var min = $r.find('.option-values-min').val();
                var max = $r.find('.option-values-max').val();
                values = { min: min, max: max };
            }
            if (name) {
                collected.push({ name: name, category: currentOptionsTab, acf_field: acf, values: values });
            }
        });

        // merge: keep existing options from other categories
        var others = userSearchOptions.filter(function(o){ return (o.category || '') !== currentOptionsTab; });
        userSearchOptions = others.concat(collected);
    }

    function fetchFieldsForCategory(category, cb) {
        $.post(acfAnalyzerAdmin.ajaxUrl, {
            action: 'acf_analyzer_get_fields_by_category',
            nonce: acfAnalyzerAdmin.nonce,
            category: category
        }, function(resp) {
            if (resp && resp.success) {
                cb(resp.data || []);
            } else {
                console.error('Failed to fetch fields for', category, resp);
                cb([]);
            }
        }).fail(function(xhr) { console.error('AJAX error fetching fields', xhr); cb([]); });
    }

    function saveUserOptions() {
        console.debug('acf-analyzer: saveUserOptions payload', userSearchOptions);
        $.post(acfAnalyzerAdmin.ajaxUrl, {
            action: 'acf_analyzer_save_user_options',
            nonce: acfAnalyzerAdmin.nonce,
            options: userSearchOptions,
            options_json: JSON.stringify(userSearchOptions)
        }, function(resp) {
            console.debug('acf-analyzer: saveUserOptions response', resp);
            if (resp && resp.success) {
                alert('User search options saved');
                userSearchOptions = resp.data && resp.data.sanitized ? resp.data.sanitized : (resp.data && resp.data.raw ? resp.data.raw : userSearchOptions);
                renderSearchOptionsEditor();
            } else {
                alert('Failed to save options: ' + (resp && resp.data ? JSON.stringify(resp.data) : 'unknown'));
            }
        }).fail(function(xhr) { console.error('AJAX error saving options', xhr); alert('AJAX error saving options'); });
    }

    // ============================================
    // UNRESTRICTED SEARCH FIELD DEFINITIONS EDITOR
    // ============================================

    var currentCategory = 'Osakeannit';
    var fieldDefinitions = {};

    function initUnrestrictedFieldsEditor() {
        var $editor = $('#unrestricted-fields-editor');
        if (!$editor.length) return;

        // Load initial data
        fieldDefinitions = acfAnalyzerAdmin.unrestrictedFields || {};

        // Ensure all categories exist
        ['Osakeannit', 'Osaketori', 'Velkakirjat'].forEach(function(cat) {
            if (!fieldDefinitions[cat]) {
                fieldDefinitions[cat] = [];
            }
        });

        // Tab click handlers
        $('.unrestricted-field-tabs .tab-btn').on('click', function() {
            var $btn = $(this);
            var category = $btn.data('category');

            $('.unrestricted-field-tabs .tab-btn').removeClass('active');
            $btn.addClass('active');

            currentCategory = category;
            renderFieldsEditor();
        });

        // Toggle checkbox handler
        $('#unrestricted-search-toggle').on('change', function() {
            var enabled = $(this).is(':checked');
            saveUnrestrictedToggle(enabled);
        });

        // Initial render
        renderFieldsEditor();
    }

    function renderFieldsEditor() {
        var $editor = $('#unrestricted-fields-editor');
        $editor.empty();

        var fields = fieldDefinitions[currentCategory] || [];
        try { console.debug('renderFieldsEditor', { currentCategory: currentCategory, fields: fields, allDefs: fieldDefinitions }); } catch(e){}

        var $container = $('<div class="unrestricted-fields-container"></div>');

        // Render each field definition
        fields.forEach(function(field, index) {
            $container.append(renderFieldRow(field, index));
        });

        // Add field button
        var $addBtn = $('<button type="button" class="button unrestricted-add-field">+ Add Field</button>');
        $addBtn.on('click', function() {
            fieldDefinitions[currentCategory].push({
                name: '',
                acf_key: '',
                type: 'range',
                options: {}
            });
            renderFieldsEditor();
        });

        // Save button
        var $saveBtn = $('<button type="button" class="button button-primary unrestricted-save-fields">Save Field Definitions</button>');
        $saveBtn.on('click', function() {
            collectFieldsData();
            saveUnrestrictedFields();
        });

        var $actions = $('<div class="unrestricted-fields-actions"></div>');
        $actions.append($addBtn).append(' ').append($saveBtn);

        $editor.append($container).append($actions);
    }

    function renderFieldRow(field, index) {
        var $row = $('<div class="unrestricted-field-row" data-index="' + index + '"></div>');

        // Display Name
        var $nameGroup = $('<div class="field-group"><label>Display Name:</label></div>');
        var $nameInput = $('<input type="text" class="field-name regular-text" />').val(field.name || '');
        $nameGroup.append($nameInput);

        // ACF Field Key
        var $acfGroup = $('<div class="field-group"><label>ACF Field:</label></div>');
        var $acfInput = $('<input type="text" class="field-acf-key regular-text" />').val(field.acf_key || '');
        $acfGroup.append($acfInput);

        // Type radio buttons
        var $typeGroup = $('<div class="field-group"><label>Type:</label></div>');
        var $typeRadios = $('<span class="type-radios"></span>');
        var radioName = 'field_type_' + currentCategory + '_' + index;

        var $rangeRadio = $('<label><input type="radio" name="' + radioName + '" value="range"' + (field.type === 'range' ? ' checked' : '') + '> Range</label>');
        var $multiRadio = $('<label><input type="radio" name="' + radioName + '" value="multiple_choice"' + (field.type === 'multiple_choice' ? ' checked' : '') + '> Multiple Choice</label>');

        $typeRadios.append($rangeRadio).append(' ').append($multiRadio);
        $typeGroup.append($typeRadios);

        // Options textarea (for multiple choice)
        var $optionsGroup = $('<div class="field-group options-group"' + (field.type !== 'multiple_choice' ? ' style="display:none;"' : '') + '><label>Options (key=value per line):</label></div>');
        var optionsText = '';
        if (field.options && typeof field.options === 'object') {
            Object.keys(field.options).forEach(function(key) {
                optionsText += key + '=' + field.options[key] + '\n';
            });
        }
        var $optionsTextarea = $('<textarea class="field-options large-text" rows="4"></textarea>').val(optionsText.trim());
        $optionsGroup.append($optionsTextarea);

        // Toggle options visibility on type change
        $typeRadios.find('input[type="radio"]').on('change', function() {
            var selectedType = $(this).val();
            if (selectedType === 'multiple_choice') {
                $optionsGroup.show();
            } else {
                $optionsGroup.hide();
            }
        });

        // Delete button
        var $deleteBtn = $('<button type="button" class="button field-delete">Delete</button>');
        $deleteBtn.on('click', function() {
            fieldDefinitions[currentCategory].splice(index, 1);
            renderFieldsEditor();
        });

        var $deleteGroup = $('<div class="field-group field-delete-group"></div>');
        $deleteGroup.append($deleteBtn);

        $row.append($nameGroup).append($acfGroup).append($typeGroup).append($optionsGroup).append($deleteGroup);

        return $row;
    }

    function collectFieldsData() {
        var $rows = $('#unrestricted-fields-editor .unrestricted-field-row');
        var fields = [];

        try { console.debug('collectFieldsData - rows found:', $rows.length); } catch(e){}

        $rows.each(function() {
            var $row = $(this);
            var name = $row.find('.field-name').val().trim();
            var acf_key = $row.find('.field-acf-key').val().trim();
            var type = $row.find('input[type="radio"]:checked').val() || 'range';
            var optionsText = $row.find('.field-options').val().trim();

            var options = {};
            if (type === 'multiple_choice' && optionsText) {
                optionsText.split('\n').forEach(function(line) {
                    line = line.trim();
                    if (line) {
                        var parts = line.split('=');
                        if (parts.length >= 2) {
                            var key = parts[0].trim();
                            var value = parts.slice(1).join('=').trim();
                            if (key) {
                                options[key] = value || key;
                            }
                        }
                    }
                });
            }

            if (name || acf_key) {
                fields.push({
                    name: name,
                    acf_key: acf_key,
                    type: type,
                    options: options
                });
            }
        });

        try { console.debug('collectFieldsData - collected fields for', currentCategory, fields); } catch(e){}
        fieldDefinitions[currentCategory] = fields;
    }

    function saveUnrestrictedToggle(enabled) {
        console.debug('acf-analyzer: saveUnrestrictedToggle', { enabled: enabled });
        $.post(acfAnalyzerAdmin.ajaxUrl, {
            action: 'acf_analyzer_save_unrestricted_toggle',
            nonce: acfAnalyzerAdmin.nonce,
            enabled: enabled ? '1' : '0'
        }, function(resp) {
            console.debug('acf-analyzer: saveUnrestrictedToggle response', resp);
            if (resp && resp.success) {
                // Saved successfully
            } else {
                alert('Failed to save toggle: ' + (resp && resp.data ? JSON.stringify(resp.data) : 'unknown'));
            }
        }).fail(function(xhr) {
            console.error('acf-analyzer: AJAX error saving toggle', xhr);
            alert('AJAX error saving toggle');
        });
    }

    function saveUnrestrictedFields() {
        try { console.debug('acf-analyzer: saveUnrestrictedFields payload', fieldDefinitions); } catch(e){}
        try { console.debug('acf-analyzer: payload typeof:', typeof fieldDefinitions, 'isArray:', Array.isArray(fieldDefinitions), JSON.stringify(fieldDefinitions).slice(0,200)); } catch(e){}
        // Send fields as an object (same style as mapping save)
        $.post(acfAnalyzerAdmin.ajaxUrl, {
            action: 'acf_analyzer_save_unrestricted_fields',
            nonce: acfAnalyzerAdmin.nonce,
            fields: fieldDefinitions,
            // Also include JSON string as a fallback for PHP parsing differences
            fields_json: JSON.stringify(fieldDefinitions)
        }, function(resp) {
            console.debug('acf-analyzer: saveUnrestrictedFields response', resp);
            if (resp && resp.success) {
                console.debug('acf-analyzer: saveUnrestrictedFields response data', resp.data);
                alert('Field definitions saved');
                // Server returns { sanitized: ..., raw: ... }
                // Prefer sanitized object if available
                if (resp.data && resp.data.sanitized) {
                    fieldDefinitions = resp.data.sanitized;
                } else if (resp.data && resp.data.fields) {
                    fieldDefinitions = resp.data.fields;
                } else {
                    fieldDefinitions = (resp.data || {});
                }
                renderFieldsEditor();
            } else {
                alert('Failed to save: ' + (resp && resp.data ? JSON.stringify(resp.data) : 'unknown'));
            }
        }).fail(function(xhr) {
            console.error('acf-analyzer: AJAX error saving field definitions', xhr);
            alert('AJAX error saving field definitions');
        });
    }

})(jQuery);
