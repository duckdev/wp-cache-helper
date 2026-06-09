<?php

declare( strict_types=1 );

namespace DuckDev\Cache\Tests\Storage;

use Brain\Monkey\Functions;
use DuckDev\Cache\Storage\TransientCache;
use DuckDev\Cache\Support\KeyPrefixer;
use DuckDev\Cache\Tests\TestCase;

final class TransientCacheTest extends TestCase {

	private function driver(): TransientCache {
		return new TransientCache( new KeyPrefixer( 'p' ) );
	}

	public function test_get_uses_prefixed_key(): void {
		Functions\expect( 'get_transient' )->once()->with( 'p_foo' )->andReturn( 'bar' );

		$this->assertSame( 'bar', $this->driver()->get( 'foo' ) );
	}

	public function test_get_site_routes_to_site_transient(): void {
		Functions\expect( 'get_site_transient' )->once()->with( 'p_foo' )->andReturn( 'bar' );

		$this->assertSame( 'bar', $this->driver()->get( 'foo', true ) );
	}

	public function test_set_passes_value_and_expiration(): void {
		Functions\expect( 'set_transient' )->once()->with( 'p_foo', 'bar', 60 )->andReturn( true );

		$this->assertTrue( $this->driver()->set( 'foo', 'bar', false, 60 ) );
	}

	public function test_set_site_routes_to_set_site_transient(): void {
		Functions\expect( 'set_site_transient' )->once()->with( 'p_foo', 'bar', 0 )->andReturn( true );

		$this->assertTrue( $this->driver()->set( 'foo', 'bar', true ) );
	}

	public function test_delete_returns_bool(): void {
		Functions\expect( 'delete_transient' )->once()->with( 'p_foo' )->andReturn( false );

		$this->assertFalse( $this->driver()->delete( 'foo' ) );
	}

	public function test_can_cache_filter_disables_reads(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value, $type ) {
				return ( 'p_can_cache' === $hook && 'transient' === $type ) ? false : $value;
			}
		);
		Functions\expect( 'get_transient' )->never();

		$this->assertFalse( $this->driver()->get( 'foo' ) );
	}
}
