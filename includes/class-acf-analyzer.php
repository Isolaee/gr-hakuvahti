<?php
/**
 * ACF Analyzer Core Class
 *
 * Provides search functionality for ACF fields across WordPress posts.
 * This class handles searching posts by ACF field criteria with support for:
 * - Exact value matching
 * - Numeric range comparisons (min/max)
 * - Multiple value OR matching
 * - Nested field access via dot notation
 * - Category filtering
 * 
 * @package ACF_Analyzer
 * @since 1.0.0
 */

class ACF_Analyzer {

    /**
     * Search posts by ACF field criteria
     * 
     * Searches through published posts and matches them against ACF field criteria.
     * Supports multiple matching strategies including exact match, range comparison,
     * and OR matching for array values.
     *
     * @since 1.0.0
     * 
     * @param array $criteria {
     *     ACF field conditions to match against
     * 
     *     @type mixed $field_name Expected value for the field. Can be:
     *                             - String/number for exact match
     *                             - Array for OR matching (matches any value)
     *                             - For range: use {field}_min or {field}_max as key
     * }
     * @param array $options {
     *     Optional. Search configuration options.
     * 
     *     @type string   $match_logic 'AND' (default) all criteria must match,
     *                                 'OR' any criterion matches
     *     @type bool     $debug       False (default). If true, includes matched
     *                                 criteria details per post
     *     @type string[] $categories  Category slugs to filter posts.
     *                                 Default: ['Velkakirjat', 'Osakeannit', 'Osaketori']
     * }
     * 
     * @return array {
     *     Search results containing matched posts and metadata
     * 
     *     @type array  $posts       Array of matched posts with ID, title, post_type, url
     *     @type int    $total_found Total number of posts found
     *     @type array  $criteria    The criteria used for search
     *     @type string $match_logic The match logic applied
     *     @type bool   $debug       Whether debug mode was enabled
     * }
     */
    public function search_by_criteria( $criteria = array(), $options = array() ) {
        // Set default options
        $defaults = array(
            'match_logic' => 'AND',  // 'AND' = all criteria must match, 'OR' = any matches
            'debug'       => false,  // Include matched criteria details per post
            'categories'  => array( 'Velkakirjat', 'Osakeannit', 'Osaketori' ),
            'or_fields'   => array(), // Fields that should use OR logic (e.g., sijainti, Luokitus)
        );

        $options = wp_parse_args( $options, $defaults );

        // Initialize results structure
        $results = array(
            'posts'       => array(),
            'total_found' => 0,
            'criteria'    => $criteria,
            'match_logic' => $options['match_logic'],
            'debug'       => $options['debug'],
        );

        // Return early if no criteria provided
        if ( empty( $criteria ) ) {
            return $results;
        }

        $paged = 1;

        // Query published posts in batches to avoid memory issues
        while ( true ) {
            $args = array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'paged'          => $paged,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'category_name'  => implode( ',', $options['categories'] ),
            );

            $query = new WP_Query( $args );

            // Break loop if no more posts
            if ( ! $query->have_posts() ) {
                break;
            }

            // Process each post
            foreach ( $query->posts as $post ) {
                // Get all ACF fields for this post
                $acf_fields = get_fields( $post->ID );

                // Skip posts without ACF data
                if ( empty( $acf_fields ) ) {
                    continue;
                }

                // Debug: Log first post's sijainti and Luokitus fields to see actual structure
                if ( $options['debug'] && $paged === 1 && $post === $query->posts[0] ) {
                    error_log( "ACF Analyzer DEBUG - First post ID: {$post->ID}, Title: {$post->post_title}" );
                    error_log( "ACF Analyzer DEBUG - sijainti field value: " . print_r( isset( $acf_fields['sijainti'] ) ? $acf_fields['sijainti'] : 'NOT SET', true ) );
                    error_log( "ACF Analyzer DEBUG - Luokitus field value: " . print_r( isset( $acf_fields['Luokitus'] ) ? $acf_fields['Luokitus'] : 'NOT SET', true ) );
                    error_log( "ACF Analyzer DEBUG - All field keys: " . implode( ', ', array_keys( $acf_fields ) ) );
                }

                $matched_criteria = array();
                $match_count      = 0;

                // Check each criterion against this post's ACF fields
                foreach ( $criteria as $field_name => $expected_value ) {
                    // Check for range comparison (_min or _max suffix)
                    if ( preg_match( '/^(.+)_(min|max)$/', $field_name, $matches ) ) {
                        $base_field = $matches[1];
                        $comparison = $matches[2];
                        $actual_value = $this->get_nested_field_value( $acf_fields, $base_field );

                        // Perform numeric comparison if both values are numeric
                        if ( is_numeric( $actual_value ) && is_numeric( $expected_value ) ) {
                            $actual_num   = (float) $actual_value;
                            $expected_num = (float) $expected_value;
                            // For 'min': actual must be >= expected
                            // For 'max': actual must be <= expected
                            $is_match     = ( 'min' === $comparison )
                                ? ( $actual_num >= $expected_num )
                                : ( $actual_num <= $expected_num );
                        } else {
                            $is_match = false;
                        }
                    } else {
                        // Exact match comparison — support multiple expected values (OR) and array fields
                        $actual_value = $this->get_nested_field_value( $acf_fields, $field_name );

                        // If expected_value is an array, treat as OR: match if any expected value equals actual
                        if ( is_array( $expected_value ) ) {
                            $is_match = false;
                            // Normalize expected values to slugs for comparison
                            $expected_norm = array_map( array( $this, 'normalize_to_slug' ), $expected_value );

                            if ( $options['debug'] ) {
                                error_log( "ACF Analyzer - Comparing field '{$field_name}': expected=" . print_r( $expected_norm, true ) . " actual=" . print_r( $actual_value, true ) );
                            }

                            // Handle array actual values (check intersection)
                            if ( is_array( $actual_value ) ) {
                                foreach ( $actual_value as $av ) {
                                    $av_norm = $this->normalize_to_slug( $av );
                                    foreach ( $expected_norm as $ev ) {
                                        if ( $av_norm === $ev ) {
                                            $is_match = true;
                                            break 2;
                                        }
                                    }
                                }
                            } else {
                                // Handle scalar actual values - normalize for comparison
                                $actual_norm = $this->normalize_to_slug( $actual_value );
                                foreach ( $expected_norm as $ev ) {
                                    if ( $actual_norm === $ev ) {
                                        $is_match = true;
                                        break;
                                    }
                                }
                            }

                            if ( $options['debug'] ) {
                                error_log( "ACF Analyzer - Field '{$field_name}' match result: " . ( $is_match ? 'YES' : 'NO' ) );
                            }
                        } else {
                            // Single expected value — normalize both for comparison
                            $is_match = ( $this->normalize_to_slug( $actual_value ) === $this->normalize_to_slug( $expected_value ) );
                        }
                    }

                    // Track matches
                    if ( $is_match ) {
                        $match_count++;
                    }

                    // Store debug info if requested
                    if ( $options['debug'] ) {
                        $matched_criteria[ $field_name ] = array(
                            'expected' => $expected_value,
                            'actual'   => $actual_value,
                            'matched'  => $is_match,
                        );
                    }
                }

                // Apply match logic to determine if post should be included
                $include_post = false;
                if ( 'AND' === $options['match_logic'] ) {
                    // AND: all criteria must match
                    $include_post = ( $match_count === count( $criteria ) );
                } else {
                    // OR: at least one criterion must match
                    $include_post = ( $match_count > 0 );
                }

                // Add post to results if it matches
                if ( $include_post ) {
                    $post_data = array(
                        'ID'        => $post->ID,
                        'title'     => $post->post_title,
                        'post_type' => $post->post_type,
                        'url'       => get_permalink( $post->ID ),
                    );

                    // Include debug info if requested
                    if ( $options['debug'] ) {
                        $post_data['matched_criteria'] = $matched_criteria;
                    }

                    $results['posts'][] = $post_data;
                }
            }

            $paged++;
            wp_reset_postdata();
        }

        // Update total count
        $results['total_found'] = count( $results['posts'] );

        return $results;
    }

    /**
     * Get nested field value using dot notation
     * 
     * Retrieves a field value from a nested array structure using dot notation.
     * For example: 'parent.child.grandchild' will traverse the array hierarchy.
     *
     * @since 1.0.0
     * 
     * @param array  $fields     ACF fields array
     * @param string $field_name Field name (supports dot notation: 'parent.child')
     * 
     * @return mixed Field value or null if not found
     */
    private function get_nested_field_value( $fields, $field_name ) {
        // Split field name by dots
        $keys  = explode( '.', $field_name );
        $value = $fields;

        // Traverse the array hierarchy
        foreach ( $keys as $key ) {
            if ( is_array( $value ) && isset( $value[ $key ] ) ) {
                $value = $value[ $key ];
            } else {
                // Key not found, return null
                return null;
            }
        }

        return $value;
    }

    /**
     * Normalize a string to slug format for comparison
     *
     * Converts strings like "Päijät-Häme" to "paijat-hame" for matching
     * against WPGB facet slugs.
     *
     * @since 1.0.0
     *
     * @param string $value The value to normalize
     * @return string Normalized slug-like string
     */
    private function normalize_to_slug( $value ) {
        if ( ! is_string( $value ) ) {
            return strval( $value );
        }

        // Convert to lowercase
        $slug = mb_strtolower( $value, 'UTF-8' );

        // Replace Finnish/Swedish special characters
        $slug = str_replace(
            array( 'ä', 'ö', 'å', 'ü', 'é', 'è', 'ê', 'á', 'à', 'â', 'í', 'ì', 'î', 'ó', 'ò', 'ô', 'ú', 'ù', 'û' ),
            array( 'a', 'o', 'a', 'u', 'e', 'e', 'e', 'a', 'a', 'a', 'i', 'i', 'i', 'o', 'o', 'o', 'u', 'u', 'u' ),
            $slug
        );

        // Replace spaces with hyphens
        $slug = preg_replace( '/\s+/', '-', $slug );

        // Remove any characters that aren't alphanumeric or hyphens
        $slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );

        // Remove multiple consecutive hyphens
        $slug = preg_replace( '/-+/', '-', $slug );

        // Trim hyphens from start and end
        $slug = trim( $slug, '-' );

        return $slug;
    }

    /**
     * Get all unique ACF field names from the database
     * 
     * Scans through posts in specified categories and collects all unique
     * ACF field names found. This is used to populate field selection dropdowns.
     * Supports nested fields with dot notation.
     *
     * @since 1.0.0
     * 
     * @param array $categories Optional. Category slugs to scan.
     *                          Default: ['Velkakirjat', 'Osakeannit', 'Osaketori']
     * 
     * @return array List of unique field names (sorted alphabetically)
     */
    public function get_all_field_names( $categories = array() ) {
        // Use default categories if none provided
        if ( empty( $categories ) ) {
            $categories = array( 'Velkakirjat', 'Osakeannit', 'Osaketori' );
        }

        $field_names = array();
        $paged = 1;

        // Query posts in batches to collect field names
        while ( true ) {
            $args = array(
                'post_type'      => 'post',
                'post_status'    => 'any',
                'posts_per_page' => 100,
                'paged'          => $paged,
                'category_name'  => implode( ',', $categories ),
            );

            $query = new WP_Query( $args );

            // Break if no more posts
            if ( ! $query->have_posts() ) {
                break;
            }

            // Collect field names from each post
            foreach ( $query->posts as $post ) {
                $acf_fields = get_fields( $post->ID );

                if ( ! empty( $acf_fields ) ) {
                    $this->collect_field_names( $acf_fields, $field_names );
                }
            }

            $paged++;
            wp_reset_postdata();

            // Limit scanning to avoid timeout (max 1000 posts)
            if ( $paged > 10 ) {
                break;
            }
        }

        // Sort alphabetically and remove duplicates
        sort( $field_names );
        return array_values( array_unique( $field_names ) );
    }

    /**
     * Recursively collect field names from ACF fields array
     * 
     * Traverses a nested ACF fields structure and collects all field names,
     * using dot notation for nested fields (e.g., 'parent.child').
     *
     * @since 1.0.0
     * 
     * @param array  $fields      ACF fields array to process
     * @param array  &$field_names Reference to field names collector (modified in place)
     * @param string $prefix      Field name prefix for nested fields (for recursion)
     * 
     * @return void
     */
    private function collect_field_names( $fields, &$field_names, $prefix = '' ) {
        foreach ( $fields as $field_name => $value ) {
            // Build full field name with prefix for nested fields
            $full_field_name = $prefix ? $prefix . '.' . $field_name : $field_name;

            // Add to list if not already present
            if ( ! in_array( $full_field_name, $field_names, true ) ) {
                $field_names[] = $full_field_name;
            }

            // Recursively collect from nested arrays (but not ACF image arrays)
            if ( is_array( $value ) && ! $this->is_acf_image_array( $value ) ) {
                $this->collect_field_names( $value, $field_names, $full_field_name );
            }
        }
    }

    /**
     * Check if array is an ACF image/file array
     * 
     * ACF stores images and files as arrays with specific keys.
     * This method identifies such arrays to prevent them from being
     * recursively processed as nested fields.
     *
     * @since 1.0.0
     * 
     * @param mixed $value Value to check
     * 
     * @return bool True if the value is an ACF image/file array, false otherwise
     */
    private function is_acf_image_array( $value ) {
        if ( ! is_array( $value ) ) {
            return false;
        }

        // ACF image arrays typically have these keys: ID, url, alt, width, height
        $image_keys = array( 'ID', 'url', 'alt', 'width', 'height' );
        $has_image_keys = 0;

        // Count how many image-specific keys are present
        foreach ( $image_keys as $key ) {
            if ( isset( $value[ $key ] ) ) {
                $has_image_keys++;
            }
        }

        // Consider it an image array if at least 3 image keys are present
        return $has_image_keys >= 3;
    }
}