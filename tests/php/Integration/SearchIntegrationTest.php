<?php
/**
 * Integration Tests for ACF Analyzer Search Functionality
 *
 * Tests the full search workflow including:
 * - ACF_Analyzer with mocked WordPress queries
 * - Hakuvahti CRUD operations with mocked database
 * - Search execution and result filtering
 *
 * @group integration
 */

use Brain\Monkey\Functions;

beforeEach( function () {
    // Mock global $wpdb
    global $wpdb;
    $wpdb         = Mockery::mock( 'wpdb' );
    $wpdb->prefix = 'wp_';
    $wpdb->rows_affected = 0;

    // Load classes under test
    require_once ABSPATH . 'includes/class-acf-analyzer.php';
    require_once ABSPATH . 'includes/class-hakuvahti.php';
} );

afterEach( function () {
    Mockery::close();
} );

describe( 'Search Integration', function () {

    describe( 'ACF_Analyzer search workflow', function () {

        it( 'performs search with AND logic matching all criteria', function () {
            // Create mock posts
            $mockPosts = [
                (object) [
                    'ID'         => 1,
                    'post_title' => 'Property in Helsinki',
                    'post_type'  => 'post',
                ],
                (object) [
                    'ID'         => 2,
                    'post_title' => 'Property in Espoo',
                    'post_type'  => 'post',
                ],
            ];

            // Mock ACF fields for each post
            $acfFieldsMap = [
                1 => [
                    'sijainti' => 'Helsinki',
                    'hinta'    => 100000,
                    'tyyppi'   => 'Asunto',
                ],
                2 => [
                    'sijainti' => 'Espoo',
                    'hinta'    => 150000,
                    'tyyppi'   => 'Asunto',
                ],
            ];

            // Mock WP_Query
            $queryCallCount = 0;
            Functions\when( 'WP_Query' )->alias( function( $args ) use ( &$queryCallCount, $mockPosts ) {
                $queryCallCount++;
                return new class( $mockPosts, $queryCallCount ) {
                    private $posts;
                    private $callCount;

                    public function __construct( $posts, $callCount ) {
                        $this->posts     = $callCount === 1 ? $posts : [];
                        $this->callCount = $callCount;
                    }

                    public function have_posts() {
                        return ! empty( $this->posts );
                    }

                    public function __get( $name ) {
                        if ( $name === 'posts' ) {
                            return $this->posts;
                        }
                        return null;
                    }
                };
            } );

            // Mock get_fields
            Functions\when( 'get_fields' )->alias( function( $post_id ) use ( $acfFieldsMap ) {
                return $acfFieldsMap[ $post_id ] ?? [];
            } );

            // Mock get_permalink
            Functions\when( 'get_permalink' )->alias( function( $post_id ) {
                return "https://example.com/post/{$post_id}";
            } );

            // Mock wp_reset_postdata
            Functions\when( 'wp_reset_postdata' )->justReturn( null );

            $analyzer = new ACF_Analyzer();
            $results  = $analyzer->search_by_criteria(
                [
                    'tyyppi' => 'Asunto',
                ],
                [
                    'match_logic' => 'AND',
                    'categories'  => [ 'Test' ],
                ]
            );

            expect( $results['total_found'] )->toBe( 2 );
            expect( $results['posts'] )->toHaveCount( 2 );
        } );

        it( 'performs range comparison for numeric fields', function () {
            $mockPosts = [
                (object) [
                    'ID'         => 1,
                    'post_title' => 'Cheap Property',
                    'post_type'  => 'post',
                ],
                (object) [
                    'ID'         => 2,
                    'post_title' => 'Expensive Property',
                    'post_type'  => 'post',
                ],
            ];

            $acfFieldsMap = [
                1 => [ 'hinta' => 50000 ],
                2 => [ 'hinta' => 300000 ],
            ];

            $queryCallCount = 0;
            Functions\when( 'WP_Query' )->alias( function( $args ) use ( &$queryCallCount, $mockPosts ) {
                $queryCallCount++;
                return new class( $mockPosts, $queryCallCount ) {
                    private $posts;
                    private $callCount;

                    public function __construct( $posts, $callCount ) {
                        $this->posts     = $callCount === 1 ? $posts : [];
                        $this->callCount = $callCount;
                    }

                    public function have_posts() {
                        return ! empty( $this->posts );
                    }

                    public function __get( $name ) {
                        if ( $name === 'posts' ) {
                            return $this->posts;
                        }
                        return null;
                    }
                };
            } );

            Functions\when( 'get_fields' )->alias( function( $post_id ) use ( $acfFieldsMap ) {
                return $acfFieldsMap[ $post_id ] ?? [];
            } );

            Functions\when( 'get_permalink' )->alias( function( $post_id ) {
                return "https://example.com/post/{$post_id}";
            } );

            Functions\when( 'wp_reset_postdata' )->justReturn( null );

            $analyzer = new ACF_Analyzer();
            $results  = $analyzer->search_by_criteria(
                [
                    'hinta_min' => 40000,
                    'hinta_max' => 100000,
                ],
                [
                    'match_logic' => 'AND',
                    'categories'  => [ 'Test' ],
                ]
            );

            // Only the cheap property should match the range
            expect( $results['total_found'] )->toBe( 1 );
            expect( $results['posts'][0]['ID'] )->toBe( 1 );
        } );

        it( 'supports OR matching for array criteria values', function () {
            $mockPosts = [
                (object) [
                    'ID'         => 1,
                    'post_title' => 'Helsinki Property',
                    'post_type'  => 'post',
                ],
                (object) [
                    'ID'         => 2,
                    'post_title' => 'Tampere Property',
                    'post_type'  => 'post',
                ],
            ];

            $acfFieldsMap = [
                1 => [ 'sijainti' => 'Helsinki' ],
                2 => [ 'sijainti' => 'Tampere' ],
            ];

            $queryCallCount = 0;
            Functions\when( 'WP_Query' )->alias( function( $args ) use ( &$queryCallCount, $mockPosts ) {
                $queryCallCount++;
                return new class( $mockPosts, $queryCallCount ) {
                    private $posts;
                    private $callCount;

                    public function __construct( $posts, $callCount ) {
                        $this->posts     = $callCount === 1 ? $posts : [];
                        $this->callCount = $callCount;
                    }

                    public function have_posts() {
                        return ! empty( $this->posts );
                    }

                    public function __get( $name ) {
                        if ( $name === 'posts' ) {
                            return $this->posts;
                        }
                        return null;
                    }
                };
            } );

            Functions\when( 'get_fields' )->alias( function( $post_id ) use ( $acfFieldsMap ) {
                return $acfFieldsMap[ $post_id ] ?? [];
            } );

            Functions\when( 'get_permalink' )->alias( function( $post_id ) {
                return "https://example.com/post/{$post_id}";
            } );

            Functions\when( 'wp_reset_postdata' )->justReturn( null );

            $analyzer = new ACF_Analyzer();
            $results  = $analyzer->search_by_criteria(
                [
                    'sijainti' => [ 'Helsinki', 'Espoo' ], // OR: Helsinki OR Espoo
                ],
                [
                    'match_logic' => 'AND',
                    'categories'  => [ 'Test' ],
                ]
            );

            // Only Helsinki property should match
            expect( $results['total_found'] )->toBe( 1 );
            expect( $results['posts'][0]['ID'] )->toBe( 1 );
        } );

    } );

    describe( 'Hakuvahti database operations', function () {

        it( 'creates hakuvahti with initial search results', function () {
            global $wpdb;

            // Mock database insert
            $wpdb->shouldReceive( 'insert' )
                 ->once()
                 ->andReturn( true );

            $wpdb->insert_id = 123;

            // Mock WP_Query for initial search (returns no posts for simplicity)
            Functions\when( 'WP_Query' )->alias( function( $args ) {
                return new class {
                    public $posts = [];
                    public function have_posts() {
                        return false;
                    }
                };
            } );

            Functions\when( 'wp_reset_postdata' )->justReturn( null );

            $id = Hakuvahti::create(
                1,                    // user_id
                'Test Search',        // name
                'Osakeannit',         // category
                createMockCriteria()  // criteria
            );

            expect( $id )->toBe( 123 );
        } );

        it( 'retrieves hakuvahdits by user', function () {
            global $wpdb;

            $mockRows = [
                (object) [
                    'id'            => 1,
                    'user_id'       => 5,
                    'name'          => 'Search 1',
                    'category'      => 'Osakeannit',
                    'criteria'      => json_encode( createMockCriteria() ),
                    'seen_post_ids' => '[]',
                    'created_at'    => '2024-01-15 10:00:00',
                ],
                (object) [
                    'id'            => 2,
                    'user_id'       => 5,
                    'name'          => 'Search 2',
                    'category'      => 'Velkakirjat',
                    'criteria'      => json_encode( createMockCriteria() ),
                    'seen_post_ids' => '[1,2,3]',
                    'created_at'    => '2024-01-14 10:00:00',
                ],
            ];

            $wpdb->shouldReceive( 'prepare' )
                 ->once()
                 ->andReturn( 'SELECT * FROM wp_hakuvahdit WHERE user_id = 5 ORDER BY created_at DESC' );

            $wpdb->shouldReceive( 'get_results' )
                 ->once()
                 ->andReturn( $mockRows );

            $results = Hakuvahti::get_by_user( 5 );

            expect( $results )->toHaveCount( 2 );
            expect( $results[0]->name )->toBe( 'Search 1' );
            expect( $results[0]->criteria )->toBeArray();
            expect( $results[1]->seen_post_ids )->toBe( [ 1, 2, 3 ] );
        } );

        it( 'deletes hakuvahti with ownership verification', function () {
            global $wpdb;

            $wpdb->shouldReceive( 'delete' )
                 ->once()
                 ->with(
                     'wp_hakuvahdit',
                     [ 'id' => 1, 'user_id' => 5 ],
                     [ '%d', '%d' ]
                 )
                 ->andReturn( 1 );

            $result = Hakuvahti::delete( 1, 5 );

            expect( $result )->toBeTrue();
        } );

        it( 'fails to delete hakuvahti with wrong user', function () {
            global $wpdb;

            $wpdb->shouldReceive( 'delete' )
                 ->once()
                 ->andReturn( 0 ); // No rows deleted

            $result = Hakuvahti::delete( 1, 999 ); // Wrong user

            expect( $result )->toBeFalse();
        } );

    } );

    describe( 'Search result filtering', function () {

        it( 'filters out already seen posts', function () {
            global $wpdb;

            // Mock get_by_id to return a hakuvahti with seen posts
            $mockHakuvahti = (object) [
                'id'            => 1,
                'user_id'       => 5,
                'name'          => 'Test Search',
                'category'      => 'Osakeannit',
                'criteria'      => json_encode( [ [ 'name' => 'tyyppi', 'values' => [ 'Asunto' ] ] ] ),
                'seen_post_ids' => json_encode( [ 1, 2 ] ), // Posts 1 and 2 already seen
            ];

            $wpdb->shouldReceive( 'prepare' )->andReturn( 'query' );
            $wpdb->shouldReceive( 'get_row' )->andReturn( $mockHakuvahti );
            $wpdb->shouldReceive( 'update' )->andReturn( true );

            // Mock search results with 3 posts (1, 2, 3)
            $mockPosts = [
                (object) [ 'ID' => 1, 'post_title' => 'Seen 1', 'post_type' => 'post' ],
                (object) [ 'ID' => 2, 'post_title' => 'Seen 2', 'post_type' => 'post' ],
                (object) [ 'ID' => 3, 'post_title' => 'New Post', 'post_type' => 'post' ],
            ];

            $queryCallCount = 0;
            Functions\when( 'WP_Query' )->alias( function( $args ) use ( &$queryCallCount, $mockPosts ) {
                $queryCallCount++;
                return new class( $mockPosts, $queryCallCount ) {
                    private $posts;
                    private $callCount;

                    public function __construct( $posts, $callCount ) {
                        $this->posts     = $callCount === 1 ? $posts : [];
                        $this->callCount = $callCount;
                    }

                    public function have_posts() {
                        return ! empty( $this->posts );
                    }

                    public function __get( $name ) {
                        if ( $name === 'posts' ) {
                            return $this->posts;
                        }
                        return null;
                    }
                };
            } );

            Functions\when( 'get_fields' )->justReturn( [ 'tyyppi' => 'Asunto' ] );
            Functions\when( 'get_permalink' )->alias( fn( $id ) => "https://example.com/{$id}" );
            Functions\when( 'wp_reset_postdata' )->justReturn( null );
            Functions\when( 'get_option' )->justReturn( false ); // ignore_seen disabled

            $results = Hakuvahti::run_search( 1, 5 );

            // Should only return post 3 (new, unseen)
            expect( $results['total_found'] )->toBe( 1 );
            expect( $results['posts'][0]['ID'] )->toBe( 3 );
            expect( $results['total_all'] )->toBe( 3 ); // Total before filtering
        } );

    } );

} );
