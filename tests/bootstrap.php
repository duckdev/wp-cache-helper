<?php
/**
 * PHPUnit bootstrap.
 *
 * Defines the WordPress constants the library guards against and
 * loads the Composer autoloader. Brain\Monkey is initialised per
 * test in {@see \DuckDev\Cache\Tests\TestCase}.
 *
 * @package DuckDev\Cache\Tests
 */

declare( strict_types=1 );

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs/WP_Error.php';
require_once __DIR__ . '/TestCase.php';
