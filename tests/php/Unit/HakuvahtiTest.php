<?php
/**
 * Unit Tests for Hakuvahti class
 *
 * Tests the saved search watch functionality including:
 * - Criteria conversion
 * - Criteria formatting
 * - Table name generation
 *
 * @group unit
 */

use Brain\Monkey\Functions;

beforeEach( function () {
    // Mock global $wpdb
    global $wpdb;
    $wpdb         = Mockery::mock( 'wpdb' );
    $wpdb->prefix = 'wp_';

    // Load the class under test
    require_once ABSPATH . 'includes/class-hakuvahti.php';
} );

afterEach( function () {
    Mockery::close();
} );

describe( 'Hakuvahti', function () {

    describe( 'format_criteria_summary', function () {

        it( 'formats criteria into human-readable summary', function () {
            $criteria = [
                [
                    'name'   => 'sijainti',
                    'values' => [ 'Helsinki', 'Espoo' ],
                ],
                [
                    'name'   => 'hinta',
                    'values' => [ '50000', '150000' ],
                ],
            ];

            $summary = Hakuvahti::format_criteria_summary( $criteria );

            expect( $summary )->toContain( 'sijainti' );
            expect( $summary )->toContain( 'Helsinki' );
            expect( $summary )->toContain( 'Espoo' );
            expect( $summary )->toContain( 'hinta' );
        } );

        it( 'returns default message for empty criteria', function () {
            $summary = Hakuvahti::format_criteria_summary( [] );
            expect( $summary )->toBe( 'Ei hakuehtoja' );

            $summary = Hakuvahti::format_criteria_summary( null );
            expect( $summary )->toBe( 'Ei hakuehtoja' );
        } );

        it( 'handles criteria with missing values', function () {
            $criteria = [
                [
                    'name' => 'sijainti',
                    // missing values key
                ],
            ];

            $summary = Hakuvahti::format_criteria_summary( $criteria );
            // Should not crash and return default or partial
            expect( $summary )->toBeString();
        } );

        it( 'joins multiple criteria with pipe separator', function () {
            $criteria = [
                [
                    'name'   => 'sijainti',
                    'values' => [ 'Helsinki' ],
                ],
                [
                    'name'   => 'tyyppi',
                    'values' => [ 'Asunto' ],
                ],
            ];

            $summary = Hakuvahti::format_criteria_summary( $criteria );

            expect( $summary )->toContain( '|' );
        } );

    } );

    describe( 'convert_criteria_for_search', function () {

        it( 'converts multiple_choice criteria correctly', function () {
            $method = new ReflectionMethod( Hakuvahti::class, 'convert_criteria_for_search' );
            $method->setAccessible( true );

            $criteria = [
                [
                    'name'   => 'sijainti',
                    'label'  => 'multiple_choice',
                    'values' => [ 'Helsinki', 'Espoo' ],
                ],
            ];

            $converted = $method->invoke( null, $criteria );

            expect( $converted )->toHaveKey( 'sijainti' );
            expect( $converted['sijainti'] )->toBe( [ 'Helsinki', 'Espoo' ] );
        } );

        it( 'converts single value to scalar', function () {
            $method = new ReflectionMethod( Hakuvahti::class, 'convert_criteria_for_search' );
            $method->setAccessible( true );

            $criteria = [
                [
                    'name'   => 'sijainti',
                    'label'  => 'multiple_choice',
                    'values' => [ 'Helsinki' ],
                ],
            ];

            $converted = $method->invoke( null, $criteria );

            expect( $converted['sijainti'] )->toBe( 'Helsinki' );
        } );

        it( 'converts range criteria to min/max keys', function () {
            $method = new ReflectionMethod( Hakuvahti::class, 'convert_criteria_for_search' );
            $method->setAccessible( true );

            $criteria = [
                [
                    'name'   => 'hinta',
                    'label'  => 'range',
                    'values' => [ '50000', '150000' ],
                ],
            ];

            $converted = $method->invoke( null, $criteria );

            expect( $converted )->toHaveKey( 'hinta_min' );
            expect( $converted )->toHaveKey( 'hinta_max' );
            expect( $converted['hinta_min'] )->toBe( 50000.0 );
            expect( $converted['hinta_max'] )->toBe( 150000.0 );
        } );

        it( 'sorts range values correctly', function () {
            $method = new ReflectionMethod( Hakuvahti::class, 'convert_criteria_for_search' );
            $method->setAccessible( true );

            // Values in wrong order
            $criteria = [
                [
                    'name'   => 'hinta',
                    'label'  => 'range',
                    'values' => [ '200000', '50000' ],
                ],
            ];

            $converted = $method->invoke( null, $criteria );

            // Should be sorted - min is smaller
            expect( $converted['hinta_min'] )->toBe( 50000.0 );
            expect( $converted['hinta_max'] )->toBe( 200000.0 );
        } );

        it( 'handles empty criteria array', function () {
            $method = new ReflectionMethod( Hakuvahti::class, 'convert_criteria_for_search' );
            $method->setAccessible( true );

            $converted = $method->invoke( null, [] );
            expect( $converted )->toBeEmpty();

            $converted = $method->invoke( null, null );
            expect( $converted )->toBeEmpty();
        } );

        it( 'skips criteria without name or values', function () {
            $method = new ReflectionMethod( Hakuvahti::class, 'convert_criteria_for_search' );
            $method->setAccessible( true );

            $criteria = [
                [
                    'name' => 'valid',
                    'values' => [ 'test' ],
                ],
                [
                    // missing name
                    'values' => [ 'test' ],
                ],
                [
                    'name' => 'no_values',
                    // missing values
                ],
            ];

            $converted = $method->invoke( null, $criteria );

            expect( $converted )->toHaveKey( 'valid' );
            expect( count( $converted ) )->toBe( 1 );
        } );

    } );

    describe( 'get_table_name', function () {

        it( 'returns correct table name with prefix', function () {
            $method = new ReflectionMethod( Hakuvahti::class, 'get_table_name' );
            $method->setAccessible( true );

            $tableName = $method->invoke( null );

            expect( $tableName )->toBe( 'wp_hakuvahdit' );
        } );

    } );

    describe( 'get_matches_table_name', function () {

        it( 'returns correct matches table name with prefix', function () {
            $method = new ReflectionMethod( Hakuvahti::class, 'get_matches_table_name' );
            $method->setAccessible( true );

            $tableName = $method->invoke( null );

            expect( $tableName )->toBe( 'wp_hakuvahti_matches' );
        } );

    } );

} );
