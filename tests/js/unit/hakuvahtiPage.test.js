/**
 * Unit Tests for Hakuvahti Page UI
 *
 * Tests the WooCommerce My Account hakuvahti management functionality.
 */

describe('Hakuvahti Page', () => {
    describe('Criteria parsing', () => {
        const parseCriteriaFromJson = (jsonString) => {
            if (!jsonString) return [];
            try {
                return JSON.parse(jsonString);
            } catch (e) {
                return [];
            }
        };

        it('parses valid JSON criteria', () => {
            const json = JSON.stringify([
                { name: 'sijainti', label: 'multiple_choice', values: ['Helsinki'] },
                { name: 'hinta', label: 'range', values: ['50000', '150000'] },
            ]);

            const criteria = parseCriteriaFromJson(json);

            expect(criteria).toHaveLength(2);
            expect(criteria[0].name).toBe('sijainti');
            expect(criteria[1].label).toBe('range');
        });

        it('returns empty array for invalid JSON', () => {
            expect(parseCriteriaFromJson('not valid json')).toEqual([]);
            expect(parseCriteriaFromJson('')).toEqual([]);
            expect(parseCriteriaFromJson(null)).toEqual([]);
        });

        it('returns empty array for empty JSON array', () => {
            expect(parseCriteriaFromJson('[]')).toEqual([]);
        });
    });

    describe('Criteria formatting for display', () => {
        const formatCriteriaPreview = (criteria) => {
            if (!criteria || !criteria.length) {
                return '<p>Ei hakuehtoja valittu</p>';
            }

            let html = '<ul class="hakuvahti-criteria-list">';
            criteria.forEach((c) => {
                html += '<li><strong>' + c.name + ':</strong> ' + c.values.join(', ') + '</li>';
            });
            html += '</ul>';
            return html;
        };

        it('formats criteria as HTML list', () => {
            const criteria = [
                { name: 'sijainti', values: ['Helsinki', 'Espoo'] },
                { name: 'tyyppi', values: ['Asunto'] },
            ];

            const html = formatCriteriaPreview(criteria);

            expect(html).toContain('<ul');
            expect(html).toContain('<li>');
            expect(html).toContain('sijainti');
            expect(html).toContain('Helsinki, Espoo');
            expect(html).toContain('tyyppi');
            expect(html).toContain('Asunto');
        });

        it('returns empty state message for empty criteria', () => {
            expect(formatCriteriaPreview([])).toBe('<p>Ei hakuehtoja valittu</p>');
            expect(formatCriteriaPreview(null)).toBe('<p>Ei hakuehtoja valittu</p>');
            expect(formatCriteriaPreview(undefined)).toBe('<p>Ei hakuehtoja valittu</p>');
        });
    });

    describe('Criteria summary for card display', () => {
        const formatCriteriaSummary = (criteria) => {
            if (!criteria || !criteria.length) return 'Ei hakuehtoja';

            const parts = [];
            criteria.forEach((c) => {
                if (c.values && c.values.length) {
                    parts.push(c.name + ': ' + c.values.join(', '));
                }
            });

            return parts.length ? parts.join(' | ') : 'Ei hakuehtoja';
        };

        it('formats criteria as pipe-separated summary', () => {
            const criteria = [
                { name: 'sijainti', values: ['Helsinki'] },
                { name: 'tyyppi', values: ['Asunto'] },
            ];

            const summary = formatCriteriaSummary(criteria);

            expect(summary).toBe('sijainti: Helsinki | tyyppi: Asunto');
        });

        it('handles multiple values in a criterion', () => {
            const criteria = [
                { name: 'sijainti', values: ['Helsinki', 'Espoo', 'Vantaa'] },
            ];

            const summary = formatCriteriaSummary(criteria);

            expect(summary).toBe('sijainti: Helsinki, Espoo, Vantaa');
        });

        it('skips criteria with empty values', () => {
            const criteria = [
                { name: 'sijainti', values: ['Helsinki'] },
                { name: 'empty', values: [] },
            ];

            const summary = formatCriteriaSummary(criteria);

            expect(summary).toBe('sijainti: Helsinki');
            expect(summary).not.toContain('empty');
        });

        it('returns default message for empty criteria', () => {
            expect(formatCriteriaSummary([])).toBe('Ei hakuehtoja');
            expect(formatCriteriaSummary(null)).toBe('Ei hakuehtoja');
        });
    });

    describe('Edit form criteria building', () => {
        const buildCriteriaFromForm = (formData) => {
            const crits = [];

            formData.forEach((item) => {
                const name = item.name?.trim();
                const label = item.label || 'multiple_choice';
                const rawVals = item.rawValues?.trim() || '';

                if (!name) return;

                const values = rawVals
                    .split(',')
                    .map((s) => s.trim())
                    .filter(Boolean);

                crits.push({ name, label, values });
            });

            return crits;
        };

        it('builds criteria from form data', () => {
            const formData = [
                { name: 'sijainti', label: 'multiple_choice', rawValues: 'Helsinki, Espoo' },
                { name: 'hinta', label: 'range', rawValues: '50000, 150000' },
            ];

            const criteria = buildCriteriaFromForm(formData);

            expect(criteria).toHaveLength(2);
            expect(criteria[0]).toEqual({
                name: 'sijainti',
                label: 'multiple_choice',
                values: ['Helsinki', 'Espoo'],
            });
            expect(criteria[1]).toEqual({
                name: 'hinta',
                label: 'range',
                values: ['50000', '150000'],
            });
        });

        it('trims whitespace from values', () => {
            const formData = [
                { name: '  sijainti  ', label: 'multiple_choice', rawValues: '  Helsinki  ,  Espoo  ' },
            ];

            const criteria = buildCriteriaFromForm(formData);

            expect(criteria[0].name).toBe('sijainti');
            expect(criteria[0].values).toEqual(['Helsinki', 'Espoo']);
        });

        it('filters out empty values', () => {
            const formData = [
                { name: 'sijainti', label: 'multiple_choice', rawValues: 'Helsinki,, Espoo,' },
            ];

            const criteria = buildCriteriaFromForm(formData);

            expect(criteria[0].values).toEqual(['Helsinki', 'Espoo']);
        });

        it('skips items without a name', () => {
            const formData = [
                { name: '', label: 'multiple_choice', rawValues: 'Helsinki' },
                { name: 'sijainti', label: 'multiple_choice', rawValues: 'Espoo' },
            ];

            const criteria = buildCriteriaFromForm(formData);

            expect(criteria).toHaveLength(1);
            expect(criteria[0].name).toBe('sijainti');
        });

        it('defaults to multiple_choice label', () => {
            const formData = [
                { name: 'sijainti', rawValues: 'Helsinki' },
            ];

            const criteria = buildCriteriaFromForm(formData);

            expect(criteria[0].label).toBe('multiple_choice');
        });
    });

    describe('Results HTML generation', () => {
        const generateResultsHtml = (posts, i18n) => {
            if (posts.length === 0) {
                return `<p class="hakuvahti-no-results">${i18n.noNewResults}</p>`;
            }

            let html = `<p class="hakuvahti-results-count"><strong>${posts.length}</strong> ${i18n.newResults}</p>`;
            html += '<ul class="hakuvahti-results-list">';
            posts.forEach((post) => {
                html += `<li><a href="${post.url}" target="_blank">${post.title}</a></li>`;
            });
            html += '</ul>';
            return html;
        };

        it('generates no results message when empty', () => {
            const html = generateResultsHtml([], { noNewResults: 'Ei uusia tuloksia' });

            expect(html).toContain('Ei uusia tuloksia');
            expect(html).toContain('hakuvahti-no-results');
        });

        it('generates list of posts with links', () => {
            const posts = [
                { ID: 1, title: 'Post 1', url: 'https://example.com/1' },
                { ID: 2, title: 'Post 2', url: 'https://example.com/2' },
            ];

            const html = generateResultsHtml(posts, {
                noNewResults: 'Ei uusia',
                newResults: 'uutta tulosta',
            });

            expect(html).toContain('<strong>2</strong>');
            expect(html).toContain('uutta tulosta');
            expect(html).toContain('Post 1');
            expect(html).toContain('https://example.com/1');
            expect(html).toContain('Post 2');
            expect(html).toContain('target="_blank"');
        });
    });

    describe('Form validation', () => {
        const validateName = (name) => {
            if (!name || !name.trim()) {
                return { valid: false, error: 'Nimi ei voi olla tyhjä' };
            }
            if (name.length > 100) {
                return { valid: false, error: 'Nimi on liian pitkä' };
            }
            return { valid: true, error: null };
        };

        it('rejects empty names', () => {
            expect(validateName('')).toEqual({ valid: false, error: 'Nimi ei voi olla tyhjä' });
            expect(validateName('   ')).toEqual({ valid: false, error: 'Nimi ei voi olla tyhjä' });
            expect(validateName(null)).toEqual({ valid: false, error: 'Nimi ei voi olla tyhjä' });
        });

        it('accepts valid names', () => {
            expect(validateName('My Search')).toEqual({ valid: true, error: null });
            expect(validateName('Helsinki properties')).toEqual({ valid: true, error: null });
        });

        it('rejects names that are too long', () => {
            const longName = 'a'.repeat(101);
            expect(validateName(longName)).toEqual({ valid: false, error: 'Nimi on liian pitkä' });
        });
    });
});
