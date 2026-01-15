<?php
/**
 * Plugin Name: ACF Field Analyzer
 * Plugin URI: https://github.com/yourusername/acf-analyzer
 * Description: Analyze Advanced Custom Fields usage across your WordPress site
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-analyzer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'ACF_ANALYZER_VERSION', '1.0.0' );
define( 'ACF_ANALYZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACF_ANALYZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load plugin classes
require_once ACF_ANALYZER_PLUGIN_DIR . 'includes/class-acf-analyzer.php';
require_once ACF_ANALYZER_PLUGIN_DIR . 'includes/class-acf-analyzer-admin.php';
require_once ACF_ANALYZER_PLUGIN_DIR . 'includes/class-acf-analyzer-shortcode.php';

// Initialize the plugin
function acf_analyzer_init() {
    // Initialize admin interface (always available so mappings can be managed)
    new ACF_Analyzer_Admin();

    // Check if ACF is active for frontend features
    if ( ! function_exists( 'get_fields' ) ) {
        add_action( 'admin_notices', 'acf_analyzer_acf_missing_notice' );
        return;
    }

    // Initialize shortcode functionality (only when ACF is present)
    new ACF_Analyzer_Shortcode();
}
add_action( 'plugins_loaded', 'acf_analyzer_init' );

// Display notice if ACF is not active
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