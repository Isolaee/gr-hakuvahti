(function($){
    'use strict';

    function renderEditor(mapping){
        var $root = $('#acf-wpgb-mapping-editor');
        if (!$root.length) return;
        $root.empty();

        var $table = $('<table class="widefat"><thead><tr><th>Facet slug</th><th>ACF field</th><th></th></tr></thead><tbody></tbody></table>');
        var $tbody = $table.find('tbody');

        var addRow = function(slug, field){
            var $row = $('<tr></tr>');
            var $slugTd = $('<td></td>');
            var $fieldTd = $('<td></td>');
            var $actionsTd = $('<td></td>');

            var $slugInput = $('<input type="text" class="regular-text" />').val(slug||'');
            var $fieldInput = $('<input type="text" class="regular-text" />').val(field||'');
            var $del = $('<button type="button" class="button-link">Remove</button>');

            $del.on('click', function(){ $row.remove(); });

            $slugTd.append($slugInput);
            $fieldTd.append($fieldInput);
            $actionsTd.append($del);
            $row.append($slugTd, $fieldTd, $actionsTd);
            $tbody.append($row);
        };

        // existing mapping rows
        if (mapping && typeof mapping === 'object'){
            Object.keys(mapping).forEach(function(k){ addRow(k, mapping[k]); });
        }

        // allow adding empty row
        var $addBtn = $('<button type="button" class="button">Add mapping</button>');
        $addBtn.on('click', function(){ addRow('', ''); });

        var $saveBtn = $('<button type="button" class="button button-primary">Save mapping</button>');
        $saveBtn.on('click', function(){
            var payload = {};
            $tbody.find('tr').each(function(){
                var s = $(this).find('td:nth-child(1) input').val().trim();
                var f = $(this).find('td:nth-child(2) input').val().trim();
                if (s !== '') payload[s] = f;
            });

            console.debug('acf-analyzer: saving mapping', payload);

            // AJAX save
            $.post(acfAnalyzerAdmin.ajaxUrl, {
                action: 'acf_analyzer_save_mapping',
                nonce: acfAnalyzerAdmin.nonce,
                mapping: payload
            }, function(resp){
                console.debug('acf-analyzer: save mapping response', resp);
                if (resp && resp.success){
                    alert('Mapping saved');
                    renderEditor(resp.data);
                } else {
                    alert('Save failed: ' + (resp && resp.data ? JSON.stringify(resp.data) : 'unknown'));
                }
            }).fail(function(xhr){
                console.error('acf-analyzer: AJAX error saving mapping', xhr);
                alert('AJAX error saving mapping');
            });
        });

        $root.append($table);
        $root.append($('<p></p>').append($addBtn).append(' ').append($saveBtn));
    }

    $(document).ready(function(){
        if (typeof acfAnalyzerAdmin === 'undefined') return;
        renderEditor(acfAnalyzerAdmin.mapping || {});
        initUnrestrictedFieldsEditor();
    });

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
        console.debug('acf-analyzer: saveUnrestrictedFields payload', fieldDefinitions);
        // Send fields as JSON string to preserve nested structure
        $.post(acfAnalyzerAdmin.ajaxUrl, {
            action: 'acf_analyzer_save_unrestricted_fields',
            nonce: acfAnalyzerAdmin.nonce,
            fields_json: JSON.stringify(fieldDefinitions)
        }, function(resp) {
            console.debug('acf-analyzer: saveUnrestrictedFields response', resp);
            if (resp && resp.success) {
                alert('Field definitions saved');
                // Server now returns sanitized structure directly
                fieldDefinitions = resp.data || {};
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
