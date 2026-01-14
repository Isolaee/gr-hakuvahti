<?php
/**
 * Pop-up Search Template
 *
 * @var array $atts Shortcode attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$popup_type = isset( $atts['type'] ) ? esc_attr( $atts['type'] ) : 'Osakeannit';
$popup_id = 'acf-popup-' . sanitize_title( $popup_type );
?>

<div class="acf-popup-wrapper">
    <button class="acf-popup-trigger" data-popup="<?php echo $popup_id; ?>">
        <?php
        // translators: %s is the popup type (Osakeannit, Osaketori, or Velkakirjat)
        echo sprintf( esc_html__( 'Search %s', 'acf-analyzer' ), esc_html( $popup_type ) );
        ?>
    </button>

    <div class="acf-popup-overlay" id="<?php echo $popup_id; ?>" data-category="<?php echo esc_attr( $popup_type ); ?>">
        <div class="acf-popup-container">
            <!-- Header -->
            <div class="acf-popup-header">
                <h2>
                    <?php
                    // translators: %s is the popup type (Osakeannit, Osaketori, or Velkakirjat)
                    echo sprintf( esc_html__( 'Search %s', 'acf-analyzer' ), esc_html( $popup_type ) );
                    ?>
                </h2>
                <button class="acf-popup-close" aria-label="<?php esc_attr_e( 'Close', 'acf-analyzer' ); ?>">&times;</button>
            </div>

            <!-- Body -->
            <div class="acf-popup-body">
                <!-- Criteria Section -->
                <div class="acf-criteria-section">
                    <h3><?php esc_html_e( 'Search Criteria', 'acf-analyzer' ); ?></h3>
                    
                    <div class="acf-criteria-list" data-fields-loaded="false">
                        <!-- Criteria rows will be added dynamically -->
                    </div>

                    <button type="button" class="acf-add-criteria">
                        <?php esc_html_e( 'Add Field', 'acf-analyzer' ); ?>
                    </button>
                </div>

                <!-- Match Logic Section -->
                <div class="acf-match-logic">
                    <label>
                        <input type="radio" name="match_logic" value="AND" checked>
                        <strong><?php esc_html_e( 'All criteria must match (AND)', 'acf-analyzer' ); ?></strong>
                        <br>
                        <small><?php esc_html_e( 'Posts must match every field you specify', 'acf-analyzer' ); ?></small>
                    </label>
                    <label>
                        <input type="radio" name="match_logic" value="OR">
                        <strong><?php esc_html_e( 'Any criteria can match (OR)', 'acf-analyzer' ); ?></strong>
                        <br>
                        <small><?php esc_html_e( 'Posts that match at least one field will be included', 'acf-analyzer' ); ?></small>
                    </label>
                </div>

                <!-- Search Button -->
                <button type="button" class="acf-popup-search-btn">
                    <?php esc_html_e( 'Search', 'acf-analyzer' ); ?>
                </button>

                <!-- Results Section -->
                <div class="acf-results-section">
                    <div class="acf-empty-state">
                        <p><?php esc_html_e( 'Add criteria and click Search to find matching posts.', 'acf-analyzer' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
