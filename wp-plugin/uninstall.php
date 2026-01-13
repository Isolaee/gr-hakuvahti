<?php
/**
 * Uninstall script
 *
 * Runs when the plugin is uninstalled
 */

// Prevent direct access
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete transients
delete_transient( 'acf_analyzer_results' );

// Delete any options (if you add any in the future)
// delete_option( 'acf_analyzer_settings' );