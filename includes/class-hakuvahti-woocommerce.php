<?php
/**
 * Hakuvahti WooCommerce Integration
 *
 * Adds a "Hakuvahdit" endpoint to the WooCommerce My Account page
 * where users can manage their saved search watches.
 *
 * @package ACF_Analyzer
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hakuvahti_WooCommerce {

    /**
     * Constructor - Register hooks
     */
    public function __construct() {
        // Only initialize if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Register endpoint
        add_action( 'init', array( $this, 'add_endpoint' ) );

        // Add menu item to My Account
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );

        // Register endpoint content
        add_action( 'woocommerce_account_hakuvahdit_endpoint', array( $this, 'render_endpoint_content' ) );

        // Enqueue scripts on hakuvahdit page
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
    }

    /**
     * Register the hakuvahdit endpoint
     */
    public function add_endpoint() {
        add_rewrite_endpoint( 'hakuvahdit', EP_ROOT | EP_PAGES );
    }

    /**
     * Add Hakuvahdit to the My Account menu
     *
     * @param array $items Existing menu items
     * @return array Modified menu items
     */
    public function add_menu_item( $items ) {
        // Insert before logout if it exists
        $logout = false;
        if ( isset( $items['customer-logout'] ) ) {
            $logout = $items['customer-logout'];
            unset( $items['customer-logout'] );
        }

        $items['hakuvahdit'] = __( 'Hakuvahdit', 'acf-analyzer' );

        if ( $logout ) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    /**
     * Render the hakuvahdit endpoint content
     */
    public function render_endpoint_content() {
        // Load the template
        include ACF_ANALYZER_PLUGIN_DIR . 'templates/hakuvahti-page.php';
    }

    /**
     * Conditionally enqueue assets on hakuvahdit page
     */
    public function maybe_enqueue_assets() {
        global $wp_query;

        // Check if we're on the hakuvahdit endpoint
        if ( ! isset( $wp_query->query_vars['hakuvahdit'] ) ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'hakuvahti-page',
            ACF_ANALYZER_PLUGIN_URL . 'assets/css/hakuvahti.css',
            array(),
            ACF_ANALYZER_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'hakuvahti-page',
            ACF_ANALYZER_PLUGIN_URL . 'assets/js/hakuvahti-page.js',
            array( 'jquery' ),
            ACF_ANALYZER_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script( 'hakuvahti-page', 'hakuvahtiConfig', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'hakuvahti_nonce' ),
            'i18n'    => array(
                'confirmDelete'  => __( 'Haluatko varmasti poistaa tämän hakuvahdin?', 'acf-analyzer' ),
                'running'        => __( 'Haetaan...', 'acf-analyzer' ),
                'noNewResults'   => __( 'Ei uusia tuloksia', 'acf-analyzer' ),
                'newResults'     => __( 'uutta tulosta', 'acf-analyzer' ),
                'deleteSuccess'  => __( 'Hakuvahti poistettu.', 'acf-analyzer' ),
                'deleteFailed'   => __( 'Poisto epäonnistui.', 'acf-analyzer' ),
                'networkError'   => __( 'Verkkovirhe. Yritä uudelleen.', 'acf-analyzer' ),
            ),
        ) );
    }
}

// Initialize the class
new Hakuvahti_WooCommerce();
