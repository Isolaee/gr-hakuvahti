<?php
/**
 * Plugin Name: ACF Field Analyzer
 * Plugin URI: https://github.com/yourusername/acf-analyzer
 * Description: Search Advanced Custom Fields across your WordPress site
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-analyzer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define plugin version constant
 * Used for cache busting and version tracking
 */
define( 'ACF_ANALYZER_VERSION', '1.0.0' );

/**
 * Define plugin directory path
 * Used for including files and templates
 */
define( 'ACF_ANALYZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Define plugin URL
 * Used for enqueueing assets (CSS, JS)
 */
define( 'ACF_ANALYZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load required plugin classes
require_once ACF_ANALYZER_PLUGIN_DIR . 'includes/class-acf-analyzer.php';
require_once ACF_ANALYZER_PLUGIN_DIR . 'includes/class-acf-analyzer-admin.php';
require_once ACF_ANALYZER_PLUGIN_DIR . 'includes/class-acf-analyzer-shortcode.php';
require_once ACF_ANALYZER_PLUGIN_DIR . 'includes/class-hakuvahti.php';
require_once ACF_ANALYZER_PLUGIN_DIR . 'includes/class-hakuvahti-woocommerce.php';

/**
 * Create database table on plugin activation
 *
 * @since 1.1.0
 * @return void
 */
function acf_analyzer_activate() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hakuvahdit';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        name varchar(255) NOT NULL,
        category varchar(100) NOT NULL,
        criteria longtext NOT NULL,
        seen_post_ids longtext,
        created_at datetime NOT NULL,
        updated_at datetime,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Create matches table to store discovered matches (deduplicated)
    $matches_table = $wpdb->prefix . 'hakuvahti_matches';
    $sql2 = "CREATE TABLE $matches_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        search_id bigint(20) unsigned NOT NULL,
        match_id bigint(20) unsigned NOT NULL,
        match_hash varchar(64) NOT NULL,
        meta longtext,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY search_match (search_id, match_hash),
        KEY search_id (search_id),
        KEY match_id (match_id)
    ) $charset_collate;";
    dbDelta( $sql2 );

    // Ensure a secret key exists for true-cron ping URL
    if ( ! get_option( 'acf_analyzer_secret_key' ) ) {
        $secret = wp_generate_password( 32, false, false );
        update_option( 'acf_analyzer_secret_key', $secret );
    }

    // Schedule daily runner if not already scheduled
    if ( ! wp_next_scheduled( 'acf_analyzer_daily_runner' ) ) {
        wp_schedule_event( time(), 'daily', 'acf_analyzer_daily_runner' );
    }

    // Flush rewrite rules for WooCommerce endpoint
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'acf_analyzer_activate' );

/**
 * Flush rewrite rules on deactivation
 *
 * @since 1.1.0
 * @return void
 */
function acf_analyzer_deactivate() {
    flush_rewrite_rules();
    // Clear scheduled daily runner
    if ( wp_next_scheduled( 'acf_analyzer_daily_runner' ) ) {
        wp_clear_scheduled_hook( 'acf_analyzer_daily_runner' );
    }
}
register_deactivation_hook( __FILE__, 'acf_analyzer_deactivate' );

/**
 * Initialize the plugin
 * 
 * This function runs after all plugins are loaded and initializes
 * the admin interface and frontend shortcodes.
 * 
 * @since 1.0.0
 * @return void
 */
function acf_analyzer_init() {
    // Initialize admin interface (always available so mappings can be managed)
    new ACF_Analyzer_Admin();

    // Register scheduled runner hook
    add_action( 'acf_analyzer_daily_runner', array( 'Hakuvahti', 'run_daily_searches' ) );

    // Register a simple HTTP ping handler for true-cron (cPanel)
    add_action( 'init', 'acf_analyzer_handle_ping' );

    // Check if ACF is active for frontend features
    if ( ! function_exists( 'get_fields' ) ) {
        // Display admin notice if ACF is not active
        add_action( 'admin_notices', 'acf_analyzer_acf_missing_notice' );
        return;
    }

    // Initialize shortcode functionality (only when ACF is present)
    new ACF_Analyzer_Shortcode();
}
add_action( 'plugins_loaded', 'acf_analyzer_init' );

/**
 * Handle HTTP ping to trigger the daily runner (protected by secret key)
 * Example: https://example.com/?hakuvahti_ping=1&key=SECRET
 */
function acf_analyzer_handle_ping() {
    if ( isset( $_GET['hakuvahti_ping'] ) && (int) $_GET['hakuvahti_ping'] === 1 ) {
        $key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
        $secret = get_option( 'acf_analyzer_secret_key', '' );
        if ( ! empty( $secret ) && hash_equals( $secret, $key ) ) {
            // Run the scheduled action immediately
            do_action( 'acf_analyzer_daily_runner' );
            // Output simple response and exit
            status_header( 200 );
            echo 'ACF Analyzer: daily runner executed';
            exit;
        } else {
            status_header( 403 );
            echo 'Forbidden';
            exit;
        }
    }
}

/**
 * Display admin notice if ACF is not active
 * 
 * Shows a warning message in the WordPress admin area when
 * Advanced Custom Fields plugin is not installed or activated.
 * 
 * @since 1.0.0
 * @return void
 */
function acf_analyzer_acf_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'ACF Field Analyzer', 'acf-analyzer' ); ?>:</strong>
            <?php esc_html_e( 'This plugin requires Advanced Custom Fields to be installed and activated.', 'acf-analyzer' ); ?>
        </p>
    </div>
    <?php
}