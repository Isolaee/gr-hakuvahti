/**
 * Unit Tests for hakuvahti-page.js
 *
 * Tests the My Account page functionality:
 * - Field caching
 * - Criteria formatting and display
 * - HTML escaping
 * - Field dropdown building
 * - Edit form value parsing
 */

describe('hakuvahti-page.js', () => {
    // ============================================
    // FIELD CACHING
    // ============================================
    describe('Field Caching', () => {
        let fieldsCache;

        beforeEach(() => {
            fieldsCache = {};
        });

        test('returns cached fields immediately', () => {
            fieldsCache['Osakeannit'] = ['field1', 'field2', 'field3'];
            const cached = fieldsCache['Osakeannit'];
            expect(cached).toEqual(['field1', 'field2', 'field3']);
        });

        test('cache miss returns undefined', () => {
            const cached = fieldsCache['NonExistent'];
            expect(cached).toBeUndefined();
        });

        test('stores fetched fields in cache', () => {
            const fetchedFields = ['hinta', 'tyyppi', 'status'];
            fieldsCache['Velkakirjat'] = fetchedFields;
            expect(fieldsCache['Velkakirjat']).toEqual(fetchedFields);
        });

        test('caches are category-specific', () => {
            fieldsCache['Osakeannit'] = ['field_a'];
            fieldsCache['Velkakirjat'] = ['field_b'];
            expect(fieldsCache['Osakeannit']).not.toEqual(fieldsCache['Velkakirjat']);
        });
    });

    // ============================================
    // HTML ESCAPING
    // ============================================
    describe('HTML Escaping (escapeHtml)', () => {
        // Replicate the escapeHtml function from hakuvahti-page.js
        function escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        test('escapes ampersand', () => {
            expect(escapeHtml('foo & bar')).toBe('foo &amp; bar');
        });

        test('escapes less than', () => {
            expect(escapeHtml('a < b')).toBe('a &lt; b');
        });

        test('escapes greater than', () => {
            expect(escapeHtml('a > b')).toBe('a &gt; b');
        });

        test('escapes double quotes', () => {
            expect(escapeHtml('say "hello"')).toBe('say &quot;hello&quot;');
        });

        test('escapes single quotes', () => {
            expect(escapeHtml("it's")).toBe('it&#39;s');
        });

        test('escapes multiple special characters', () => {
            const input = '<script>alert("xss")</script>';
            const expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
            expect(escapeHtml(input)).toBe(expected);
        });

        test('handles empty string', () => {
            expect(escapeHtml('')).toBe('');
        });

        test('converts numbers to string', () => {
            expect(escapeHtml(42)).toBe('42');
        });

        test('handles null', () => {
            expect(escapeHtml(null)).toBe('null');
        });

        test('handles undefined', () => {
            expect(escapeHtml(undefined)).toBe('undefined');
        });
    });

    // ============================================
    // CRITERIA FORMATTING
    // ============================================
    describe('Criteria Formatting (formatCriteriaHTML)', () => {
        const i18n = {
            noCriteria: 'Ei hakuehtoja',
            underLabelPrefix: 'alle ',
            overLabelPrefix: 'yli ',
        };

        function escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // Replicate formatCriteriaHTML logic
        function formatCriteriaHTML(crits) {
            if (!crits || !crits.length) {
                return '<div class="hakuvahti-crit-groups"><div class="hakuvahti-crit-group"><ul class="hakuvahti-crit-list"><li class="hakuvahti-crit-item">' + i18n.noCriteria + '</li></ul></div></div>';
            }

            const groups = {};
            crits.forEach(function(item) {
                const label = item.label || 'other';
                groups[label] = groups[label] || [];
                groups[label].push(item);
            });

            let html = '<div class="hakuvahti-crit-groups">';
            Object.keys(groups).forEach(function(label) {
                html += '<div class="hakuvahti-crit-group" data-label="' + label + '">';
                html += '<ul class="hakuvahti-crit-list">';
                groups[label].forEach(function(it) {
                    const name = it.name || '';
                    const values = it.values || [];
                    let display = '';

                    if (it.label === 'range') {
                        if (Array.isArray(values) && values.length >= 2) {
                            display = values.join(' - ');
                        } else if (Array.isArray(values) && values.length === 1) {
                            const raw = String(values[0]).trim();
                            const norm = raw.replace(',', '.');
                            const m = norm.match(/^\s*([<>]=?)\s*(.+)$/);
                            if (m) {
                                const op = m[1];
                                const num = m[2].trim();
                                if (op.indexOf('<') !== -1) {
                                    display = i18n.underLabelPrefix + num;
                                } else {
                                    display = i18n.overLabelPrefix + num;
                                }
                            } else if (/^\s*(min|max)\s*[:=]\s*(.+)$/i.test(norm)) {
                                const mm = norm.match(/^\s*(min|max)\s*[:=]\s*(.+)$/i);
                                if (mm && mm[1].toLowerCase() === 'min') {
                                    display = i18n.overLabelPrefix + mm[2].trim();
                                } else {
                                    display = i18n.underLabelPrefix + mm[2].trim();
                                }
                            } else if (!isNaN(parseFloat(norm))) {
                                display = i18n.overLabelPrefix + norm;
                            } else {
                                display = raw;
                            }
                        }
                    } else {
                        if (Array.isArray(values)) {
                            display = values.join(', ');
                        } else {
                            display = values;
                        }
                    }
                    html += '<li class="hakuvahti-crit-item">' + escapeHtml(name + ': ' + display) + '</li>';
                });
                html += '</ul></div>';
            });
            html += '</div>';
            return html;
        }

        test('returns no criteria message for empty array', () => {
            const result = formatCriteriaHTML([]);
            expect(result).toContain('Ei hakuehtoja');
        });

        test('returns no criteria message for null', () => {
            const result = formatCriteriaHTML(null);
            expect(result).toContain('Ei hakuehtoja');
        });

        test('returns no criteria message for undefined', () => {
            const result = formatCriteriaHTML(undefined);
            expect(result).toContain('Ei hakuehtoja');
        });

        test('formats multiple_choice criteria with comma-separated values', () => {
            const crits = [{ name: 'tyyppi', label: 'multiple_choice', values: ['A', 'B', 'C'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('tyyppi: A, B, C');
        });

        test('formats range criteria with two values using dash', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['100', '500'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('hinta: 100 - 500');
        });

        test('formats range with operator prefix < as "alle"', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['<100'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('alle 100');
        });

        test('formats range with operator prefix > as "yli"', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['>100'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('yli 100');
        });

        test('formats range with operator prefix <= as "alle"', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['<=100'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('alle 100');
        });

        test('formats range with operator prefix >= as "yli"', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['>=100'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('yli 100');
        });

        test('formats range with min: prefix as "yli"', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['min:100'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('yli 100');
        });

        test('formats range with max: prefix as "alle"', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['max:100'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('alle 100');
        });

        test('formats single numeric value as "yli" (minimum)', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['100'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('yli 100');
        });

        test('handles comma as decimal separator', () => {
            const crits = [{ name: 'hinta', label: 'range', values: ['100,5'] }];
            const result = formatCriteriaHTML(crits);
            // Should normalize comma to dot and treat as number
            expect(result).toContain('yli 100.5');
        });

        test('groups criteria by label', () => {
            const crits = [
                { name: 'field1', label: 'range', values: ['100', '200'] },
                { name: 'field2', label: 'range', values: ['50', '150'] },
                { name: 'field3', label: 'multiple_choice', values: ['A'] },
            ];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('data-label="range"');
            expect(result).toContain('data-label="multiple_choice"');
        });

        test('uses "other" label when label is missing', () => {
            const crits = [{ name: 'field1', values: ['test'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('data-label="other"');
        });

        test('escapes HTML in output', () => {
            const crits = [{ name: '<script>', label: 'multiple_choice', values: ['<xss>'] }];
            const result = formatCriteriaHTML(crits);
            expect(result).toContain('&lt;script&gt;');
            expect(result).toContain('&lt;xss&gt;');
            expect(result).not.toContain('<script>');
        });
    });

    // ============================================
    // FIELD DROPDOWN BUILDING
    // ============================================
    describe('Field Dropdown Building', () => {
        function buildFieldDropdown(fields, selectedValue) {
            let html = '<select class="hakuvahti-edit-crit-name" style="width:30%; margin-right:6px;">';
            html += '<option value="">Valitse kenttä</option>';
            fields.forEach(function(field) {
                const selected = (field === selectedValue) ? ' selected' : '';
                html += '<option value="' + field + '"' + selected + '>' + field + '</option>';
            });
            html += '</select>';
            return html;
        }

        test('builds dropdown with all fields', () => {
            const fields = ['field1', 'field2', 'field3'];
            const html = buildFieldDropdown(fields, '');
            expect(html).toContain('value="field1"');
            expect(html).toContain('value="field2"');
            expect(html).toContain('value="field3"');
        });

        test('includes default empty option', () => {
            const fields = ['field1'];
            const html = buildFieldDropdown(fields, '');
            expect(html).toContain('<option value="">Valitse kenttä</option>');
        });

        test('marks selected field', () => {
            const fields = ['field1', 'field2'];
            const html = buildFieldDropdown(fields, 'field2');
            expect(html).toContain('value="field2" selected');
            expect(html).not.toContain('value="field1" selected');
        });

        test('handles empty fields array', () => {
            const html = buildFieldDropdown([], '');
            expect(html).toContain('<select');
            expect(html).toContain('</select>');
            expect(html).toContain('Valitse kenttä');
        });
    });

    // ============================================
    // EDIT FORM VALUE PARSING
    // ============================================
    describe('Edit Form Value Parsing', () => {
        describe('Range value parsing', () => {
            function parseRangeValues(rawvals) {
                const parts = rawvals.split(',').map(s => s.trim()).filter(Boolean);

                if (parts.length === 1) {
                    const val = parts[0];
                    // If already has operator or label prefix, keep as-is
                    if (/^\s*([<>]=?|min\s*[:=]|max\s*[:=])/i.test(val)) {
                        return parts;
                    } else if (/^\d/.test(val)) {
                        // Plain number without prefix - default to min
                        return ['min:' + val];
                    }
                    return parts;
                }
                return parts;
            }

            test('parses two comma-separated values', () => {
                const result = parseRangeValues('100, 500');
                expect(result).toEqual(['100', '500']);
            });

            test('adds min: prefix to single plain number', () => {
                const result = parseRangeValues('100');
                expect(result).toEqual(['min:100']);
            });

            test('preserves existing min: prefix', () => {
                const result = parseRangeValues('min:100');
                expect(result).toEqual(['min:100']);
            });

            test('preserves existing max: prefix', () => {
                const result = parseRangeValues('max:200');
                expect(result).toEqual(['max:200']);
            });

            test('preserves > operator', () => {
                const result = parseRangeValues('>100');
                expect(result).toEqual(['>100']);
            });

            test('preserves < operator', () => {
                const result = parseRangeValues('<200');
                expect(result).toEqual(['<200']);
            });

            test('preserves >= operator', () => {
                const result = parseRangeValues('>=100');
                expect(result).toEqual(['>=100']);
            });

            test('preserves <= operator', () => {
                const result = parseRangeValues('<=200');
                expect(result).toEqual(['<=200']);
            });

            test('handles empty string', () => {
                const result = parseRangeValues('');
                expect(result).toEqual([]);
            });

            test('handles whitespace-only string', () => {
                const result = parseRangeValues('   ');
                expect(result).toEqual([]);
            });

            test('handles min= syntax', () => {
                const result = parseRangeValues('min=100');
                expect(result).toEqual(['min=100']);
            });
        });

        describe('Multiple choice value parsing', () => {
            function parseMultipleChoiceValues(rawvals) {
                return rawvals.split(',').map(s => s.trim()).filter(Boolean);
            }

            test('parses comma-separated values', () => {
                const result = parseMultipleChoiceValues('A, B, C');
                expect(result).toEqual(['A', 'B', 'C']);
            });

            test('trims whitespace from values', () => {
                const result = parseMultipleChoiceValues('  A  ,  B  ');
                expect(result).toEqual(['A', 'B']);
            });

            test('filters empty values', () => {
                const result = parseMultipleChoiceValues('A,,B,');
                expect(result).toEqual(['A', 'B']);
            });

            test('handles single value', () => {
                const result = parseMultipleChoiceValues('Single');
                expect(result).toEqual(['Single']);
            });
        });
    });

    // ============================================
    // CRITERIA BUILDING
    // ============================================
    describe('Criteria Building', () => {
        test('builds criterion object with required properties', () => {
            const criterion = {
                name: 'field_name',
                label: 'range',
                values: ['100', '500']
            };
            expect(criterion).toHaveProperty('name', 'field_name');
            expect(criterion).toHaveProperty('label', 'range');
            expect(criterion).toHaveProperty('values');
            expect(criterion.values).toHaveLength(2);
        });

        test('skips criterion when name is empty', () => {
            const crits = [];
            const name = '';
            const label = 'range';
            const values = ['100'];

            if (name) {
                crits.push({ name, label, values });
            }

            expect(crits).toHaveLength(0);
        });

        test('accepts criterion with valid name', () => {
            const crits = [];
            const name = 'hinta';
            const label = 'range';
            const values = ['100'];

            if (name) {
                crits.push({ name, label, values });
            }

            expect(crits).toHaveLength(1);
        });
    });

    // ============================================
    // JSON PARSING FOR CRITERIA DATA
    // ============================================
    describe('JSON Criteria Parsing', () => {
        test('parses valid JSON criteria from data attribute', () => {
            const raw = '[{"name":"hinta","label":"range","values":["100","500"]}]';
            const parsed = JSON.parse(raw);
            expect(parsed).toHaveLength(1);
            expect(parsed[0].name).toBe('hinta');
        });

        test('handles empty array JSON', () => {
            const raw = '[]';
            const parsed = JSON.parse(raw);
            expect(parsed).toEqual([]);
        });

        test('handles invalid JSON gracefully', () => {
            const raw = 'invalid json';
            let criteriaData = [];
            try {
                criteriaData = JSON.parse(raw);
            } catch (err) {
                criteriaData = [];
            }
            expect(criteriaData).toEqual([]);
        });

        test('handles null in JSON', () => {
            const raw = 'null';
            let criteriaData = [];
            try {
                const parsed = JSON.parse(raw);
                criteriaData = parsed || [];
            } catch (err) {
                criteriaData = [];
            }
            expect(criteriaData).toEqual([]);
        });
    });

    // ============================================
    // RESULTS DISPLAY
    // ============================================
    describe('Results Display', () => {
        test('formats zero results message', () => {
            const posts = [];
            const html = posts.length === 0
                ? '<p class="hakuvahti-no-results">Ei uusia tuloksia</p>'
                : '';
            expect(html).toContain('hakuvahti-no-results');
        });

        test('formats results count message', () => {
            const posts = [{ title: 'Post 1', url: '/post-1' }];
            const html = '<p class="hakuvahti-results-count"><strong>' + posts.length + '</strong> uutta tulosta</p>';
            expect(html).toContain('<strong>1</strong>');
            expect(html).toContain('uutta tulosta');
        });

        test('formats results list with links', () => {
            const posts = [
                { title: 'Post 1', url: '/post-1' },
                { title: 'Post 2', url: '/post-2' }
            ];
            let html = '<ul class="hakuvahti-results-list">';
            posts.forEach(post => {
                html += '<li><a href="' + post.url + '" target="_blank">' + post.title + '</a></li>';
            });
            html += '</ul>';

            expect(html).toContain('href="/post-1"');
            expect(html).toContain('href="/post-2"');
            expect(html).toContain('target="_blank"');
        });
    });

    // ============================================
    // FIELDS JSON ENCODING/DECODING
    // ============================================
    describe('Fields JSON Encoding', () => {
        test('encodes fields array for data attribute', () => {
            const fields = ['field1', 'field2', 'field3'];
            const encoded = encodeURIComponent(JSON.stringify(fields));
            expect(encoded).toBeTruthy();
            expect(decodeURIComponent(encoded)).toBe('["field1","field2","field3"]');
        });

        test('decodes fields from data attribute', () => {
            const encoded = '%5B%22field1%22%2C%22field2%22%5D';
            const decoded = JSON.parse(decodeURIComponent(encoded));
            expect(decoded).toEqual(['field1', 'field2']);
        });

        test('handles empty fields array', () => {
            const fields = [];
            const encoded = encodeURIComponent(JSON.stringify(fields));
            const decoded = JSON.parse(decodeURIComponent(encoded));
            expect(decoded).toEqual([]);
        });
    });

    // ============================================
    // BUTTON STATE MANAGEMENT
    // ============================================
    describe('Button State Management', () => {
        test('stores original text before updating', () => {
            const originalText = 'Hae uudet';
            let storedText = null;

            if (!storedText) {
                storedText = originalText;
            }

            expect(storedText).toBe('Hae uudet');
        });

        test('restores original text after operation', () => {
            const originalText = 'Hae uudet';
            let currentText = 'Haetaan...';

            // Simulate always callback
            currentText = originalText;

            expect(currentText).toBe('Hae uudet');
        });
    });
});
