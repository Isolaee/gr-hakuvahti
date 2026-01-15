<?php
/**
 * ACF Analyzer Shortcode Handler
 *
 * Handles frontend shortcodes and AJAX functionality for:
 * - Search popup shortcode for ACF field searching
 * - WP Grid Builder facet logger button
 * - Frontend AJAX handlers for search and field retrieval
 * - Asset enqueueing for frontend pages
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
        add_shortcode( 'acf_search_popup', array( $this, 'render_search_popup' ) );
        add_shortcode( 'wpgb_facet_logger', array( $this, 'render_wpgb_facet_logger' ) );
        
        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        
        // AJAX handlers for search (logged-in and public)
        add_action( 'wp_ajax_acf_popup_search', array( $this, 'ajax_search_handler' ) );
        add_action( 'wp_ajax_nopriv_acf_popup_search', array( $this, 'ajax_search_handler' ) );
        
        // AJAX handlers for field retrieval (logged-in and public)
        add_action( 'wp_ajax_acf_popup_get_fields', array( $this, 'ajax_get_fields' ) );
        add_action( 'wp_ajax_nopriv_acf_popup_get_fields', array( $this, 'ajax_get_fields' ) );
        
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
     * the wpgb_facet_logger or acf_search_popup shortcode.
     * 
     * @since 1.0.0
     * @return void
     */
    public function enqueue_frontend_assets() {
        // Enqueue frontend stylesheet
        wp_enqueue_style(
            'acf-analyzer-frontend',
            ACF_ANALYZER_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ACF_ANALYZER_VERSION
        );

        // Enqueue frontend JavaScript
        wp_enqueue_script(
            'acf-analyzer-frontend',
            ACF_ANALYZER_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            ACF_ANALYZER_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script(
            'acf-analyzer-frontend',
            'acfAnalyzer',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'acf_popup_search' ),
            )
        );

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
                // Check if either shortcode is present in the content
                if ( has_shortcode( $content, 'wpgb_facet_logger' ) || has_shortcode( $content, 'acf_search_popup' ) ) {
                    $should_enqueue_logger = true;
                }
            }
        }

        // Enqueue and localize logger script if needed
        if ( $should_enqueue_logger ) {
            wp_enqueue_script( 'acf-analyzer-wpgb-logger' );
            wp_localize_script( 'acf-analyzer-wpgb-logger', 'acfWpgbLogger', array(
                'use_api_default' => true,
            ) );
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
    public function render_wpgb_facet_logger( $atts ) {
        // Parse shortcode attributes with defaults
        $atts = shortcode_atts(
            array(
                'label'  => 'Log WPGB facets',
                'target' => '',
                'use_api' => 'true',
            ),
            $atts,
            'wpgb_facet_logger'
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

                // Provide configuration to the JavaScript
                $use_api_bool = in_array( strtolower( $atts['use_api'] ), array( '1', 'true', 'yes' ), true );
                wp_localize_script( 'acf-analyzer-wpgb-logger', 'acfWpgbLogger', array(
                    'use_api_default' => $use_api_bool,
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'acf_popup_search' ),
                ) );

                // Load admin-defined mapping from database option
                // Maps WP Grid Builder facet slugs to ACF field names
                $admin_mapping = get_option( 'acf_wpgb_facet_map', array() );
                $mapping = is_array( $admin_mapping ) ? $admin_mapping : array();
                wp_localize_script( 'acf-analyzer-wpgb-logger', 'acfWpgbFacetMap', $mapping );
        }

        // Load and return the button template
        ob_start();
        include ACF_ANALYZER_PLUGIN_DIR . 'templates/logger-button.php';
        return ob_get_clean();
    }

    /**
     * Get WP Grid Builder facet to ACF field mapping
     * 
     * Legacy method - mapping is now handled via admin interface
     * and stored in 'acf_wpgb_facet_map' option.
     * 
     * @since 1.0.0
     * @deprecated Use get_option('acf_wpgb_facet_map') instead
     * 
     * @return array Mapping array (facet_slug => acf_field_name)
     */
    protected function get_wpgb_facet_mapping() {
        $mapping = array();

        if ( ! function_exists( 'get_post_types' ) ) {
            return $mapping;
        }

        $types = get_post_types( array(), 'names' );
        $candidates = array();
        foreach ( $types as $t ) {
            if ( stripos( $t, 'wpgb' ) !== false || stripos( $t, 'grid' ) !== false || stripos( $t, 'facet' ) !== false ) {
                $candidates[] = $t;
            }
        }

        if ( empty( $candidates ) ) {
            return $mapping;
        }

        foreach ( $candidates as $ptype ) {
            $posts = get_posts( array( 'post_type' => $ptype, 'numberposts' => -1 ) );
            foreach ( $posts as $p ) {
                $slug = null;
                // try common places: post_name, post_title
                if ( ! empty( $p->post_name ) ) {
                    $slug = $p->post_name;
                }

                // try to extract from post meta
                $meta = get_post_meta( $p->ID );
                if ( is_array( $meta ) && ! empty( $meta ) ) {
                    foreach ( $meta as $k => $v ) {
                        // look for obvious keys
                        if ( stripos( $k, 'slug' ) !== false || stripos( $k, 'name' ) !== false ) {
                            if ( is_array( $v ) ) {
                                $candidate = reset( $v );
                            } else {
                                $candidate = $v;
                            }
                            if ( is_string( $candidate ) && $candidate !== '' ) {
                                $slug = $slug ?: sanitize_text_field( $candidate );
                            }
                        }

                        // if meta contains 'acf' or 'field' try to extract mapping
                        if ( ( stripos( $k, 'acf' ) !== false || stripos( $k, 'field' ) !== false || stripos( $k, 'source' ) !== false ) && ! empty( $v ) ) {
                            $val = is_array( $v ) ? reset( $v ) : $v;
                            if ( is_string( $val ) && $val !== '' ) {
                                $acf_field = sanitize_text_field( $val );
                                if ( $slug ) {
                                    $mapping[ $slug ] = $acf_field;
                                }
                            } elseif ( is_array( $val ) ) {
                                // if it's an array try to find keys that look like ACF
                                foreach ( $val as $sub ) {
                                    if ( is_string( $sub ) && ( stripos( $sub, 'field_' ) !== false || stripos( $sub, 'acf_' ) !== false || preg_match('/^[a-z0-9_]+$/i', $sub) ) ) {
                                        if ( $slug ) {
                                            $mapping[ $slug ] = sanitize_text_field( $sub );
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // try to parse JSON from content if present
                if ( empty( $mapping ) || ! isset( $mapping[ $slug ] ) ) {
                    $content = trim( $p->post_content );
                    if ( $content ) {
                        $decoded = json_decode( $content, true );
                        if ( is_array( $decoded ) ) {
                            // look for keys that point to source/field
                            if ( isset( $decoded['source'] ) && $slug ) {
                                $mapping[ $slug ] = sanitize_text_field( (string) $decoded['source'] );
                            } elseif ( isset( $decoded['field'] ) && $slug ) {
                                $mapping[ $slug ] = sanitize_text_field( (string) $decoded['field'] );
                            } else {
                                // deep scan for any value containing 'acf' or 'field'
                                array_walk_recursive( $decoded, function( $v ) use ( &$mapping, $slug ) {
                                    if ( ! $slug ) return;
                                    if ( is_string( $v ) && ( stripos( $v, 'acf' ) !== false || stripos( $v, 'field' ) !== false ) ) {
                                        $mapping[ $slug ] = sanitize_text_field( $v );
                                    }
                                } );
                            }
                        }
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Render search popup shortcode
     * 
     * Creates a search popup interface for searching posts by ACF fields.
     * The popup type can be specified to filter by specific categories.
     * 
     * Shortcode: [acf_search_popup type="Osakeannit"]
     * 
     * @since 1.0.0
     * 
     * @param array $atts {
     *     Shortcode attributes
     * 
     *     @type string $type Post category type (Osakeannit, Osaketori, or Velkakirjat).
     *                        Default: 'Osakeannit'
     * }
     * 
     * @return string HTML output for the search popup
     */
    public function render_search_popup( $atts ) {
        // Parse attributes with defaults
        $atts = shortcode_atts(
            array(
                'type' => 'Osakeannit', // Default type: Osakeannit, Osaketori, or Velkakirjat
            ),
            $atts,
            'acf_search_popup'
        );

        // Validate type against allowed values
        $allowed_types = array( 'Osakeannit', 'Osaketori', 'Velkakirjat' );
        if ( ! in_array( $atts['type'], $allowed_types, true ) ) {
            $atts['type'] = 'Osakeannit';
        }

        // Load and return the popup template
        ob_start();
        include ACF_ANALYZER_PLUGIN_DIR . 'templates/popup-search.php';
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

        // Sanitize criteria
        $sanitized_criteria = array();

        // Support two input shapes:
        // 1) array of { field: 'name', value: 'x' }
        // 2) associative mapping: field_name => value
        if ( is_array( $criteria ) ) {
            // Detect associative mapping (string keys)
            $is_assoc = array_keys( $criteria ) !== range( 0, count( $criteria ) - 1 );
            if ( $is_assoc ) {
                foreach ( $criteria as $field => $val ) {
                    $f = sanitize_text_field( (string) $field );
                    $v = is_scalar( $val ) ? sanitize_text_field( (string) $val ) : wp_json_encode( $val );
                    if ( $f !== '' ) {
                        $sanitized_criteria[ $f ] = $v;
                    }
                }
            } else {
                foreach ( $criteria as $criterion ) {
                    if ( isset( $criterion['field'] ) && isset( $criterion['value'] ) ) {
                        $field = sanitize_text_field( $criterion['field'] );
                        $value = sanitize_text_field( (string) $criterion['value'] );
                        if ( isset( $sanitized_criteria[ $field ] ) ) {
                            // If existing value is not an array, convert it
                            if ( ! is_array( $sanitized_criteria[ $field ] ) ) {
                                $sanitized_criteria[ $field ] = array( $sanitized_criteria[ $field ] );
                            }
                            $sanitized_criteria[ $field ][] = $value;
                        } else {
                            $sanitized_criteria[ $field ] = $value;
                        }
                    }
                }
            }
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
}
