<?php
/**
 * Hakuvahti Model Class
 *
 * Handles CRUD operations and search execution for saved search watches (hakuvahdits).
 * Each hakuvahti stores search criteria and tracks which results the user has already seen.
 *
 * @package ACF_Analyzer
 * @since 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hakuvahti {

    /**
     * Get matches table name
     *
     * @return string
     */
    private static function get_matches_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'hakuvahti_matches';
    }

    /**
     * Run all saved searches and send email summaries for new matches
     *
     * This method is intended to be called by the scheduled daily event.
     * It uses the existing `run_search()` method to find NEW results per watch
     * and persists deduplicated matches into the `hakuvahti_matches` table.
     * New matches are aggregated by user and emailed using `wp_mail()`.
     *
     * @return void
     */
    public static function run_daily_searches() {
        global $wpdb;

        $table = self::get_table_name();
        $matches_table = self::get_matches_table_name();

        // Debug log array to store in option
        $debug_log = array();
        $debug_log[] = '[' . current_time( 'mysql' ) . '] Starting daily search run';

        $rows = $wpdb->get_results( "SELECT * FROM $table" );
        $debug_log[] = 'Found ' . count( $rows ) . ' hakuvahdit in database';

        if ( empty( $rows ) ) {
            $debug_log[] = 'No hakuvahdit found, exiting';
            update_option( 'acf_analyzer_last_run', current_time( 'mysql' ) );
            update_option( 'acf_analyzer_last_run_debug', $debug_log );
            return;
        }

        $emails = array();

        foreach ( $rows as $row ) {
            $hakuvahti_id = $row->id;
            $user_id = $row->user_id;

            $debug_log[] = '---';
            $debug_log[] = "Processing hakuvahti ID: {$hakuvahti_id}, Name: {$row->name}, User: {$user_id}";
            $debug_log[] = "Category: {$row->category}";
            $debug_log[] = "Criteria (raw): {$row->criteria}";

            $result = self::run_search( $hakuvahti_id, $user_id, $debug_log );

            if ( ! $result ) {
                $debug_log[] = "run_search returned FALSE";
                continue;
            }

            $debug_log[] = "run_search returned: total_all={$result['total_all']}, total_found (new)={$result['total_found']}";

            if ( empty( $result['posts'] ) ) {
                $debug_log[] = "No NEW posts found (all may have been seen already)";
                continue;
            }

            $debug_log[] = "Found " . count( $result['posts'] ) . " new posts";

            // For each new post, attempt to insert into matches table (deduplicated by unique index)
            foreach ( $result['posts'] as $post_item ) {
                $match_hash = sha1( $post_item['ID'] . '|' . $hakuvahti_id );
                $meta = wp_json_encode( $post_item );
                $now = current_time( 'mysql' );

                $sql = $wpdb->prepare(
                    "INSERT INTO $matches_table (search_id, match_id, match_hash, meta, created_at) VALUES (%d, %d, %s, %s, %s) ON DUPLICATE KEY UPDATE id = id",
                    $hakuvahti_id,
                    $post_item['ID'],
                    $match_hash,
                    $meta,
                    $now
                );
                $wpdb->query( $sql );

                // If rows_affected > 0, we inserted a new match
                if ( isset( $wpdb->rows_affected ) && $wpdb->rows_affected > 0 ) {
                    $emails[ $user_id ][] = array(
                        'hakuvahti' => $result['hakuvahti'],
                        'post'      => $post_item,
                    );
                }
            }
        }

        // Send emails per user
        $debug_log[] = '---';
        $debug_log[] = 'Email queue: ' . count( $emails ) . ' users';

        if ( ! empty( $emails ) ) {
            foreach ( $emails as $uid => $items ) {
                $user = get_userdata( $uid );
                if ( ! $user || empty( $user->user_email ) ) {
                    $debug_log[] = "User {$uid}: no email found, skipping";
                    continue;
                }

                $subject = sprintf( __( 'Hakuvahti: %d uutta tulosta', 'acf-analyzer' ), count( $items ) );

                // Group items by hakuvahti
                $grouped = array();
                foreach ( $items as $it ) {
                    $hv_id = $it['hakuvahti']['id'];
                    if ( ! isset( $grouped[ $hv_id ] ) ) {
                        $grouped[ $hv_id ] = array(
                            'hakuvahti' => $it['hakuvahti'],
                            'posts'     => array(),
                        );
                    }
                    $grouped[ $hv_id ]['posts'][] = $it['post'];
                }

                // Build HTML email
                $message = self::build_email_html( $user, $grouped );

                // Set HTML headers
                $headers = array( 'Content-Type: text/html; charset=UTF-8' );

                // Send email
                $mail_result = wp_mail( $user->user_email, $subject, $message, $headers );
                $debug_log[] = "Email to {$user->user_email}: " . ( $mail_result ? 'sent' : 'FAILED' );
            }
        }

        $debug_log[] = '---';
        $debug_log[] = 'Daily run completed at ' . current_time( 'mysql' );

        // Record last run time and debug log
        update_option( 'acf_analyzer_last_run', current_time( 'mysql' ) );
        update_option( 'acf_analyzer_last_run_debug', $debug_log );
    }

    /**
     * Get the database table name
     *
     * @return string Table name with WordPress prefix
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'hakuvahdit';
    }

    /**
     * Create a new hakuvahti
     *
     * @param int    $user_id  WordPress user ID
     * @param string $name     User-defined name for the hakuvahti
     * @param string $category Category to search (Osakeannit, Velkakirjat, Osaketori)
     * @param array  $criteria Search criteria array
     * @return int|false Insert ID on success, false on failure
     */
    public static function create( $user_id, $name, $category, $criteria ) {
        global $wpdb;

        $table = self::get_table_name();

        // Run initial search to get current matching posts
        $analyzer = new ACF_Analyzer();
        $search_criteria = self::convert_criteria_for_search( $criteria );
        $options = array(
            'categories'  => array( $category ),
            'match_logic' => 'AND',
        );
        $results = $analyzer->search_by_criteria( $search_criteria, $options );

        // Extract post IDs from results to mark as seen
        $seen_post_ids = array();
        if ( ! empty( $results['posts'] ) ) {
            foreach ( $results['posts'] as $post ) {
                $seen_post_ids[] = $post['ID'];
            }
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'user_id'       => $user_id,
                'name'          => sanitize_text_field( $name ),
                'category'      => sanitize_text_field( $category ),
                'criteria'      => wp_json_encode( $criteria ),
                'seen_post_ids' => wp_json_encode( $seen_post_ids ),
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $inserted ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get all hakuvahdits for a user
     *
     * @param int $user_id WordPress user ID
     * @return array Array of hakuvahti objects
     */
    public static function get_by_user( $user_id ) {
        global $wpdb;

        $table = self::get_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );

        // Decode JSON fields
        foreach ( $results as &$row ) {
            $row->criteria = json_decode( $row->criteria, true );
            $row->seen_post_ids = json_decode( $row->seen_post_ids, true );
            if ( ! is_array( $row->seen_post_ids ) ) {
                $row->seen_post_ids = array();
            }
        }

        return $results;
    }

    /**
     * Get a single hakuvahti by ID
     *
     * @param int $id Hakuvahti ID
     * @return object|null Hakuvahti object or null if not found
     */
    public static function get_by_id( $id ) {
        global $wpdb;

        $table = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $id
            )
        );

        if ( $row ) {
            $row->criteria = json_decode( $row->criteria, true );
            $row->seen_post_ids = json_decode( $row->seen_post_ids, true );
            if ( ! is_array( $row->seen_post_ids ) ) {
                $row->seen_post_ids = array();
            }
        }

        return $row;
    }

    /**
     * Delete a hakuvahti
     *
     * @param int $id      Hakuvahti ID
     * @param int $user_id User ID (for ownership verification)
     * @return bool True on success, false on failure
     */
    public static function delete( $id, $user_id ) {
        global $wpdb;

        $table = self::get_table_name();

        // Verify ownership before deleting
        $deleted = $wpdb->delete(
            $table,
            array(
                'id'      => $id,
                'user_id' => $user_id,
            ),
            array( '%d', '%d' )
        );

        return $deleted !== false && $deleted > 0;
    }

    /**
     * Run search and return only NEW results (not previously seen)
     *
     * @param int   $id        Hakuvahti ID
     * @param int   $user_id   User ID (for ownership verification)
     * @param array &$debug_log Optional reference to debug log array
     * @return array|false Search results array or false on failure
     */
    public static function run_search( $id, $user_id, &$debug_log = null ) {
        $hakuvahti = self::get_by_id( $id );

        // Verify hakuvahti exists and belongs to user
        if ( ! $hakuvahti || (int) $hakuvahti->user_id !== (int) $user_id ) {
            if ( is_array( $debug_log ) ) {
                $debug_log[] = "  -> Hakuvahti not found or user mismatch (hakuvahti user: " . ( $hakuvahti ? $hakuvahti->user_id : 'null' ) . ", given user: {$user_id})";
            }
            return false;
        }

        // Run search with stored criteria
        $analyzer = new ACF_Analyzer();
        $search_criteria = self::convert_criteria_for_search( $hakuvahti->criteria );

        if ( is_array( $debug_log ) ) {
            $debug_log[] = "  -> Converted criteria: " . wp_json_encode( $search_criteria );
        }

        $options = array(
            'categories'  => array( $hakuvahti->category ),
            'match_logic' => 'AND',
            'debug'       => true,
        );

        if ( is_array( $debug_log ) ) {
            $debug_log[] = "  -> Search options: " . wp_json_encode( $options );
        }

        $results = $analyzer->search_by_criteria( $search_criteria, $options );

        if ( is_array( $debug_log ) ) {
            $debug_log[] = "  -> search_by_criteria returned: total_found={$results['total_found']}, posts count=" . count( $results['posts'] );
            if ( ! empty( $results['posts'] ) ) {
                $post_ids = array_map( function( $p ) { return $p['ID']; }, array_slice( $results['posts'], 0, 5 ) );
                $debug_log[] = "  -> First 5 post IDs: " . implode( ', ', $post_ids );
            }
        }

        // Check if we should ignore seen posts (testing mode)
        $ignore_seen = get_option( 'acf_analyzer_ignore_seen', false );

        if ( is_array( $debug_log ) ) {
            $debug_log[] = "  -> Ignore seen setting: " . ( $ignore_seen ? 'YES (showing all posts)' : 'NO (only new posts)' );
        }

        // Filter out already-seen posts (unless ignore_seen is enabled)
        $seen_ids = $hakuvahti->seen_post_ids;

        if ( is_array( $debug_log ) ) {
            $debug_log[] = "  -> Already seen post IDs: " . ( ! empty( $seen_ids ) ? implode( ', ', array_slice( $seen_ids, 0, 10 ) ) . ( count( $seen_ids ) > 10 ? '...' : '' ) : '(none)' );
        }

        $new_posts = array();
        $new_post_ids = array();

        if ( ! empty( $results['posts'] ) ) {
            foreach ( $results['posts'] as $post ) {
                // If ignore_seen is enabled, include all posts; otherwise filter out seen ones
                if ( $ignore_seen || ! in_array( $post['ID'], $seen_ids, true ) ) {
                    $new_posts[] = $post;
                    $new_post_ids[] = $post['ID'];
                }
            }
        }

        if ( is_array( $debug_log ) ) {
            $debug_log[] = "  -> Posts to return: " . count( $new_posts );
        }

        // Update seen_post_ids with newly found posts (only if not in ignore mode)
        if ( ! $ignore_seen && ! empty( $new_post_ids ) ) {
            self::mark_posts_seen( $id, $new_post_ids );
        }

        return array(
            'posts'       => $new_posts,
            'total_found' => count( $new_posts ),
            'total_all'   => $results['total_found'],
            'hakuvahti'   => array(
                'id'       => $hakuvahti->id,
                'name'     => $hakuvahti->name,
                'category' => $hakuvahti->category,
            ),
        );
    }

    /**
     * Mark posts as seen for a hakuvahti
     *
     * @param int   $id       Hakuvahti ID
     * @param array $post_ids Array of post IDs to mark as seen
     * @return bool True on success, false on failure
     */
    public static function mark_posts_seen( $id, $post_ids ) {
        global $wpdb;

        $hakuvahti = self::get_by_id( $id );
        if ( ! $hakuvahti ) {
            return false;
        }

        // Merge with existing seen IDs (avoid duplicates)
        $current_seen = $hakuvahti->seen_post_ids;
        $merged = array_unique( array_merge( $current_seen, $post_ids ) );

        $table = self::get_table_name();

        $updated = $wpdb->update(
            $table,
            array(
                'seen_post_ids' => wp_json_encode( array_values( $merged ) ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return $updated !== false;
    }

    /**
     * Get count of new (unseen) posts for a hakuvahti
     *
     * @param int $id      Hakuvahti ID
     * @param int $user_id User ID (for ownership verification)
     * @return int|false Number of new posts or false on failure
     */
    public static function get_new_count( $id, $user_id ) {
        $hakuvahti = self::get_by_id( $id );

        if ( ! $hakuvahti || (int) $hakuvahti->user_id !== (int) $user_id ) {
            return false;
        }

        // Run search
        $analyzer = new ACF_Analyzer();
        $search_criteria = self::convert_criteria_for_search( $hakuvahti->criteria );
        $options = array(
            'categories'  => array( $hakuvahti->category ),
            'match_logic' => 'AND',
        );
        $results = $analyzer->search_by_criteria( $search_criteria, $options );

        // Count unseen posts
        $seen_ids = $hakuvahti->seen_post_ids;
        $new_count = 0;

        if ( ! empty( $results['posts'] ) ) {
            foreach ( $results['posts'] as $post ) {
                if ( ! in_array( $post['ID'], $seen_ids, true ) ) {
                    $new_count++;
                }
            }
        }

        return $new_count;
    }

    /**
     * Convert frontend criteria format to search_by_criteria format
     *
     * Frontend sends: [{name, label, values}, ...]
     * search_by_criteria expects: {field_name => value or [values], field_min => num, field_max => num}
     *
     * @param array $criteria Frontend criteria array
     * @return array Converted criteria for ACF_Analyzer::search_by_criteria
     */
    private static function convert_criteria_for_search( $criteria ) {
        $search_criteria = array();

        if ( ! is_array( $criteria ) ) {
            return $search_criteria;
        }

        foreach ( $criteria as $item ) {
            if ( ! isset( $item['name'] ) || ! isset( $item['values'] ) ) {
                continue;
            }

            $field_name = $item['name'];
            $label = isset( $item['label'] ) ? $item['label'] : 'multiple_choice';
            $values = $item['values'];

            if ( 'range' === $label && count( $values ) >= 2 ) {
                // Range: extract min and max
                $nums = array_map( 'floatval', $values );
                sort( $nums );
                $search_criteria[ $field_name . '_min' ] = $nums[0];
                $search_criteria[ $field_name . '_max' ] = $nums[ count( $nums ) - 1 ];
            } else {
                // Multiple choice or single value
                if ( count( $values ) === 1 ) {
                    $search_criteria[ $field_name ] = $values[0];
                } else {
                    $search_criteria[ $field_name ] = $values;
                }
            }
        }

        return $search_criteria;
    }

    /**
     * Get recent matches from the matches table
     *
     * Returns the most recent matches grouped by search (hakuvahti).
     *
     * @param int $limit Maximum number of matches to return
     * @return array Array of recent match records with hakuvahti and post info
     */
    public static function get_recent_matches( $limit = 3 ) {
        global $wpdb;

        $matches_table = self::get_matches_table_name();
        $hakuvahdit_table = self::get_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, h.name as hakuvahti_name, h.category, h.user_id
                 FROM $matches_table m
                 LEFT JOIN $hakuvahdit_table h ON m.search_id = h.id
                 ORDER BY m.created_at DESC
                 LIMIT %d",
                $limit
            )
        );

        // Enrich with post and user data
        foreach ( $results as &$row ) {
            $row->meta = json_decode( $row->meta, true );
            $post = get_post( $row->match_id );
            $row->post_title = $post ? $post->post_title : __( '(Deleted)', 'acf-analyzer' );
            $row->post_url = $post ? get_permalink( $post->ID ) : '';
            $user = get_userdata( $row->user_id );
            $row->user_email = $user ? $user->user_email : '';
            $row->user_display_name = $user ? $user->display_name : __( '(Unknown)', 'acf-analyzer' );
        }

        return $results;
    }

    /**
     * Format criteria for display (human-readable summary)
     *
     * @param array $criteria Criteria array
     * @return string Human-readable criteria summary
     */
    public static function format_criteria_summary( $criteria ) {
        if ( empty( $criteria ) || ! is_array( $criteria ) ) {
            return __( 'Ei hakuehtoja', 'acf-analyzer' );
        }

        $parts = array();
        foreach ( $criteria as $item ) {
            if ( ! isset( $item['name'] ) || ! isset( $item['values'] ) ) {
                continue;
            }

            $name = $item['name'];
            $values = $item['values'];

            if ( ! empty( $values ) ) {
                $parts[] = $name . ': ' . implode( '-', $values );
            }
        }

        return ! empty( $parts ) ? implode( ' | ', $parts ) : __( 'Ei hakuehtoja', 'acf-analyzer' );
    }

    /**
     * Build HTML email content for hakuvahti notifications
     *
     * @param WP_User $user    The user receiving the email
     * @param array   $grouped Posts grouped by hakuvahti
     * @return string HTML email content
     */
    private static function build_email_html( $user, $grouped ) {
        $site_name = get_bloginfo( 'name' );
        $total_posts = 0;
        foreach ( $grouped as $g ) {
            $total_posts += count( $g['posts'] );
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 40px; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                <?php echo esc_html( $site_name ); ?>
                            </h1>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                <?php esc_html_e( 'Hakuvahti-ilmoitus', 'acf-analyzer' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;">
                            <p style="margin: 0 0 20px; color: #333; font-size: 16px; line-height: 1.5;">
                                <?php echo sprintf( esc_html__( 'Hei %s,', 'acf-analyzer' ), esc_html( $user->display_name ) ); ?>
                            </p>
                            <p style="margin: 0 0 30px; color: #333; font-size: 16px; line-height: 1.5;">
                                <?php echo sprintf(
                                    esc_html( _n(
                                        'Löytyi %d uusi hakutulos hakuvahdeillesi:',
                                        'Löytyi %d uutta hakutulosta hakuvahdeillesi:',
                                        $total_posts,
                                        'acf-analyzer'
                                    ) ),
                                    $total_posts
                                ); ?>
                            </p>

                            <?php foreach ( $grouped as $group ) :
                                $hv = $group['hakuvahti'];
                                $posts = $group['posts'];
                            ?>
                            <!-- Hakuvahti Box -->
                            <div style="margin-bottom: 25px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                                <!-- Hakuvahti Header -->
                                <div style="background-color: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #e0e0e0;">
                                    <h2 style="margin: 0; color: #333; font-size: 16px; font-weight: 600;">
                                        <?php echo esc_html( $hv['name'] ); ?>
                                    </h2>
                                    <p style="margin: 5px 0 0; color: #666; font-size: 13px;">
                                        <?php echo esc_html( $hv['category'] ); ?> &bull;
                                        <?php echo sprintf(
                                            esc_html( _n( '%d uusi tulos', '%d uutta tulosta', count( $posts ), 'acf-analyzer' ) ),
                                            count( $posts )
                                        ); ?>
                                    </p>
                                </div>

                                <!-- Posts List -->
                                <div style="padding: 0;">
                                    <?php foreach ( $posts as $index => $post_item ) :
                                        $thumbnail_url = get_the_post_thumbnail_url( $post_item['ID'], 'medium' );
                                        $border_style = $index > 0 ? 'border-top: 1px solid #eee;' : '';
                                    ?>
                                    <div style="padding: 15px 20px; <?php echo $border_style; ?>">
                                        <?php if ( $thumbnail_url ) : ?>
                                        <a href="<?php echo esc_url( $post_item['url'] ); ?>" style="display: block; margin-bottom: 12px;">
                                            <img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $post_item['title'] ); ?>" style="width: 100%; height: auto; border-radius: 4px; display: block;">
                                        </a>
                                        <?php endif; ?>
                                        <h3 style="margin: 0 0 10px; font-size: 15px; font-weight: 600;">
                                            <a href="<?php echo esc_url( $post_item['url'] ); ?>" style="color: #667eea; text-decoration: none;">
                                                <?php echo esc_html( $post_item['title'] ); ?>
                                            </a>
                                        </h3>
                                        <a href="<?php echo esc_url( $post_item['url'] ); ?>" style="display: inline-block; color: #667eea; font-size: 13px; text-decoration: none; font-weight: 500;">
                                            <?php esc_html_e( 'Lue lisää →', 'acf-analyzer' ); ?>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <p style="margin: 30px 0 0; color: #666; font-size: 14px; line-height: 1.5;">
                                <?php esc_html_e( 'Voit hallita hakuvahtejasi kirjautumalla tilillesi.', 'acf-analyzer' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 25px 40px; border-radius: 0 0 8px 8px; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0; color: #999; font-size: 12px; text-align: center;">
                                <?php echo sprintf(
                                    esc_html__( 'Tämä viesti lähetettiin osoitteesta %s', 'acf-analyzer' ),
                                    esc_html( $site_name )
                                ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
