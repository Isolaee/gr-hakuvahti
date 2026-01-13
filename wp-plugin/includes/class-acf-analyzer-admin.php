<?php
/**
 * ACF Analyzer Admin Interface
 *
 * Handles the WordPress admin interface for the plugin
 */

class ACF_Analyzer_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_post_acf_analyzer_run', array( $this, 'handle_analysis' ) );
        add_action( 'admin_post_acf_analyzer_export', array( $this, 'handle_export' ) );
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_management_page(
            __( 'ACF Field Analyzer', 'acf-analyzer' ),
            __( 'ACF Analyzer', 'acf-analyzer' ),
            'manage_options',
            'acf-analyzer',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Enqueue admin CSS and JS
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'tools_page_acf-analyzer' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'acf-analyzer-admin',
            ACF_ANALYZER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ACF_ANALYZER_VERSION
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Get all public post types
        $post_types = get_post_types( array( 'public' => true ), 'objects' );

        // Get previous analysis results if available
        $results = get_transient( 'acf_analyzer_results' );

        include ACF_ANALYZER_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Handle analysis request
     */
    public function handle_analysis() {
        // Verify nonce
        if ( ! isset( $_POST['acf_analyzer_nonce'] ) ||
             ! wp_verify_nonce( $_POST['acf_analyzer_nonce'], 'acf_analyzer_run' ) ) {
            wp_die( __( 'Invalid nonce', 'acf-analyzer' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'acf-analyzer' ) );
        }

        // Get selected post types
        $selected_post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', $_POST['post_types'] ) : array();

        if ( empty( $selected_post_types ) ) {
            wp_redirect( add_query_arg( 'error', 'no_post_types', admin_url( 'tools.php?page=acf-analyzer' ) ) );
            exit;
        }

        // Run analysis
        $analyzer = new ACF_Analyzer();
        $results = $analyzer->analyze( $selected_post_types );

        // Store results in transient (expires in 1 hour)
        set_transient( 'acf_analyzer_results', $results, HOUR_IN_SECONDS );

        // Redirect back with success message
        wp_redirect( add_query_arg( 'analysis', 'complete', admin_url( 'tools.php?page=acf-analyzer' ) ) );
        exit;
    }

    /**
     * Handle export request
     */
    public function handle_export() {
        // Verify nonce
        if ( ! isset( $_GET['nonce'] ) ||
             ! wp_verify_nonce( $_GET['nonce'], 'acf_analyzer_export' ) ) {
            wp_die( __( 'Invalid nonce', 'acf-analyzer' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'acf-analyzer' ) );
        }

        // Get results from transient
        $results = get_transient( 'acf_analyzer_results' );

        if ( ! $results ) {
            wp_die( __( 'No analysis results available. Please run an analysis first.', 'acf-analyzer' ) );
        }

        $format = isset( $_GET['format'] ) ? sanitize_text_field( $_GET['format'] ) : 'json';
        $analyzer = new ACF_Analyzer();

        // Set headers for download
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="acf-analysis-' . date( 'Y-m-d-His' ) . '.' . $format . '"' );
        header( 'Pragma: no-cache' );

        if ( $format === 'csv' ) {
            echo $analyzer->export_csv( $results );
        } else {
            echo $analyzer->export_json( $results );
        }

        exit;
    }
}