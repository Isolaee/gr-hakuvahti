<?php
/**
 * PHPUnit/Pest Bootstrap File
 *
 * Sets up the testing environment with WordPress function mocks
 * using Brain\Monkey for isolated unit testing.
 */

// Composer autoloader
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Initialize Brain\Monkey
use Brain\Monkey;

// Define WordPress constants if not defined
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

/**
 * Bootstrap Brain\Monkey in each test
 */
Monkey\setUp();

// Register shutdown function to tear down Brain\Monkey
register_shutdown_function( function() {
    Monkey\tearDown();
} );
