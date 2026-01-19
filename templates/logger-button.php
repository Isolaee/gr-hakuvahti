<?php
// Expected variables: $atts from shortcode_atts in render_hakuvahti
$target = isset( $atts['target'] ) ? $atts['target'] : '';
$use_api = isset( $atts['use_api'] ) ? $atts['use_api'] : 'true';
$use_api_attr = in_array( strtolower( $use_api ), array( '1', 'true', 'yes' ), true ) ? '1' : '0';
?>
<?php if ( is_user_logged_in() ) : ?>
<div class="acf-analyzer-buttons">
    <button class="acf-hakuvahti-save" data-target="<?php echo esc_attr( $target ); ?>" data-use-api="<?php echo esc_attr( $use_api_attr ); ?>">
        <?php esc_html_e( 'Hakuvahti', 'acf-analyzer' ); ?>
    </button>
</div>

<!-- Hakuvahti Save Modal -->
<div id="hakuvahti-save-modal" class="hakuvahti-modal" aria-hidden="true" hidden>
    <div class="hakuvahti-modal-content">
        <span class="hakuvahti-modal-close">&times;</span>
        <form id="hakuvahti-save-form">
            <p class="hakuvahti-info"><?php esc_html_e( 'Hakuvahti tallentaa aktiivisen haun', 'acf-analyzer' ); ?></p>
            <div id="hakuvahti-criteria-preview" aria-live="polite"></div>
            <p><?php esc_html_e( 'Anna nimi Hakuvahdille.', 'acf-analyzer' ); ?></p>
            <input type="text" id="hakuvahti-name" name="name" required>
            <button type="submit" class="hakuvahti-submit"><?php esc_html_e( 'Tallenna', 'acf-analyzer' ); ?></button>
        </form>
        <div id="hakuvahti-save-message"></div>
    </div>
</div>
<?php endif; ?>
