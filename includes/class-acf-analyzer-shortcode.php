<?php
/**
 * ACF Analyzer Shortcode Handler
 *
 * Handles the shortcode for frontend search pop-up functionality
 */

class ACF_Analyzer_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode( 'acf_search_popup', array( $this, 'render_search_popup' ) );
        add_shortcode( 'wpgb_facet_logger', array( $this, 'render_wpgb_facet_logger' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_ajax_acf_popup_search', array( $this, 'ajax_search_handler' ) );
        add_action( 'wp_ajax_nopriv_acf_popup_search', array( $this, 'ajax_search_handler' ) );
        add_action( 'wp_ajax_acf_popup_get_fields', array( $this, 'ajax_get_fields' ) );
        add_action( 'wp_ajax_nopriv_acf_popup_get_fields', array( $this, 'ajax_get_fields' ) );
        
        // Track if shortcode is used
        $this->shortcode_used = false;
    }

    /**
     * Enqueue frontend CSS and JS
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'acf-analyzer-frontend',
            ACF_ANALYZER_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ACF_ANALYZER_VERSION
        );

        wp_enqueue_script(
            'acf-analyzer-frontend',
            ACF_ANALYZER_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            ACF_ANALYZER_VERSION,
            true
        );

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

        // Enqueue logger script only if the current post content contains the shortcode
        $should_enqueue_logger = false;
        if ( is_singular() ) {
            $post = get_post();
            if ( $post ) {
                $content = $post->post_content;
                if ( has_shortcode( $content, 'wpgb_facet_logger' ) || has_shortcode( $content, 'acf_search_popup' ) ) {
                    $should_enqueue_logger = true;
                }
            }
        }

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
     * @param array $atts
     * @return string
     */
    public function render_wpgb_facet_logger( $atts ) {
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

            if ( ! wp_script_is( 'acf-analyzer-wpgb-logger', 'enqueued' ) ) {
                wp_enqueue_script( 'acf-analyzer-wpgb-logger' );
            }

            // Provide default use_api setting to script
            $use_api_bool = in_array( strtolower( $atts['use_api'] ), array( '1', 'true', 'yes' ), true );
            wp_localize_script( 'acf-analyzer-wpgb-logger', 'acfWpgbLogger', array(
                'use_api_default' => $use_api_bool,
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'acf_popup_search' ),
            ) );
            // Also localize facet->ACF mapping. Allow admin-provided mapping (option) to override auto-detected mapping.
            $auto_mapping = $this->get_wpgb_facet_mapping();
            $admin_mapping = get_option( 'acf_wpgb_facet_map', array() );
            $mapping = array_merge( (array) $auto_mapping, (array) $admin_mapping );
            wp_localize_script( 'acf-analyzer-wpgb-logger', 'acfWpgbFacetMap', $mapping );
        }

        ob_start();
        include ACF_ANALYZER_PLUGIN_DIR . 'templates/logger-button.php';
        return ob_get_clean();
    }

    /**
     * Attempt to build a best-effort mapping of WP Grid Builder facet slug => ACF field/key
     * Returns an associative array suitable for localizing into JS.
     *
     * @return array
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
     * Render shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_search_popup( $atts ) {
        $atts = shortcode_atts(
            array(
                'type' => 'Osakeannit', // Default type: Osakeannit, Osaketori, or Velkakirjat
            ),
            $atts,
            'acf_search_popup'
        );

        // Validate type
        $allowed_types = array( 'Osakeannit', 'Osaketori', 'Velkakirjat' );
        if ( ! in_array( $atts['type'], $allowed_types, true ) ) {
            $atts['type'] = 'Osakeannit';
        }

        ob_start();
        include ACF_ANALYZER_PLUGIN_DIR . 'templates/popup-search.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler to get available fields for a category
     */
    public function ajax_get_fields() {
        check_ajax_referer( 'acf_popup_search', 'nonce' );

        $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

        if ( empty( $category ) ) {
            wp_send_json_error( array( 'message' => 'Category is required' ) );
        }

        // Get fields from the analyzer
        $analyzer = new ACF_Analyzer();
        $field_names = $analyzer->get_all_field_names( array( $category ) );

        // Exclude specific fields from dropdown
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

        // Filter out excluded fields
        $field_names = array_values( array_diff( $field_names, $excluded_fields ) );

        wp_send_json_success( array( 'fields' => $field_names ) );
    }

    /**
     * AJAX handler for search functionality
     */
    public function ajax_search_handler() {
        check_ajax_referer( 'acf_popup_search', 'nonce' );

        $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
        $criteria = isset( $_POST['criteria'] ) ? $_POST['criteria'] : array();
        $match_logic = isset( $_POST['match_logic'] ) ? sanitize_text_field( $_POST['match_logic'] ) : 'AND';

        if ( empty( $criteria ) ) {
            wp_send_json_error( array( 'message' => 'Criteria are required' ) );
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
                        $sanitized_criteria[ $field ] = $value;
                    }
                }
            }
        }

        if ( empty( $sanitized_criteria ) ) {
            wp_send_json_error( array( 'message' => 'No valid criteria provided' ) );
        }

        // Use the existing search_by_criteria method
        $analyzer = new ACF_Analyzer();
        $options = array(
            'match_logic' => $match_logic,
            'categories'  => array( $category ),
            'debug'       => false,
        );

        $results = $analyzer->search_by_criteria( $sanitized_criteria, $options );

        // Format results for display
        $formatted_results = array();
        foreach ( $results['posts'] as $post ) {
            $formatted_results[] = array(
                'id'    => $post['ID'],
                'title' => $post['title'],
                'url'   => $post['url'],
            );
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
