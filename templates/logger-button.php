<?php
// Hakuvahti button and modal template
?>
<?php if ( is_user_logged_in() ) : ?>
<div class="acf-analyzer-buttons">
    <button class="acf-hakuvahti-save">
        <?php esc_html_e( 'Hakuvahti', 'acf-analyzer' ); ?>
    </button>
</div>

<!-- Hakuvahti Save Modal -->
<div id="hakuvahti-save-modal" class="hakuvahti-modal" aria-hidden="true" style="display: none;">
    <div class="hakuvahti-modal-content">
        <form id="hakuvahti-save-form">
            <p class="hakuvahti-info"><?php esc_html_e( 'Luo hakuvahti valitsemalla hakuehdot:', 'acf-analyzer' ); ?></p>
            <div id="hakuvahti-criteria-preview" aria-live="polite"></div>
            <p><?php esc_html_e( 'Anna nimi Hakuvahdille:', 'acf-analyzer' ); ?></p>
            <input type="text" id="hakuvahti-name" name="name" required placeholder="<?php esc_attr_e( 'Esim. Omat hakuehdot', 'acf-analyzer' ); ?>">
            <button type="submit" class="hakuvahti-submit"><?php esc_html_e( 'Tallenna', 'acf-analyzer' ); ?></button>
        </form>
        <div id="hakuvahti-save-message"></div>
    </div>
</div>
<?php endif; ?>
