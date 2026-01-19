# ACF Field Analyzer (Hakuvahti)

A WordPress plugin for searching posts by Advanced Custom Fields (ACF) criteria and creating saved search "watches" (hakuvahdits) that automatically notify users when new matching posts are published.

**Version:** 1.0.0
**Text Domain:** `acf-analyzer`
**Primary Language:** Finnish with English fallbacks

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Architecture](#architecture)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Development](#development)
- [Testing](#testing)

## Features

### Core Search Capabilities
- **ACF Field Search** - Search posts by multiple ACF field criteria with AND/OR logic
- **Range Comparisons** - Support for numeric min/max field searches (e.g., price range)
- **Nested Field Support** - Access nested ACF fields using dot notation (`parent.child`)
- **Value Normalization** - Handles special characters (Finnish/Swedish diacritics) for accurate matching

### Saved Searches (Hakuvahdits)
- **Save Search Criteria** - Users can save search configurations for repeated monitoring
- **New Post Detection** - Automatically tracks which posts have been seen vs. new
- **Email Notifications** - Sends formatted HTML emails when new posts match saved searches
- **Daily Runner** - Scheduled WP-Cron executes all saved searches and sends consolidated emails

### Integrations
- **WP Grid Builder** - Map WPGB facets to ACF fields for seamless filtering
- **WooCommerce** - User management interface in WooCommerce My Account page
- **True-Cron Support** - External HTTP ping endpoint for shared hosting without WP-Cron

### Admin Tools
- **Mapping Editor** - Configure WPGB facet-to-ACF field mappings
- **Debug Logs** - View execution logs from the daily runner
- **Recent Matches** - Monitor recent search matches
- **Field Discovery** - Automatic ACF field name detection

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| Advanced Custom Fields (ACF) | Required |
| WooCommerce | Optional (for My Account integration) |
| WP Grid Builder | Optional (for facet integration) |

## Installation

### Via cPanel Git Deployment (Recommended)

1. Login to **cPanel** → **Git Version Control**
2. Click **Create** and enter:
   - Clone URL: `https://github.com/Isolaee/gr-hakuvahti.git`
   - Repository Path: `public_html/wp-content/plugins/acf-analyzer`
   - Repository Name: `acf-analyzer`
3. Click **Manage** → **Pull or Deploy** → **Deploy HEAD Commit**
4. Activate in WordPress Admin → Plugins

See [CPANEL_DEPLOYMENT.md](CPANEL_DEPLOYMENT.md) for detailed instructions.

### Via ZIP Upload

1. Download or clone this repository
2. Create a ZIP file of the entire repository
3. In WordPress admin, go to **Plugins** → **Add New**
4. Click **Upload Plugin**
5. Choose the ZIP file and click **Install Now**
6. Click **Activate Plugin**

### Manual Installation

1. Clone or download this repository
2. Copy the entire folder to `wp-content/plugins/acf-analyzer/`
3. Activate in WordPress Admin → Plugins

## Configuration

### WPGB Facet Mapping

Navigate to **Tools** → **ACF Analyzer** and use the mapping editor to connect WP Grid Builder facets to ACF fields:

```
Facet Slug          ACF Field Name
─────────────────────────────────────
sijainti       →    sijainti_field
hinta          →    hinta_field
tyyppi         →    tyyppi_field
```

### Daily Runner Schedule

The plugin automatically schedules a daily WP-Cron event on activation. View status in the admin panel under "Scheduled Runner Status".

### True-Cron Setup (Optional)

For shared hosting without reliable WP-Cron:

1. Find your secret key in the admin panel
2. Set up an external cron job to ping:
   ```
   GET https://yoursite.com/?acf_analyzer_cron=1&secret=YOUR_SECRET_KEY
   ```

## Usage

### Frontend: Saving a Hakuvahti

1. Visit a page with WP Grid Builder facets (e.g., Osakeannit, Osaketori, Velkakirjat)
2. Use facets to filter results
3. Click the "Tallenna hakuvahti" button (added via shortcode)
4. Enter a name for your search and save
5. Initial matching posts are marked as "seen"

**Shortcode:**
```php
[acf_hakuvahti category="osakeanti" button_text="Tallenna hakuvahti"]
```

### WooCommerce: Managing Hakuvahdits

1. Go to My Account → Hakuvahdit
2. View all saved searches with criteria summaries
3. Run searches manually to check for new matches
4. Edit names or delete searches

### Admin: Running Searches

1. Navigate to **Tools** → **ACF Analyzer**
2. Select category and add search criteria
3. Choose match logic (AND/OR)
4. Click "Run Search" to view results

### Email Notifications

When the daily runner finds new matches:
- Users receive one consolidated email per day
- Email contains all new matches grouped by hakuvahti
- HTML-formatted with post titles, excerpts, and links

## Architecture

### File Structure

```
hakuvahti/
├── acf-analyzer.php                    # Main plugin bootstrap
├── includes/
│   ├── class-acf-analyzer.php          # Core search engine
│   ├── class-acf-analyzer-admin.php    # Admin interface & AJAX
│   ├── class-acf-analyzer-shortcode.php # Frontend shortcodes & AJAX
│   ├── class-hakuvahti.php             # Saved searches model
│   └── class-hakuvahti-woocommerce.php # WooCommerce integration
├── templates/
│   ├── admin-page.php                  # Admin dashboard
│   ├── hakuvahti-page.php              # WooCommerce account page
│   └── logger-button.php               # Shortcode button output
├── assets/
│   ├── css/
│   │   ├── admin.css                   # Admin styling
│   │   └── hakuvahti.css               # Frontend modal & page styling
│   └── js/
│       ├── admin-mapping.js            # Admin facet mapping editor
│       ├── wpgb-facet-logger.js        # Frontend facet collection
│       └── hakuvahti-page.js           # My Account page functionality
├── uninstall.php                       # Cleanup on uninstall
├── CPANEL_DEPLOYMENT.md                # Deployment guide
└── README.md                           # This file
```

### Core Classes

| Class | Responsibility |
|-------|----------------|
| `ACF_Analyzer` | Core search engine with batch processing and field matching |
| `ACF_Analyzer_Admin` | Admin interface, AJAX handlers, mapping editor |
| `ACF_Analyzer_Shortcode` | Frontend shortcodes and public AJAX endpoints |
| `Hakuvahti` | Saved searches CRUD, daily runner, email notifications |
| `Hakuvahti_WooCommerce` | WooCommerce My Account endpoint integration |

### Search Algorithm

1. Query posts in batches of 200 (memory management)
2. Retrieve ACF fields via `get_fields()` for each post
3. For each criterion, compare normalized values:
   - **Range**: `field_min`/`field_max` with `>=`/`<=` operators
   - **Array OR**: Match if any value equals actual
   - **Scalar**: Exact normalized match
4. Apply match logic (AND requires all criteria, OR requires at least one)
5. Return matched posts with metadata

### Deduplication Strategy

- SHA1 hash: `sha1($post_id . '|' . $hakuvahti_id)`
- MySQL UNIQUE constraint on `(search_id, match_hash)`
- Prevents duplicate notifications for same post/search combination

## Database Schema

### Table: `wp_hakuvahdit`

Stores saved search configurations.

```sql
CREATE TABLE wp_hakuvahdit (
    id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    criteria LONGTEXT NOT NULL,         -- JSON-encoded search criteria
    seen_post_ids LONGTEXT,             -- JSON-encoded array of seen post IDs
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    KEY user_id (user_id)
);
```

### Table: `wp_hakuvahti_matches`

Stores deduplicated match results from daily runs.

```sql
CREATE TABLE wp_hakuvahti_matches (
    id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    search_id BIGINT(20) UNSIGNED NOT NULL,
    match_id BIGINT(20) UNSIGNED NOT NULL,
    match_hash VARCHAR(64) NOT NULL,
    meta LONGTEXT,                       -- JSON post data
    created_at DATETIME NOT NULL,
    UNIQUE KEY search_match (search_id, match_hash),
    KEY search_id (search_id),
    KEY match_id (match_id)
);
```

### WordPress Options

| Option Key | Description |
|------------|-------------|
| `acf_wpgb_facet_map` | WPGB facet → ACF field mappings |
| `acf_analyzer_last_run` | Timestamp of last daily runner execution |
| `acf_analyzer_last_run_debug` | Debug log from last run |
| `acf_analyzer_secret_key` | 32-char secret for true-cron |
| `acf_analyzer_ignore_seen` | Flag for showing all vs. new posts only |

### Transients

| Transient Key | TTL | Description |
|---------------|-----|-------------|
| `acf_analyzer_field_names` | 1 hour | Cached ACF field list |
| `acf_analyzer_search_results` | 1 hour | Cached search results |

## API Reference

### AJAX Endpoints

#### Public Endpoints (no login required)

| Action | Description | Parameters |
|--------|-------------|------------|
| `acf_popup_search` | Search posts by criteria | `nonce`, `category`, `criteria`, `match_logic` |
| `acf_popup_get_fields` | Get ACF fields for category | `nonce`, `category` |

#### Authenticated Endpoints (login required)

| Action | Description | Parameters |
|--------|-------------|------------|
| `hakuvahti_save` | Create/update saved search | `nonce`, `name`, `category`, `criteria` |
| `hakuvahti_list` | List user's saved searches | `nonce` |
| `hakuvahti_run` | Execute saved search | `nonce`, `id` |
| `hakuvahti_delete` | Delete saved search | `nonce`, `id` |

#### Admin Endpoints (manage_options required)

| Action | Description | Parameters |
|--------|-------------|------------|
| `acf_analyzer_save_mapping` | Save WPGB facet mappings | `nonce`, `mapping` |
| `acf_analyzer_get_fields` | Refresh field names cache | `nonce` |

### PHP Hooks

#### Actions

```php
// Scheduled daily runner
do_action('acf_analyzer_daily_runner');
```

#### Filters

The plugin currently does not expose public filters but uses standard WordPress patterns for extensibility.

### JavaScript API

#### Facet Logger (`wpgb-facet-logger.js`)

```javascript
// Collect current facet values
const criteria = getCurrentCriteria(targetGrid, useApi);

// Format criteria for display
const html = formatCriteriaPreview(criteria);

// Get WPGB instances
const instances = getWpgbInstances();
```

## Security

### Nonce Verification

| Nonce Action | Usage |
|--------------|-------|
| `acf_analyzer_save_mapping` | Admin mapping saves |
| `acf_analyzer_search` | Admin search form |
| `acf_popup_search` | Frontend search |
| `hakuvahti_nonce` | Hakuvahti CRUD operations |

### Access Control

- **Admin functions**: `current_user_can('manage_options')` check
- **User operations**: Ownership verification (`user_id` match)
- **True-cron**: `hash_equals()` constant-time comparison

### Data Sanitization

- All POST input: `sanitize_text_field()`
- JSON data: `wp_json_encode()` / `json_decode()` with validation
- SQL queries: `$wpdb->prepare()` with placeholders
- Output: `esc_html()`, `esc_attr()`, `esc_url()`

## Troubleshooting

### Common Issues

**"This plugin requires ACF"**
- Install and activate the Advanced Custom Fields plugin first

**No fields found in search**
- Ensure posts with ACF data exist in the selected categories
- Click "Refresh Fields" in admin to clear the cache

**Search results disappeared**
- Results are cached for 1 hour via transients
- Run a new search or wait for cache expiration

**Emails not sending**
- Check server mail configuration
- Verify WordPress email settings
- Check spam folder

**Facet logger shows no data**
- Ensure WP Grid Builder is active
- Verify facets have selected values
- Check browser console for JavaScript errors

**True-cron returns 403**
- Verify the secret key matches
- Ensure the key is not empty

### Debug Mode

Enable debug logging by adding criteria with `debug: true`:

```php
$criteria = [
    'field_name' => 'value',
    'debug' => true
];
```

Debug output is written to PHP error log and stored in `acf_analyzer_last_run_debug` option.

### Viewing Debug Logs

1. Go to **Tools** → **ACF Analyzer**
2. Scroll to "Debug Log from Last Run" section
3. View line-by-line execution trace

## Development

### Constants

```php
define('ACF_ANALYZER_VERSION', '1.0.0');
define('ACF_ANALYZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACF_ANALYZER_PLUGIN_URL', plugin_dir_url(__FILE__));
```

### Adding New Search Criteria Types

Extend the `search_by_criteria()` method in `class-acf-analyzer.php`:

```php
// In the comparison loop, add new comparison type
if ($your_condition) {
    // Custom matching logic
    $match = your_comparison($actual_value, $criteria_value);
}
```

### Customizing Email Templates

Modify `build_email_html()` in `class-hakuvahti.php`:

```php
public function build_email_html($user, $grouped) {
    // Custom HTML template
}
```

### Supported Categories

Default categories (configurable in class constructors):

- `osakeanti` - Share offerings
- `osaketori` - Stock market
- `velkakirja` - Debt instruments

### Testing

The plugin includes comprehensive automated test suites for both PHP and JavaScript.

#### Testing Tools

| Language   |                     Framework                                            | Purpose                    |
|------------|--------------------------------------------------------------------------|----------------------------|
|   PHP      | [Pest](https://pestphp.com/)                                             | Unit & integration tests   |
|   PHP      | [Mockery](https://github.com/mockery/mockery)                            | Mocking framework          |
|   PHP      | [Brain\Monkey](https://github.com/Brain-WP/BrainMonkey)                  | WordPress function mocking |
| JavaScript | [Jest](https://jestjs.io/) | Unit & integration tests                    |                            |
| JavaScript | [@testing-library/jest-dom](https://github.com/testing-library/jest-dom) | DOM testing utilities      |

#### Installing Test Dependencies

```bash
# PHP dependencies
composer install

# JavaScript dependencies
npm install
```

#### Running Tests

**PHP Tests (Pest)**

```bash
# Run all PHP tests
composer test

# Run only unit tests
composer test:unit

# Run only integration tests
composer test:integration

# Run with coverage report
composer test:coverage
```

**JavaScript Tests (Jest)**

```bash
# Run all JavaScript tests
npm test

# Run only unit tests
npm run test:unit

# Run only integration tests
npm run test:integration

# Run in watch mode (re-runs on file changes)
npm run test:watch

# Run with coverage report
npm run test:coverage
```

#### Test Structure

```
tests/
├── php/
│   ├── bootstrap.php           # Test environment setup
│   ├── Pest.php                # Pest configuration
│   ├── Unit/
│   │   ├── ACFAnalyzerTest.php # ACF_Analyzer unit tests
│   │   └── HakuvahtiTest.php   # Hakuvahti unit tests
│   └── Integration/
│       └── SearchIntegrationTest.php
└── js/
    ├── setup.js                # Jest setup & mocks
    ├── unit/
    │   ├── facetLogger.test.js # Facet collection tests
    │   └── hakuvahtiPage.test.js
    └── integration/
        └── ajaxOperations.test.js
```

#### Manual Testing Workflow

1. Create test posts with ACF data
2. Set up WPGB facets pointing to ACF fields
3. Configure facet mappings in admin
4. Save a hakuvahti from the frontend
5. Manually trigger the daily runner
6. Verify email delivery and match recording

## License

This plugin is proprietary software developed for GR.

## Support

For issues and feature requests, contact the development team or create an issue in the repository.
