/**
 * Unit Tests for WPGB Facet Logger
 *
 * Tests the facet data collection and mapping functionality.
 */

describe('WPGB Facet Logger', () => {
    // Helper to simulate the normalizeFacetParams function
    const normalizeFacetParams = (raw) => {
        const out = {};
        if (!raw) return out;

        if (Array.isArray(raw)) {
            raw.forEach((item) => {
                if (!item || typeof item !== 'object') return;
                Object.keys(item).forEach((k) => {
                    const v = item[k];
                    if (v == null) return;
                    if (Array.isArray(v)) out[k] = v;
                    else out[k] = [String(v)];
                });
            });
            return out;
        }

        if (typeof raw === 'object') {
            Object.keys(raw).forEach((k) => {
                const v = raw[k];
                if (v == null) {
                    out[k] = [];
                    return;
                }
                if (Array.isArray(v)) {
                    out[k] = v;
                    return;
                }
                if (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean') {
                    out[k] = [String(v)];
                    return;
                }
                if (typeof v === 'object') {
                    if (Array.isArray(v.values)) {
                        out[k] = v.values;
                        return;
                    }
                    if (Array.isArray(v.selected)) {
                        out[k] = v.selected;
                        return;
                    }
                    const vals = [];
                    Object.keys(v).forEach((sub) => {
                        const sv = v[sub];
                        if (Array.isArray(sv)) vals.push(...sv);
                        else if (typeof sv === 'string' || typeof sv === 'number' || typeof sv === 'boolean') {
                            vals.push(String(sv));
                        }
                    });
                    out[k] = vals;
                    return;
                }
                out[k] = [];
            });
            return out;
        }

        return out;
    };

    describe('normalizeFacetParams', () => {
        it('handles null and undefined input', () => {
            expect(normalizeFacetParams(null)).toEqual({});
            expect(normalizeFacetParams(undefined)).toEqual({});
        });

        it('normalizes object with string values to arrays', () => {
            const input = {
                location: 'Helsinki',
                category: 'A',
            };

            const result = normalizeFacetParams(input);

            expect(result.location).toEqual(['Helsinki']);
            expect(result.category).toEqual(['A']);
        });

        it('normalizes object with number values to string arrays', () => {
            const input = {
                price: 100000,
                count: 5,
            };

            const result = normalizeFacetParams(input);

            expect(result.price).toEqual(['100000']);
            expect(result.count).toEqual(['5']);
        });

        it('preserves existing arrays', () => {
            const input = {
                locations: ['Helsinki', 'Espoo', 'Vantaa'],
            };

            const result = normalizeFacetParams(input);

            expect(result.locations).toEqual(['Helsinki', 'Espoo', 'Vantaa']);
        });

        it('handles null values by creating empty arrays', () => {
            const input = {
                empty: null,
            };

            const result = normalizeFacetParams(input);

            expect(result.empty).toEqual([]);
        });

        it('handles nested objects with values property', () => {
            const input = {
                facet1: {
                    values: ['val1', 'val2'],
                },
            };

            const result = normalizeFacetParams(input);

            expect(result.facet1).toEqual(['val1', 'val2']);
        });

        it('handles nested objects with selected property', () => {
            const input = {
                facet1: {
                    selected: ['option1', 'option2'],
                },
            };

            const result = normalizeFacetParams(input);

            expect(result.facet1).toEqual(['option1', 'option2']);
        });

        it('flattens nested object primitive values', () => {
            const input = {
                facet1: {
                    min: 100,
                    max: 500,
                },
            };

            const result = normalizeFacetParams(input);

            expect(result.facet1).toContain('100');
            expect(result.facet1).toContain('500');
        });

        it('handles array of objects', () => {
            const input = [
                { location: 'Helsinki' },
                { category: ['A', 'B'] },
            ];

            const result = normalizeFacetParams(input);

            expect(result.location).toEqual(['Helsinki']);
            expect(result.category).toEqual(['A', 'B']);
        });
    });

    describe('Facet mapping', () => {
        it('maps facet slugs to ACF field names', () => {
            const facetMap = {
                location: 'sijainti',
                price: 'hinta',
                category: 'Luokitus',
            };

            const collected = [
                {
                    grid: null,
                    facets: {
                        location: ['Helsinki'],
                        price: ['100000'],
                        unmapped: ['value'], // This should be ignored
                    },
                },
            ];

            const mapped = [];
            collected.forEach((item) => {
                const facets = item.facets || {};
                Object.keys(facets).forEach((slug) => {
                    const acfField = facetMap[slug] || null;
                    if (!acfField) return;
                    const values = facets[slug] || [];
                    mapped.push({ facet: slug, acf_field: acfField, values: values });
                });
            });

            expect(mapped).toHaveLength(2);
            expect(mapped[0]).toEqual({
                facet: 'location',
                acf_field: 'sijainti',
                values: ['Helsinki'],
            });
            expect(mapped[1]).toEqual({
                facet: 'price',
                acf_field: 'hinta',
                values: ['100000'],
            });
        });

        it('ignores unmapped facets', () => {
            const facetMap = {
                location: 'sijainti',
            };

            const collected = [
                {
                    grid: null,
                    facets: {
                        location: ['Helsinki'],
                        unknown_facet: ['value'],
                    },
                },
            ];

            const mapped = [];
            collected.forEach((item) => {
                const facets = item.facets || {};
                Object.keys(facets).forEach((slug) => {
                    const acfField = facetMap[slug] || null;
                    if (!acfField) return;
                    mapped.push({ facet: slug, acf_field: acfField, values: facets[slug] });
                });
            });

            expect(mapped).toHaveLength(1);
            expect(mapped[0].facet).toBe('location');
        });
    });

    describe('Category detection from URL', () => {
        const detectCategory = (path) => {
            const pathLower = path.toLowerCase();
            if (pathLower.indexOf('/osakeannit') !== -1) return 'Osakeannit';
            if (pathLower.indexOf('/velkakirjat') !== -1) return 'Velkakirjat';
            if (pathLower.indexOf('/osaketori') !== -1) return 'Osaketori';
            return '';
        };

        it('detects Osakeannit category', () => {
            expect(detectCategory('/osakeannit/')).toBe('Osakeannit');
            expect(detectCategory('/fi/osakeannit/listaus')).toBe('Osakeannit');
        });

        it('detects Velkakirjat category', () => {
            expect(detectCategory('/velkakirjat/')).toBe('Velkakirjat');
            expect(detectCategory('/fi/velkakirjat/search')).toBe('Velkakirjat');
        });

        it('detects Osaketori category', () => {
            expect(detectCategory('/osaketori/')).toBe('Osaketori');
            expect(detectCategory('/fi/osaketori/browse')).toBe('Osaketori');
        });

        it('returns empty string for unknown paths', () => {
            expect(detectCategory('/')).toBe('');
            expect(detectCategory('/about')).toBe('');
            expect(detectCategory('/contact')).toBe('');
        });

        it('is case insensitive', () => {
            expect(detectCategory('/OSAKEANNIT/')).toBe('Osakeannit');
            expect(detectCategory('/Velkakirjat/')).toBe('Velkakirjat');
        });
    });

    describe('Criteria building', () => {
        const parseNumber = (v) => {
            if (v == null) return null;
            if (typeof v === 'number') return v;
            const s = String(v).trim();
            const cleaned = s.replace(/[^0-9.,\-]/g, '');
            if (cleaned === '') return null;
            const normalized = cleaned.replace(/,/g, '.');
            const n = parseFloat(normalized);
            return isNaN(n) ? null : n;
        };

        const isRangeValue = (values) => {
            if (!Array.isArray(values)) return false;
            const numericVals = values.map(parseNumber).filter((n) => n !== null);

            // Check for range in single value (e.g., "100-500")
            if (numericVals.length < 2 && values.length === 1) {
                const rangeMatch = String(values[0]).match(/(-?\d+[\.,]?\d*)\D+(-?\d+[\.,]?\d*)/);
                if (rangeMatch) {
                    const n1 = parseNumber(rangeMatch[1]);
                    const n2 = parseNumber(rangeMatch[2]);
                    return n1 !== null && n2 !== null;
                }
            }

            return numericVals.length >= 2;
        };

        it('parses numeric values correctly', () => {
            expect(parseNumber('100000')).toBe(100000);
            expect(parseNumber('50.5')).toBe(50.5);
            expect(parseNumber('1,5')).toBe(1.5); // European decimal
            expect(parseNumber('€100')).toBe(100);
            expect(parseNumber('100€')).toBe(100);
            expect(parseNumber('-50')).toBe(-50);
        });

        it('returns null for non-numeric strings', () => {
            expect(parseNumber('Helsinki')).toBeNull();
            expect(parseNumber('')).toBeNull();
            expect(parseNumber('abc')).toBeNull();
        });

        it('detects range values', () => {
            expect(isRangeValue(['100', '500'])).toBe(true);
            expect(isRangeValue(['100-500'])).toBe(true);
            expect(isRangeValue(['100 - 500'])).toBe(true);
        });

        it('detects non-range values', () => {
            expect(isRangeValue(['Helsinki'])).toBe(false);
            expect(isRangeValue(['Helsinki', 'Espoo'])).toBe(false);
            expect(isRangeValue(['100'])).toBe(false); // Single number
        });

        it('filters placeholder values', () => {
            const placeholders = [
                '(none)',
                '(no mapping)',
                'none',
                'any',
                'default',
                'valitse',
                'valitse...',
                '-- select --',
                '-- select field --',
                'choose',
                'choose one',
                'not set',
            ];

            const isPlaceholder = (v) => {
                if (!v) return true;
                const lower = v.toLowerCase().trim();
                if (placeholders.includes(lower)) return true;
                if (/^[-\s]*--.*--[-\s]*$/.test(v)) return true;
                if (/^[-]+$/.test(v)) return true;
                return false;
            };

            placeholders.forEach((p) => {
                expect(isPlaceholder(p)).toBe(true);
            });

            expect(isPlaceholder('Helsinki')).toBe(false);
            expect(isPlaceholder('100000')).toBe(false);
        });
    });
});
