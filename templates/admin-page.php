<?php
/**
 * Admin page template
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap acf-analyzer-wrap">
    <h1><?php esc_html_e( 'ACF Field Analyzer', 'acf-analyzer' ); ?></h1>

    <!-- Hakuvahti: Daily runner status and controls -->
    <div class="acf-analyzer-section" style="margin-bottom:20px;">
        <h2><?php esc_html_e( 'Scheduled Runner', 'acf-analyzer' ); ?></h2>
        <p>
            <strong><?php esc_html_e( 'Last run:', 'acf-analyzer' ); ?></strong>
            <?php echo ! empty( $last_run ) ? esc_html( $last_run ) : esc_html__( 'Not run yet', 'acf-analyzer' ); ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'True-cron ping URL:', 'acf-analyzer' ); ?></strong><br>
            <?php if ( ! empty( $secret_key ) ) :
                $ping = esc_url( add_query_arg( array( 'hakuvahti_ping' => 1, 'key' => $secret_key ), site_url() ) );
            ?>
                <code><?php echo $ping; ?></code>
            <?php else : ?>
                <em><?php esc_html_e( 'No secret key available', 'acf-analyzer' ); ?></em>
            <?php endif; ?>
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px;">
            <?php wp_nonce_field( 'acf_analyzer_run_now' ); ?>
            <input type="hidden" name="action" value="acf_analyzer_run_now">
            <label style="display:block; margin-bottom:10px;">
                <input type="checkbox" name="ignore_seen" value="1" <?php checked( get_option( 'acf_analyzer_ignore_seen', false ) ); ?>>
                <?php esc_html_e( 'Ignore seen posts (show ALL matching posts, not just new ones)', 'acf-analyzer' ); ?>
            </label>
            <?php submit_button( __( 'Run now', 'acf-analyzer' ), 'secondary', '', false ); ?>
        </form>

        <!-- Debug Log from Last Run -->
        <h3 style="margin-top:20px;"><?php esc_html_e( 'Last Run Debug Log', 'acf-analyzer' ); ?></h3>
        <?php if ( ! empty( $last_run_debug ) ) : ?>
            <div style="background:#f5f5f5; border:1px solid #ccc; padding:10px; max-height:400px; overflow:auto; font-family:monospace; font-size:12px;">
                <?php foreach ( $last_run_debug as $line ) : ?>
                    <div style="<?php echo strpos( $line, '---' ) === 0 ? 'border-top:1px solid #ddd; margin-top:5px; padding-top:5px;' : ''; ?>">
                        <?php echo esc_html( $line ); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><em><?php esc_html_e( 'No debug log available. Run the daily search to generate a log.', 'acf-analyzer' ); ?></em></p>
        <?php endif; ?>
    </div>

    <!-- Search Options -->
    <div class="acf-analyzer-section">
        <h2><?php esc_html_e( 'Search Options', 'acf-analyzer' ); ?></h2>
        <p><?php esc_html_e( 'Define default behavior for searches performed by the hakuvahti system.', 'acf-analyzer' ); ?></p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acf_analyzer_save_options', 'acf_analyzer_save_options_nonce' ); ?>
            <input type="hidden" name="action" value="acf_analyzer_save_options">

            <table class="form-table">
                <tr>
                    <th scope="row"><label><?php esc_html_e( 'Default Match Logic', 'acf-analyzer' ); ?></label></th>
                    <td>
                        <label><input type="radio" name="default_match_logic" value="AND" <?php checked( isset( $search_options ) ? $search_options['default_match_logic'] : 'AND', 'AND' ); ?>> <?php esc_html_e( 'AND (all criteria must match)', 'acf-analyzer' ); ?></label>
                        <br>
                        <label><input type="radio" name="default_match_logic" value="OR" <?php checked( isset( $search_options ) ? $search_options['default_match_logic'] : 'AND', 'OR' ); ?>> <?php esc_html_e( 'OR (any criterion matches)', 'acf-analyzer' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e( 'Results Per Page', 'acf-analyzer' ); ?></label></th>
                    <td>
                        <input type="number" name="results_per_page" value="<?php echo esc_attr( isset( $search_options ) ? $search_options['results_per_page'] : 20 ); ?>" min="1" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of results shown per page in search UIs.', 'acf-analyzer' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e( 'Enable Debug By Default', 'acf-analyzer' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="debug_by_default" value="1" <?php checked( isset( $search_options ) ? $search_options['debug_by_default'] : false ); ?>> <?php esc_html_e( 'Enable debug mode by default for searches', 'acf-analyzer' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e( 'Guest Hakuvahti TTL (days)', 'acf-analyzer' ); ?></label></th>
                    <td>
                        <input type="number" name="guest_ttl_days" value="<?php echo esc_attr( get_option( 'acf_analyzer_guest_ttl_days', 30 ) ); ?>" min="1" max="365" class="small-text">
                        <p class="description"><?php esc_html_e( 'How many days guest (non-logged-in) hakuvahdits remain active before automatic deletion.', 'acf-analyzer' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Save Search Options', 'acf-analyzer' ), 'primary', '', false ); ?>
        </form>
    </div>

    <!-- User-defined Search Options (dynamic) -->
    <div class="acf-analyzer-section">
        <h2><?php esc_html_e( 'User Search Options', 'acf-analyzer' ); ?></h2>
        <p><?php esc_html_e( 'Define search options that users can use when creating hakuvahti watches. These fields will appear in the frontend popup for the selected category.', 'acf-analyzer' ); ?></p>

        <div class="user-search-tabs">
            <button type="button" class="tab-btn active" data-category="Osakeannit">Osakeannit</button>
            <button type="button" class="tab-btn" data-category="Osaketori">Osaketori</button>
            <button type="button" class="tab-btn" data-category="Velkakirjat">Velkakirjat</button>
        </div>

        <div id="search-options-editor"></div>

    </div>

    <?php
    // Display success messages
    if ( isset( $_GET['options'] ) && $_GET['options'] === 'saved' ) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Search options saved successfully!', 'acf-analyzer' ); ?></p>
        </div>
        <?php
    }

    if ( isset( $_GET['run'] ) && $_GET['run'] === 'ok' ) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Scheduled runner executed successfully!', 'acf-analyzer' ); ?></p>
        </div>
        <?php
    }
    ?>
</div>
