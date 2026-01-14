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

    // Display search success message
    if ( isset( $_GET['search'] ) && $_GET['search'] === 'complete' ) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Search completed successfully!', 'acf-analyzer' ); ?></p>
        </div>
        <?php
    }

    // Display search error message
    if ( isset( $_GET['error'] ) && $_GET['error'] === 'no_criteria' ) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php esc_html_e( 'Please enter at least one search criterion.', 'acf-analyzer' ); ?></p>
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

        <!-- Criteria Search Form -->
        <div class="acf-analyzer-section">
            <h2>
                <?php esc_html_e( 'Search by ACF Criteria', 'acf-analyzer' ); ?>
                <a href="<?php echo esc_url( add_query_arg( 'refresh_fields', '1', admin_url( 'tools.php?page=acf-analyzer' ) ) ); ?>" class="button button-small" style="margin-left: 10px; vertical-align: middle;">
                    <?php esc_html_e( 'Refresh Fields', 'acf-analyzer' ); ?>
                </a>
            </h2>
            <p><?php esc_html_e( 'Search published posts (Velkakirjat, Osakeannit, Osaketori) by ACF field values.', 'acf-analyzer' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="acf-criteria-search-form">
                <input type="hidden" name="action" value="acf_analyzer_search">
                <?php wp_nonce_field( 'acf_analyzer_search', 'acf_analyzer_search_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Criteria', 'acf-analyzer' ); ?></label>
                        </th>
                        <td>
                            <?php if ( empty( $acf_field_names ) ) : ?>
                                <div class="notice notice-warning inline">
                                    <p><?php esc_html_e( 'No ACF fields found. Please ensure posts with ACF data exist.', 'acf-analyzer' ); ?></p>
                                </div>
                            <?php else : ?>
                            <div id="criteria-rows">
                                <div class="criteria-row">
                                    <select name="criteria_field[]" class="criteria-field">
                                        <option value=""><?php esc_html_e( '-- Select field --', 'acf-analyzer' ); ?></option>
                                        <?php foreach ( $acf_field_names as $field_name ) : ?>
                                            <option value="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_html( $field_name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="criteria_compare[]" class="criteria-compare">
                                        <option value="equals">=</option>
                                        <option value="min">&ge; (min)</option>
                                        <option value="max">&le; (max)</option>
                                    </select>
                                    <input type="text" name="criteria_value[]" placeholder="<?php esc_attr_e( 'Value', 'acf-analyzer' ); ?>" class="regular-text">
                                    <button type="button" class="button remove-criteria" style="display:none;">&times;</button>
                                </div>
                            </div>
                            <p>
                                <button type="button" class="button" id="add-criteria">
                                    <?php esc_html_e( '+ Add Criterion', 'acf-analyzer' ); ?>
                                </button>
                            </p>
                            <p class="description"><?php esc_html_e( 'Nested fields are shown with dot notation (e.g., parent.child)', 'acf-analyzer' ); ?></p>
                            <?php endif; ?>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Match Logic', 'acf-analyzer' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="radio" name="match_logic" value="AND" checked>
                                <?php esc_html_e( 'AND (all criteria must match)', 'acf-analyzer' ); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="match_logic" value="OR">
                                <?php esc_html_e( 'OR (any criterion matches)', 'acf-analyzer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Debug Mode', 'acf-analyzer' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug" value="1">
                                <?php esc_html_e( 'Show matched criteria details per post', 'acf-analyzer' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Search Posts', 'acf-analyzer' ), 'primary', 'submit', false ); ?>
            </form>

            <?php if ( ! empty( $acf_field_names ) ) : ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var addBtn = document.getElementById('add-criteria');
                var container = document.getElementById('criteria-rows');

                if (!addBtn || !container) return;

                // Build field options HTML from PHP array
                var fieldOptions = '<?php
                    $options_html = '<option value="">' . esc_js( __( '-- Select field --', 'acf-analyzer' ) ) . '</option>';
                    foreach ( $acf_field_names as $field_name ) {
                        $options_html .= '<option value="' . esc_attr( $field_name ) . '">' . esc_html( $field_name ) . '</option>';
                    }
                    echo $options_html;
                ?>';

                addBtn.addEventListener('click', function() {
                    var row = document.createElement('div');
                    row.className = 'criteria-row';
                    row.innerHTML = '<select name="criteria_field[]" class="criteria-field">' + fieldOptions + '</select>' +
                        '<select name="criteria_compare[]" class="criteria-compare">' +
                            '<option value="equals">=</option>' +
                            '<option value="min">\u2265 (min)</option>' +
                            '<option value="max">\u2264 (max)</option>' +
                        '</select>' +
                        '<input type="text" name="criteria_value[]" placeholder="<?php echo esc_js( __( 'Value', 'acf-analyzer' ) ); ?>" class="regular-text">' +
                        ' <button type="button" class="button remove-criteria">&times;</button>';
                    container.appendChild(row);
                    updateRemoveButtons();
                });

                container.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-criteria')) {
                        e.target.parentElement.remove();
                        updateRemoveButtons();
                    }
                });

                function updateRemoveButtons() {
                    var rows = container.querySelectorAll('.criteria-row');
                    rows.forEach(function(row, index) {
                        var btn = row.querySelector('.remove-criteria');
                        btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
                    });
                }
            });
            </script>
            <?php endif; ?>
        </div>

        <?php if ( $search_results ) : ?>
            <!-- Search Results -->
            <div class="acf-analyzer-section acf-analyzer-results">
                <h2><?php esc_html_e( 'Search Results', 'acf-analyzer' ); ?></h2>

                <div class="acf-analyzer-overview">
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e( 'Posts Found', 'acf-analyzer' ); ?></th>
                                <td><?php echo esc_html( number_format_i18n( $search_results['total_found'] ) ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Match Logic', 'acf-analyzer' ); ?></th>
                                <td><?php echo esc_html( $search_results['match_logic'] ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Criteria', 'acf-analyzer' ); ?></th>
                                <td>
                                    <?php foreach ( $search_results['criteria'] as $field => $value ) : ?>
                                        <code><?php echo esc_html( $field ); ?> = <?php echo esc_html( $value ); ?></code><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if ( ! empty( $search_results['posts'] ) ) : ?>
                    <div class="acf-analyzer-fields" style="margin-top: 20px;">
                        <h3><?php esc_html_e( 'Matching Posts', 'acf-analyzer' ); ?></h3>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'ID', 'acf-analyzer' ); ?></th>
                                    <th><?php esc_html_e( 'Title', 'acf-analyzer' ); ?></th>
                                    <th><?php esc_html_e( 'Post Type', 'acf-analyzer' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'acf-analyzer' ); ?></th>
                                    <?php if ( $search_results['debug'] ) : ?>
                                        <th><?php esc_html_e( 'Matched Criteria', 'acf-analyzer' ); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $search_results['posts'] as $post_item ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $post_item['ID'] ); ?></td>
                                        <td><strong><?php echo esc_html( $post_item['title'] ); ?></strong></td>
                                        <td><?php echo esc_html( $post_item['post_type'] ); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url( $post_item['url'] ); ?>" target="_blank"><?php esc_html_e( 'View', 'acf-analyzer' ); ?></a> |
                                            <a href="<?php echo esc_url( get_edit_post_link( $post_item['ID'] ) ); ?>"><?php esc_html_e( 'Edit', 'acf-analyzer' ); ?></a>
                                        </td>
                                        <?php if ( $search_results['debug'] && isset( $post_item['matched_criteria'] ) ) : ?>
                                            <td>
                                                <?php foreach ( $post_item['matched_criteria'] as $field => $data ) : ?>
                                                    <div style="margin-bottom: 5px;">
                                                        <code><?php echo esc_html( $field ); ?></code>:
                                                        <?php if ( $data['matched'] ) : ?>
                                                            <span style="color: green;">✓</span>
                                                        <?php else : ?>
                                                            <span style="color: red;">✗</span>
                                                            <small>(actual: <?php echo esc_html( is_null( $data['actual'] ) ? 'null' : $data['actual'] ); ?>)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <p><em><?php esc_html_e( 'No posts matched the criteria.', 'acf-analyzer' ); ?></em></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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