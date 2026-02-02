<?php
/**
 * ACF Analyzer Admin Interface
 *
 * Handles the WordPress admin interface for the plugin including:
 * - Admin menu and page rendering
 * - User Search Options management
 * - Scheduled runner controls
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
     * - AJAX handlers for User Search Options
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Register admin menu page
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Enqueue admin assets (CSS/JS)
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // AJAX handlers for User Search Options
        add_action( 'wp_ajax_acf_analyzer_get_fields_by_category', array( $this, 'ajax_get_fields_by_category' ) );
        add_action( 'wp_ajax_acf_analyzer_save_user_options', array( $this, 'ajax_save_user_search_options' ) );

        // Admin post handler to save search options
        add_action( 'admin_post_acf_analyzer_save_options', array( $this, 'handle_save_options' ) );

        // Admin action to manually trigger daily run
        add_action( 'admin_post_acf_analyzer_run_now', array( $this, 'handle_run_now' ) );
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
     * Includes the User Search Options editor JavaScript.
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

        // Enqueue admin JavaScript
        wp_enqueue_script(
            'acf-analyzer-admin-js',
            ACF_ANALYZER_PLUGIN_URL . 'assets/js/admin-mapping.js',
            array( 'jquery' ),
            ACF_ANALYZER_VERSION,
            true
        );

        // Localize script with AJAX parameters and User Search Options
        wp_localize_script( 'acf-analyzer-admin-js', 'acfAnalyzerAdmin', array(
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'acf_analyzer_save_mapping' ),
            'userSearchOptions'  => get_option( 'acf_analyzer_user_search_options', array() ),
            'categories'         => array( 'Osakeannit', 'Osaketori', 'Velkakirjat' ),
        ) );
    }

    /**
     * Render admin page
     *
     * Loads and displays the admin page template which includes:
     * - Scheduled runner controls
     * - Debug log display
     * - User Search Options editor
     *
     * @since 1.0.0
     * @return void
     */
    public function render_admin_page() {
        // Provide last run and secret key to template
        $last_run = get_option( 'acf_analyzer_last_run', '' );
        $secret_key = get_option( 'acf_analyzer_secret_key', '' );

        // Get debug log from last run
        $last_run_debug = get_option( 'acf_analyzer_last_run_debug', array() );

        // Get saved search options
        $search_options = array(
            'default_match_logic' => get_option( 'acf_analyzer_default_match_logic', 'AND' ),
            'results_per_page'    => (int) get_option( 'acf_analyzer_results_per_page', 20 ),
            'debug_by_default'    => (bool) get_option( 'acf_analyzer_debug_by_default', false ),
        );

        // Get hakuvahti creation stats for last 7 days
        $hakuvahti_stats = Hakuvahti::get_stats_last_7_days();

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

        // Guest TTL setting (1-365 days, default 30)
        $guest_ttl_days = isset( $_POST['guest_ttl_days'] ) ? absint( $_POST['guest_ttl_days'] ) : 30;
        if ( $guest_ttl_days < 1 ) {
            $guest_ttl_days = 1;
        }
        if ( $guest_ttl_days > 365 ) {
            $guest_ttl_days = 365;
        }

        update_option( 'acf_analyzer_default_match_logic', $default_match_logic );
        update_option( 'acf_analyzer_results_per_page', $results_per_page );
        update_option( 'acf_analyzer_debug_by_default', $debug_by_default );
        update_option( 'acf_analyzer_guest_ttl_days', $guest_ttl_days );

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
     * AJAX handler: Get ACF field names for posts in a category
     *
     * @return void Sends JSON response
     */
    public function ajax_get_fields_by_category() {
        check_ajax_referer( 'acf_analyzer_save_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
        if ( empty( $category ) ) {
            wp_send_json_error( 'Missing category' );
        }

        $query = new WP_Query( array(
            'post_type'      => 'post',
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'category_name'  => $category,
        ) );

        $fields_meta = array();
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $p ) {
                if ( ! function_exists( 'get_fields' ) ) continue;
                $post_fields = get_fields( $p->ID );
                if ( ! is_array( $post_fields ) ) continue;

                foreach ( $post_fields as $k => $v ) {
                    if ( isset( $fields_meta[ $k ] ) ) continue;

                    $field_obj = false;
                    if ( function_exists( 'get_field_object' ) ) {
                        $field_obj = get_field_object( $k, $p->ID );
                    }

                    $entry = array( 'key' => $k, 'label' => ( $field_obj && ! empty( $field_obj['label'] ) ) ? $field_obj['label'] : $k, 'has_choices' => false, 'choices' => array() );
                    if ( $field_obj && isset( $field_obj['choices'] ) && is_array( $field_obj['choices'] ) && ! empty( $field_obj['choices'] ) ) {
                        $entry['has_choices'] = true;
                        $entry['choices'] = $field_obj['choices'];
                    }

                    $fields_meta[ $k ] = $entry;
                }
            }
        }

        // Sort keys for predictable order
        $keys = array_keys( $fields_meta );
        sort( $keys );

        $out = array();
        foreach ( $keys as $k ) {
            $out[] = $fields_meta[ $k ];
        }

        wp_send_json_success( $out );
    }

    /**
     * AJAX handler: Save admin-defined user search options
     *
     * @return void Sends JSON response
     */
    public function ajax_save_user_search_options() {
        check_ajax_referer( 'acf_analyzer_save_mapping', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        // Accept array 'options' or legacy JSON
        if ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
            $raw = $_POST['options'];
        } else {
            $options_json = isset( $_POST['options_json'] ) ? wp_unslash( $_POST['options_json'] ) : '';
            $raw = json_decode( $options_json, true );
        }

        if ( ! is_array( $raw ) ) {
            wp_send_json_error( 'Invalid options data' );
        }

        $sanitized = array();
        $fields_cache = array(); // category -> array(key => label)
        $valid_categories = array( 'Osakeannit', 'Osaketori', 'Velkakirjat' );

        $existing_keys = array();
        foreach ( $raw as $opt ) {
            if ( ! is_array( $opt ) ) continue;
            $name = isset( $opt['name'] ) ? sanitize_text_field( $opt['name'] ) : '';
            $key  = isset( $opt['key'] ) ? sanitize_text_field( $opt['key'] ) : '';
            $cat  = isset( $opt['category'] ) ? sanitize_text_field( $opt['category'] ) : '';
            $acf  = isset( $opt['acf_field'] ) ? sanitize_text_field( $opt['acf_field'] ) : '';
            $option_type = isset( $opt['option_type'] ) ? sanitize_text_field( $opt['option_type'] ) : 'acf_field';

            // Word search type uses special __word_search field, skip auto-detection
            if ( 'word_search' === $option_type ) {
                $acf = '__word_search';
            }
            // If acf_field missing, attempt to auto-detect based on category fields
            elseif ( $acf === '' && $cat !== '' ) {
                if ( ! isset( $fields_cache[ $cat ] ) ) {
                    // build fields for this category similar to ajax_get_fields_by_category
                    $query = new WP_Query( array(
                        'post_type'      => 'post',
                        'post_status'    => 'any',
                        'posts_per_page' => 200,
                        'category_name'  => $cat,
                    ) );

                    $fields_meta = array();
                    if ( $query->have_posts() ) {
                        foreach ( $query->posts as $p ) {
                            if ( ! function_exists( 'get_fields' ) ) continue;
                            $post_fields = get_fields( $p->ID );
                            if ( ! is_array( $post_fields ) ) continue;

                            foreach ( $post_fields as $k => $v ) {
                                if ( isset( $fields_meta[ $k ] ) ) continue;
                                $field_obj = false;
                                if ( function_exists( 'get_field_object' ) ) {
                                    $field_obj = get_field_object( $k, $p->ID );
                                }
                                $label = ( $field_obj && ! empty( $field_obj['label'] ) ) ? $field_obj['label'] : $k;
                                $fields_meta[ $k ] = $label;
                            }
                        }
                        wp_reset_postdata();
                    }

                    $fields_cache[ $cat ] = $fields_meta;
                }

                // try to match by key first
                if ( $key && isset( $fields_cache[ $cat ][ $key ] ) ) {
                    $acf = $key;
                } else {
                    // try to match by display name -> label
                    $found = '';
                    foreach ( $fields_cache[ $cat ] as $fkey => $flabel ) {
                        if ( strcasecmp( $flabel, $name ) === 0 || strcasecmp( $fkey, $name ) === 0 || strcasecmp( $fkey, sanitize_title( $name ) ) === 0 ) {
                            $found = $fkey;
                            break;
                        }
                    }
                    if ( $found ) {
                        $acf = $found;
                    }
                }
            }

            if ( $name === '' || ! in_array( $cat, $valid_categories, true ) ) {
                continue;
            }

            // generate a key from name if not provided
            if ( $key === '' ) {
                $key = sanitize_title( $name );
            } else {
                $key = sanitize_title( $key );
            }

            // ensure unique key
            $base = $key;
            $i = 1;
            while ( in_array( $key, $existing_keys, true ) ) {
                $key = $base . '-' . $i;
                $i++;
            }
            $existing_keys[] = $key;

            // sanitize values if provided (choices array or min/max object)
            $sanitized_values = null;
            if ( isset( $opt['values'] ) ) {
                if ( is_array( $opt['values'] ) ) {
                    // detect associative min/max or numeric-indexed choices
                    $is_assoc = array_keys( $opt['values'] ) !== range(0, count( $opt['values'] ) - 1 );
                    if ( $is_assoc && isset( $opt['values']['min'] ) || isset( $opt['values']['max'] ) ) {
                        $sanitized_values = array(
                            'min' => isset( $opt['values']['min'] ) ? sanitize_text_field( $opt['values']['min'] ) : '',
                            'max' => isset( $opt['values']['max'] ) ? sanitize_text_field( $opt['values']['max'] ) : '',
                            'postfix' => isset( $opt['values']['postfix'] ) ? sanitize_text_field( $opt['values']['postfix'] ) : '',
                        );
                    } else {
                        // treat as list of choice keys
                        $sanitized_values = array();
                        foreach ( $opt['values'] as $v ) {
                            $sanitized_values[] = sanitize_text_field( $v );
                        }
                    }
                }
            }

            // Require acf_field at this point; if still missing, abort and ask admin to correct
            // (word_search type is exempt as it uses special __word_search field)
            if ( empty( $acf ) && 'word_search' !== $option_type ) {
                wp_send_json_error( array( 'message' => sprintf( __( 'ACF field could not be determined for option "%s" in category "%s". Please set the ACF field explicitly.', 'acf-analyzer' ), $name, $cat ) ) );
            }

            $sanitized[] = array(
                'name' => $name,
                'key'  => $key,
                'category' => $cat,
                'option_type' => $option_type,
                'acf_field' => $acf,
                'values' => $sanitized_values,
            );
        }

        update_option( 'acf_analyzer_user_search_options', $sanitized );

        wp_send_json_success( array( 'sanitized' => $sanitized, 'raw' => $raw ) );
    }
}