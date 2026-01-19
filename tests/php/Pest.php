<?php
/**
 * Pest PHP Configuration
 *
 * This file is the main configuration file for Pest PHP.
 * It's automatically discovered by Pest and used to configure the test suite.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses()
    ->beforeEach( function () {
        Monkey\setUp();

        // Define common WordPress functions
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();

        // Common WordPress function stubs
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'wp_parse_args' )->alias( function( $args, $defaults ) {
            return array_merge( $defaults, $args );
        } );
        Functions\when( 'current_time' )->justReturn( '2024-01-15 12:00:00' );
    } )
    ->afterEach( function () {
        Monkey\tearDown();
    } )
    ->in( 'Unit', 'Integration' );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend( 'toBeValidSearchResult', function () {
    return $this
        ->toBeArray()
        ->toHaveKeys( [ 'posts', 'total_found', 'criteria', 'match_logic' ] );
} );

expect()->extend( 'toBeValidHakuvahti', function () {
    return $this
        ->toBeObject()
        ->toHaveProperties( [ 'id', 'user_id', 'name', 'category', 'criteria' ] );
} );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create mock ACF fields for testing
 */
function createMockAcfFields( array $overrides = [] ): array {
    return array_merge( [
        'hinta'      => 100000,
        'sijainti'   => 'Helsinki',
        'Luokitus'   => 'A',
        'koko'       => 50,
        'tyyppi'     => 'Asunto',
        'nested'     => [
            'field1' => 'value1',
            'field2' => 'value2',
        ],
    ], $overrides );
}

/**
 * Create mock hakuvahti criteria
 */
function createMockCriteria( array $criteria = [] ): array {
    if ( empty( $criteria ) ) {
        return [
            [
                'name'   => 'sijainti',
                'label'  => 'multiple_choice',
                'values' => [ 'Helsinki', 'Espoo' ],
            ],
            [
                'name'   => 'hinta',
                'label'  => 'range',
                'values' => [ '50000', '150000' ],
            ],
        ];
    }
    return $criteria;
}
