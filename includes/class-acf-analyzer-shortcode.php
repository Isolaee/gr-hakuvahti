<?php
/**
 * ACF Analyzer Shortcode Handler
 *
 * Handles frontend shortcodes and AJAX functionality for:
 * - WP Grid Builder facet logger button
 * - Frontend AJAX handlers for search and field retrieval
 * 
 * @package ACF_Analyzer
 * @since 1.0.0
 */

class ACF_Analyzer_Shortcode {

    /**
     * Constructor - Register shortcodes and hooks
     * 
     * Sets up WordPress hooks for:
     * - Shortcode registration
     * - Frontend asset enqueueing
     * - AJAX handlers (both logged-in and logged-out users)
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Register shortcodes
        add_shortcode( 'hakuvahti', array( $this, 'render_hakuvahti' ) );
        
        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        
        // AJAX handlers for search (logged-in and public)
        add_action( 'wp_ajax_acf_popup_search', array( $this, 'ajax_search_handler' ) );
        add_action( 'wp_ajax_nopriv_acf_popup_search', array( $this, 'ajax_search_handler' ) );
        
        // AJAX handlers for field retrieval (logged-in and public)
        add_action( 'wp_ajax_acf_popup_get_fields', array( $this, 'ajax_get_fields' ) );
        add_action( 'wp_ajax_nopriv_acf_popup_get_fields', array( $this, 'ajax_get_fields' ) );

        // Hakuvahti AJAX handlers (logged-in users only)
        add_action( 'wp_ajax_hakuvahti_save', array( $this, 'ajax_hakuvahti_save' ) );
        add_action( 'wp_ajax_hakuvahti_list', array( $this, 'ajax_hakuvahti_list' ) );
        add_action( 'wp_ajax_hakuvahti_run', array( $this, 'ajax_hakuvahti_run' ) );
        add_action( 'wp_ajax_hakuvahti_delete', array( $this, 'ajax_hakuvahti_delete' ) );

        // Track if shortcode is used
        $this->shortcode_used = false;
    }

    /**
     * Enqueue frontend CSS and JavaScript
     * 
     * Loads frontend assets including:
     * - Frontend CSS for popup styling
     * - Frontend JS for popup functionality
     * - WP Grid Builder facet logger (conditionally, only if shortcode is present)
     * 
     * The logger script is only enqueued on singular pages that contain
     * the hakuvahti or acf_search_popup shortcode.
     * 
     * @since 1.0.0
     * @return void
     */
    public function enqueue_frontend_assets() {
        // Register logger script (do not always enqueue; only on pages where shortcode is used)
        wp_register_script(
            'acf-analyzer-wpgb-logger',
            ACF_ANALYZER_PLUGIN_URL . 'assets/js/wpgb-facet-logger.js',
            array( 'jquery' ),
            ACF_ANALYZER_VERSION,
            true
        );

        // Conditionally enqueue logger script if the current post content contains the shortcode
        $should_enqueue_logger = false;
        if ( is_singular() ) {
            $post = get_post();
            if ( $post ) {
                $content = $post->post_content;
                if ( has_shortcode( $content, 'hakuvahti' ) ) {
                    $should_enqueue_logger = true;
                }
            }
        }

        // Enqueue and localize logger script if needed
        if ( $should_enqueue_logger ) {
            wp_enqueue_script( 'acf-analyzer-wpgb-logger' );

            // Enqueue hakuvahti CSS for the save modal
            wp_enqueue_style(
                'hakuvahti-modal',
                ACF_ANALYZER_PLUGIN_URL . 'assets/css/hakuvahti.css',
                array(),
                ACF_ANALYZER_VERSION
            );

            wp_localize_script( 'acf-analyzer-wpgb-logger', 'acfWpgbLogger', array(
                'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
                'hakuvahtiNonce'     => wp_create_nonce( 'hakuvahti_nonce' ),
                'isLoggedIn'         => is_user_logged_in(),
                'userSearchOptions'  => get_option( 'acf_analyzer_user_search_options', array() ),
            ) );
        }

                // Ensure modal stylesheet is also enqueued when rendering shortcode directly
                if ( ! wp_style_is( 'hakuvahti-modal', 'enqueued' ) ) {
                    wp_enqueue_style(
                        'hakuvahti-modal',
                        ACF_ANALYZER_PLUGIN_URL . 'assets/css/hakuvahti.css',
                        array(),
                        ACF_ANALYZER_VERSION
                    );
                }
    }

    /**
     * Render the WP Grid Builder facet logger button shortcode
     * 
     * Outputs a button that, when clicked, collects selected values from
     * WP Grid Builder facets on the page and either logs them to console
     * or sends them to the search AJAX handler.
     * 
     * The shortcode supports these attributes:
     * - label: Button text (default: 'Log WPGB facets')
     * - target: Target element selector (optional)
     * - use_api: Whether to use WPGB API (default: true)
     *
     * @since 1.0.0
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output for the logger button
     */
    public function render_hakuvahti( $atts ) {
        // Parse shortcode attributes with defaults
        $atts = shortcode_atts(
            array(
                'label'  => 'Log WPGB facets',
                'target' => '',
                'use_api' => 'true',
            ),
            $atts,
            'hakuvahti'
        );

        // Ensure the logger script is enqueued when the shortcode is rendered
        if ( function_exists( 'wp_enqueue_script' ) ) {
            // Register script if not already registered by enqueue_frontend_assets
            if ( ! wp_script_is( 'acf-analyzer-wpgb-logger', 'registered' ) ) {
                wp_register_script(
                    'acf-analyzer-wpgb-logger',
                    ACF_ANALYZER_PLUGIN_URL . 'assets/js/wpgb-facet-logger.js',
                    array( 'jquery' ),
                    ACF_ANALYZER_VERSION,
                    true
                );
            }

            // Enqueue if not already enqueued
            if ( ! wp_script_is( 'acf-analyzer-wpgb-logger', 'enqueued' ) ) {
                wp_enqueue_script( 'acf-analyzer-wpgb-logger' );
            }

            // Provide User Search Options to the JavaScript
            wp_localize_script( 'acf-analyzer-wpgb-logger', 'acfWpgbLogger', array(
                'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
                'hakuvahtiNonce'     => wp_create_nonce( 'hakuvahti_nonce' ),
                'isLoggedIn'         => is_user_logged_in(),
                'userSearchOptions'  => get_option( 'acf_analyzer_user_search_options', array() ),
            ) );
        }

        // Load and return the button template
        ob_start();
        include ACF_ANALYZER_PLUGIN_DIR . 'templates/logger-button.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler: Get available ACF fields for a category
     * 
     * Returns a list of ACF field names available in posts from the
     * specified category. Some fields are excluded from the results
     * as they're not useful for searching (e.g., images, descriptions).
     * 
     * Expected POST parameters:
     * - nonce: Security nonce
     * - category: Category slug to fetch fields from
     * 
     * @since 1.0.0
     * @return void Sends JSON response with field names
     */
    public function ajax_get_fields() {
        // Verify nonce for security
        check_ajax_referer( 'acf_popup_search', 'nonce' );

        // Get and validate category parameter
        $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

        if ( empty( $category ) ) {
            wp_send_json_error( array( 'message' => 'Category is required' ) );
        }

        // Get fields from the analyzer
        $analyzer = new ACF_Analyzer();
        $field_names = $analyzer->get_all_field_names( array( $category ) );

        // Exclude specific fields that shouldn't appear in search dropdowns
        // These are typically large text fields, images, or metadata
        $excluded_fields = array(
            'Ilmoituksen_otsikko',
            'kuva',
            'Y-tunnus',
            'verkkosivu_url',
            'yrityksen_perustamisvuosi',
            'ilmoitusteksti',
            'Videopitch',
            'markkinnointimateriaali_tiedosto',
            'tavoitteet_2026',
            'haluatko_lisata_lisatiedon',
            'kuvaus_osaamistarpeista',
            'card_image',
            'mahdolliset_rajoitukset',
            'mahdolliset_rajoitukset.0',
            'mahdolliset_rajoitukset.1',
            'markkinointimateriaali',
        );

        // Filter out excluded fields and re-index array
        $field_names = array_values( array_diff( $field_names, $excluded_fields ) );

        // Return success with field list
        wp_send_json_success( array( 'fields' => $field_names ) );
    }

    /**
     * AJAX handler: Search posts by ACF field criteria
     * 
     * Receives search criteria from the frontend and performs a search
     * using the ACF_Analyzer class. Supports flexible criteria formats:
     * - Associative array: { field_name: value }
     * - Array of objects: [{ name: field_name, value: value }]
     * - Range comparisons via _min/_max suffixes
     * 
     * Expected POST parameters:
     * - nonce: Security nonce
     * - category: Category slug to search within (optional, uses default categories if not provided)
     * - criteria: Array or object of search criteria
     * - match_logic: 'AND' or 'OR' (default: 'AND')
     * - debug: Boolean, whether to include debug info (default: false)
     * 
     * @since 1.0.0
     * @return void Sends JSON response with search results
     */
    public function ajax_search_handler() {
        // Verify nonce for security
        check_ajax_referer( 'acf_popup_search', 'nonce' );

        // Get and sanitize parameters
        $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
        $criteria = isset( $_POST['criteria'] ) ? $_POST['criteria'] : array();
        $match_logic = isset( $_POST['match_logic'] ) ? sanitize_text_field( $_POST['match_logic'] ) : 'AND';
        $debug = isset( $_POST['debug'] ) && ( $_POST['debug'] === '1' || $_POST['debug'] === 'true' || $_POST['debug'] === 1 );

        // Handle 'ALL' match_logic - return all Osakeanti posts
        if ( $match_logic === 'ALL' || empty( $criteria ) ) {
            $all_posts = array();
            $paged = 1;
            
            while ( true ) {
                $args = array(
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => 200,
                    'paged'          => $paged,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'category_name'  => 'Osakeannit',
                );

                $query = new WP_Query( $args );
                if ( ! $query->have_posts() ) {
                    break;
                }

                foreach ( $query->posts as $post ) {
                    $all_posts[] = array(
                        'id'    => $post->ID,
                        'title' => $post->post_title,
                        'url'   => get_permalink( $post->ID ),
                    );
                }

                $paged++;
                wp_reset_postdata();
            }

            wp_send_json_success( array(
                'total'   => count( $all_posts ),
                'posts'   => $all_posts,
                'message' => sprintf(
                    _n( 'Found %d Osakeanti post', 'Found %d Osakeanti posts', count( $all_posts ), 'acf-analyzer' ),
                    count( $all_posts )
                ),
            ) );
        }

        // Sanitize criteria - new format: array of { name, label, values }
        $sanitized_criteria = array();
        $or_fields = array(); // Track fields that should use OR logic

        if ( is_array( $criteria ) ) {
            foreach ( $criteria as $criterion ) {
                // New format: { name: field_name, label: 'range'|'multiple_choice', values: [...] }
                if ( isset( $criterion['name'] ) && isset( $criterion['label'] ) && isset( $criterion['values'] ) ) {
                    $field_name = sanitize_text_field( $criterion['name'] );
                    $label = sanitize_text_field( $criterion['label'] );
                    $values = is_array( $criterion['values'] ) ? array_map( 'sanitize_text_field', $criterion['values'] ) : array( sanitize_text_field( $criterion['values'] ) );
                    
                    if ( $debug ) {
                        error_log( "ACF Search - Processing criterion: name={$field_name}, label={$label}, values=" . print_r( $values, true ) );
                    }
                    
                    // Process based on label type
                    if ( $label === 'range' ) {
                        // Range: parse numeric values and create _min and _max fields
                        // Supports: plain numbers, "min:X", "max:X", operator prefixes (<, >, <=, >=)
                        $numeric_vals = array();
                        $min_val = null;
                        $max_val = null;

                        foreach ( $values as $val ) {
                            // Check for explicit min:/max: labels first
                            if ( preg_match( '/^\s*(min|max)\s*[:=]\s*(.+)$/i', $val, $label_match ) ) {
                                $which = strtolower( $label_match[1] );
                                $cleaned = preg_replace( '/[^0-9.,\-]/', '', $label_match[2] );
                                $cleaned = str_replace( ',', '.', $cleaned );
                                if ( is_numeric( $cleaned ) ) {
                                    if ( $which === 'min' ) {
                                        $min_val = floatval( $cleaned );
                                    } else {
                                        $max_val = floatval( $cleaned );
                                    }
                                }
                                continue;
                            }

                            // Check for operator prefixes (<, >, <=, >=)
                            if ( preg_match( '/^\s*([<>]=?)\s*(.+)$/', $val, $op_match ) ) {
                                $op = $op_match[1];
                                $cleaned = preg_replace( '/[^0-9.,\-]/', '', $op_match[2] );
                                $cleaned = str_replace( ',', '.', $cleaned );
                                if ( is_numeric( $cleaned ) ) {
                                    $num = floatval( $cleaned );
                                    if ( strpos( $op, '<' ) !== false ) {
                                        $max_val = ( $max_val === null ) ? $num : min( $max_val, $num );
                                    } else {
                                        $min_val = ( $min_val === null ) ? $num : max( $min_val, $num );
                                    }
                                }
                                continue;
                            }

                            // Parse plain numeric value (handle comma as decimal separator)
                            $cleaned = preg_replace( '/[^0-9.,\-]/', '', $val );
                            $cleaned = str_replace( ',', '.', $cleaned );
                            if ( is_numeric( $cleaned ) ) {
                                $numeric_vals[] = floatval( $cleaned );
                            } else {
                                // Try to extract range from string like "1000-5000"
                                if ( preg_match( '/(-?\d+[\.,]?\d*)\D+(-?\d+[\.,]?\d*)/', $val, $matches ) ) {
                                    $n1 = str_replace( ',', '.', preg_replace( '/[^0-9.,\-]/', '', $matches[1] ) );
                                    $n2 = str_replace( ',', '.', preg_replace( '/[^0-9.,\-]/', '', $matches[2] ) );
                                    if ( is_numeric( $n1 ) ) $numeric_vals[] = floatval( $n1 );
                                    if ( is_numeric( $n2 ) ) $numeric_vals[] = floatval( $n2 );
                                }
                            }
                        }

                        // If we have two or more plain numeric values, use them as min/max
                        if ( count( $numeric_vals ) >= 2 ) {
                            $min_val = min( $numeric_vals );
                            $max_val = max( $numeric_vals );
                        } elseif ( count( $numeric_vals ) === 1 && $min_val === null && $max_val === null ) {
                            // Single unlabeled value with no explicit min/max - treat as exact match (legacy)
                            $sanitized_criteria[ $field_name ] = (string) $numeric_vals[0];
                            if ( $debug ) {
                                error_log( "ACF Search - Range field '{$field_name}' single value (exact): {$numeric_vals[0]}" );
                            }
                        }

                        // Set min/max if we have them
                        if ( $min_val !== null ) {
                            $sanitized_criteria[ $field_name . '_min' ] = (string) $min_val;
                        }
                        if ( $max_val !== null ) {
                            $sanitized_criteria[ $field_name . '_max' ] = (string) $max_val;
                        }

                        if ( $debug && ( $min_val !== null || $max_val !== null ) ) {
                            error_log( "ACF Search - Range field '{$field_name}': min={$min_val}, max={$max_val}" );
                        }
                    } elseif ( $label === 'multiple_choice' ) {
                        // Multiple choice: store as array and mark for OR logic
                        $sanitized_criteria[ $field_name ] = $values;
                        $or_fields[] = $field_name;
                        if ( $debug ) {
                            error_log( "ACF Search - Multiple choice field '{$field_name}': " . print_r( $values, true ) );
                        }
                    }
                }
            }
        }

        if ( $debug ) {
            error_log( 'ACF Search - Final sanitized criteria: ' . print_r( $sanitized_criteria, true ) );
            error_log( 'ACF Search - OR fields: ' . print_r( $or_fields, true ) );
        }

        // If no sanitized criteria after processing, this shouldn't happen anymore
        // as empty criteria is already handled above with match_logic === 'ALL'
        if ( empty( $sanitized_criteria ) ) {
            wp_send_json_error( array( 'message' => 'No valid criteria provided after sanitization' ) );
        }

        // Use the existing search_by_criteria method
        $analyzer = new ACF_Analyzer();
        $options = array(
            'match_logic' => $match_logic,
            'categories'  => array( $category ),
            'debug'       => $debug,
            'or_fields'   => $or_fields, // Pass OR fields to the analyzer
        );

        $results = $analyzer->search_by_criteria( $sanitized_criteria, $options );

        // Format results for display
        $formatted_results = array();
        foreach ( $results['posts'] as $post ) {
            $item = array(
                'id'    => $post['ID'],
                'title' => $post['title'],
                'url'   => $post['url'],
            );
            if ( $options['debug'] && isset( $post['matched_criteria'] ) ) {
                $item['matched_criteria'] = $post['matched_criteria'];
            }
            $formatted_results[] = $item;
        }

        wp_send_json_success( array(
            'total'   => $results['total_found'],
            'posts'   => $formatted_results,
            'message' => sprintf(
                _n( 'Found %d post matching your criteria', 'Found %d posts matching your criteria', $results['total_found'], 'acf-analyzer' ),
                $results['total_found']
            ),
        ) );
    }

    /**
     * AJAX handler: Save a new hakuvahti
     *
     * Expected POST parameters:
     * - nonce: Security nonce
     * - name: Name for the hakuvahti
     * - category: Category to search
     * - criteria: Array of search criteria
     *
     * @since 1.1.0
     * @return void
     */
    public function ajax_hakuvahti_save() {
        // Verify nonce
        check_ajax_referer( 'hakuvahti_nonce', 'nonce' );

        // Must be logged in
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Sinun täytyy olla kirjautunut sisään.', 'acf-analyzer' ) ) );
        }

        $user_id = get_current_user_id();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
        $criteria = isset( $_POST['criteria'] ) ? $_POST['criteria'] : array();

        // Validate inputs
        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Nimi on pakollinen.', 'acf-analyzer' ) ) );
        }

        if ( empty( $category ) ) {
            wp_send_json_error( array( 'message' => __( 'Kategoria on pakollinen.', 'acf-analyzer' ) ) );
        }

        // Sanitize criteria array
        $sanitized_criteria = array();
        if ( is_array( $criteria ) ) {
            foreach ( $criteria as $item ) {
                if ( isset( $item['name'] ) && isset( $item['values'] ) ) {
                    $sanitized_criteria[] = array(
                        'name'   => sanitize_text_field( $item['name'] ),
                        'label'  => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : 'multiple_choice',
                        'values' => is_array( $item['values'] ) ? array_map( 'sanitize_text_field', $item['values'] ) : array( sanitize_text_field( $item['values'] ) ),
                    );
                }
            }
        }

        // If an ID was provided, treat this as an update (rename or update criteria)
        if ( $id ) {
            // Verify ownership
            $existing = Hakuvahti::get_by_id( $id );
            if ( ! $existing || (int) $existing->user_id !== (int) $user_id ) {
                wp_send_json_error( array( 'message' => __( 'Hakuvahtiä ei löytynyt tai oikeudet puuttuvat.', 'acf-analyzer' ) ) );
            }

            global $wpdb;
            $table = $wpdb->prefix . 'hakuvahdit';

            $update_data = array( 'name' => sanitize_text_field( $name ), 'updated_at' => current_time( 'mysql' ) );
            $update_format = array( '%s', '%s' );

            if ( ! empty( $sanitized_criteria ) ) {
                $update_data['criteria'] = wp_json_encode( $sanitized_criteria );
                array_unshift( $update_format, '%s' );
            }

            $updated = $wpdb->update( $table, $update_data, array( 'id' => $id ), $update_format, array( '%d' ) );

            if ( $updated !== false ) {
                wp_send_json_success( array( 'message' => __( 'Hakuvahti päivitetty.', 'acf-analyzer' ), 'id' => $id ) );
            }

            wp_send_json_error( array( 'message' => __( 'Hakuvahdin päivitys epäonnistui.', 'acf-analyzer' ) ) );
        }

        // Create new hakuvahti
        $new_id = Hakuvahti::create( $user_id, $name, $category, $sanitized_criteria );

        if ( $new_id ) {
            wp_send_json_success( array(
                'message' => __( 'Hakuvahti tallennettu!', 'acf-analyzer' ),
                'id'      => $new_id,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Hakuvahdin tallennus epäonnistui.', 'acf-analyzer' ) ) );
        }
    }

    /**
     * AJAX handler: List user's hakuvahdits
     *
     * @since 1.1.0
     * @return void
     */
    public function ajax_hakuvahti_list() {
        // Verify nonce
        check_ajax_referer( 'hakuvahti_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Sinun täytyy olla kirjautunut sisään.', 'acf-analyzer' ) ) );
        }

        $user_id = get_current_user_id();
        $hakuvahdits = Hakuvahti::get_by_user( $user_id );

        // Format for frontend
        $formatted = array();
        foreach ( $hakuvahdits as $hv ) {
            $formatted[] = array(
                'id'               => $hv->id,
                'name'             => $hv->name,
                'category'         => $hv->category,
                'criteria'         => $hv->criteria,
                'criteria_summary' => Hakuvahti::format_criteria_summary( $hv->criteria ),
                'created_at'       => $hv->created_at,
                'updated_at'       => $hv->updated_at,
            );
        }

        wp_send_json_success( array( 'hakuvahdits' => $formatted ) );
    }

    /**
     * AJAX handler: Run a hakuvahti search
     *
     * Expected POST parameters:
     * - nonce: Security nonce
     * - id: Hakuvahti ID
     *
     * @since 1.1.0
     * @return void
     */
    public function ajax_hakuvahti_run() {
        // Verify nonce
        check_ajax_referer( 'hakuvahti_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Sinun täytyy olla kirjautunut sisään.', 'acf-analyzer' ) ) );
        }

        $user_id = get_current_user_id();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Hakuvahti ID puuttuu.', 'acf-analyzer' ) ) );
        }

        $results = Hakuvahti::run_search( $id, $user_id );

        if ( false === $results ) {
            wp_send_json_error( array( 'message' => __( 'Hakuvahtia ei löytynyt tai sinulla ei ole oikeutta siihen.', 'acf-analyzer' ) ) );
        }

        wp_send_json_success( $results );
    }

    /**
     * AJAX handler: Delete a hakuvahti
     *
     * Expected POST parameters:
     * - nonce: Security nonce
     * - id: Hakuvahti ID
     *
     * @since 1.1.0
     * @return void
     */
    public function ajax_hakuvahti_delete() {
        // Verify nonce
        check_ajax_referer( 'hakuvahti_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Sinun täytyy olla kirjautunut sisään.', 'acf-analyzer' ) ) );
        }

        $user_id = get_current_user_id();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Hakuvahti ID puuttuu.', 'acf-analyzer' ) ) );
        }

        $deleted = Hakuvahti::delete( $id, $user_id );

        if ( $deleted ) {
            wp_send_json_success( array( 'message' => __( 'Hakuvahti poistettu.', 'acf-analyzer' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Hakuvahdin poisto epäonnistui.', 'acf-analyzer' ) ) );
        }
    }
}
