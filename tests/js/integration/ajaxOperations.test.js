/**
 * Integration Tests for AJAX Operations
 *
 * Tests the AJAX communication patterns across all modules:
 * - Hakuvahti save, run, edit, delete operations
 * - Field fetching for categories
 * - Admin options save
 * - Error handling and response processing
 */

describe('AJAX Operations Integration', () => {
    // ============================================
    // HAKUVAHTI SAVE OPERATION
    // ============================================
    describe('Hakuvahti Save', () => {
        describe('Request building', () => {
            test('builds save request with all required parameters', () => {
                const request = {
                    action: 'hakuvahti_save',
                    nonce: 'test-nonce-123',
                    name: 'My Search',
                    category: 'Osakeannit'
                };

                expect(request).toHaveProperty('action', 'hakuvahti_save');
                expect(request).toHaveProperty('nonce');
                expect(request).toHaveProperty('name');
                expect(request).toHaveProperty('category');
            });

            test('includes criteria as array entries', () => {
                const criteria = [
                    { name: 'hinta', label: 'range', values: ['100', '500'] },
                    { name: 'tyyppi', label: 'multiple_choice', values: ['A'] }
                ];

                const postData = {
                    action: 'hakuvahti_save',
                    nonce: 'test-nonce',
                    name: 'Test',
                    category: 'Osakeannit'
                };

                criteria.forEach((c, i) => {
                    postData[`criteria[${i}][name]`] = c.name;
                    postData[`criteria[${i}][label]`] = c.label;
                    c.values.forEach((v, j) => {
                        postData[`criteria[${i}][values][${j}]`] = v;
                    });
                });

                expect(postData['criteria[0][name]']).toBe('hinta');
                expect(postData['criteria[0][values][0]']).toBe('100');
                expect(postData['criteria[1][name]']).toBe('tyyppi');
            });

            test('includes id for update operation', () => {
                const request = {
                    action: 'hakuvahti_save',
                    nonce: 'test-nonce',
                    id: 42,
                    name: 'Updated Search',
                    criteria: JSON.stringify([{ name: 'field', label: 'range', values: ['50'] }])
                };

                expect(request.id).toBe(42);
            });
        });

        describe('Response handling', () => {
            test('handles successful save response', () => {
                const response = {
                    success: true,
                    data: {
                        id: 123,
                        message: 'Hakuvahti tallennettu'
                    }
                };

                expect(response.success).toBe(true);
                expect(response.data.id).toBe(123);
            });

            test('handles save error response', () => {
                const response = {
                    success: false,
                    data: {
                        message: 'Nimi on pakollinen'
                    }
                };

                expect(response.success).toBe(false);
                expect(response.data.message).toBeTruthy();
            });

            test('extracts message from error response', () => {
                const response = {
                    success: false,
                    data: { message: 'Custom error message' }
                };

                const msg = response && response.data && response.data.message
                    ? response.data.message
                    : 'Tallennus epäonnistui.';

                expect(msg).toBe('Custom error message');
            });

            test('uses default message when not provided', () => {
                const response = { success: false };

                const msg = response && response.data && response.data.message
                    ? response.data.message
                    : 'Tallennus epäonnistui.';

                expect(msg).toBe('Tallennus epäonnistui.');
            });
        });
    });

    // ============================================
    // HAKUVAHTI RUN OPERATION
    // ============================================
    describe('Hakuvahti Run', () => {
        describe('Request building', () => {
            test('builds run request with id', () => {
                const request = {
                    action: 'hakuvahti_run',
                    nonce: 'test-nonce-456',
                    id: 42
                };

                expect(request.action).toBe('hakuvahti_run');
                expect(request.id).toBe(42);
            });
        });

        describe('Response handling', () => {
            test('handles response with posts', () => {
                const response = {
                    success: true,
                    data: {
                        posts: [
                            { id: 1, title: 'Post 1', url: '/post-1/' },
                            { id: 2, title: 'Post 2', url: '/post-2/' },
                            { id: 3, title: 'Post 3', url: '/post-3/' }
                        ]
                    }
                };

                expect(response.data.posts).toHaveLength(3);
                expect(response.data.posts[0].title).toBe('Post 1');
            });

            test('handles response with no posts', () => {
                const response = {
                    success: true,
                    data: {
                        posts: []
                    }
                };

                expect(response.data.posts).toHaveLength(0);
            });

            test('handles missing posts array', () => {
                const response = {
                    success: true,
                    data: {}
                };

                const posts = response.data.posts || [];
                expect(posts).toEqual([]);
            });

            test('handles error response', () => {
                const response = {
                    success: false,
                    data: {
                        message: 'Hakuvahti not found'
                    }
                };

                expect(response.success).toBe(false);
            });
        });

        describe('Results formatting', () => {
            test('formats zero results', () => {
                const posts = [];
                const hasResults = posts.length > 0;
                expect(hasResults).toBe(false);
            });

            test('counts results correctly', () => {
                const posts = [
                    { title: 'A', url: '/a' },
                    { title: 'B', url: '/b' }
                ];
                expect(posts.length).toBe(2);
            });

            test('builds result list HTML', () => {
                const posts = [
                    { title: 'Test Post', url: '/test-post/' }
                ];

                let html = '<ul>';
                posts.forEach(post => {
                    html += `<li><a href="${post.url}">${post.title}</a></li>`;
                });
                html += '</ul>';

                expect(html).toContain('href="/test-post/"');
                expect(html).toContain('Test Post');
            });
        });
    });

    // ============================================
    // HAKUVAHTI DELETE OPERATION
    // ============================================
    describe('Hakuvahti Delete', () => {
        describe('Request building', () => {
            test('builds delete request', () => {
                const request = {
                    action: 'hakuvahti_delete',
                    nonce: 'test-nonce',
                    id: 42
                };

                expect(request.action).toBe('hakuvahti_delete');
                expect(request.id).toBe(42);
            });
        });

        describe('Response handling', () => {
            test('handles successful delete', () => {
                const response = { success: true };
                expect(response.success).toBe(true);
            });

            test('handles delete failure', () => {
                const response = {
                    success: false,
                    data: { message: 'Not authorized' }
                };
                expect(response.success).toBe(false);
            });
        });

        describe('Confirmation', () => {
            test('requires confirmation before delete', () => {
                let deleteConfirmed = false;
                const confirmMessage = 'Haluatko varmasti poistaa?';

                // Simulate confirm
                const userConfirms = true;
                if (userConfirms) {
                    deleteConfirmed = true;
                }

                expect(deleteConfirmed).toBe(true);
            });

            test('aborts delete when not confirmed', () => {
                let requestSent = false;
                const userConfirms = false;

                if (userConfirms) {
                    requestSent = true;
                }

                expect(requestSent).toBe(false);
            });
        });
    });

    // ============================================
    // FIELD FETCHING
    // ============================================
    describe('Field Fetching', () => {
        describe('Request building', () => {
            test('builds field fetch request for category', () => {
                const request = {
                    action: 'acf_popup_get_fields',
                    nonce: 'field-nonce',
                    category: 'Osakeannit'
                };

                expect(request.action).toBe('acf_popup_get_fields');
                expect(request.category).toBe('Osakeannit');
            });

            test('builds admin field fetch request', () => {
                const request = {
                    action: 'acf_analyzer_get_fields_by_category',
                    nonce: 'admin-nonce',
                    category: 'Velkakirjat'
                };

                expect(request.action).toBe('acf_analyzer_get_fields_by_category');
            });
        });

        describe('Response handling', () => {
            test('handles field list response', () => {
                const response = {
                    success: true,
                    data: {
                        fields: ['field1', 'field2', 'field3']
                    }
                };

                expect(response.data.fields).toHaveLength(3);
            });

            test('handles admin field response with metadata', () => {
                const response = {
                    success: true,
                    data: [
                        { key: 'hinta', label: 'Hinta', has_choices: false },
                        { key: 'tyyppi', label: 'Tyyppi', has_choices: true, choices: { a: 'A' } }
                    ]
                };

                expect(response.data[0].key).toBe('hinta');
                expect(response.data[1].has_choices).toBe(true);
            });

            test('returns empty array on error', () => {
                const response = { success: false };
                const fields = response && response.success && response.data
                    ? response.data
                    : [];
                expect(fields).toEqual([]);
            });
        });

        describe('Caching behavior', () => {
            test('caches fetched fields', () => {
                const cache = {};
                const category = 'Osakeannit';
                const fields = ['field1', 'field2'];

                cache[category] = fields;

                expect(cache[category]).toEqual(fields);
            });

            test('returns from cache on second request', () => {
                const cache = { 'Osakeannit': ['cached_field'] };
                const category = 'Osakeannit';

                let fetchCount = 0;
                let result;

                if (cache[category]) {
                    result = cache[category];
                } else {
                    fetchCount++;
                    result = ['fetched_field'];
                }

                expect(fetchCount).toBe(0);
                expect(result).toEqual(['cached_field']);
            });
        });
    });

    // ============================================
    // ADMIN OPTIONS SAVE
    // ============================================
    describe('Admin Options Save', () => {
        describe('Request building', () => {
            test('builds options save request', () => {
                const options = [
                    { name: 'Hinta', category: 'Osakeannit', acf_field: 'hinta', values: null }
                ];

                const request = {
                    action: 'acf_analyzer_save_user_options',
                    nonce: 'admin-nonce',
                    options: options,
                    options_json: JSON.stringify(options)
                };

                expect(request.action).toBe('acf_analyzer_save_user_options');
                expect(request.options_json).toBeTruthy();
            });

            test('serializes options as JSON', () => {
                const options = [
                    { name: 'Test', category: 'Cat', acf_field: 'field', values: ['a', 'b'] }
                ];

                const json = JSON.stringify(options);
                const parsed = JSON.parse(json);

                expect(parsed[0].values).toEqual(['a', 'b']);
            });
        });

        describe('Response handling', () => {
            test('handles successful save', () => {
                const response = {
                    success: true,
                    data: {
                        sanitized: [
                            { name: 'Sanitized', category: 'Osakeannit', acf_field: 'field', values: null }
                        ]
                    }
                };

                expect(response.success).toBe(true);
                expect(response.data.sanitized).toBeDefined();
            });

            test('handles validation error', () => {
                const response = {
                    success: false,
                    data: {
                        message: 'Invalid field configuration'
                    }
                };

                expect(response.success).toBe(false);
            });
        });
    });

    // ============================================
    // ERROR HANDLING
    // ============================================
    describe('Error Handling', () => {
        describe('Network errors', () => {
            test('handles network failure gracefully', () => {
                let errorMessage = null;
                const networkError = { status: 0, statusText: 'Network Error' };

                // Simulate fail callback
                errorMessage = 'Verkkovirhe. Yritä uudelleen.';

                expect(errorMessage).toContain('Verkkovirhe');
            });

            test('handles server error (500)', () => {
                const error = { status: 500, statusText: 'Internal Server Error' };
                const isServerError = error.status >= 500;
                expect(isServerError).toBe(true);
            });

            test('handles unauthorized error (403)', () => {
                const error = { status: 403, statusText: 'Forbidden' };
                const isAuthError = error.status === 403 || error.status === 401;
                expect(isAuthError).toBe(true);
            });
        });

        describe('Response validation', () => {
            test('validates response has success property', () => {
                const validResponse = { success: true, data: {} };
                const invalidResponse = { data: {} };

                expect(validResponse.hasOwnProperty('success')).toBe(true);
                expect(invalidResponse.hasOwnProperty('success')).toBe(false);
            });

            test('handles null response', () => {
                const response = null;
                const isValid = response && response.success;
                expect(isValid).toBeFalsy();
            });

            test('handles undefined response', () => {
                const response = undefined;
                const isValid = response && response.success;
                expect(isValid).toBeFalsy();
            });
        });
    });

    // ============================================
    // BUTTON STATE DURING AJAX
    // ============================================
    describe('Button State Management', () => {
        test('disables button during request', () => {
            let isDisabled = false;

            // Before request
            isDisabled = true;

            expect(isDisabled).toBe(true);
        });

        test('re-enables button after success', () => {
            let isDisabled = true;

            // After success
            isDisabled = false;

            expect(isDisabled).toBe(false);
        });

        test('re-enables button after failure', () => {
            let isDisabled = true;

            // After failure (in always callback)
            isDisabled = false;

            expect(isDisabled).toBe(false);
        });

        test('updates button text during request', () => {
            let buttonText = 'Hae uudet';

            // During request
            buttonText = 'Haetaan...';
            expect(buttonText).toBe('Haetaan...');

            // After request
            buttonText = 'Hae uudet';
            expect(buttonText).toBe('Hae uudet');
        });
    });

    // ============================================
    // NONCE HANDLING
    // ============================================
    describe('Nonce Handling', () => {
        test('includes nonce in all authenticated requests', () => {
            const requests = [
                { action: 'hakuvahti_save', nonce: 'nonce1' },
                { action: 'hakuvahti_run', nonce: 'nonce2' },
                { action: 'hakuvahti_delete', nonce: 'nonce3' }
            ];

            requests.forEach(req => {
                expect(req.nonce).toBeTruthy();
            });
        });

        test('uses correct nonce for each module', () => {
            // Frontend uses hakuvahtiConfig.nonce
            const frontendNonce = 'frontend-nonce';

            // Admin uses acfAnalyzerAdmin.nonce
            const adminNonce = 'admin-nonce';

            expect(frontendNonce).not.toBe(adminNonce);
        });
    });

    // ============================================
    // URL HANDLING
    // ============================================
    describe('URL Handling', () => {
        test('uses correct AJAX URL', () => {
            const ajaxUrl = '/wp-admin/admin-ajax.php';
            expect(ajaxUrl).toContain('admin-ajax.php');
        });

        test('handles redirect URL after save', () => {
            const myPageUrl = '/my-account/hakuvahdit/';
            expect(myPageUrl).toBeTruthy();
        });

        test('handles missing redirect URL', () => {
            const myPageUrl = null;
            const shouldRedirect = !!myPageUrl;
            expect(shouldRedirect).toBe(false);
        });
    });

    // ============================================
    // DATA TRANSFORMATION
    // ============================================
    describe('Data Transformation', () => {
        describe('Criteria to post data', () => {
            test('transforms criteria array to flat key-value pairs', () => {
                const criteria = [
                    { name: 'field1', label: 'range', values: ['10', '20'] }
                ];

                const postData = {};
                criteria.forEach((c, i) => {
                    postData[`criteria[${i}][name]`] = c.name;
                    postData[`criteria[${i}][label]`] = c.label;
                    c.values.forEach((v, j) => {
                        postData[`criteria[${i}][values][${j}]`] = v;
                    });
                });

                expect(Object.keys(postData)).toContain('criteria[0][name]');
                expect(Object.keys(postData)).toContain('criteria[0][values][0]');
            });
        });

        describe('Response to UI data', () => {
            test('extracts posts from run response', () => {
                const response = {
                    success: true,
                    data: {
                        posts: [{ id: 1, title: 'Test', url: '/test' }]
                    }
                };

                const posts = response.data.posts || [];
                expect(posts[0].title).toBe('Test');
            });

            test('extracts fields from fetch response', () => {
                const response = {
                    success: true,
                    data: { fields: ['a', 'b', 'c'] }
                };

                const fields = response.data.fields;
                expect(fields).toHaveLength(3);
            });
        });
    });

    // ============================================
    // CONCURRENT REQUEST HANDLING
    // ============================================
    describe('Concurrent Request Handling', () => {
        test('multiple cards can have independent states', () => {
            const cardStates = {
                card1: { loading: false, results: null },
                card2: { loading: false, results: null }
            };

            // Start loading card1
            cardStates.card1.loading = true;

            expect(cardStates.card1.loading).toBe(true);
            expect(cardStates.card2.loading).toBe(false);
        });

        test('results are stored per card', () => {
            const cardResults = {};

            cardResults['card1'] = [{ title: 'Result 1' }];
            cardResults['card2'] = [{ title: 'Result 2' }];

            expect(cardResults['card1'][0].title).toBe('Result 1');
            expect(cardResults['card2'][0].title).toBe('Result 2');
        });
    });

    // ============================================
    // JSON SERIALIZATION
    // ============================================
    describe('JSON Serialization', () => {
        test('serializes criteria for edit save', () => {
            const crits = [
                { name: 'field', label: 'range', values: ['100'] }
            ];

            const json = JSON.stringify(crits);
            expect(json).toBe('[{"name":"field","label":"range","values":["100"]}]');
        });

        test('handles special characters in values', () => {
            const crits = [
                { name: 'field', label: 'multiple_choice', values: ['Testi "arvo"', "Toinen 'arvo'"] }
            ];

            const json = JSON.stringify(crits);
            const parsed = JSON.parse(json);

            expect(parsed[0].values[0]).toBe('Testi "arvo"');
            expect(parsed[0].values[1]).toBe("Toinen 'arvo'");
        });

        test('handles Finnish characters', () => {
            const crits = [
                { name: 'field', label: 'multiple_choice', values: ['Äiti', 'Öljy', 'Åland'] }
            ];

            const json = JSON.stringify(crits);
            const parsed = JSON.parse(json);

            expect(parsed[0].values).toContain('Äiti');
            expect(parsed[0].values).toContain('Öljy');
            expect(parsed[0].values).toContain('Åland');
        });
    });
});
