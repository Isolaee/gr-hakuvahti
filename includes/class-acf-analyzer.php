<?php
/**
 * ACF Analyzer Core Class
 *
 * Handles the analysis of ACF fields across posts
 */

class ACF_Analyzer {

    /**
     * Analyze ACF fields for given post types
     *
     * @param array $post_types Post types to analyze
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyze( $post_types = array(), $options = array() ) {
        $defaults = array(
            'posts_per_page' => 200,
            'post_status'    => 'any',
            'fields_filter'  => null, // Array of field names to include, or null for all
        );

        $options = wp_parse_args( $options, $defaults );

        // If no post types specified, analyze all public post types
        if ( empty( $post_types ) ) {
            $post_types = get_post_types( array( 'public' => true ), 'names' );
        }

        $results = array(
            'total_posts'        => 0,
            'empty_acf_count'    => 0,
            'post_type_breakdown'=> array(),
            'field_usage'        => array(),
            'date_range'         => array(
                'earliest' => null,
                'latest'   => null,
            ),
        );

        $paged = 1;
        $field_usage = array();

        // Query posts in batches
        while ( true ) {
            $args = array(
                'post_type'      => $post_types,
                'post_status'    => $options['post_status'],
                'posts_per_page' => $options['posts_per_page'],
                'paged'          => $paged,
                'orderby'        => 'date',
                'order'          => 'DESC',
            );

            $query = new WP_Query( $args );

            if ( ! $query->have_posts() ) {
                break;
            }

            foreach ( $query->posts as $post ) {
                $results['total_posts']++;

                // Update post type breakdown
                if ( ! isset( $results['post_type_breakdown'][ $post->post_type ] ) ) {
                    $results['post_type_breakdown'][ $post->post_type ] = 0;
                }
                $results['post_type_breakdown'][ $post->post_type ]++;

                // Update date range
                if ( is_null( $results['date_range']['earliest'] ) || $post->post_date < $results['date_range']['earliest'] ) {
                    $results['date_range']['earliest'] = $post->post_date;
                }
                if ( is_null( $results['date_range']['latest'] ) || $post->post_date > $results['date_range']['latest'] ) {
                    $results['date_range']['latest'] = $post->post_date;
                }

                // Analyze ACF fields
                $acf_fields = get_fields( $post->ID );

                if ( empty( $acf_fields ) ) {
                    $results['empty_acf_count']++;
                    continue;
                }

                // Filter fields if specified
                if ( is_array( $options['fields_filter'] ) ) {
                    $filtered = array();
                    foreach ( $options['fields_filter'] as $field_name ) {
                        if ( isset( $acf_fields[ $field_name ] ) ) {
                            $filtered[ $field_name ] = $acf_fields[ $field_name ];
                        }
                    }
                    $acf_fields = $filtered;
                }

                $this->analyze_fields( $acf_fields, $field_usage, $post->post_type );
            }

            $paged++;
            wp_reset_postdata();
        }

        // Convert field usage data to array format
        $results['field_usage'] = $this->format_field_usage( $field_usage );

        return $results;
    }

    /**
     * Search posts by ACF field criteria
     *
     * @param array $criteria ACF field conditions (field_name => expected_value)
     * @param array $options Search options
     * @return array Search results
     */
    public function search_by_criteria( $criteria = array(), $options = array() ) {
        $defaults = array(
            'match_logic' => 'AND',  // 'AND' = all criteria must match, 'OR' = any matches
            'debug'       => false,  // Include matched criteria details per post
            'categories'  => array( 'Velkakirjat', 'Osakeannit', 'Osaketori' ),
        );

        $options = wp_parse_args( $options, $defaults );

        $results = array(
            'posts'       => array(),
            'total_found' => 0,
            'criteria'    => $criteria,
            'match_logic' => $options['match_logic'],
            'debug'       => $options['debug'],
        );

        if ( empty( $criteria ) ) {
            return $results;
        }

        $paged = 1;

        // Query published posts in batches
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

            if ( ! $query->have_posts() ) {
                break;
            }

            foreach ( $query->posts as $post ) {
                $acf_fields = get_fields( $post->ID );

                if ( empty( $acf_fields ) ) {
                    continue;
                }

                $matched_criteria = array();
                $match_count      = 0;

                // Check each criterion
                foreach ( $criteria as $field_name => $expected_value ) {
                    // Check for range comparison (_min or _max suffix)
                    if ( preg_match( '/^(.+)_(min|max)$/', $field_name, $matches ) ) {
                        $base_field = $matches[1];
                        $comparison = $matches[2];
                        $actual_value = $this->get_nested_field_value( $acf_fields, $base_field );

                        if ( is_numeric( $actual_value ) && is_numeric( $expected_value ) ) {
                            $actual_num   = (float) $actual_value;
                            $expected_num = (float) $expected_value;
                            $is_match     = ( 'min' === $comparison )
                                ? ( $actual_num >= $expected_num )
                                : ( $actual_num <= $expected_num );
                        } else {
                            $is_match = false;
                        }
                    } else {
                        // Exact match comparison
                        $actual_value = $this->get_nested_field_value( $acf_fields, $field_name );
                        $is_match     = ( $actual_value === $expected_value );
                    }

                    if ( $is_match ) {
                        $match_count++;
                    }

                    if ( $options['debug'] ) {
                        $matched_criteria[ $field_name ] = array(
                            'expected' => $expected_value,
                            'actual'   => $actual_value,
                            'matched'  => $is_match,
                        );
                    }
                }

                // Apply match logic
                $include_post = false;
                if ( 'AND' === $options['match_logic'] ) {
                    $include_post = ( $match_count === count( $criteria ) );
                } else { // OR
                    $include_post = ( $match_count > 0 );
                }

                if ( $include_post ) {
                    $post_data = array(
                        'ID'        => $post->ID,
                        'title'     => $post->post_title,
                        'post_type' => $post->post_type,
                        'url'       => get_permalink( $post->ID ),
                    );

                    if ( $options['debug'] ) {
                        $post_data['matched_criteria'] = $matched_criteria;
                    }

                    $results['posts'][] = $post_data;
                }
            }

            $paged++;
            wp_reset_postdata();
        }

        $results['total_found'] = count( $results['posts'] );

        return $results;
    }

    /**
     * Get nested field value using dot notation
     *
     * @param array  $fields ACF fields array
     * @param string $field_name Field name (supports dot notation: 'parent.child')
     * @return mixed Field value or null if not found
     */
    private function get_nested_field_value( $fields, $field_name ) {
        $keys  = explode( '.', $field_name );
        $value = $fields;

        foreach ( $keys as $key ) {
            if ( is_array( $value ) && isset( $value[ $key ] ) ) {
                $value = $value[ $key ];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Analyze individual fields recursively
     *
     * @param array  $fields Array of ACF fields
     * @param array  &$field_usage Reference to field usage tracker
     * @param string $post_type Current post type
     * @param string $prefix Field name prefix for nested fields
     */
    private function analyze_fields( $fields, &$field_usage, $post_type, $prefix = '' ) {
        foreach ( $fields as $field_name => $value ) {
            $full_field_name = $prefix ? $prefix . '.' . $field_name : $field_name;

            // Initialize field tracking
            if ( ! isset( $field_usage[ $full_field_name ] ) ) {
                $field_usage[ $full_field_name ] = array(
                    'count'        => 0,
                    'post_types'   => array(),
                    'data_types'   => array(),
                    'sample_values'=> array(),
                    'null_count'   => 0,
                );
            }

            $field_usage[ $full_field_name ]['count']++;

            // Track post type
            if ( ! in_array( $post_type, $field_usage[ $full_field_name ]['post_types'] ) ) {
                $field_usage[ $full_field_name ]['post_types'][] = $post_type;
            }

            // Handle null or empty values
            if ( is_null( $value ) || $value === '' ) {
                $field_usage[ $full_field_name ]['null_count']++;
            } else {
                // Track data type
                $data_type = gettype( $value );
                if ( ! in_array( $data_type, $field_usage[ $full_field_name ]['data_types'] ) ) {
                    $field_usage[ $full_field_name ]['data_types'][] = $data_type;
                }

                // Store sample values (max 5)
                if ( count( $field_usage[ $full_field_name ]['sample_values'] ) < 5 ) {
                    $field_usage[ $full_field_name ]['sample_values'][] = $value;
                }

                // Recursively analyze nested arrays/objects
                if ( is_array( $value ) && ! $this->is_acf_image_array( $value ) ) {
                    $this->analyze_fields( $value, $field_usage, $post_type, $full_field_name );
                }
            }
        }
    }

    /**
     * Check if array is an ACF image/file array
     *
     * @param mixed $value Value to check
     * @return bool
     */
    private function is_acf_image_array( $value ) {
        if ( ! is_array( $value ) ) {
            return false;
        }

        // ACF image arrays typically have these keys
        $image_keys = array( 'ID', 'url', 'alt', 'width', 'height' );
        $has_image_keys = 0;

        foreach ( $image_keys as $key ) {
            if ( isset( $value[ $key ] ) ) {
                $has_image_keys++;
            }
        }

        return $has_image_keys >= 3;
    }

    /**
     * Format field usage data for output
     *
     * @param array $field_usage Raw field usage data
     * @return array Formatted field usage
     */
    private function format_field_usage( $field_usage ) {
        $formatted = array();

        foreach ( $field_usage as $field_name => $data ) {
            $fill_rate = $data['count'] > 0
                ? round( ( ( $data['count'] - $data['null_count'] ) / $data['count'] ) * 100, 1 )
                : 0;

            $formatted[] = array(
                'field_name'    => $field_name,
                'count'         => $data['count'],
                'null_count'    => $data['null_count'],
                'fill_rate'     => $fill_rate,
                'post_types'    => $data['post_types'],
                'data_types'    => $data['data_types'],
                'sample_values' => $data['sample_values'],
            );
        }

        // Sort by usage count (descending)
        usort( $formatted, function( $a, $b ) {
            return $b['count'] - $a['count'];
        });

        return $formatted;
    }

    /**
     * Export analysis results as JSON
     *
     * @param array $results Analysis results
     * @return string JSON string
     */
    public function export_json( $results ) {
        return wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Get all unique ACF field names from the database
     *
     * @param array $categories Category slugs to scan (defaults to Velkakirjat, Osakeannit, Osaketori)
     * @return array List of unique field names
     */
    public function get_all_field_names( $categories = array() ) {
        if ( empty( $categories ) ) {
            $categories = array( 'Velkakirjat', 'Osakeannit', 'Osaketori' );
        }

        $field_names = array();
        $paged = 1;

        // Query posts in batches (same approach as analyze() method)
        while ( true ) {
            $args = array(
                'post_type'      => 'post',
                'post_status'    => 'any',
                'posts_per_page' => 100,
                'paged'          => $paged,
                'category_name'  => implode( ',', $categories ),
            );

            $query = new WP_Query( $args );

            if ( ! $query->have_posts() ) {
                break;
            }

            foreach ( $query->posts as $post ) {
                $acf_fields = get_fields( $post->ID );

                if ( ! empty( $acf_fields ) ) {
                    $this->collect_field_names( $acf_fields, $field_names );
                }
            }

            $paged++;
            wp_reset_postdata();

            // Limit scanning to avoid timeout
            if ( $paged > 10 ) {
                break;
            }
        }

        sort( $field_names );
        return array_values( array_unique( $field_names ) );
    }

    /**
     * Recursively collect field names from ACF fields array
     *
     * @param array  $fields ACF fields array
     * @param array  &$field_names Reference to field names collector
     * @param string $prefix Field name prefix for nested fields
     */
    private function collect_field_names( $fields, &$field_names, $prefix = '' ) {
        foreach ( $fields as $field_name => $value ) {
            $full_field_name = $prefix ? $prefix . '.' . $field_name : $field_name;

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
     * Export analysis results as CSV
     *
     * @param array $results Analysis results
     * @return string CSV string
     */
    public function export_csv( $results ) {
        $csv = array();

        // CSV Header
        $csv[] = array(
            'Field Name',
            'Usage Count',
            'Null Count',
            'Fill Rate (%)',
            'Post Types',
            'Data Types',
        );

        // CSV Rows
        foreach ( $results['field_usage'] as $field ) {
            $csv[] = array(
                $field['field_name'],
                $field['count'],
                $field['null_count'],
                $field['fill_rate'],
                implode( ', ', $field['post_types'] ),
                implode( ', ', $field['data_types'] ),
            );
        }

        // Convert to CSV string
        ob_start();
        $output = fopen( 'php://output', 'w' );
        foreach ( $csv as $row ) {
            fputcsv( $output, $row );
        }
        fclose( $output );
        return ob_get_clean();
    }
}