/**
 * Jest Test Setup
 *
 * Mocks for jQuery, WordPress globals, and common test utilities
 * for testing the ACF Field Analyzer plugin JavaScript.
 */

// Import jest-dom matchers
require('@testing-library/jest-dom');

// ============================================
// JQUERY MOCK
// ============================================

// Store event handlers for simulation
const eventHandlers = {};
const ajaxRequests = [];

// Mock jQuery element
function createMockElement(selector = '') {
    const elements = [];
    const data = {};
    const css = {};
    const classes = new Set();
    let htmlContent = '';
    let textContent = '';
    let val = '';
    let isVisible = true;
    let isDisabled = false;

    const mockEl = {
        length: 1,
        selector,

        // DOM traversal
        find: jest.fn((sel) => createMockElement(sel)),
        closest: jest.fn((sel) => createMockElement(sel)),
        parent: jest.fn(() => createMockElement()),
        children: jest.fn(() => createMockElement()),
        first: jest.fn(function() { return this; }),
        last: jest.fn(function() { return this; }),
        eq: jest.fn(function() { return this; }),

        // DOM manipulation
        append: jest.fn(function(content) {
            htmlContent += typeof content === 'string' ? content : '';
            return this;
        }),
        prepend: jest.fn(function() { return this; }),
        html: jest.fn(function(content) {
            if (content === undefined) return htmlContent;
            htmlContent = content;
            return this;
        }),
        text: jest.fn(function(content) {
            if (content === undefined) return textContent;
            textContent = content;
            return this;
        }),
        val: jest.fn(function(value) {
            if (value === undefined) return val;
            val = value;
            return this;
        }),
        attr: jest.fn(function(name, value) {
            if (value === undefined) return data[name];
            data[name] = value;
            return this;
        }),
        data: jest.fn(function(key, value) {
            if (value === undefined) return data[key];
            data[key] = value;
            return this;
        }),
        prop: jest.fn(function(name, value) {
            if (name === 'disabled') {
                if (value === undefined) return isDisabled;
                isDisabled = value;
            }
            return this;
        }),

        // CSS/Classes
        addClass: jest.fn(function(cls) {
            classes.add(cls);
            return this;
        }),
        removeClass: jest.fn(function(cls) {
            classes.delete(cls);
            return this;
        }),
        hasClass: jest.fn((cls) => classes.has(cls)),
        css: jest.fn(function(prop, value) {
            if (value === undefined) return css[prop];
            css[prop] = value;
            return this;
        }),

        // Visibility
        show: jest.fn(function() { isVisible = true; return this; }),
        hide: jest.fn(function() { isVisible = false; return this; }),
        toggle: jest.fn(function() { isVisible = !isVisible; return this; }),
        slideDown: jest.fn(function(duration, callback) {
            isVisible = true;
            if (typeof duration === 'function') duration();
            if (typeof callback === 'function') callback();
            return this;
        }),
        slideUp: jest.fn(function(duration, callback) {
            isVisible = false;
            if (typeof duration === 'function') duration();
            if (typeof callback === 'function') callback();
            return this;
        }),
        is: jest.fn(function(selector) {
            if (selector === ':visible') return isVisible;
            return false;
        }),

        // Events
        on: jest.fn(function(event, selectorOrHandler, handler) {
            const actualHandler = handler || selectorOrHandler;
            if (!eventHandlers[event]) eventHandlers[event] = [];
            eventHandlers[event].push(actualHandler);
            return this;
        }),
        off: jest.fn(function() { return this; }),
        click: jest.fn(function(handler) {
            if (handler) {
                if (!eventHandlers['click']) eventHandlers['click'] = [];
                eventHandlers['click'].push(handler);
            }
            return this;
        }),
        trigger: jest.fn(function(event) {
            if (eventHandlers[event]) {
                eventHandlers[event].forEach(h => h.call(this, { preventDefault: jest.fn(), stopPropagation: jest.fn() }));
            }
            return this;
        }),

        // Iteration
        each: jest.fn(function(callback) {
            elements.forEach((el, i) => callback.call(el, i, el));
            return this;
        }),
        filter: jest.fn(function() { return this; }),
        not: jest.fn(function() { return createMockElement(); }),

        // DOM insertion
        empty: jest.fn(function() {
            htmlContent = '';
            return this;
        }),
        remove: jest.fn(function() { return this; }),

        // Utilities
        get: jest.fn((i) => elements[i]),
        push: (el) => elements.push(el),

        // For testing
        _getData: () => data,
        _getClasses: () => classes,
        _getHtml: () => htmlContent,
        _isVisible: () => isVisible,
    };

    return mockEl;
}

// Create the jQuery mock function
const jQueryMock = jest.fn((selector) => {
    if (typeof selector === 'function') {
        // $(document).ready() or $(function() {})
        selector();
        return createMockElement();
    }
    if (typeof selector === 'string' && selector.startsWith('<')) {
        // Creating element from HTML string
        return createMockElement(selector);
    }
    return createMockElement(selector);
});

// jQuery static methods
jQueryMock.ajax = jest.fn(() => ({
    done: jest.fn(function(cb) { this._doneCb = cb; return this; }),
    fail: jest.fn(function(cb) { this._failCb = cb; return this; }),
    always: jest.fn(function(cb) { this._alwaysCb = cb; return this; }),
}));

jQueryMock.post = jest.fn((url, data, callback) => {
    const request = {
        url,
        data,
        _doneCb: null,
        _failCb: null,
        _alwaysCb: null,
        done: function(cb) { this._doneCb = cb; return this; },
        fail: function(cb) { this._failCb = cb; return this; },
        always: function(cb) { this._alwaysCb = cb; return this; },
        // For testing - simulate response
        _resolve: function(response) {
            if (this._doneCb) this._doneCb(response);
            if (this._alwaysCb) this._alwaysCb();
        },
        _reject: function(error) {
            if (this._failCb) this._failCb(error);
            if (this._alwaysCb) this._alwaysCb();
        }
    };
    ajaxRequests.push(request);
    if (callback) {
        // Old-style callback
        request.done(callback);
    }
    return request;
});

jQueryMock.get = jest.fn(() => ({
    done: jest.fn().mockReturnThis(),
    fail: jest.fn().mockReturnThis(),
    always: jest.fn().mockReturnThis(),
}));

jQueryMock.extend = jest.fn((deep, target, ...sources) => {
    return Object.assign(target || {}, ...sources);
});

jQueryMock.fn = {
    extend: jest.fn(),
};

// Assign to global
global.$ = jQueryMock;
global.jQuery = jQueryMock;

// ============================================
// WORDPRESS GLOBALS MOCK
// ============================================

// Mock for wpgb-facet-logger.js
global.acfWpgbLogger = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    hakuvahtiNonce: 'test-nonce-123',
    myPageUrl: '/my-account/hakuvahdit/',
    userSearchOptions: [
        { name: 'Hinta', category: 'Osakeannit', acf_field: 'hinta', values: { min: '', max: '', postfix: '€' } },
        { name: 'Tyyppi', category: 'Osakeannit', acf_field: 'tyyppi', values: ['A', 'B', 'C'] },
        { name: 'Korko', category: 'Velkakirjat', acf_field: 'korko', values: { min: '0', max: '20', postfix: '%' } },
    ],
};

// Mock for hakuvahti-page.js
global.hakuvahtiConfig = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'test-nonce-456',
    fieldNonce: 'field-nonce-789',
    i18n: {
        running: 'Haetaan...',
        noNewResults: 'Ei uusia tuloksia',
        newResults: 'uutta tulosta',
        networkError: 'Verkkovirhe',
        confirmDelete: 'Haluatko varmasti poistaa?',
        deleteFailed: 'Poistaminen epäonnistui',
        saveFailed: 'Tallennus epäonnistui',
        saving: 'Tallennetaan...',
        save: 'Tallenna',
        cancel: 'Peruuta',
        remove: 'Poista',
        addCriterion: 'Lisää ehto',
        loadingFields: 'Ladataan kenttiä...',
        noCriteria: 'Ei hakuehtoja',
        selectField: 'Valitse kenttä',
        underLabelPrefix: 'alle ',
        overLabelPrefix: 'yli ',
    },
};

// Mock for admin-mapping.js
global.acfAnalyzerAdmin = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'admin-nonce-abc',
    categories: ['Osakeannit', 'Velkakirjat', 'Osaketori'],
    userSearchOptions: [
        { name: 'Hinta', category: 'Osakeannit', acf_field: 'hinta', values: { min: '', max: '', postfix: '€' } },
    ],
};

// ============================================
// DOM SETUP
// ============================================

// Reset DOM before each test
beforeEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';

    // Clear event handlers
    Object.keys(eventHandlers).forEach(key => delete eventHandlers[key]);

    // Clear ajax requests
    ajaxRequests.length = 0;

    // Reset jQuery mock calls
    jQueryMock.mockClear();
    jQueryMock.post.mockClear();
    jQueryMock.ajax.mockClear();
});

// ============================================
// TEST UTILITIES
// ============================================

/**
 * Get the last AJAX request made via $.post
 */
global.getLastAjaxRequest = () => ajaxRequests[ajaxRequests.length - 1];

/**
 * Get all AJAX requests
 */
global.getAllAjaxRequests = () => [...ajaxRequests];

/**
 * Simulate a successful AJAX response
 */
global.resolveAjaxRequest = (request, response) => {
    request._resolve(response);
};

/**
 * Simulate a failed AJAX request
 */
global.rejectAjaxRequest = (request, error = {}) => {
    request._reject(error);
};

/**
 * Create a mock event object
 */
global.createMockEvent = (overrides = {}) => ({
    preventDefault: jest.fn(),
    stopPropagation: jest.fn(),
    target: document.createElement('div'),
    ...overrides,
});

/**
 * Set the URL pathname for category detection tests
 */
global.setUrlPath = (path) => {
    delete window.location;
    window.location = { pathname: path, href: `https://example.com${path}` };
};

/**
 * Create a mock hakuvahti card element
 */
global.createMockCard = (id, name, category, criteria) => {
    const card = document.createElement('div');
    card.className = 'hakuvahti-card';
    card.setAttribute('data-id', id);
    card.setAttribute('data-category', category);
    card.setAttribute('data-criteria', JSON.stringify(criteria));
    card.innerHTML = `
        <span class="hakuvahti-name">${name}</span>
        <div class="hakuvahti-criteria"></div>
        <div class="hakuvahti-results"></div>
        <div class="hakuvahti-edit-form"></div>
        <button class="hakuvahti-run-btn" data-id="${id}">Hae uudet</button>
        <button class="hakuvahti-edit-btn" data-id="${id}">Muokkaa</button>
        <button class="hakuvahti-delete-btn" data-id="${id}">Poista</button>
    `;
    return card;
};

/**
 * Create the hakuvahti modal structure
 */
global.createMockModal = () => {
    const modal = document.createElement('div');
    modal.id = 'hakuvahti-modal';
    modal.innerHTML = `
        <div class="hakuvahti-modal-overlay"></div>
        <div class="hakuvahti-modal-content">
            <button class="hakuvahti-modal-close">&times;</button>
            <div id="hakuvahti-criteria-preview"></div>
            <input type="text" id="hakuvahti-save-name" />
            <div class="hakuvahti-save-status"></div>
            <button class="hakuvahti-save-popup">Tallenna</button>
            <button class="hakuvahti-cancel-popup">Peruuta</button>
        </div>
    `;
    return modal;
};

// ============================================
// CONSOLE MOCKS
// ============================================

// Suppress console output during tests (optional - comment out for debugging)
global.console = {
    ...console,
    log: jest.fn(),
    debug: jest.fn(),
    info: jest.fn(),
    warn: jest.fn(),
    error: jest.fn(),
};
