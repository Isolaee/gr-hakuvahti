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
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_ajax_acf_popup_search', array( $this, 'ajax_search_handler' ) );
        add_action( 'wp_ajax_nopriv_acf_popup_search', array( $this, 'ajax_search_handler' ) );
        add_action( 'wp_ajax_acf_popup_get_fields', array( $this, 'ajax_get_fields' ) );
        add_action( 'wp_ajax_nopriv_acf_popup_get_fields', array( $this, 'ajax_get_fields' ) );
    }

    /**
     * Enqueue frontend CSS and JS
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if shortcode is present
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'acf_search_popup' ) ) {
            return;
        }

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

        if ( empty( $category ) || empty( $criteria ) ) {
            wp_send_json_error( array( 'message' => 'Category and criteria are required' ) );
        }

        // Sanitize criteria
        $sanitized_criteria = array();
        foreach ( $criteria as $criterion ) {
            if ( isset( $criterion['field'] ) && isset( $criterion['value'] ) ) {
                $field = sanitize_text_field( $criterion['field'] );
                $value = sanitize_text_field( $criterion['value'] );
                $sanitized_criteria[ $field ] = $value;
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
