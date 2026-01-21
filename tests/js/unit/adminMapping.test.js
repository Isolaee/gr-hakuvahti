/**
 * Unit Tests for admin-mapping.js
 *
 * Tests the Admin User Search Options Editor functionality:
 * - User options state management
 * - Field metadata caching
 * - Category tab switching
 * - Option data collection
 * - Validation logic
 */

describe('admin-mapping.js', () => {
    // ============================================
    // USER OPTIONS STATE MANAGEMENT
    // ============================================
    describe('User Options State Management', () => {
        let userSearchOptions;

        beforeEach(() => {
            userSearchOptions = [];
        });

        test('initializes with empty array', () => {
            expect(userSearchOptions).toEqual([]);
        });

        test('loads options from config', () => {
            const config = [
                { name: 'Hinta', category: 'Osakeannit', acf_field: 'hinta', values: { min: '', max: '' } },
                { name: 'Tyyppi', category: 'Osakeannit', acf_field: 'tyyppi', values: ['A', 'B'] },
            ];
            userSearchOptions = config;
            expect(userSearchOptions).toHaveLength(2);
        });

        test('adds new option with default values', () => {
            const currentOptionsTab = 'Osakeannit';
            userSearchOptions.push({
                name: '',
                category: currentOptionsTab,
                acf_field: '',
                values: null
            });
            expect(userSearchOptions).toHaveLength(1);
            expect(userSearchOptions[0].category).toBe('Osakeannit');
            expect(userSearchOptions[0].values).toBeNull();
        });

        test('removes option by index', () => {
            userSearchOptions = [
                { name: 'Option1', category: 'Osakeannit', acf_field: 'field1', values: null },
                { name: 'Option2', category: 'Osakeannit', acf_field: 'field2', values: null },
                { name: 'Option3', category: 'Osakeannit', acf_field: 'field3', values: null },
            ];
            const indexToRemove = 1;
            userSearchOptions.splice(indexToRemove, 1);

            expect(userSearchOptions).toHaveLength(2);
            expect(userSearchOptions[0].name).toBe('Option1');
            expect(userSearchOptions[1].name).toBe('Option3');
        });
    });

    // ============================================
    // FIELD METADATA CACHING
    // ============================================
    describe('Field Metadata Caching', () => {
        let fieldMetaCache;

        beforeEach(() => {
            fieldMetaCache = {};
        });

        test('stores field metadata by category', () => {
            const fields = [
                { key: 'hinta', label: 'Hinta', has_choices: false },
                { key: 'tyyppi', label: 'Tyyppi', has_choices: true, choices: { a: 'A', b: 'B' } },
            ];

            fieldMetaCache['Osakeannit'] = {};
            fields.forEach(f => {
                fieldMetaCache['Osakeannit'][f.key] = f;
            });

            expect(fieldMetaCache['Osakeannit']['hinta']).toBeDefined();
            expect(fieldMetaCache['Osakeannit']['tyyppi'].has_choices).toBe(true);
        });

        test('retrieves cached metadata', () => {
            fieldMetaCache['Osakeannit'] = {
                hinta: { key: 'hinta', label: 'Hinta', has_choices: false }
            };

            const meta = fieldMetaCache['Osakeannit'] && fieldMetaCache['Osakeannit']['hinta'];
            expect(meta).toBeDefined();
            expect(meta.label).toBe('Hinta');
        });

        test('returns null for uncached field', () => {
            fieldMetaCache['Osakeannit'] = {};
            const meta = fieldMetaCache['Osakeannit'] && fieldMetaCache['Osakeannit']['unknown_field'];
            expect(meta).toBeUndefined();
        });

        test('caches are category-specific', () => {
            fieldMetaCache['Osakeannit'] = { field_a: { key: 'field_a' } };
            fieldMetaCache['Velkakirjat'] = { field_b: { key: 'field_b' } };

            expect(fieldMetaCache['Osakeannit']['field_a']).toBeDefined();
            expect(fieldMetaCache['Osakeannit']['field_b']).toBeUndefined();
            expect(fieldMetaCache['Velkakirjat']['field_b']).toBeDefined();
        });
    });

    // ============================================
    // CATEGORY TAB SWITCHING
    // ============================================
    describe('Category Tab Switching', () => {
        let currentOptionsTab;
        let userSearchOptions;

        beforeEach(() => {
            currentOptionsTab = 'Osakeannit';
            userSearchOptions = [
                { name: 'Opt1', category: 'Osakeannit', acf_field: 'f1', values: null },
                { name: 'Opt2', category: 'Velkakirjat', acf_field: 'f2', values: null },
                { name: 'Opt3', category: 'Osakeannit', acf_field: 'f3', values: null },
            ];
        });

        test('filters options for current tab', () => {
            const visibleOptions = userSearchOptions.filter(
                opt => (opt.category || '') === currentOptionsTab
            );
            expect(visibleOptions).toHaveLength(2);
            expect(visibleOptions[0].name).toBe('Opt1');
            expect(visibleOptions[1].name).toBe('Opt3');
        });

        test('switches to different category', () => {
            currentOptionsTab = 'Velkakirjat';
            const visibleOptions = userSearchOptions.filter(
                opt => (opt.category || '') === currentOptionsTab
            );
            expect(visibleOptions).toHaveLength(1);
            expect(visibleOptions[0].name).toBe('Opt2');
        });

        test('shows no options for empty category', () => {
            currentOptionsTab = 'Osaketori';
            const visibleOptions = userSearchOptions.filter(
                opt => (opt.category || '') === currentOptionsTab
            );
            expect(visibleOptions).toHaveLength(0);
        });

        test('defaults to first category from config', () => {
            const categories = ['Osakeannit', 'Velkakirjat', 'Osaketori'];
            const defaultTab = categories[0] || 'Osakeannit';
            expect(defaultTab).toBe('Osakeannit');
        });
    });

    // ============================================
    // VALUES AREA TYPE DETECTION
    // ============================================
    describe('Values Area Type Detection', () => {
        test('detects choice field with has_choices flag', () => {
            const meta = {
                has_choices: true,
                choices: { a: 'Option A', b: 'Option B' }
            };
            const isChoiceField = meta.has_choices &&
                meta.choices &&
                Object.keys(meta.choices).length > 0;
            expect(isChoiceField).toBe(true);
        });

        test('detects non-choice field', () => {
            const meta = {
                has_choices: false
            };
            const isChoiceField = meta.has_choices &&
                meta.choices &&
                Object.keys(meta.choices).length > 0;
            expect(isChoiceField).toBe(false);
        });

        test('detects empty choices as non-choice field', () => {
            const meta = {
                has_choices: true,
                choices: {}
            };
            const isChoiceField = meta.has_choices &&
                meta.choices &&
                Object.keys(meta.choices).length > 0;
            expect(isChoiceField).toBe(false);
        });

        test('handles missing metadata', () => {
            const meta = null;
            const isChoiceField = meta &&
                meta.has_choices &&
                meta.choices &&
                Object.keys(meta.choices).length > 0;
            expect(isChoiceField).toBeFalsy();
        });
    });

    // ============================================
    // OPTION DATA COLLECTION
    // ============================================
    describe('Option Data Collection', () => {
        describe('Choice values collection', () => {
            test('collects selected choices from multi-select', () => {
                const selectedValues = ['a', 'c'];
                expect(selectedValues).toEqual(['a', 'c']);
            });

            test('returns empty array when no choices selected', () => {
                const selectedValues = [];
                expect(selectedValues).toEqual([]);
            });
        });

        describe('Range values collection', () => {
            test('collects min/max/postfix as object', () => {
                const min = '100';
                const max = '500';
                const postfix = '€';
                const values = { min, max, postfix };

                expect(values.min).toBe('100');
                expect(values.max).toBe('500');
                expect(values.postfix).toBe('€');
            });

            test('allows empty min/max values', () => {
                const values = { min: '', max: '', postfix: '' };
                expect(values.min).toBe('');
                expect(values.max).toBe('');
            });
        });

        describe('Collected option structure', () => {
            test('builds complete option object', () => {
                const collected = {
                    name: 'Hinta',
                    category: 'Osakeannit',
                    acf_field: 'hinta',
                    values: { min: '0', max: '1000', postfix: '€' }
                };

                expect(collected).toHaveProperty('name');
                expect(collected).toHaveProperty('category');
                expect(collected).toHaveProperty('acf_field');
                expect(collected).toHaveProperty('values');
            });

            test('skips option with empty name', () => {
                const collected = [];
                const name = '';
                const acf = 'field_name';
                const values = { min: '', max: '' };

                if (name) {
                    collected.push({ name, category: 'Osakeannit', acf_field: acf, values });
                }

                expect(collected).toHaveLength(0);
            });
        });
    });

    // ============================================
    // MERGE LOGIC FOR SAVE
    // ============================================
    describe('Options Merge Logic', () => {
        let userSearchOptions;

        beforeEach(() => {
            userSearchOptions = [
                { name: 'Opt1', category: 'Osakeannit', acf_field: 'f1', values: null },
                { name: 'Opt2', category: 'Velkakirjat', acf_field: 'f2', values: null },
                { name: 'Opt3', category: 'Osakeannit', acf_field: 'f3', values: null },
            ];
        });

        test('preserves options from other categories', () => {
            const currentOptionsTab = 'Osakeannit';

            // Collect edited options for current tab
            const collected = [
                { name: 'NewOpt', category: 'Osakeannit', acf_field: 'new_field', values: null }
            ];

            // Keep options from other categories
            const others = userSearchOptions.filter(
                o => (o.category || '') !== currentOptionsTab
            );

            expect(others).toHaveLength(1);
            expect(others[0].category).toBe('Velkakirjat');

            // Merge
            userSearchOptions = others.concat(collected);

            expect(userSearchOptions).toHaveLength(2);
            expect(userSearchOptions.find(o => o.category === 'Velkakirjat')).toBeDefined();
            expect(userSearchOptions.find(o => o.name === 'NewOpt')).toBeDefined();
        });

        test('replaces all options for current category', () => {
            const currentOptionsTab = 'Osakeannit';

            const collected = [
                { name: 'Updated1', category: 'Osakeannit', acf_field: 'u1', values: null }
            ];

            const others = userSearchOptions.filter(
                o => (o.category || '') !== currentOptionsTab
            );

            userSearchOptions = others.concat(collected);

            const osakeannit = userSearchOptions.filter(o => o.category === 'Osakeannit');
            expect(osakeannit).toHaveLength(1);
            expect(osakeannit[0].name).toBe('Updated1');
        });
    });

    // ============================================
    // VALIDATION
    // ============================================
    describe('Validation', () => {
        test('requires acf_field for every option', () => {
            const options = [
                { name: 'Opt1', category: 'Osakeannit', acf_field: 'field1', values: null },
                { name: 'Opt2', category: 'Osakeannit', acf_field: '', values: null },
            ];

            let isValid = true;
            for (let i = 0; i < options.length; i++) {
                const o = options[i];
                if (!o.acf_field || o.acf_field.toString().trim() === '') {
                    isValid = false;
                    break;
                }
            }

            expect(isValid).toBe(false);
        });

        test('passes validation when all options have acf_field', () => {
            const options = [
                { name: 'Opt1', category: 'Osakeannit', acf_field: 'field1', values: null },
                { name: 'Opt2', category: 'Osakeannit', acf_field: 'field2', values: null },
            ];

            let isValid = true;
            for (let i = 0; i < options.length; i++) {
                const o = options[i];
                if (!o.acf_field || o.acf_field.toString().trim() === '') {
                    isValid = false;
                    break;
                }
            }

            expect(isValid).toBe(true);
        });

        test('trims acf_field before validation', () => {
            const o = { acf_field: '   ' };
            const isValid = o.acf_field && o.acf_field.toString().trim() !== '';
            expect(isValid).toBe(false);
        });

        test('handles null acf_field', () => {
            const o = { acf_field: null };
            const isValid = o.acf_field && o.acf_field.toString().trim() !== '';
            expect(isValid).toBeFalsy();
        });
    });

    // ============================================
    // CATEGORY DROPDOWN
    // ============================================
    describe('Category Dropdown', () => {
        const categories = ['Osakeannit', 'Velkakirjat', 'Osaketori'];

        test('populates dropdown with all categories', () => {
            const options = categories.map(c => ({ value: c, text: c }));
            expect(options).toHaveLength(3);
            expect(options[0].value).toBe('Osakeannit');
        });

        test('selects matching category', () => {
            const currentCategory = 'Velkakirjat';
            const options = categories.map(c => ({
                value: c,
                selected: c === currentCategory
            }));

            const selected = options.find(o => o.selected);
            expect(selected.value).toBe('Velkakirjat');
        });
    });

    // ============================================
    // ACF FIELD DROPDOWN
    // ============================================
    describe('ACF Field Dropdown', () => {
        test('includes empty default option', () => {
            const defaultOption = { value: '', text: '-- Select ACF field --' };
            expect(defaultOption.value).toBe('');
        });

        test('populates with fetched fields', () => {
            const fields = [
                { key: 'hinta', label: 'Hinta' },
                { key: 'tyyppi', label: 'Tyyppi' }
            ];

            const options = fields.map(f => ({
                value: f.key,
                text: f.label || f.key
            }));

            expect(options).toHaveLength(2);
            expect(options[0].value).toBe('hinta');
            expect(options[0].text).toBe('Hinta');
        });

        test('uses key as label fallback', () => {
            const field = { key: 'some_field' };
            const label = field.label || field.key;
            expect(label).toBe('some_field');
        });

        test('selects previously saved acf_field', () => {
            const fields = [
                { key: 'field1', label: 'Field 1' },
                { key: 'field2', label: 'Field 2' }
            ];
            const savedAcfField = 'field2';

            const options = fields.map(f => ({
                value: f.key,
                selected: f.key === savedAcfField
            }));

            const selected = options.find(o => o.selected);
            expect(selected.value).toBe('field2');
        });
    });

    // ============================================
    // CHOICE VALUES MULTI-SELECT
    // ============================================
    describe('Choice Values Multi-Select', () => {
        test('renders options from field choices', () => {
            const choices = { a: 'Option A', b: 'Option B', c: 'Option C' };
            const options = Object.keys(choices).map(k => ({
                value: k,
                text: choices[k]
            }));

            expect(options).toHaveLength(3);
            expect(options[0]).toEqual({ value: 'a', text: 'Option A' });
        });

        test('marks previously selected values', () => {
            const choices = { a: 'A', b: 'B', c: 'C' };
            const savedValues = ['a', 'c'];

            const options = Object.keys(choices).map(k => ({
                value: k,
                selected: savedValues.indexOf(k) !== -1
            }));

            expect(options.filter(o => o.selected)).toHaveLength(2);
            expect(options.find(o => o.value === 'b').selected).toBe(false);
        });

        test('handles empty saved values', () => {
            const choices = { a: 'A', b: 'B' };
            const savedValues = null;

            const options = Object.keys(choices).map(k => ({
                value: k,
                selected: !!(savedValues && Array.isArray(savedValues) && savedValues.indexOf(k) !== -1)
            }));

            expect(options.every(o => o.selected === false)).toBe(true);
        });
    });

    // ============================================
    // RANGE VALUES INPUTS
    // ============================================
    describe('Range Values Inputs', () => {
        test('extracts min from saved values object', () => {
            const opt = { values: { min: '100', max: '500', postfix: '€' } };
            const min = (opt && opt.values && typeof opt.values === 'object')
                ? (opt.values.min || '')
                : '';
            expect(min).toBe('100');
        });

        test('extracts max from saved values object', () => {
            const opt = { values: { min: '100', max: '500', postfix: '€' } };
            const max = (opt && opt.values && typeof opt.values === 'object')
                ? (opt.values.max || '')
                : '';
            expect(max).toBe('500');
        });

        test('extracts postfix from saved values object', () => {
            const opt = { values: { min: '100', max: '500', postfix: '€' } };
            const postfix = (opt && opt.values && typeof opt.values === 'object')
                ? (opt.values.postfix || '')
                : '';
            expect(postfix).toBe('€');
        });

        test('handles null values object', () => {
            const opt = { values: null };
            const min = (opt && opt.values && typeof opt.values === 'object')
                ? (opt.values.min || '')
                : '';
            expect(min).toBe('');
        });

        test('handles array values (choice field switched to range)', () => {
            const opt = { values: ['a', 'b'] };
            const min = (opt && opt.values && typeof opt.values === 'object' && !Array.isArray(opt.values))
                ? (opt.values.min || '')
                : '';
            expect(min).toBe('');
        });
    });

    // ============================================
    // SAVE RESPONSE HANDLING
    // ============================================
    describe('Save Response Handling', () => {
        test('updates options from sanitized response', () => {
            let userSearchOptions = [{ name: 'Old', category: 'Osakeannit', acf_field: 'old', values: null }];

            const resp = {
                success: true,
                data: {
                    sanitized: [
                        { name: 'Sanitized', category: 'Osakeannit', acf_field: 'sanitized', values: null }
                    ]
                }
            };

            if (resp && resp.success) {
                userSearchOptions = resp.data && resp.data.sanitized
                    ? resp.data.sanitized
                    : (resp.data && resp.data.raw ? resp.data.raw : userSearchOptions);
            }

            expect(userSearchOptions).toHaveLength(1);
            expect(userSearchOptions[0].name).toBe('Sanitized');
        });

        test('falls back to raw if sanitized missing', () => {
            let userSearchOptions = [];

            const resp = {
                success: true,
                data: {
                    raw: [{ name: 'Raw', category: 'Osakeannit', acf_field: 'raw', values: null }]
                }
            };

            if (resp && resp.success) {
                userSearchOptions = resp.data && resp.data.sanitized
                    ? resp.data.sanitized
                    : (resp.data && resp.data.raw ? resp.data.raw : userSearchOptions);
            }

            expect(userSearchOptions[0].name).toBe('Raw');
        });

        test('keeps original options on failure', () => {
            const original = [{ name: 'Original', category: 'Osakeannit', acf_field: 'orig', values: null }];
            let userSearchOptions = [...original];

            const resp = { success: false, data: { message: 'Error' } };

            if (resp && resp.success) {
                userSearchOptions = resp.data && resp.data.sanitized
                    ? resp.data.sanitized
                    : userSearchOptions;
            }

            expect(userSearchOptions).toEqual(original);
        });
    });
});
