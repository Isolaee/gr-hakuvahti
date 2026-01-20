<?php
/**
 * ACF Analyzer Admin Interface
 *
 * Handles the WordPress admin interface for the plugin including:
 * - Admin menu and page rendering
 * - WP Grid Builder to ACF field mapping editor
 * - Search form and results display
 * - AJAX handlers for saving mappings and field retrieval
 * 
 * @package ACF_Analyzer
 * @since 1.0.0
 */

class ACF_Analyzer_Admin {

    /**
     * Constructor - Register WordPress hooks
     * 
     * Sets up all admin-related WordPress hooks including:
     * - Admin menu registration
     * - Asset enqueueing
     * - AJAX handlers for mappings and search
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Register admin menu page
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Enqueue admin assets (CSS/JS)
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // AJAX handler for saving facet mappings
        add_action( 'wp_ajax_acf_analyzer_save_mapping', array( $this, 'ajax_save_mapping' ) );
        
        // Handler for search form submission
        add_action( 'admin_post_acf_analyzer_search', array( $this, 'handle_search' ) );
        
        // AJAX handler for retrieving ACF field names
        add_action( 'wp_ajax_acf_analyzer_get_fields', array( $this, 'ajax_get_field_names' ) );
        // Admin post handler to save search options
        add_action( 'admin_post_acf_analyzer_save_options', array( $this, 'handle_save_options' ) );
        // Admin action to manually trigger daily run
        add_action( 'admin_post_acf_analyzer_run_now', array( $this, 'handle_run_now' ) );

        // AJAX handlers for unrestricted search field definitions
        add_action( 'wp_ajax_acf_analyzer_save_unrestricted_toggle', array( $this, 'ajax_save_unrestricted_toggle' ) );
        add_action( 'wp_ajax_acf_analyzer_save_unrestricted_fields', array( $this, 'ajax_save_unrestricted_fields' ) );
        add_action( 'wp_ajax_acf_analyzer_get_unrestricted_fields', array( $this, 'ajax_get_unrestricted_fields' ) );
    }

    /**
     * Add admin menu page
     * 
     * Creates a submenu page under WordPress Tools menu.
     * The page displays the search interface and mapping editor.
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        add_management_page(
            __( 'ACF Field Analyzer', 'acf-analyzer' ),  // Page title
            __( 'ACF Analyzer', 'acf-analyzer' ),        // Menu title
            'manage_options',                             // Capability required
            'acf-analyzer',                               // Menu slug
            array( $this, 'render_admin_page' )          // Callback function
        );
    }

    /**
     * Enqueue admin CSS and JS
     * 
     * Loads admin-specific assets only on the plugin's admin page.
     * Includes the mapping editor JavaScript and localizes it with
     * current mappings and AJAX parameters.
     * 
     * @since 1.0.0
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin page
        if ( 'tools_page_acf-analyzer' !== $hook ) {
            return;
        }

        // Enqueue admin stylesheet
        wp_enqueue_style(
            'acf-analyzer-admin',
            ACF_ANALYZER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ACF_ANALYZER_VERSION
        );

        // Enqueue mapping editor JavaScript
        wp_enqueue_script(
            'acf-analyzer-admin-js',
            ACF_ANALYZER_PLUGIN_URL . 'assets/js/admin-mapping.js',
            array( 'jquery' ),
            ACF_ANALYZER_VERSION,
            true
        );

        // Localize script with current mapping and AJAX parameters
        $mapping = get_option( 'acf_wpgb_facet_map', array() );
        $unrestricted_fields = get_option( 'acf_analyzer_unrestricted_fields', array() );
        $unrestricted_enabled = get_option( 'acf_analyzer_unrestricted_search', false );

        wp_localize_script( 'acf-analyzer-admin-js', 'acfAnalyzerAdmin', array(
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'acf_analyzer_save_mapping' ),
            'mapping'            => $mapping,
            'unrestrictedFields' => $unrestricted_fields,
            'unrestrictedEnabled' => (bool) $unrestricted_enabled,
            'searchOptions'      => array(
                'default_match_logic' => get_option( 'acf_analyzer_default_match_logic', 'AND' ),
                'results_per_page'    => (int) get_option( 'acf_analyzer_results_per_page', 20 ),
                'debug_by_default'    => (bool) get_option( 'acf_analyzer_debug_by_default', false ),
            ),
        ) );
    }

    /**
     * AJAX handler: Save WP Grid Builder facet mappings
     * 
     * Receives mapping data from the admin interface and saves it to the database.
     * Mapping format: array( 'facet_slug' => 'acf_field_name' )
     * 
     * @since 1.0.0
     * @return void Sends JSON response
     */
    public function ajax_save_mapping() {
        // Verify nonce for security
        check_ajax_referer( 'acf_analyzer_save_mapping', 'nonce' );

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        // Get and validate mapping data
        $data = isset( $_POST['mapping'] ) ? $_POST['mapping'] : array();
        if ( ! is_array( $data ) ) {
            wp_send_json_error( 'Bad mapping data' );
        }

        // Sanitize mapping entries
        $sanitized = array();
        foreach ( $data as $slug => $field ) {
            $s_slug = sanitize_text_field( $slug );
            $s_field = sanitize_text_field( $field );
            if ( $s_slug !== '' ) {
                $sanitized[ $s_slug ] = $s_field;
            }
        }

        // Save to database
        update_option( 'acf_wpgb_facet_map', $sanitized );

        // Return success with sanitized data
        wp_send_json_success( $sanitized );
    }

    /**
     * Render admin page
     * 
     * Loads and displays the admin page template which includes:
     * - WP Grid Builder facet mapping editor
     * - ACF field search form
     * - Search results display
     * 
     * @since 1.0.0
     * @return void
     */
    public function render_admin_page() {
        // Get previous search results if available (stored in transient)
        $search_results = get_transient( 'acf_analyzer_search_results' );

        // Clear field names cache if requested via URL parameter
        if ( isset( $_GET['refresh_fields'] ) && $_GET['refresh_fields'] === '1' ) {
            delete_transient( 'acf_analyzer_field_names' );
        }

        // Get available ACF field names for dropdown population
        $acf_field_names = $this->get_acf_field_names();

        // Provide last run and secret key to template
        $last_run = get_option( 'acf_analyzer_last_run', '' );
        $secret_key = get_option( 'acf_analyzer_secret_key', '' );

        // Get recent matches for display
        $recent_matches = Hakuvahti::get_recent_matches( 3 );

        // Get debug log from last run
        $last_run_debug = get_option( 'acf_analyzer_last_run_debug', array() );

        // Get saved search options
        $search_options = array(
            'default_match_logic' => get_option( 'acf_analyzer_default_match_logic', 'AND' ),
            'results_per_page'    => (int) get_option( 'acf_analyzer_results_per_page', 20 ),
            'debug_by_default'    => (bool) get_option( 'acf_analyzer_debug_by_default', false ),
        );

        // Load and display the admin template
        include ACF_ANALYZER_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Handle saving of search options from admin form
     *
     * @return void Redirects back to admin page
     */
    public function handle_save_options() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'acf-analyzer' ) );
        }

        if ( ! isset( $_POST['acf_analyzer_save_options_nonce'] ) || ! wp_verify_nonce( $_POST['acf_analyzer_save_options_nonce'], 'acf_analyzer_save_options' ) ) {
            wp_die( __( 'Invalid nonce', 'acf-analyzer' ) );
        }

        $default_match_logic = isset( $_POST['default_match_logic'] ) && in_array( $_POST['default_match_logic'], array( 'AND', 'OR' ), true ) ? sanitize_text_field( $_POST['default_match_logic'] ) : 'AND';
        $results_per_page = isset( $_POST['results_per_page'] ) ? absint( $_POST['results_per_page'] ) : 20;
        if ( $results_per_page <= 0 ) { $results_per_page = 20; }
        $debug_by_default = isset( $_POST['debug_by_default'] ) && ( $_POST['debug_by_default'] === '1' || $_POST['debug_by_default'] === 'on' );

        update_option( 'acf_analyzer_default_match_logic', $default_match_logic );
        update_option( 'acf_analyzer_results_per_page', $results_per_page );
        update_option( 'acf_analyzer_debug_by_default', $debug_by_default );

        wp_redirect( add_query_arg( 'options', 'saved', admin_url( 'tools.php?page=acf-analyzer' ) ) );
        exit;
    }

    /**
     * Handle manual 'Run now' admin_post action
     *
     * @return void
     */
    public function handle_run_now() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'acf-analyzer' ) );
        }

        check_admin_referer( 'acf_analyzer_run_now' );

        // Save the ignore_seen setting
        $ignore_seen = isset( $_POST['ignore_seen'] ) && $_POST['ignore_seen'] === '1';
        update_option( 'acf_analyzer_ignore_seen', $ignore_seen );

        // Trigger the same scheduled action immediately
        do_action( 'acf_analyzer_daily_runner' );

        wp_redirect( add_query_arg( 'run', 'ok', admin_url( 'tools.php?page=acf-analyzer' ) ) );
        exit;
    }

    /**
     * Get ACF field names with caching
     * 
     * Retrieves all ACF field names from the database and caches them
     * for one hour to improve performance. The cache is stored in a transient.
     * 
     * @since 1.0.0
     * @return array List of ACF field names
     */
    private function get_acf_field_names() {
        // Try to get from cache first
        $field_names = get_transient( 'acf_analyzer_field_names' );

        // If not in cache, fetch from database
        if ( false === $field_names ) {
            $analyzer = new ACF_Analyzer();
            $field_names = $analyzer->get_all_field_names();
            
            // Only cache if we found fields, otherwise try again next load
            if ( ! empty( $field_names ) ) {
                set_transient( 'acf_analyzer_field_names', $field_names, HOUR_IN_SECONDS );
            }
        }

        return is_array( $field_names ) ? $field_names : array();
    }

    /**
     * AJAX handler: Get ACF field names
     * 
     * Refreshes the field names cache and returns all available ACF field names.
     * Used by the admin interface to update field selection dropdowns.
     * 
     * @since 1.0.0
     * @return void Sends JSON response
     */
    public function ajax_get_field_names() {
        // Verify nonce
        check_ajax_referer( 'acf_analyzer_search', 'nonce' );

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        // Clear cache and fetch fresh field names
        delete_transient( 'acf_analyzer_field_names' );
        $field_names = $this->get_acf_field_names();

        // Return error if no fields found
        if ( empty( $field_names ) ) {
            wp_send_json_error( __( 'No ACF fields found', 'acf-analyzer' ) );
        }

        // Return success with field names
        wp_send_json_success( $field_names );
    }

    /**
     * Handle search by criteria request
     * 
     * Processes the search form submission, validates input, performs the search,
     * and stores results in a transient for display. Supports:
     * - Multiple search criteria
     * - Range comparisons (min/max)
     * - AND/OR match logic
     * - Debug mode for detailed match info
     * 
     * @since 1.0.0
     * @return void Redirects back to admin page with results
     */
    public function handle_search() {
        // Verify nonce
        if ( ! isset( $_POST['acf_analyzer_search_nonce'] ) ||
             ! wp_verify_nonce( $_POST['acf_analyzer_search_nonce'], 'acf_analyzer_search' ) ) {
            wp_die( __( 'Invalid nonce', 'acf-analyzer' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'acf-analyzer' ) );
        }

        // Get criteria from form
        $field_names    = isset( $_POST['criteria_field'] ) ? array_map( 'sanitize_text_field', $_POST['criteria_field'] ) : array();
        $field_values   = isset( $_POST['criteria_value'] ) ? array_map( 'sanitize_text_field', $_POST['criteria_value'] ) : array();
        $field_compares = isset( $_POST['criteria_compare'] ) ? array_map( 'sanitize_text_field', $_POST['criteria_compare'] ) : array();

        // Build criteria array with comparison suffixes
        $criteria = array();
        foreach ( $field_names as $index => $field_name ) {
            $field_name = trim( $field_name );
            if ( ! empty( $field_name ) && isset( $field_values[ $index ] ) ) {
                $compare = isset( $field_compares[ $index ] ) ? $field_compares[ $index ] : 'equals';
                $key     = $field_name;

                // Append suffix for range comparisons
                if ( 'min' === $compare ) {
                    $key = $field_name . '_min';
                } elseif ( 'max' === $compare ) {
                    $key = $field_name . '_max';
                }

                $criteria[ $key ] = $field_values[ $index ];
            }
        }

        if ( empty( $criteria ) ) {
            wp_redirect( add_query_arg( 'error', 'no_criteria', admin_url( 'tools.php?page=acf-analyzer' ) ) );
            exit;
        }

        // Get options
        $match_logic = isset( $_POST['match_logic'] ) ? sanitize_text_field( $_POST['match_logic'] ) : 'AND';
        $debug       = isset( $_POST['debug'] ) && $_POST['debug'] === '1';

        // Run search
        $analyzer = new ACF_Analyzer();
        $search_results = $analyzer->search_by_criteria(
            $criteria,
            array(
                'match_logic' => $match_logic,
                'debug'       => $debug,
            )
        );

        // Store results in transient (expires in 1 hour)
        set_transient( 'acf_analyzer_search_results', $search_results, HOUR_IN_SECONDS );

        // Redirect back with success message
        wp_redirect( add_query_arg( 'search', 'complete', admin_url( 'tools.php?page=acf-analyzer' ) ) );
        exit;
    }

    /**
     * AJAX handler: Save unrestricted search toggle
     *
     * @since 1.2.0
     * @return void Sends JSON response
     */
    public function ajax_save_unrestricted_toggle() {
        check_ajax_referer( 'acf_analyzer_save_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $enabled = isset( $_POST['enabled'] ) && ( $_POST['enabled'] === '1' || $_POST['enabled'] === 'true' || $_POST['enabled'] === true );
        update_option( 'acf_analyzer_unrestricted_search', $enabled );

        wp_send_json_success( array( 'enabled' => $enabled ) );
    }

    /**
     * AJAX handler: Save unrestricted search field definitions
     *
     * Expected POST data:
     * - nonce: Security nonce
     * - fields_json: JSON string of category => field definitions
     *
     * @since 1.2.0
     * @return void Sends JSON response
     */
    public function ajax_save_unrestricted_fields() {
        check_ajax_referer( 'acf_analyzer_save_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        // Accept either a direct POSTed array `fields` (preferred) or the legacy `fields_json` string
        if ( isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ) {
            $fields = $_POST['fields'];
        } else {
            // Decode JSON string from POST (legacy)
            $fields_json = isset( $_POST['fields_json'] ) ? wp_unslash( $_POST['fields_json'] ) : '';
            $fields = json_decode( $fields_json, true );
        }

        // Debug: log received payload for troubleshooting
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[acf_analyzer] ajax_save_unrestricted_fields - raw fields_json: ' . substr( $fields_json, 0, 10000 ) );
            error_log( '[acf_analyzer] ajax_save_unrestricted_fields - decoded fields: ' . print_r( $fields, true ) );
        }

        if ( ! is_array( $fields ) ) {
            wp_send_json_error( 'Invalid fields data' );
        }

        // Sanitize field definitions
        $sanitized = array();
        $valid_categories = array( 'Osakeannit', 'Osaketori', 'Velkakirjat' );

        foreach ( $fields as $category => $category_fields ) {
            $category = sanitize_text_field( $category );
            if ( ! in_array( $category, $valid_categories, true ) ) {
                continue;
            }

            $sanitized[ $category ] = array();

            if ( is_array( $category_fields ) ) {
                foreach ( $category_fields as $field ) {
                    if ( ! is_array( $field ) || empty( $field['name'] ) || empty( $field['acf_key'] ) ) {
                        continue;
                    }

                    $sanitized_field = array(
                        'name'    => sanitize_text_field( $field['name'] ),
                        'acf_key' => sanitize_text_field( $field['acf_key'] ),
                        'type'    => in_array( $field['type'], array( 'range', 'multiple_choice' ), true ) ? $field['type'] : 'range',
                        'options' => array(),
                    );

                    // Handle multiple choice options
                    if ( $sanitized_field['type'] === 'multiple_choice' && ! empty( $field['options'] ) ) {
                        if ( is_array( $field['options'] ) ) {
                            foreach ( $field['options'] as $key => $value ) {
                                $sanitized_field['options'][ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
                            }
                        }
                    }

                    $sanitized[ $category ][] = $sanitized_field;
                }
            }
        }

        update_option( 'acf_analyzer_unrestricted_fields', $sanitized );

        // Return both sanitized and raw decoded payload to aid debugging client-side
        $response = array(
            'sanitized' => $sanitized,
            'raw'       => $fields,
            // also include under `fields` key for parity with JS payload
            'fields'    => $sanitized,
        );

        wp_send_json_success( $response );
    }

    /**
     * AJAX handler: Get unrestricted search field definitions
     *
     * @since 1.2.0
     * @return void Sends JSON response
     */
    public function ajax_get_unrestricted_fields() {
        check_ajax_referer( 'acf_analyzer_save_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $fields = get_option( 'acf_analyzer_unrestricted_fields', array() );
        $enabled = get_option( 'acf_analyzer_unrestricted_search', false );

        wp_send_json_success( array(
            'fields'  => $fields,
            'enabled' => (bool) $enabled,
        ) );
    }
}