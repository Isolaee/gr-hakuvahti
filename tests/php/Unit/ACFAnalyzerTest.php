<?php
/**
 * Unit Tests for ACF_Analyzer class
 *
 * Tests the core search functionality including:
 * - Slug normalization
 * - Nested field value retrieval
 * - Range comparisons
 * - OR/AND matching logic
 *
 * @group unit
 */

use Brain\Monkey\Functions;

beforeEach( function () {
    // Load the class under test
    require_once ABSPATH . 'includes/class-acf-analyzer.php';
} );

describe( 'ACF_Analyzer', function () {

    describe( 'normalize_to_slug', function () {

        it( 'converts Finnish characters to ASCII equivalents', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'normalize_to_slug' );
            $method->setAccessible( true );

            expect( $method->invoke( $analyzer, 'Päijät-Häme' ) )->toBe( 'paijat-hame' );
            expect( $method->invoke( $analyzer, 'Österbotten' ) )->toBe( 'osterbotten' );
            expect( $method->invoke( $analyzer, 'Åland' ) )->toBe( 'aland' );
        } );

        it( 'converts strings to lowercase', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'normalize_to_slug' );
            $method->setAccessible( true );

            expect( $method->invoke( $analyzer, 'HELSINKI' ) )->toBe( 'helsinki' );
            expect( $method->invoke( $analyzer, 'Helsinki' ) )->toBe( 'helsinki' );
        } );

        it( 'replaces spaces with hyphens', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'normalize_to_slug' );
            $method->setAccessible( true );

            expect( $method->invoke( $analyzer, 'New York City' ) )->toBe( 'new-york-city' );
            expect( $method->invoke( $analyzer, 'Los  Angeles' ) )->toBe( 'los-angeles' );
        } );

        it( 'removes special characters', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'normalize_to_slug' );
            $method->setAccessible( true );

            expect( $method->invoke( $analyzer, 'Test@Value!' ) )->toBe( 'testvalue' );
            expect( $method->invoke( $analyzer, 'Hello (World)' ) )->toBe( 'hello-world' );
        } );

        it( 'handles non-string values', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'normalize_to_slug' );
            $method->setAccessible( true );

            expect( $method->invoke( $analyzer, 12345 ) )->toBe( '12345' );
            expect( $method->invoke( $analyzer, 3.14 ) )->toBe( '3.14' );
        } );

        it( 'removes multiple consecutive hyphens', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'normalize_to_slug' );
            $method->setAccessible( true );

            expect( $method->invoke( $analyzer, 'test--value' ) )->toBe( 'test-value' );
            expect( $method->invoke( $analyzer, 'a - - - b' ) )->toBe( 'a-b' );
        } );

        it( 'trims hyphens from start and end', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'normalize_to_slug' );
            $method->setAccessible( true );

            expect( $method->invoke( $analyzer, '-test-' ) )->toBe( 'test' );
            expect( $method->invoke( $analyzer, '---hello---' ) )->toBe( 'hello' );
        } );

    } );

    describe( 'get_nested_field_value', function () {

        it( 'retrieves top-level field values', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'get_nested_field_value' );
            $method->setAccessible( true );

            $fields = [
                'name'  => 'Test',
                'price' => 100,
            ];

            expect( $method->invoke( $analyzer, $fields, 'name' ) )->toBe( 'Test' );
            expect( $method->invoke( $analyzer, $fields, 'price' ) )->toBe( 100 );
        } );

        it( 'retrieves nested field values using dot notation', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'get_nested_field_value' );
            $method->setAccessible( true );

            $fields = [
                'parent' => [
                    'child' => [
                        'grandchild' => 'deep_value',
                    ],
                ],
            ];

            expect( $method->invoke( $analyzer, $fields, 'parent.child.grandchild' ) )->toBe( 'deep_value' );
            expect( $method->invoke( $analyzer, $fields, 'parent.child' ) )->toBe( [ 'grandchild' => 'deep_value' ] );
        } );

        it( 'returns null for non-existent fields', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'get_nested_field_value' );
            $method->setAccessible( true );

            $fields = [ 'name' => 'Test' ];

            expect( $method->invoke( $analyzer, $fields, 'nonexistent' ) )->toBeNull();
            expect( $method->invoke( $analyzer, $fields, 'parent.child' ) )->toBeNull();
        } );

    } );

    describe( 'is_acf_image_array', function () {

        it( 'identifies ACF image arrays', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'is_acf_image_array' );
            $method->setAccessible( true );

            $imageArray = [
                'ID'     => 123,
                'url'    => 'https://example.com/image.jpg',
                'alt'    => 'Test image',
                'width'  => 800,
                'height' => 600,
            ];

            expect( $method->invoke( $analyzer, $imageArray ) )->toBeTrue();
        } );

        it( 'rejects regular arrays', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'is_acf_image_array' );
            $method->setAccessible( true );

            $regularArray = [
                'name'  => 'Test',
                'value' => 123,
            ];

            expect( $method->invoke( $analyzer, $regularArray ) )->toBeFalse();
        } );

        it( 'rejects non-arrays', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'is_acf_image_array' );
            $method->setAccessible( true );

            expect( $method->invoke( $analyzer, 'string' ) )->toBeFalse();
            expect( $method->invoke( $analyzer, 123 ) )->toBeFalse();
            expect( $method->invoke( $analyzer, null ) )->toBeFalse();
        } );

        it( 'requires at least 3 image keys', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'is_acf_image_array' );
            $method->setAccessible( true );

            // Only 2 image keys - should not be recognized as image
            $partial = [
                'ID'  => 123,
                'url' => 'https://example.com/image.jpg',
            ];

            expect( $method->invoke( $analyzer, $partial ) )->toBeFalse();

            // 3 image keys - should be recognized
            $withThree = [
                'ID'  => 123,
                'url' => 'https://example.com/image.jpg',
                'alt' => 'Test',
            ];

            expect( $method->invoke( $analyzer, $withThree ) )->toBeTrue();
        } );

    } );

    describe( 'collect_field_names', function () {

        it( 'collects top-level field names', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'collect_field_names' );
            $method->setAccessible( true );

            $fields      = [
                'name'  => 'Test',
                'price' => 100,
                'type'  => 'Apartment',
            ];
            $field_names = [];

            $method->invokeArgs( $analyzer, [ $fields, &$field_names, '' ] );

            expect( $field_names )->toContain( 'name' );
            expect( $field_names )->toContain( 'price' );
            expect( $field_names )->toContain( 'type' );
        } );

        it( 'collects nested field names with dot notation', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'collect_field_names' );
            $method->setAccessible( true );

            $fields      = [
                'location' => [
                    'city'    => 'Helsinki',
                    'country' => 'Finland',
                ],
            ];
            $field_names = [];

            $method->invokeArgs( $analyzer, [ $fields, &$field_names, '' ] );

            expect( $field_names )->toContain( 'location' );
            expect( $field_names )->toContain( 'location.city' );
            expect( $field_names )->toContain( 'location.country' );
        } );

        it( 'skips ACF image arrays for recursion', function () {
            $analyzer = new ACF_Analyzer();
            $method   = new ReflectionMethod( $analyzer, 'collect_field_names' );
            $method->setAccessible( true );

            $fields      = [
                'featured_image' => [
                    'ID'     => 123,
                    'url'    => 'https://example.com/image.jpg',
                    'alt'    => 'Test',
                    'width'  => 800,
                    'height' => 600,
                ],
            ];
            $field_names = [];

            $method->invokeArgs( $analyzer, [ $fields, &$field_names, '' ] );

            // Should have the parent field but not recurse into image properties
            expect( $field_names )->toContain( 'featured_image' );
            expect( $field_names )->not->toContain( 'featured_image.ID' );
            expect( $field_names )->not->toContain( 'featured_image.url' );
        } );

    } );

    describe( 'search_by_criteria', function () {

        it( 'returns empty results for empty criteria', function () {
            $analyzer = new ACF_Analyzer();
            $results  = $analyzer->search_by_criteria( [] );

            expect( $results )->toBeArray();
            expect( $results['posts'] )->toBeEmpty();
            expect( $results['total_found'] )->toBe( 0 );
            expect( $results['criteria'] )->toBeEmpty();
        } );

        it( 'returns proper result structure', function () {
            // Mock WP_Query to return no posts
            Functions\when( 'WP_Query' )->alias( function( $args ) {
                return new class {
                    public $posts = [];
                    public function have_posts() {
                        return false;
                    }
                };
            } );

            $analyzer = new ACF_Analyzer();
            $results  = $analyzer->search_by_criteria( [ 'sijainti' => 'Helsinki' ] );

            expect( $results )->toBeValidSearchResult();
            expect( $results['match_logic'] )->toBe( 'AND' );
            expect( $results['debug'] )->toBeFalse();
        } );

    } );

} );
