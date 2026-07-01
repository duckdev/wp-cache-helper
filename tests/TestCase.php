<?php
/**
 * Base test case wiring Brain\Monkey + WP_Error stub.
 *
 * @package DuckDev\Cache\Tests
 */

declare( strict_types=1 );

namespace DuckDev\Cache\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);

		// apply_filters: by default, return the passed value unchanged.
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				return $value;
			}
		);

		// Default: backend does not support native group flush. Individual
		// tests can override with Functions\expect() / Functions\when().
		Functions\when( 'wp_cache_supports' )->justReturn( false );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
