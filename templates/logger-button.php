<?php
// Expected variables: $atts from shortcode_atts in render_wpgb_facet_logger
$label = isset( $atts['label'] ) ? $atts['label'] : 'Log WPGB facets';
$target = isset( $atts['target'] ) ? $atts['target'] : '';
$use_api = isset( $atts['use_api'] ) ? $atts['use_api'] : 'true';
$use_api_attr = in_array( strtolower( $use_api ), array( '1', 'true', 'yes' ), true ) ? '1' : '0';
?>
<div class="acf-analyzer-buttons">
    <button class="acf-wpgb-facet-logger" data-target="<?php echo esc_attr( $target ); ?>" data-use-api="<?php echo esc_attr( $use_api_attr ); ?>">
        <?php echo esc_html( $label ); ?>
    </button>
    <?php if ( is_user_logged_in() ) : ?>
    <button class="acf-hakuvahti-save" data-target="<?php echo esc_attr( $target ); ?>">
        <?php esc_html_e( 'Tallenna hakuvahti', 'acf-analyzer' ); ?>
    </button>
    <?php endif; ?>
</div>

<?php if ( is_user_logged_in() ) : ?>
<!-- Hakuvahti Save Modal -->
<div id="hakuvahti-save-modal" class="hakuvahti-modal" style="display:none;">
    <div class="hakuvahti-modal-content">
        <span class="hakuvahti-modal-close">&times;</span>
        <h3><?php esc_html_e( 'Tallenna hakuvahti', 'acf-analyzer' ); ?></h3>
        <form id="hakuvahti-save-form">
            <label for="hakuvahti-name"><?php esc_html_e( 'Hakuvahdin nimi:', 'acf-analyzer' ); ?></label>
            <input type="text" id="hakuvahti-name" name="name" required placeholder="<?php esc_attr_e( 'Anna hakuvahdille nimi', 'acf-analyzer' ); ?>">
            <div id="hakuvahti-criteria-preview"></div>
            <button type="submit" class="hakuvahti-submit"><?php esc_html_e( 'Tallenna', 'acf-analyzer' ); ?></button>
        </form>
        <div id="hakuvahti-save-message"></div>
    </div>
</div>
<?php endif; ?>
