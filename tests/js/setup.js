/**
 * Jest Setup File
 *
 * Configures the testing environment for JavaScript tests.
 * Sets up DOM testing utilities and mocks for WordPress/jQuery dependencies.
 */

require('@testing-library/jest-dom');

// Mock jQuery
const mockJQuery = jest.fn((selector) => {
    const elements = [];

    const jQueryObj = {
        length: 0,
        find: jest.fn(() => jQueryObj),
        closest: jest.fn(() => jQueryObj),
        data: jest.fn((key, value) => {
            if (value !== undefined) return jQueryObj;
            return undefined;
        }),
        attr: jest.fn((key, value) => {
            if (value !== undefined) return jQueryObj;
            return '';
        }),
        val: jest.fn((value) => {
            if (value !== undefined) return jQueryObj;
            return '';
        }),
        text: jest.fn((value) => {
            if (value !== undefined) return jQueryObj;
            return '';
        }),
        html: jest.fn((value) => {
            if (value !== undefined) return jQueryObj;
            return '';
        }),
        on: jest.fn(() => jQueryObj),
        off: jest.fn(() => jQueryObj),
        click: jest.fn(() => jQueryObj),
        submit: jest.fn(() => jQueryObj),
        prop: jest.fn(() => jQueryObj),
        hide: jest.fn(() => jQueryObj),
        show: jest.fn(() => jQueryObj),
        slideUp: jest.fn(() => jQueryObj),
        slideDown: jest.fn(() => jQueryObj),
        fadeIn: jest.fn(() => jQueryObj),
        fadeOut: jest.fn(() => jQueryObj),
        stop: jest.fn(() => jQueryObj),
        addClass: jest.fn(() => jQueryObj),
        removeClass: jest.fn(() => jQueryObj),
        is: jest.fn(() => false),
        appendTo: jest.fn(() => jQueryObj),
        parent: jest.fn(() => jQueryObj),
        remove: jest.fn(() => jQueryObj),
        focus: jest.fn(() => jQueryObj),
        each: jest.fn((fn) => {
            elements.forEach((el, i) => fn.call(el, i, el));
            return jQueryObj;
        }),
        0: null,
    };

    return jQueryObj;
});

// Static jQuery methods
mockJQuery.post = jest.fn(() => ({
    done: jest.fn(function(cb) { this._done = cb; return this; }),
    fail: jest.fn(function(cb) { this._fail = cb; return this; }),
    always: jest.fn(function(cb) { this._always = cb; return this; }),
}));

mockJQuery.ajax = jest.fn(() => ({
    done: jest.fn(function(cb) { this._done = cb; return this; }),
    fail: jest.fn(function(cb) { this._fail = cb; return this; }),
    always: jest.fn(function(cb) { this._always = cb; return this; }),
}));

// Set up global jQuery
global.jQuery = mockJQuery;
global.$ = mockJQuery;

// Mock WordPress global config
global.hakuvahtiConfig = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'test-nonce-123',
    i18n: {
        running: 'Suoritetaan...',
        noNewResults: 'Ei uusia tuloksia',
        newResults: 'uutta tulosta',
        networkError: 'Verkkovirhe',
        confirmDelete: 'Haluatko varmasti poistaa?',
        deleteFailed: 'Poisto epäonnistui',
        saveFailed: 'Tallennus epäonnistui',
        saving: 'Tallennetaan...',
        save: 'Tallenna',
        cancel: 'Peruuta',
        remove: 'Poista',
        addCriterion: 'Lisää ehto',
        nameLabel: 'Nimi',
        noCriteria: 'Ei hakuehtoja',
    },
};

global.acfWpgbLogger = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    hakuvahtiNonce: 'test-nonce-456',
    use_api_default: true,
};

global.acfWpgbFacetMap = {
    'location': 'sijainti',
    'price': 'hinta',
    'category': 'Luokitus',
};

// Mock window.WP_Grid_Builder
global.WP_Grid_Builder = null;

// Mock CustomEvent
global.CustomEvent = class CustomEvent extends Event {
    constructor(type, options = {}) {
        super(type, options);
        this.detail = options.detail || null;
    }
};

// Mock console methods for cleaner test output
global.console = {
    ...console,
    log: jest.fn(),
    info: jest.fn(),
    warn: jest.fn(),
    error: jest.fn(),
};

// Reset mocks before each test
beforeEach(() => {
    jest.clearAllMocks();
});
