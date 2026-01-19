/**
 * Integration Tests for AJAX Operations
 *
 * Tests the AJAX request/response handling for hakuvahti operations.
 */

describe('AJAX Operations Integration', () => {
    let mockAjaxCall;
    let mockResponse;

    beforeEach(() => {
        mockResponse = null;
        mockAjaxCall = {
            _done: null,
            _fail: null,
            _always: null,
            done(cb) {
                this._done = cb;
                return this;
            },
            fail(cb) {
                this._fail = cb;
                return this;
            },
            always(cb) {
                this._always = cb;
                return this;
            },
            // Simulate success response
            triggerSuccess(data) {
                if (this._done) this._done(data);
                if (this._always) this._always();
            },
            // Simulate failure
            triggerFail(error) {
                if (this._fail) this._fail(error);
                if (this._always) this._always();
            },
        };
    });

    describe('Save Hakuvahti', () => {
        const saveHakuvahti = (data, callbacks) => {
            const request = mockAjaxCall;

            // Simulate async behavior
            setTimeout(() => {
                if (mockResponse && mockResponse.success) {
                    request.triggerSuccess(mockResponse);
                } else if (mockResponse) {
                    request.triggerSuccess(mockResponse);
                } else {
                    request.triggerFail({ status: 500 });
                }
            }, 0);

            return request
                .done(callbacks.onSuccess)
                .fail(callbacks.onError)
                .always(callbacks.onComplete);
        };

        it('handles successful save', (done) => {
            mockResponse = {
                success: true,
                data: {
                    id: 123,
                    message: 'Hakuvahti tallennettu!',
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.success).toBe(true);
                    expect(resp.data.id).toBe(123);
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    expect(callbacks.onSuccess).toHaveBeenCalled();
                    expect(callbacks.onError).not.toHaveBeenCalled();
                    done();
                }),
            };

            saveHakuvahti(
                { name: 'Test', category: 'Osakeannit', criteria: [] },
                callbacks
            );
        });

        it('handles validation error', (done) => {
            mockResponse = {
                success: false,
                data: {
                    message: 'Nimi on pakollinen',
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.success).toBe(false);
                    expect(resp.data.message).toBe('Nimi on pakollinen');
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            saveHakuvahti({ name: '', category: 'Osakeannit', criteria: [] }, callbacks);
        });

        it('handles network error', (done) => {
            mockResponse = null; // Triggers fail

            const callbacks = {
                onSuccess: jest.fn(),
                onError: jest.fn((error) => {
                    expect(error.status).toBe(500);
                }),
                onComplete: jest.fn(() => {
                    expect(callbacks.onError).toHaveBeenCalled();
                    done();
                }),
            };

            saveHakuvahti({ name: 'Test', category: 'Osakeannit', criteria: [] }, callbacks);
        });
    });

    describe('Run Search', () => {
        const runSearch = (hakuvahtiId, callbacks) => {
            const request = mockAjaxCall;

            setTimeout(() => {
                if (mockResponse) {
                    request.triggerSuccess(mockResponse);
                } else {
                    request.triggerFail({ status: 500 });
                }
            }, 0);

            return request
                .done(callbacks.onSuccess)
                .fail(callbacks.onError)
                .always(callbacks.onComplete);
        };

        it('returns new posts when found', (done) => {
            mockResponse = {
                success: true,
                data: {
                    posts: [
                        { ID: 1, title: 'New Property 1', url: 'https://example.com/1' },
                        { ID: 2, title: 'New Property 2', url: 'https://example.com/2' },
                    ],
                    total_found: 2,
                    total_all: 10,
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.success).toBe(true);
                    expect(resp.data.posts).toHaveLength(2);
                    expect(resp.data.total_found).toBe(2);
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            runSearch(123, callbacks);
        });

        it('returns empty posts array when no new results', (done) => {
            mockResponse = {
                success: true,
                data: {
                    posts: [],
                    total_found: 0,
                    total_all: 10,
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.data.posts).toHaveLength(0);
                    expect(resp.data.total_found).toBe(0);
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            runSearch(123, callbacks);
        });

        it('handles unauthorized access', (done) => {
            mockResponse = {
                success: false,
                data: {
                    message: 'Ei oikeuksia',
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.success).toBe(false);
                    expect(resp.data.message).toContain('oikeuksia');
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            runSearch(999, callbacks);
        });
    });

    describe('Delete Hakuvahti', () => {
        const deleteHakuvahti = (hakuvahtiId, callbacks) => {
            const request = mockAjaxCall;

            setTimeout(() => {
                if (mockResponse) {
                    request.triggerSuccess(mockResponse);
                } else {
                    request.triggerFail({ status: 500 });
                }
            }, 0);

            return request
                .done(callbacks.onSuccess)
                .fail(callbacks.onError)
                .always(callbacks.onComplete);
        };

        it('handles successful deletion', (done) => {
            mockResponse = {
                success: true,
                data: {
                    message: 'Hakuvahti poistettu',
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.success).toBe(true);
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    expect(callbacks.onSuccess).toHaveBeenCalled();
                    done();
                }),
            };

            deleteHakuvahti(123, callbacks);
        });

        it('handles deletion failure', (done) => {
            mockResponse = {
                success: false,
                data: {
                    message: 'Poisto epäonnistui',
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.success).toBe(false);
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            deleteHakuvahti(123, callbacks);
        });
    });

    describe('Get Fields', () => {
        const getFields = (category, callbacks) => {
            const request = mockAjaxCall;

            setTimeout(() => {
                if (mockResponse) {
                    request.triggerSuccess(mockResponse);
                } else {
                    request.triggerFail({ status: 500 });
                }
            }, 0);

            return request
                .done(callbacks.onSuccess)
                .fail(callbacks.onError)
                .always(callbacks.onComplete);
        };

        it('returns available fields for category', (done) => {
            mockResponse = {
                success: true,
                data: {
                    fields: [
                        'sijainti',
                        'hinta',
                        'tyyppi',
                        'koko',
                        'Luokitus',
                    ],
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.data.fields).toContain('sijainti');
                    expect(resp.data.fields).toContain('hinta');
                    expect(resp.data.fields).toHaveLength(5);
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            getFields('Osakeannit', callbacks);
        });

        it('returns empty array when no fields found', (done) => {
            mockResponse = {
                success: true,
                data: {
                    fields: [],
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.data.fields).toHaveLength(0);
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            getFields('UnknownCategory', callbacks);
        });
    });

    describe('List User Hakuvahdits', () => {
        const listHakuvahdits = (callbacks) => {
            const request = mockAjaxCall;

            setTimeout(() => {
                if (mockResponse) {
                    request.triggerSuccess(mockResponse);
                } else {
                    request.triggerFail({ status: 500 });
                }
            }, 0);

            return request
                .done(callbacks.onSuccess)
                .fail(callbacks.onError)
                .always(callbacks.onComplete);
        };

        it('returns list of user hakuvahdits', (done) => {
            mockResponse = {
                success: true,
                data: {
                    hakuvahdits: [
                        {
                            id: 1,
                            name: 'Helsinki Search',
                            category: 'Osakeannit',
                            criteria: [{ name: 'sijainti', values: ['Helsinki'] }],
                            created_at: '2024-01-15 10:00:00',
                        },
                        {
                            id: 2,
                            name: 'Espoo Search',
                            category: 'Velkakirjat',
                            criteria: [{ name: 'sijainti', values: ['Espoo'] }],
                            created_at: '2024-01-14 10:00:00',
                        },
                    ],
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.data.hakuvahdits).toHaveLength(2);
                    expect(resp.data.hakuvahdits[0].name).toBe('Helsinki Search');
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            listHakuvahdits(callbacks);
        });

        it('returns empty list for new user', (done) => {
            mockResponse = {
                success: true,
                data: {
                    hakuvahdits: [],
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.data.hakuvahdits).toHaveLength(0);
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            listHakuvahdits(callbacks);
        });

        it('requires authentication', (done) => {
            mockResponse = {
                success: false,
                data: {
                    message: 'Kirjaudu sisään',
                },
            };

            const callbacks = {
                onSuccess: jest.fn((resp) => {
                    expect(resp.success).toBe(false);
                    expect(resp.data.message).toContain('Kirjaudu');
                }),
                onError: jest.fn(),
                onComplete: jest.fn(() => {
                    done();
                }),
            };

            listHakuvahdits(callbacks);
        });
    });
});
