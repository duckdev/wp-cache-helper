<?php

declare( strict_types=1 );

namespace DuckDev\Cache\Tests\Storage;

use Brain\Monkey\Functions;
use DuckDev\Cache\Storage\ObjectCache;
use DuckDev\Cache\Support\KeyPrefixer;
use DuckDev\Cache\Tests\TestCase;

final class ObjectCacheTest extends TestCase {

	private function driver(): ObjectCache {
		return new ObjectCache( new KeyPrefixer( 'p' ) );
	}

	public function test_get_returns_false_when_group_uninitialised(): void {
		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_version', 'p_default' )->andReturn( false );

		$found  = true; // sanity-check that the out-param is reset.
		$result = $this->driver()->get( 'foo', '', $found );

		$this->assertFalse( $result );
		$this->assertFalse( $found );
	}

	public function test_get_returns_cached_value_when_versions_match(): void {
		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_version', 'p_posts' )->andReturn( 3 );

		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_foo', 'p_posts' )
			->andReturn(
				array(
					'data'    => array( 'hello' ),
					'version' => 3,
				)
			);

		$found  = false;
		$result = $this->driver()->get( 'foo', 'posts', $found );

		$this->assertSame( array( 'hello' ), $result );
		$this->assertTrue( $found );
	}

	public function test_get_treats_version_mismatch_as_miss(): void {
		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_version', 'p_default' )->andReturn( 5 );

		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_foo', 'p_default' )
			->andReturn(
				array(
					'data'    => 'stale',
					'version' => 2,
				)
			);

		$found = false;
		$this->assertFalse( $this->driver()->get( 'foo', '', $found ) );
		$this->assertFalse( $found );
	}

	public function test_get_distinguishes_cached_false_from_miss(): void {
		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_version', 'p_default' )->andReturn( 1 );

		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_foo', 'p_default' )
			->andReturn(
				array(
					'data'    => false,
					'version' => 1,
				)
			);

		$found = false;
		$this->assertFalse( $this->driver()->get( 'foo', '', $found ) );
		$this->assertTrue( $found, 'A cached false must be reported as a hit.' );
	}

	public function test_set_initialises_version_on_first_write(): void {
		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_version', 'p_default' )->andReturn( false );

		Functions\expect( 'wp_cache_set' )->once()
			->with( 'p_version', 1, 'p_default' )->andReturn( true );

		Functions\expect( 'wp_cache_set' )->once()
			->with(
				'p_foo',
				array(
					'data'    => 'bar',
					'version' => 1,
				),
				'p_default',
				0
			)->andReturn( true );

		$this->assertTrue( $this->driver()->set( 'foo', 'bar' ) );
	}

	public function test_flush_group_seeds_version_when_missing(): void {
		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_version', 'p_posts' )->andReturn( false );

		Functions\expect( 'wp_cache_set' )->once()
			->with( 'p_version', 1, 'p_posts' )->andReturn( true );

		$this->assertTrue( $this->driver()->flush_group( 'posts' ) );
	}

	public function test_flush_group_increments_existing_version(): void {
		Functions\expect( 'wp_cache_get' )->once()
			->with( 'p_version', 'p_posts' )->andReturn( 4 );

		Functions\expect( 'wp_cache_incr' )->once()
			->with( 'p_version', 1, 'p_posts' )->andReturn( 5 );

		$this->assertTrue( $this->driver()->flush_group( 'posts' ) );
	}

	public function test_delete_returns_bool(): void {
		Functions\expect( 'wp_cache_delete' )->once()
			->with( 'p_foo', 'p_default' )->andReturn( true );

		$this->assertTrue( $this->driver()->delete( 'foo' ) );
	}

	public function test_can_cache_filter_short_circuits_set(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value, $type ) {
				return ( 'p_can_cache' === $hook && 'object' === $type ) ? false : $value;
			}
		);
		Functions\expect( 'wp_cache_set' )->never();

		$this->assertFalse( $this->driver()->set( 'foo', 'bar' ) );
	}

	public function test_flush_prefers_wp_cache_flush(): void {
		Functions\expect( 'wp_cache_flush' )->once()->andReturn( true );

		$this->driver()->flush();
	}

	public function test_native_flush_group_calls_core_function(): void {
		Functions\when( 'wp_cache_supports' )->justReturn( true );
		Functions\expect( 'wp_cache_flush_group' )->once()
			->with( 'p_posts' )->andReturn( true );

		$this->assertTrue( $this->driver()->flush_group( 'posts' ) );
	}

	public function test_native_set_writes_raw_value_without_envelope(): void {
		Functions\when( 'wp_cache_supports' )->justReturn( true );
		Functions\expect( 'wp_cache_set' )->once()
			->with( 'p_foo', 'bar', 'p_default', 0 )->andReturn( true );

		$this->assertTrue( $this->driver()->set( 'foo', 'bar' ) );
	}

	public function test_native_get_returns_raw_value_and_reports_hit(): void {
		Functions\when( 'wp_cache_supports' )->justReturn( true );
		$driver = $this->native_driver( 'bar', true );

		$found  = false;
		$result = $driver->get( 'foo', '', $found );

		$this->assertSame( 'bar', $result );
		$this->assertTrue( $found );
	}

	public function test_native_get_reports_miss(): void {
		Functions\when( 'wp_cache_supports' )->justReturn( true );
		$driver = $this->native_driver( false, false );

		$found  = true;
		$result = $driver->get( 'foo', '', $found );

		$this->assertFalse( $result );
		$this->assertFalse( $found );
	}

	public function test_native_get_unwraps_legacy_envelope(): void {
		Functions\when( 'wp_cache_supports' )->justReturn( true );
		$driver = $this->native_driver(
			array(
				'data'    => array( 'hello' ),
				'version' => 3,
			),
			true
		);

		$found  = false;
		$result = $driver->get( 'foo', '', $found );

		$this->assertSame( array( 'hello' ), $result );
		$this->assertTrue( $found );
	}

	public function test_native_get_distinguishes_cached_false_from_miss(): void {
		Functions\when( 'wp_cache_supports' )->justReturn( true );
		$driver = $this->native_driver( false, true );

		$found  = false;
		$result = $driver->get( 'foo', '', $found );

		$this->assertFalse( $result );
		$this->assertTrue( $found, 'A cached false must be reported as a hit.' );
	}

	/**
	 * Build a driver whose native_get() returns a stubbed [value, found] pair.
	 * Sidesteps Brain\Monkey's inability to relay wp_cache_get()'s by-ref $found.
	 */
	private function native_driver( $value, bool $found ): ObjectCache {
		return new class( new KeyPrefixer( 'p' ), $value, $found ) extends ObjectCache {
			/**
			 * Stubbed value returned by native_get().
			 *
			 * @var mixed
			 */
			private $stub_value;

			/**
			 * Stubbed found flag returned by native_get().
			 *
			 * @var bool
			 */
			private bool $stub_found;

			public function __construct( KeyPrefixer $prefixer, $value, bool $found ) {
				parent::__construct( $prefixer );
				$this->stub_value = $value;
				$this->stub_found = $found;
			}

			protected function native_get( string $key, string $group_key ): array {
				return array( $this->stub_value, $this->stub_found );
			}
		};
	}
}
