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

        // Clean up expired guest hakuvahdits before processing
        $expired_count = self::cleanup_expired_hakuvahdits();
        if ( $expired_count > 0 ) {
            $debug_log[] = "Cleaned up {$expired_count} expired guest hakuvahdit";
        }

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
                    // Use a unique key per email recipient (user_id for registered, email for guests)
                    $is_guest = ( (int) $user_id === 0 );
                    $email_key = $is_guest ? 'guest_' . $row->guest_email : 'user_' . $user_id;

                    $emails[ $email_key ][] = array(
                        'hakuvahti'    => $result['hakuvahti'],
                        'post'         => $post_item,
                        'is_guest'     => $is_guest,
                        'guest_email'  => $is_guest ? $row->guest_email : null,
                        'delete_token' => $is_guest ? $row->delete_token : null,
                        'user_id'      => $user_id,
                    );
                }
            }
        }

        // Send emails per recipient (user or guest)
        $debug_log[] = '---';
        $debug_log[] = 'Email queue: ' . count( $emails ) . ' recipients';

        if ( ! empty( $emails ) ) {
            foreach ( $emails as $email_key => $items ) {
                // Determine if this is a guest or registered user
                $first_item = $items[0];
                $is_guest = $first_item['is_guest'];

                if ( $is_guest ) {
                    // Guest user - use guest_email directly
                    $recipient_email = $first_item['guest_email'];
                    $display_name = ''; // Guests don't have display names
                    $delete_token = $first_item['delete_token'];

                    if ( empty( $recipient_email ) ) {
                        $debug_log[] = "Guest hakuvahti: no email found, skipping";
                        continue;
                    }
                } else {
                    // Registered user - lookup via get_userdata
                    $uid = $first_item['user_id'];
                    $user = get_userdata( $uid );
                    if ( ! $user || empty( $user->user_email ) ) {
                        $debug_log[] = "User {$uid}: no email found, skipping";
                        continue;
                    }
                    $recipient_email = $user->user_email;
                    $display_name = $user->display_name;
                    $delete_token = null;
                }

                $subject = sprintf( __( 'Hakuvahti: %d uutta tulosta', 'acf-analyzer' ), count( $items ) );

                // Group items by hakuvahti
                $grouped = array();
                foreach ( $items as $it ) {
                    $hv_id = $it['hakuvahti']['id'];
                    if ( ! isset( $grouped[ $hv_id ] ) ) {
                        $grouped[ $hv_id ] = array(
                            'hakuvahti'    => $it['hakuvahti'],
                            'posts'        => array(),
                            'delete_token' => $it['delete_token'],
                        );
                    }
                    $grouped[ $hv_id ]['posts'][] = $it['post'];
                }

                // Build HTML email (pass guest info for unsubscribe link)
                $message = self::build_email_html_for_recipient( $recipient_email, $display_name, $grouped, $is_guest );

                // Set HTML headers
                $headers = array( 'Content-Type: text/html; charset=UTF-8' );

                // Send email
                $mail_result = wp_mail( $recipient_email, $subject, $message, $headers );
                $debug_log[] = "Email to {$recipient_email}" . ( $is_guest ? ' (guest)' : '' ) . ": " . ( $mail_result ? 'sent' : 'FAILED' );
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
     * Get hakuvahti creation statistics for the last 7 days
     *
     * @return array {
     *     @type int $total        Total hakuvahdits created in last 7 days
     *     @type int $registered   Created by registered (logged-in) users
     *     @type int $guests       Created by guest (non-logged-in) users
     *     @type int $active_total Total active hakuvahdits (all time, not expired)
     * }
     */
    public static function get_stats_last_7_days() {
        global $wpdb;

        $table = self::get_table_name();
        $since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        $now   = current_time( 'mysql' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM( CASE WHEN user_id > 0 THEN 1 ELSE 0 END ) AS registered,
                SUM( CASE WHEN user_id = 0 THEN 1 ELSE 0 END ) AS guests
            FROM $table
            WHERE created_at >= %s",
            $since
        ) );

        $active_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE expires_at IS NULL OR expires_at > %s",
            $now
        ) );

        return array(
            'total'        => $row ? (int) $row->total : 0,
            'registered'   => $row ? (int) $row->registered : 0,
            'guests'       => $row ? (int) $row->guests : 0,
            'active_total' => $active_total,
        );
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
     * Create a new hakuvahti for a guest (non-logged-in user)
     *
     * Guest hakuvahdits are stored with user_id = 0, include a guest_email,
     * a unique delete_token for unsubscribe links, and an expires_at timestamp.
     *
     * @since 1.2.0
     * @param string $email    Guest's email address
     * @param string $name     User-defined name for the hakuvahti
     * @param string $category Category to search (Osakeannit, Velkakirjat, Osaketori)
     * @param array  $criteria Search criteria array
     * @param string $ip       IP address of the guest (for rate limiting)
     * @return int|false Insert ID on success, false on failure
     */
    public static function create_guest( $email, $name, $category, $criteria, $ip = '' ) {
        global $wpdb;

        $table = self::get_table_name();

        // Generate unique delete token for unsubscribe link
        $delete_token = wp_generate_password( 32, false, false );

        // Calculate expiration date based on admin setting
        $ttl_days = (int) get_option( 'acf_analyzer_guest_ttl_days', 90 );
        $expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$ttl_days} days" ) );

        // Run initial search to get current matching posts (same as regular create)
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
                'user_id'       => 0, // Guest user
                'name'          => sanitize_text_field( $name ),
                'category'      => sanitize_text_field( $category ),
                'criteria'      => wp_json_encode( $criteria ),
                'seen_post_ids' => wp_json_encode( $seen_post_ids ),
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
                'guest_email'   => sanitize_email( $email ),
                'delete_token'  => $delete_token,
                'expires_at'    => $expires_at,
                'created_by_ip' => sanitize_text_field( $ip ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $inserted ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Delete a hakuvahti by its delete token
     *
     * Used for guest unsubscribe links. The delete token is unique per hakuvahti.
     *
     * @since 1.2.0
     * @param string $token The delete token from the unsubscribe link
     * @return bool True if deleted, false if not found
     */
    public static function delete_by_token( $token ) {
        global $wpdb;

        if ( empty( $token ) ) {
            return false;
        }

        $table = self::get_table_name();

        // Find and delete the hakuvahti with this token
        $deleted = $wpdb->delete(
            $table,
            array( 'delete_token' => $token ),
            array( '%s' )
        );

        return $deleted !== false && $deleted > 0;
    }

    /**
     * Clean up expired guest hakuvahdits
     *
     * Deletes all hakuvahdits where expires_at has passed.
     * Called at the start of run_daily_searches().
     *
     * @since 1.2.0
     * @return int Number of deleted records
     */
    public static function cleanup_expired_hakuvahdits() {
        global $wpdb;

        $table = self::get_table_name();

        // Delete hakuvahdits where expires_at is in the past
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE expires_at IS NOT NULL AND expires_at < %s",
                current_time( 'mysql' )
            )
        );

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Check rate limit for guest hakuvahti creation
     *
     * Enforces limits: max 3 per IP per hour, max 5 total per email
     *
     * @since 1.2.0
     * @param string $email Guest email
     * @param string $ip    Guest IP address
     * @return array Array with 'allowed' (bool) and 'message' (string) keys
     */
    public static function check_guest_rate_limit( $email, $ip ) {
        global $wpdb;

        $table = self::get_table_name();

        // Check IP rate limit: max 3 per hour
        $one_hour_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );
        $ip_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE created_by_ip = %s AND created_at > %s",
                $ip,
                $one_hour_ago
            )
        );

        if ( (int) $ip_count >= 3 ) {
            return array(
                'allowed' => false,
                'message' => __( 'Liian monta hakuvahtia lyhyessä ajassa. Yritä myöhemmin uudelleen.', 'acf-analyzer' ),
            );
        }

        // Check email limit: max 5 active hakuvahdits per email
        $email_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE guest_email = %s",
                $email
            )
        );

        if ( (int) $email_count >= 5 ) {
            return array(
                'allowed' => false,
                'message' => __( 'Tällä sähköpostiosoitteella on jo enimmäismäärä hakuvahteja.', 'acf-analyzer' ),
            );
        }

        return array(
            'allowed' => true,
            'message' => '',
        );
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

        // Verify hakuvahti exists and belongs to user (or is a guest hakuvahti with user_id = 0)
        $is_guest = $hakuvahti && (int) $hakuvahti->user_id === 0;
        $user_matches = $hakuvahti && (int) $hakuvahti->user_id === (int) $user_id;

        if ( ! $hakuvahti || ( ! $is_guest && ! $user_matches ) ) {
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

            // Normalize values to array for easier handling
            if ( ! is_array( $values ) ) {
                $values = array( $values );
            }

            if ( 'word_search' === $label ) {
                // Word search criteria - sanitize and pass through as special key
                $analyzer = new ACF_Analyzer();
                $words = array();
                foreach ( $values as $v ) {
                    $sanitized = $analyzer->sanitize_word_search_input( $v );
                    $words = array_merge( $words, $sanitized );
                }
                if ( ! empty( $words ) ) {
                    $search_criteria['__word_search'] = array_unique( $words );
                }
            } elseif ( 'range' === $label ) {
                // Support multiple formats:
                // - two plain numbers => min/max
                // - operator-prefixed single values like '<100' or '>=200' => map to _min/_max
                // - mix of operator and plain numbers
                $min = null;
                $max = null;
                $plain = array();

                foreach ( $values as $v ) {
                    $raw = trim( (string) $v );
                    if ( $raw === '' ) {
                        continue;
                    }

                    // Normalize decimal comma to dot
                    $norm = str_replace( ',', '.', $raw );

                    // Operator form: <, <=, >, >=
                    if ( preg_match( '/^\s*([<>]=?)\s*(.+)$/', $norm, $m ) ) {
                        $op = $m[1];
                        $num = floatval( preg_replace( '/[^0-9\.\-]/', '', $m[2] ) );
                        if ( strpos( $op, '<' ) !== false ) {
                            if ( $max === null ) {
                                $max = $num;
                            } else {
                                $max = min( $max, $num );
                            }
                        } else {
                            if ( $min === null ) {
                                $min = $num;
                            } else {
                                $min = max( $min, $num );
                            }
                        }
                        continue;
                    }

                    // Explicit labels like min:100 or max:100
                    if ( preg_match( '/^\s*(min|max)\s*[:=]\s*(.+)$/i', $norm, $m2 ) ) {
                        $which = strtolower( $m2[1] );
                        $num = floatval( preg_replace( '/[^0-9\.\-]/', '', $m2[2] ) );
                        if ( $which === 'min' ) {
                            $min = $num;
                        } else {
                            $max = $num;
                        }
                        continue;
                    }

                    // Plain numeric
                    if ( is_numeric( $norm ) ) {
                        $plain[] = floatval( $norm );
                    }
                    // otherwise ignore non-numeric strings here
                }

                if ( count( $plain ) >= 2 ) {
                    sort( $plain );
                    $search_criteria[ $field_name . '_min' ] = $plain[0];
                    $search_criteria[ $field_name . '_max' ] = $plain[ count( $plain ) - 1 ];
                } else {
                    // If plain numbers contain one value, treat as minimum (consistent with display "yli X")
                    if ( count( $plain ) === 1 ) {
                        $pv = $plain[0];
                        if ( $min === null ) {
                            $min = $pv;
                        }
                        // If max was already set via operator, keep it
                    }

                    if ( $min !== null ) {
                        $search_criteria[ $field_name . '_min' ] = $min;
                    }
                    if ( $max !== null ) {
                        $search_criteria[ $field_name . '_max' ] = $max;
                    }
                }
            } else {
                // Multiple choice or single value (non-range)
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
        // Load admin-defined user search options to possibly retrieve postfix/unit for fields
        $user_options = get_option( 'acf_analyzer_user_search_options', array() );

        foreach ( $criteria as $item ) {
            if ( ! isset( $item['name'] ) || ! isset( $item['values'] ) ) {
                continue;
            }

            $name = $item['name'];
            $values = $item['values'];

            if ( empty( $values ) ) {
                continue;
            }

            // Handle word_search specially
            $label = isset( $item['label'] ) ? $item['label'] : '';
            if ( 'word_search' === $label || '__word_search' === $name ) {
                $words = is_array( $values ) ? implode( ' ', $values ) : $values;
                $parts[] = __( 'Sanahaku', 'acf-analyzer' ) . ': ' . $words;
                continue;
            }

            $postfix = '';
            // Try to find matching admin option by acf_field to get postfix
            if ( is_array( $user_options ) ) {
                foreach ( $user_options as $u ) {
                    if ( isset( $u['acf_field'] ) && $u['acf_field'] === $name ) {
                        if ( isset( $u['values'] ) && is_array( $u['values'] ) && isset( $u['values']['postfix'] ) ) {
                            $postfix = $u['values']['postfix'];
                        }
                        break;
                    }
                }
            }

            // Format range-like values (min/max) and operator-prefixed single values specially
            if ( is_array( $values ) && count( $values ) >= 2 ) {
                $parts[] = $name . ': ' . implode( ' - ', $values ) . ( $postfix ? ' ' . $postfix : '' );
            } else {
                // Single-value or list
                if ( is_array( $values ) ) {
                    if ( count( $values ) === 1 ) {
                        $v = trim( (string) $values[0] );
                        // operator-prefixed single values
                        if ( preg_match( '/^\s*([<>]=?)\s*(.+)$/', $v, $m ) ) {
                            $op = $m[1];
                            $num = trim( $m[2] );
                            if ( strpos( $op, '<' ) !== false ) {
                                $parts[] = $name . ': ' . sprintf( /* translators: %s = value */ __( 'alle %s', 'acf-analyzer' ), $num ) . ( $postfix ? ' ' . $postfix : '' );
                            } else {
                                $parts[] = $name . ': ' . sprintf( /* translators: %s = value */ __( 'yli %s', 'acf-analyzer' ), $num ) . ( $postfix ? ' ' . $postfix : '' );
                            }
                        } elseif ( preg_match( '/^\s*(min|max)\s*[:=]\s*(.+)$/i', $v, $m2 ) ) {
                            $which = strtolower( $m2[1] );
                            $num = trim( $m2[2] );
                            if ( $which === 'min' ) {
                                $parts[] = $name . ': ' . sprintf( __( 'yli %s', 'acf-analyzer' ), $num ) . ( $postfix ? ' ' . $postfix : '' );
                            } else {
                                $parts[] = $name . ': ' . sprintf( __( 'alle %s', 'acf-analyzer' ), $num ) . ( $postfix ? ' ' . $postfix : '' );
                            }
                        } elseif ( is_numeric( trim( $v ) ) ) {
                            // Plain numeric single value - default to "yli" (over/min) for consistency
                            $parts[] = $name . ': ' . sprintf( __( 'yli %s', 'acf-analyzer' ), trim( $v ) ) . ( $postfix ? ' ' . $postfix : '' );
                        } else {
                            $parts[] = $name . ': ' . $v . ( $postfix ? ' ' . $postfix : '' );
                        }
                    } else {
                        $parts[] = $name . ': ' . implode( ', ', $values );
                    }
                } else {
                    $parts[] = $name . ': ' . $values;
                }
            }
        }

        return ! empty( $parts ) ? implode( ' | ', $parts ) : __( 'Ei hakuehtoja', 'acf-analyzer' );
    }

    /**
     * Build HTML email content for hakuvahti notifications (legacy, for registered users)
     *
     * @param WP_User $user    The user receiving the email
     * @param array   $grouped Posts grouped by hakuvahti
     * @return string HTML email content
     */
    private static function build_email_html( $user, $grouped ) {
        return self::build_email_html_for_recipient(
            $user->user_email,
            $user->display_name,
            $grouped,
            false
        );
    }

    /**
     * Build HTML email content for hakuvahti notifications
     *
     * Supports both registered users and guests. For guests, includes
     * unsubscribe (delete) links for each hakuvahti.
     *
     * @since 1.2.0
     * @param string $email        Recipient email address
     * @param string $display_name Recipient display name (empty for guests)
     * @param array  $grouped      Posts grouped by hakuvahti
     * @param bool   $is_guest     Whether recipient is a guest (non-logged-in user)
     * @return string HTML email content
     */
    private static function build_email_html_for_recipient( $email, $display_name, $grouped, $is_guest ) {
        $site_name = get_bloginfo( 'name' );
        $total_posts = 0;
        foreach ( $grouped as $g ) {
            $total_posts += count( $g['posts'] );
        }

        // Greeting text differs for guests vs registered users
        $greeting = $is_guest
            ? esc_html__( 'Hei,', 'acf-analyzer' )
            : sprintf( esc_html__( 'Hei %s,', 'acf-analyzer' ), esc_html( $display_name ) );

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
                        <td style="background-color: #032e5b; padding: 30px 40px; border-radius: 8px 8px 0 0;">
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
                                <?php echo $greeting; ?>
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
                                $delete_token = isset( $group['delete_token'] ) ? $group['delete_token'] : null;
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
                                        $company_name = get_field( 'Yrityksen_nimi', $post_item['ID'] );
                                        $border_style = $index > 0 ? 'border-top: 1px solid #eee;' : '';
                                    ?>
                                    <div style="padding: 15px 20px; <?php echo $border_style; ?>">
                                        <!-- Company Name -->
                                        <?php if ( $company_name ) : ?>
                                        <p style="margin: 0 0 10px; color: #666; font-size: 13px;">
                                            <?php echo esc_html( $company_name ); ?>
                                        </p>
                                        <?php endif; ?>

                                        <!-- Two Column Layout -->
                                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
                                            <tr>
                                                <!-- Left Column: Text -->
                                                <td style="vertical-align: top; padding-right: 15px;">
                                                    <h3 style="margin: 0; font-size: 15px; font-weight: 600;">
                                                        <a href="<?php echo esc_url( $post_item['url'] ); ?>" style="color: #032e5b; text-decoration: none;">
                                                            <?php echo esc_html( $post_item['title'] ); ?>
                                                        </a>
                                                    </h3>
                                                </td>
                                                <!-- Right Column: Image -->
                                                <?php if ( $thumbnail_url ) : ?>
                                                <td style="vertical-align: top; width: 84px;">
                                                    <a href="<?php echo esc_url( $post_item['url'] ); ?>" style="display: block;">
                                                        <img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $post_item['title'] ); ?>" style="width: 84px; height: auto; border-radius: 4px; display: block;">
                                                    </a>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        </table>

                                        <!-- Link Row -->
                                        <a href="<?php echo esc_url( $post_item['url'] ); ?>" style="display: inline-block; color: #032e5b; font-size: 13px; text-decoration: none; font-weight: 500;">
                                            <?php esc_html_e( 'Lue lisää →', 'acf-analyzer' ); ?>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ( $is_guest && $delete_token ) :
                                    $delete_url = add_query_arg( 'hakuvahti_delete', $delete_token, home_url() );
                                ?>
                                <!-- Guest Unsubscribe Link -->
                                <div style="background-color: #f8f9fa; padding: 12px 20px; border-top: 1px solid #e0e0e0;">
                                    <p style="margin: 0; font-size: 12px; color: #666;">
                                        <?php esc_html_e( 'Etkö halua enää ilmoituksia tästä hakuvahdista?', 'acf-analyzer' ); ?>
                                        <a href="<?php echo esc_url( $delete_url ); ?>" style="color: #032e5b; text-decoration: underline;">
                                            <?php esc_html_e( 'Poista hakuvahti', 'acf-analyzer' ); ?>
                                        </a>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>

                            <?php if ( $is_guest ) : ?>
                            <p style="margin: 30px 0 0; color: #666; font-size: 14px; line-height: 1.5;">
                                <?php
                                $ttl_days = (int) get_option( 'acf_analyzer_guest_ttl_days', 90 );
                                echo sprintf(
                                    esc_html__( 'Hakuvahtisi on voimassa %d päivää luomisesta.', 'acf-analyzer' ),
                                    $ttl_days
                                );
                                ?>
                            </p>
                            <?php else : ?>
                            <p style="margin: 30px 0 0; color: #666; font-size: 14px; line-height: 1.5;">
                                <?php esc_html_e( 'Voit hallita hakuvahtejasi kirjautumalla tilillesi.', 'acf-analyzer' ); ?>
                            </p>
                            <?php endif; ?>
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
