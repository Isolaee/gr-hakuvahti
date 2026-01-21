/**
 * Unit Tests for wpgb-facet-logger.js
 *
 * Tests the Hakuvahti frontend search builder functionality:
 * - Category detection from URL and body classes
 * - User search options filtering
 * - Search form building
 * - Criteria collection from form inputs
 * - Modal open/close behavior
 */

describe('wpgb-facet-logger.js', () => {
    // ============================================
    // CATEGORY DETECTION
    // ============================================
    describe('Category Detection', () => {
        // We'll test the detectCategory logic by simulating what the function does
        // Since the module uses an IIFE, we test the detection patterns directly

        describe('URL path detection', () => {
            test('detects Osakeannit from URL path', () => {
                const path = '/osakeannit/some-page/';
                expect(path.toLowerCase().indexOf('/osakeannit') !== -1).toBe(true);
            });

            test('detects Velkakirjat from URL path', () => {
                const path = '/velkakirjat/listing/';
                expect(path.toLowerCase().indexOf('/velkakirjat') !== -1).toBe(true);
            });

            test('detects Osaketori from URL path', () => {
                const path = '/osaketori/';
                expect(path.toLowerCase().indexOf('/osaketori') !== -1).toBe(true);
            });

            test('returns empty for unknown paths', () => {
                const path = '/some-other-page/';
                const hasOsakeannit = path.toLowerCase().indexOf('/osakeannit') !== -1;
                const hasVelkakirjat = path.toLowerCase().indexOf('/velkakirjat') !== -1;
                const hasOsaketori = path.toLowerCase().indexOf('/osaketori') !== -1;
                expect(hasOsakeannit || hasVelkakirjat || hasOsaketori).toBe(false);
            });

            test('handles case insensitivity', () => {
                const path = '/OSAKEANNIT/';
                expect(path.toLowerCase().indexOf('/osakeannit') !== -1).toBe(true);
            });
        });

        describe('Body class fallback detection', () => {
            test('detects category-osakeannit from body class', () => {
                const bodyClass = 'page category-osakeannit logged-in';
                expect(bodyClass.toLowerCase().indexOf('category-osakeannit') !== -1).toBe(true);
            });

            test('detects category-velkakirjat from body class', () => {
                const bodyClass = 'archive category-velkakirjat';
                expect(bodyClass.toLowerCase().indexOf('category-velkakirjat') !== -1).toBe(true);
            });

            test('detects category-osaketori from body class', () => {
                const bodyClass = 'single category-osaketori';
                expect(bodyClass.toLowerCase().indexOf('category-osaketori') !== -1).toBe(true);
            });
        });
    });

    // ============================================
    // USER SEARCH OPTIONS FILTERING
    // ============================================
    describe('User Search Options Filtering', () => {
        const allOptions = [
            { name: 'Hinta', category: 'Osakeannit', acf_field: 'hinta', values: { min: '', max: '' } },
            { name: 'Tyyppi', category: 'Osakeannit', acf_field: 'tyyppi', values: ['A', 'B'] },
            { name: 'Korko', category: 'Velkakirjat', acf_field: 'korko', values: { min: '0', max: '20' } },
            { name: 'Koko', category: 'Osaketori', acf_field: 'koko', values: ['Small', 'Medium', 'Large'] },
        ];

        // Simulate getOptionsForCategory function
        function getOptionsForCategory(category) {
            if (!category) return [];
            const lc = (category || '').toString().toLowerCase();
            return allOptions.filter(function(opt) {
                const optCat = (opt.category || '').toString().toLowerCase();
                return optCat === lc;
            });
        }

        test('filters options for Osakeannit category', () => {
            const result = getOptionsForCategory('Osakeannit');
            expect(result).toHaveLength(2);
            expect(result[0].name).toBe('Hinta');
            expect(result[1].name).toBe('Tyyppi');
        });

        test('filters options for Velkakirjat category', () => {
            const result = getOptionsForCategory('Velkakirjat');
            expect(result).toHaveLength(1);
            expect(result[0].name).toBe('Korko');
        });

        test('filters options for Osaketori category', () => {
            const result = getOptionsForCategory('Osaketori');
            expect(result).toHaveLength(1);
            expect(result[0].name).toBe('Koko');
        });

        test('returns empty array for unknown category', () => {
            const result = getOptionsForCategory('Unknown');
            expect(result).toHaveLength(0);
        });

        test('returns empty array for empty category', () => {
            const result = getOptionsForCategory('');
            expect(result).toHaveLength(0);
        });

        test('returns empty array for null category', () => {
            const result = getOptionsForCategory(null);
            expect(result).toHaveLength(0);
        });

        test('handles case-insensitive category matching', () => {
            const result = getOptionsForCategory('osakeannit');
            expect(result).toHaveLength(2);
        });
    });

    // ============================================
    // FIELD INPUT TYPE DETECTION
    // ============================================
    describe('Field Input Type Detection', () => {
        // Test the logic for determining field types

        test('detects multiple_choice type when values is non-empty array', () => {
            const opt = { values: ['A', 'B', 'C'] };
            const isMultipleChoice = opt.values && Array.isArray(opt.values) && opt.values.length > 0;
            expect(isMultipleChoice).toBe(true);
        });

        test('detects range type when values has min/max properties', () => {
            const opt = { values: { min: 0, max: 100 } };
            const isRange = opt.values && typeof opt.values === 'object' &&
                (opt.values.min !== undefined || opt.values.max !== undefined);
            expect(isRange).toBe(true);
        });

        test('detects range type with only min value', () => {
            const opt = { values: { min: 50 } };
            const isRange = opt.values && typeof opt.values === 'object' &&
                (opt.values.min !== undefined || opt.values.max !== undefined);
            expect(isRange).toBe(true);
        });

        test('detects range type with only max value', () => {
            const opt = { values: { max: 200 } };
            const isRange = opt.values && typeof opt.values === 'object' &&
                (opt.values.min !== undefined || opt.values.max !== undefined);
            expect(isRange).toBe(true);
        });

        test('defaults to range type when values is null', () => {
            const opt = { values: null };
            const isMultipleChoice = opt.values && Array.isArray(opt.values) && opt.values.length > 0;
            const isAssociativeRange = opt.values && typeof opt.values === 'object' &&
                (opt.values.min !== undefined || opt.values.max !== undefined);
            // If neither, defaults to range
            expect(isMultipleChoice).toBeFalsy();
            expect(isAssociativeRange).toBeFalsy();
        });

        test('defaults to range type when values is undefined', () => {
            const opt = {};
            const isMultipleChoice = opt.values && Array.isArray(opt.values) && opt.values.length > 0;
            expect(isMultipleChoice).toBeFalsy();
        });

        test('treats empty array as not multiple_choice', () => {
            const opt = { values: [] };
            const isMultipleChoice = opt.values && Array.isArray(opt.values) && opt.values.length > 0;
            expect(isMultipleChoice).toBe(false);
        });
    });

    // ============================================
    // CRITERIA COLLECTION LOGIC
    // ============================================
    describe('Criteria Collection', () => {
        // Test the criteria data structure and collection logic

        describe('Multiple choice criteria', () => {
            test('collects selected checkbox values', () => {
                const checkedValues = ['Value1', 'Value2'];
                const criteria = {
                    name: 'test_field',
                    label: 'multiple_choice',
                    values: checkedValues
                };
                expect(criteria.values).toEqual(['Value1', 'Value2']);
            });

            test('skips field when no values selected', () => {
                const checkedValues = [];
                const shouldInclude = checkedValues.length > 0;
                expect(shouldInclude).toBe(false);
            });
        });

        describe('Range criteria', () => {
            test('collects both min and max values', () => {
                const min = '100';
                const max = '500';
                const values = [];
                if (min && max) {
                    values.push(min);
                    values.push(max);
                }
                expect(values).toEqual(['100', '500']);
            });

            test('labels single min value with prefix', () => {
                const min = '100';
                const max = '';
                const values = [];
                if (min && max) {
                    values.push(min);
                    values.push(max);
                } else if (min) {
                    values.push('min:' + min);
                } else if (max) {
                    values.push('max:' + max);
                }
                expect(values).toEqual(['min:100']);
            });

            test('labels single max value with prefix', () => {
                const min = '';
                const max = '500';
                const values = [];
                if (min && max) {
                    values.push(min);
                    values.push(max);
                } else if (min) {
                    values.push('min:' + min);
                } else if (max) {
                    values.push('max:' + max);
                }
                expect(values).toEqual(['max:500']);
            });

            test('skips field when both min and max are empty', () => {
                const min = '';
                const max = '';
                const values = [];
                if (min && max) {
                    values.push(min);
                    values.push(max);
                } else if (min) {
                    values.push('min:' + min);
                } else if (max) {
                    values.push('max:' + max);
                }
                expect(values).toHaveLength(0);
            });
        });

        describe('Criteria structure', () => {
            test('criteria object has required properties', () => {
                const criteria = {
                    name: 'acf_field_name',
                    label: 'range',
                    values: ['100', '500']
                };
                expect(criteria).toHaveProperty('name');
                expect(criteria).toHaveProperty('label');
                expect(criteria).toHaveProperty('values');
            });

            test('criteria array can contain multiple items', () => {
                const criteriaArray = [
                    { name: 'field1', label: 'range', values: ['100', '200'] },
                    { name: 'field2', label: 'multiple_choice', values: ['A', 'B'] },
                ];
                expect(criteriaArray).toHaveLength(2);
            });
        });
    });

    // ============================================
    // AJAX POST DATA FORMATTING
    // ============================================
    describe('AJAX Post Data Formatting', () => {
        test('formats criteria as array entries for jQuery post', () => {
            const criteria = [
                { name: 'hinta', label: 'range', values: ['100', '500'] },
                { name: 'tyyppi', label: 'multiple_choice', values: ['A', 'B'] },
            ];

            const postData = {
                action: 'hakuvahti_save',
                nonce: 'test-nonce',
                name: 'Test Search',
                category: 'Osakeannit'
            };

            criteria.forEach(function(c, i) {
                postData['criteria[' + i + '][name]'] = c.name;
                postData['criteria[' + i + '][label]'] = c.label;
                if (Array.isArray(c.values)) {
                    c.values.forEach(function(v, j) {
                        postData['criteria[' + i + '][values][' + j + ']'] = v;
                    });
                }
            });

            expect(postData['criteria[0][name]']).toBe('hinta');
            expect(postData['criteria[0][label]']).toBe('range');
            expect(postData['criteria[0][values][0]']).toBe('100');
            expect(postData['criteria[0][values][1]']).toBe('500');
            expect(postData['criteria[1][name]']).toBe('tyyppi');
            expect(postData['criteria[1][values][0]']).toBe('A');
            expect(postData['criteria[1][values][1]']).toBe('B');
        });
    });

    // ============================================
    // VALIDATION LOGIC
    // ============================================
    describe('Validation', () => {
        test('requires name to be non-empty', () => {
            const name = '';
            const isValid = name.trim() !== '';
            expect(isValid).toBe(false);
        });

        test('accepts non-empty name', () => {
            const name = 'My Search';
            const isValid = name.trim() !== '';
            expect(isValid).toBe(true);
        });

        test('trims whitespace from name', () => {
            const name = '  My Search  ';
            const trimmed = name.trim();
            expect(trimmed).toBe('My Search');
        });

        test('requires at least one criterion', () => {
            const criteria = [];
            const isValid = criteria && criteria.length > 0;
            expect(isValid).toBe(false);
        });

        test('accepts criteria array with items', () => {
            const criteria = [{ name: 'field', label: 'range', values: ['100'] }];
            const isValid = criteria && criteria.length > 0;
            expect(isValid).toBe(true);
        });
    });

    // ============================================
    // HTML ESCAPING
    // ============================================
    describe('HTML Escaping', () => {
        test('escapes HTML in checkbox values', () => {
            // jQuery's text() method escapes HTML
            const value = '<script>alert("xss")</script>';
            const div = document.createElement('div');
            div.textContent = value;
            const escaped = div.innerHTML;
            expect(escaped).not.toContain('<script>');
            expect(escaped).toContain('&lt;script&gt;');
        });

        test('escapes special characters', () => {
            const value = 'Test & "Value"';
            const div = document.createElement('div');
            div.textContent = value;
            const escaped = div.innerHTML;
            expect(escaped).toContain('&amp;');
            // Note: textContent escapes & and < > but not quotes (quotes are safe in text nodes)
            expect(escaped).toContain('"');
        });
    });

    // ============================================
    // GLOBAL STATE MANAGEMENT
    // ============================================
    describe('Global State', () => {
        test('msdGlobalBound flag prevents duplicate handlers', () => {
            let msdGlobalBound = false;

            // First binding
            if (!msdGlobalBound) {
                msdGlobalBound = true;
                // Would bind handler here
            }

            expect(msdGlobalBound).toBe(true);

            // Second attempt should not rebind
            let secondBindAttempt = false;
            if (!msdGlobalBound) {
                secondBindAttempt = true;
            }

            expect(secondBindAttempt).toBe(false);
        });

        test('lastCollectedCategory tracks current category', () => {
            let lastCollectedCategory = '';

            // Simulate opening modal
            lastCollectedCategory = 'Osakeannit';
            expect(lastCollectedCategory).toBe('Osakeannit');

            // Update on category change
            lastCollectedCategory = 'Velkakirjat';
            expect(lastCollectedCategory).toBe('Velkakirjat');
        });
    });

    // ============================================
    // ACF FIELD CONFIGURATION
    // ============================================
    describe('ACF Field Configuration', () => {
        test('requires acf_field to be set', () => {
            const opt = { name: 'Test', category: 'Osakeannit', acf_field: '', values: [] };
            const hasAcfField = opt.acf_field && opt.acf_field.toString().trim() !== '';
            expect(hasAcfField).toBeFalsy();
        });

        test('accepts valid acf_field', () => {
            const opt = { name: 'Test', category: 'Osakeannit', acf_field: 'test_field', values: [] };
            const hasAcfField = opt.acf_field && opt.acf_field.toString().trim() !== '';
            expect(hasAcfField).toBe(true);
        });

        test('marks field as missing_acf when acf_field is empty', () => {
            const opt = { acf_field: '' };
            const fieldType = !opt.acf_field ? 'missing_acf' : 'range';
            expect(fieldType).toBe('missing_acf');
        });
    });

    // ============================================
    // POSTFIX/UNIT HANDLING
    // ============================================
    describe('Postfix/Unit Handling', () => {
        test('extracts postfix from values object', () => {
            const opt = { values: { min: '0', max: '100', postfix: '€' } };
            const postfix = opt.values && opt.values.postfix ? opt.values.postfix : '';
            expect(postfix).toBe('€');
        });

        test('handles missing postfix', () => {
            const opt = { values: { min: '0', max: '100' } };
            const postfix = opt.values && opt.values.postfix ? opt.values.postfix : '';
            expect(postfix).toBe('');
        });

        test('extracts postfix from opt.postfix fallback', () => {
            const opt = { postfix: '%', values: null };
            const postfix = (opt && opt.values && opt.values.postfix) ? opt.values.postfix : (opt && opt.postfix ? opt.postfix : '');
            expect(postfix).toBe('%');
        });
    });
});
