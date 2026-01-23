<?php
/**
 * Hakuvahti button and modal template
 *
 * Supports both logged-in users and guests. Guests see an email input field
 * and a notice about the hakuvahti expiration period.
 *
 * @package ACF_Analyzer
 * @since 1.2.0
 */

$is_logged_in = is_user_logged_in();
$ttl_days = (int) get_option( 'acf_analyzer_guest_ttl_days', 30 );
?>
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

            <?php if ( ! $is_logged_in ) : ?>
            <!-- Guest email input -->
            <input type="email" id="hakuvahti-guest-email" placeholder="<?php esc_attr_e( 'Sähköpostiosoitteesi', 'acf-analyzer' ); ?>" style="width:100%; margin-bottom:8px;" />
            <p class="hakuvahti-guest-notice" style="font-size: 12px; color: #666; margin: 0 0 12px 0;">
                <?php
                echo sprintf(
                    esc_html__( 'Saat ilmoitukset sähköpostiisi. Hakuvahti on voimassa %d päivää.', 'acf-analyzer' ),
                    $ttl_days
                );
                ?>
            </p>
            <?php endif; ?>

            <div id="hakuvahti-criteria-preview">Loading...</div>

            <div class="hakuvahti-modal-actions" style="margin-top:10px;">
                <button class="hakuvahti-save-popup button button-primary"><?php esc_html_e( 'Tallenna', 'acf-analyzer' ); ?></button>
                <span class="hakuvahti-save-status" style="margin-left:8px;"></span>
            </div>
        </div>
    </div>
</div>
