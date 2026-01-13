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

    <?php
    // Display success message
    if ( isset( $_GET['analysis'] ) && $_GET['analysis'] === 'complete' ) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Analysis completed successfully!', 'acf-analyzer' ); ?></p>
        </div>
        <?php
    }

    // Display error message
    if ( isset( $_GET['error'] ) && $_GET['error'] === 'no_post_types' ) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e( 'Please select at least one post type to analyze.', 'acf-analyzer' ); ?></p>
        </div>
        <?php
    }
    ?>

    <div class="acf-analyzer-content">
        <!-- Analysis Form -->
        <div class="acf-analyzer-section">
            <h2><?php esc_html_e( 'Run Analysis', 'acf-analyzer' ); ?></h2>
            <p><?php esc_html_e( 'Select the post types you want to analyze for ACF field usage.', 'acf-analyzer' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="acf_analyzer_run">
                <?php wp_nonce_field( 'acf_analyzer_run', 'acf_analyzer_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Post Types', 'acf-analyzer' ); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ( $post_types as $post_type ) : ?>
                                    <label>
                                        <input type="checkbox"
                                               name="post_types[]"
                                               value="<?php echo esc_attr( $post_type->name ); ?>"
                                               <?php checked( in_array( $post_type->name, array( 'osakeanti', 'osaketori', 'velkakirja' ) ) ); ?>>
                                        <?php echo esc_html( $post_type->label ); ?>
                                    </label>
                                    <br>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Run Analysis', 'acf-analyzer' ), 'primary', 'submit', false ); ?>
            </form>
        </div>

        <?php if ( $results ) : ?>
            <!-- Analysis Results -->
            <div class="acf-analyzer-section acf-analyzer-results">
                <h2><?php esc_html_e( 'Analysis Results', 'acf-analyzer' ); ?></h2>

                <!-- Export Buttons -->
                <div class="acf-analyzer-export">
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acf_analyzer_export&format=json' ), 'acf_analyzer_export', 'nonce' ) ); ?>"
                       class="button">
                        <?php esc_html_e( 'Export as JSON', 'acf-analyzer' ); ?>
                    </a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acf_analyzer_export&format=csv' ), 'acf_analyzer_export', 'nonce' ) ); ?>"
                       class="button">
                        <?php esc_html_e( 'Export as CSV', 'acf-analyzer' ); ?>
                    </a>
                </div>

                <!-- Overview -->
                <div class="acf-analyzer-overview">
                    <h3><?php esc_html_e( 'Overview', 'acf-analyzer' ); ?></h3>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e( 'Total Posts', 'acf-analyzer' ); ?></th>
                                <td><?php echo esc_html( number_format_i18n( $results['total_posts'] ) ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Posts without ACF data', 'acf-analyzer' ); ?></th>
                                <td><?php echo esc_html( number_format_i18n( $results['empty_acf_count'] ) ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Date Range', 'acf-analyzer' ); ?></th>
                                <td>
                                    <?php
                                    echo esc_html(
                                        $results['date_range']['earliest'] . ' ' .
                                        __( 'to', 'acf-analyzer' ) . ' ' .
                                        $results['date_range']['latest']
                                    );
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Post Type Breakdown -->
                <div class="acf-analyzer-post-types">
                    <h3><?php esc_html_e( 'Post Type Breakdown', 'acf-analyzer' ); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Post Type', 'acf-analyzer' ); ?></th>
                                <th><?php esc_html_e( 'Count', 'acf-analyzer' ); ?></th>
                                <th><?php esc_html_e( 'Percentage', 'acf-analyzer' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $results['post_type_breakdown'] as $type => $count ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $type ); ?></strong></td>
                                    <td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
                                    <td>
                                        <?php
                                        $percentage = ( $count / $results['total_posts'] ) * 100;
                                        echo esc_html( number_format_i18n( $percentage, 1 ) . '%' );
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Field Usage -->
                <div class="acf-analyzer-fields">
                    <h3><?php esc_html_e( 'ACF Field Usage', 'acf-analyzer' ); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Field Name', 'acf-analyzer' ); ?></th>
                                <th><?php esc_html_e( 'Usage Count', 'acf-analyzer' ); ?></th>
                                <th><?php esc_html_e( 'Fill Rate', 'acf-analyzer' ); ?></th>
                                <th><?php esc_html_e( 'Post Types', 'acf-analyzer' ); ?></th>
                                <th><?php esc_html_e( 'Data Types', 'acf-analyzer' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $results['field_usage'] as $field ) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $field['field_name'] ); ?></strong>
                                    </td>
                                    <td><?php echo esc_html( number_format_i18n( $field['count'] ) ); ?></td>
                                    <td>
                                        <span class="acf-analyzer-fill-rate"
                                              data-rate="<?php echo esc_attr( $field['fill_rate'] ); ?>">
                                            <?php echo esc_html( number_format_i18n( $field['fill_rate'], 1 ) . '%' ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( implode( ', ', $field['post_types'] ) ); ?></td>
                                    <td><?php echo esc_html( implode( ', ', $field['data_types'] ) ); ?></td>
                                </tr>
                                <?php if ( ! empty( $field['sample_values'] ) ) : ?>
                                    <tr class="acf-analyzer-samples">
                                        <td colspan="5">
                                            <details>
                                                <summary><?php esc_html_e( 'Sample Values', 'acf-analyzer' ); ?></summary>
                                                <ul>
                                                    <?php foreach ( array_slice( $field['sample_values'], 0, 3 ) as $sample ) : ?>
                                                        <li>
                                                            <code><?php
                                                                $sample_str = is_array( $sample ) || is_object( $sample )
                                                                    ? wp_json_encode( $sample )
                                                                    : $sample;
                                                                echo esc_html( mb_substr( $sample_str, 0, 100 ) );
                                                            ?></code>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>