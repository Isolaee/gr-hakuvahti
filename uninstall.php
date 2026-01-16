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

// Drop hakuvahdit table
global $wpdb;
$table_name = $wpdb->prefix . 'hakuvahdit';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Delete any options (if you add any in the future)
// delete_option( 'acf_analyzer_settings' );