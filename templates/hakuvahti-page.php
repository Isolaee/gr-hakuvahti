<?php
/**
 * Hakuvahdit My Account Page Template
 *
 * Displays user's saved search watches with options to run or delete them.
 *
 * @package ACF_Analyzer
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user_id = get_current_user_id();
$hakuvahdits = Hakuvahti::get_by_user( $user_id );
?>

<div class="hakuvahti-page">
    <h2><?php esc_html_e( 'Hakuvahdit', 'acf-analyzer' ); ?></h2>
    <p class="hakuvahti-description">
        <?php esc_html_e( 'Täällä näet tallennetut hakuvahtisi. Voit muokata tai poistaa hakuvahdin.', 'acf-analyzer' ); ?>
    </p>

    <?php if ( empty( $hakuvahdits ) ) : ?>
        <div class="hakuvahti-empty">
            <p><?php esc_html_e( 'Sinulla ei ole vielä hakuvahteja.', 'acf-analyzer' ); ?></p>
            <p>
                <?php
                printf(
                    /* translators: %1$s, %2$s, %3$s are category page links */
                    esc_html__( 'Luo hakuvahti valitsemalla hakuehdot %1$s, %2$s tai %3$s -sivulla ja klikkaamalla "Tallenna hakuvahti" -painiketta.', 'acf-analyzer' ),
                    '<a href="' . esc_url( home_url( '/osakeannit/' ) ) . '">Osakeannit</a>',
                    '<a href="' . esc_url( home_url( '/velkakirjat/' ) ) . '">Velkakirjat</a>',
                    '<a href="' . esc_url( home_url( '/osaketori/' ) ) . '">Osaketori</a>'
                );
                ?>
            </p>
        </div>
    <?php else : ?>
        <div class="hakuvahti-list" id="hakuvahti-list">
            <?php foreach ( $hakuvahdits as $hv ) : ?>
                <div class="hakuvahti-card" data-id="<?php echo esc_attr( $hv->id ); ?>" data-category="<?php echo esc_attr( $hv->category ); ?>" data-criteria="<?php echo esc_attr( wp_json_encode( $hv->criteria ) ); ?>">
                    <div class="hakuvahti-card-header">
                        <h3 class="hakuvahti-name"><?php echo esc_html( $hv->name ); ?></h3>
                        <span class="hakuvahti-category"><?php echo esc_html( $hv->category ); ?></span>
                    </div>
                    <div class="hakuvahti-card-body">
                        <p class="hakuvahti-criteria">
                            <strong><?php esc_html_e( 'Hakuehdot:', 'acf-analyzer' ); ?></strong>
                            <?php echo esc_html( Hakuvahti::format_criteria_summary( $hv->criteria ) ); ?>
                        </p>
                        <p class="hakuvahti-meta">
                            <?php
                            printf(
                                /* translators: %s is the creation date */
                                esc_html__( 'Luotu: %s', 'acf-analyzer' ),
                                date_i18n( get_option( 'date_format' ), strtotime( $hv->created_at ) )
                            );
                            ?>
                        </p>
                    </div>
                    <div class="hakuvahti-card-actions">
                        <button class="hakuvahti-delete-btn button" data-id="<?php echo esc_attr( $hv->id ); ?>">
                            <?php esc_html_e( 'Poista', 'acf-analyzer' ); ?>
                        </button>
                    </div>
                    <div class="hakuvahti-edit-form" style="display:none; margin-top:10px;"></div>
                    <div class="hakuvahti-results" style="display:none;"></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
