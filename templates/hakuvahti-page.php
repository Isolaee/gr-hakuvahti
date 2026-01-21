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
// Load admin-defined user search options to get display names and postfixes
$user_search_options = get_option( 'acf_analyzer_user_search_options', array() );
?>

<div class="hakuvahti-page">
    <h2><?php esc_html_e( 'Hakuvahdit', 'acf-analyzer' ); ?></h2>
    <p class="hakuvahti-description">
        <?php esc_html_e( 'Täällä näet tallennetut hakuvahtisi.', 'acf-analyzer' ); ?>
    </p>

    <?php if ( empty( $hakuvahdits ) ) : ?>
        <div class="hakuvahti-empty">
            <p><?php esc_html_e( 'Sinulla ei ole vielä hakuvahteja.', 'acf-analyzer' ); ?></p>
            <p>
                <?php
                printf(
                    /* translators: %1$s, %2$s, %3$s are category page links */
                    esc_html__( 'Luo hakuvahti valitsemalla hakuehdot %1$s, %2$s tai %3$s -sivulla ja klikkaamalla "Luo hakuvahti" -painiketta.', 'acf-analyzer' ),
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
                        <?php
                        // Simple grouping: show criteria grouped by their label/type
                        $groups = array();
                        if ( ! empty( $hv->criteria ) && is_array( $hv->criteria ) ) {
                            foreach ( $hv->criteria as $c ) {
                                $label = isset( $c['label'] ) ? $c['label'] : 'other';
                                $groups[ $label ][] = $c;
                            }
                        }
                        ?>
                        <div class="hakuvahti-criteria">
                            <strong><?php esc_html_e( 'Hakuehdot:', 'acf-analyzer' ); ?></strong>
                            <?php if ( empty( $groups ) ) : ?>
                                <?php esc_html_e( 'Ei hakuehtoja', 'acf-analyzer' ); ?>
                            <?php else : ?>
                                <div class="hakuvahti-crit-groups">
                                    <?php foreach ( $groups as $label => $items ) : ?>
                                        <div class="hakuvahti-crit-group" data-label="<?php echo esc_attr( $label ); ?>">
                                            <ul class="hakuvahti-crit-list">
                                                <?php foreach ( $items as $it ) :
                                                    $acf_field = isset( $it['name'] ) ? $it['name'] : '';
                                                    $values = isset( $it['values'] ) ? $it['values'] : '';
                                                    $display = '';

                                                    // Look up display name and postfix from admin-defined options
                                                    $display_name = $acf_field;
                                                    $postfix = '';
                                                    if ( is_array( $user_search_options ) ) {
                                                        foreach ( $user_search_options as $opt ) {
                                                            if ( isset( $opt['acf_field'] ) && $opt['acf_field'] === $acf_field ) {
                                                                if ( isset( $opt['name'] ) && ! empty( $opt['name'] ) ) {
                                                                    $display_name = $opt['name'];
                                                                }
                                                                if ( isset( $opt['values'] ) && is_array( $opt['values'] ) && isset( $opt['values']['postfix'] ) ) {
                                                                    $postfix = $opt['values']['postfix'];
                                                                }
                                                                break;
                                                            }
                                                        }
                                                    }

                                                    // If this is a word_search field, display the search terms
                                                    if ( isset( $it['label'] ) && $it['label'] === 'word_search' ) {
                                                        $display_name = __( 'Sanahaku', 'acf-analyzer' );
                                                        $display = is_array( $values ) ? implode( ' ', $values ) : $values;
                                                    }
                                                    // If this is a range field, handle single-value and two-value cases
                                                    elseif ( isset( $it['label'] ) && $it['label'] === 'range' ) {
                                                        // normalize to array
                                                        $vals = is_array( $values ) ? $values : array( $values );
                                                        if ( count( $vals ) >= 2 ) {
                                                            $display = implode( ' - ', $vals ) . ( $postfix ? ' ' . $postfix : '' );
                                                        } elseif ( count( $vals ) === 1 ) {
                                                            $raw = trim( (string) $vals[0] );
                                                            $norm = str_replace( ',', '.', $raw );
                                                            // operator form: <, <=, >, >=
                                                            if ( preg_match( '/^\s*([<>]=?)\s*(.+)$/', $norm, $m ) ) {
                                                                $op = $m[1];
                                                                $num = trim( $m[2] );
                                                                if ( strpos( $op, '<' ) !== false ) {
                                                                    $display = sprintf( /* translators: %s = value */ __( 'alle %s', 'acf-analyzer' ), $num ) . ( $postfix ? ' ' . $postfix : '' );
                                                                } else {
                                                                    $display = sprintf( /* translators: %s = value */ __( 'yli %s', 'acf-analyzer' ), $num ) . ( $postfix ? ' ' . $postfix : '' );
                                                                }
                                                            } elseif ( preg_match( '/^\s*(min|max)\s*[:=]\s*(.+)$/i', $norm, $m2 ) ) {
                                                                $which = strtolower( $m2[1] );
                                                                $num = trim( $m2[2] );
                                                                if ( $which === 'min' ) {
                                                                    $display = sprintf( __( 'yli %s', 'acf-analyzer' ), $num ) . ( $postfix ? ' ' . $postfix : '' );
                                                                } else {
                                                                    $display = sprintf( __( 'alle %s', 'acf-analyzer' ), $num ) . ( $postfix ? ' ' . $postfix : '' );
                                                                }
                                                            } elseif ( is_numeric( $norm ) ) {
                                                                // default: single numeric treated as minimum
                                                                $display = sprintf( __( 'yli %s', 'acf-analyzer' ), $norm ) . ( $postfix ? ' ' . $postfix : '' );
                                                            } else {
                                                                $display = $raw . ( $postfix ? ' ' . $postfix : '' );
                                                            }
                                                        }
                                                    } else {
                                                        // Non-range: join multiple values or show single
                                                        $display = is_array( $values ) ? implode( ', ', $values ) : $values;
                                                    }
                                                ?>
                                                    <li class="hakuvahti-crit-item"><?php echo esc_html( $display_name . ': ' . $display ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
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
