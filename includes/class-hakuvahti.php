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
     * @param int $id      Hakuvahti ID
     * @param int $user_id User ID (for ownership verification)
     * @return array|false Search results array or false on failure
     */
    public static function run_search( $id, $user_id ) {
        $hakuvahti = self::get_by_id( $id );

        // Verify hakuvahti exists and belongs to user
        if ( ! $hakuvahti || (int) $hakuvahti->user_id !== (int) $user_id ) {
            return false;
        }

        // Run search with stored criteria
        $analyzer = new ACF_Analyzer();
        $search_criteria = self::convert_criteria_for_search( $hakuvahti->criteria );
        $options = array(
            'categories'  => array( $hakuvahti->category ),
            'match_logic' => 'AND',
        );
        $results = $analyzer->search_by_criteria( $search_criteria, $options );

        // Filter out already-seen posts
        $seen_ids = $hakuvahti->seen_post_ids;
        $new_posts = array();
        $new_post_ids = array();

        if ( ! empty( $results['posts'] ) ) {
            foreach ( $results['posts'] as $post ) {
                if ( ! in_array( $post['ID'], $seen_ids, true ) ) {
                    $new_posts[] = $post;
                    $new_post_ids[] = $post['ID'];
                }
            }
        }

        // Update seen_post_ids with newly found posts
        if ( ! empty( $new_post_ids ) ) {
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
                $parts[] = $name . ': ' . implode( ', ', $values );
            }
        }

        return ! empty( $parts ) ? implode( ' | ', $parts ) : __( 'Ei hakuehtoja', 'acf-analyzer' );
    }
}
