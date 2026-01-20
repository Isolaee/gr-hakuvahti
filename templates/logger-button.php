<?php
// Hakuvahti button and modal template
?>
<?php if ( is_user_logged_in() ) : ?>
    <button class="hakuvahti-open-popup button"><?php esc_html_e( 'Luo hakuvahti', 'acf-analyzer' ); ?></button>

    <div id="hakuvahti-modal" class="hakuvahti-modal" style="display:none;">
        <div class="hakuvahti-modal-overlay"></div>
        <div class="hakuvahti-modal-dialog">
            <header class="hakuvahti-modal-header">
                <h3><?php esc_html_e( 'Luo hakuvahti', 'acf-analyzer' ); ?></h3>
                <button class="hakuvahti-modal-close" aria-label="Sulje">&times;</button>
            </header>
            <div class="hakuvahti-modal-body">
                <input type="text" id="hakuvahti-save-name" placeholder="<?php esc_attr_e( 'Anna nimi hakuvahdille', 'acf-analyzer' ); ?>" style="width:100%; margin-bottom:8px;" />

                <div id="hakuvahti-criteria-preview">Loading...</div>

                <div class="hakuvahti-modal-actions" style="margin-top:10px;">
                    <button class="hakuvahti-save-popup button button-primary"><?php esc_html_e( 'Tallenna', 'acf-analyzer' ); ?></button>
                    <span class="hakuvahti-save-status" style="margin-left:8px;"></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
