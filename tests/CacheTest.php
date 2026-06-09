<?php

declare( strict_types=1 );

namespace DuckDev\Cache\Tests;

use DuckDev\Cache\Cache;
use DuckDev\Cache\Contracts\ObjectCacheInterface;
use DuckDev\Cache\Contracts\TransientCacheInterface;

final class CacheTest extends TestCase {

	private function helper(
		?ObjectCacheInterface $object_cache = null,
		?TransientCacheInterface $transient_cache = null
	): Cache {
		return new Cache(
			'p',
			$object_cache ?? $this->createMock( ObjectCacheInterface::class ),
			$transient_cache ?? $this->createMock( TransientCacheInterface::class )
		);
	}

	public function test_remember_returns_cached_value_without_calling_callback(): void {
		$object_cache = $this->createMock( ObjectCacheInterface::class );
		$object_cache
			->method( 'get' )
			->willReturnCallback(
				static function ( $key, $group, &$found ) {
					$found = true;
					return 'cached';
				}
			);
		$object_cache->expects( $this->never() )->method( 'set' );

		$result = $this->helper( $object_cache )->remember(
			'foo',
			static function () {
				throw new \RuntimeException( 'callback should not run on hit' );
			}
		);

		$this->assertSame( 'cached', $result );
	}

	public function test_remember_runs_callback_and_stores_on_miss(): void {
		$object_cache = $this->createMock( ObjectCacheInterface::class );
		$object_cache
			->method( 'get' )
			->willReturnCallback(
				static function ( $key, $group, &$found ) {
					$found = false;
					return false;
				}
			);
		$object_cache->expects( $this->once() )
			->method( 'set' )
			->with( 'foo', 'computed', '', 0 )
			->willReturn( true );

		$result = $this->helper( $object_cache )->remember(
			'foo',
			static function () {
				return 'computed';
			}
		);

		$this->assertSame( 'computed', $result );
	}

	public function test_remember_treats_cached_false_as_a_hit(): void {
		$object_cache = $this->createMock( ObjectCacheInterface::class );
		$object_cache
			->method( 'get' )
			->willReturnCallback(
				static function ( $key, $group, &$found ) {
					$found = true;
					return false;
				}
			);
		$object_cache->expects( $this->never() )->method( 'set' );

		$result = $this->helper( $object_cache )->remember(
			'foo',
			static function () {
				return 'should not run';
			}
		);

		$this->assertFalse( $result );
	}

	public function test_remember_does_not_cache_wp_error(): void {
		$object_cache = $this->createMock( ObjectCacheInterface::class );
		$object_cache
			->method( 'get' )
			->willReturnCallback(
				static function ( $key, $group, &$found ) {
					$found = false;
					return false;
				}
			);
		$object_cache->expects( $this->never() )->method( 'set' );

		$error  = new \WP_Error( 'oops', 'boom' );
		$result = $this->helper( $object_cache )->remember(
			'foo',
			static function () use ( $error ) {
				return $error;
			}
		);

		$this->assertSame( $error, $result );
	}

	public function test_forget_returns_default_on_miss(): void {
		$object_cache = $this->createMock( ObjectCacheInterface::class );
		$object_cache
			->method( 'get' )
			->willReturnCallback(
				static function ( $key, $group, &$found ) {
					$found = false;
					return false;
				}
			);
		$object_cache->expects( $this->never() )->method( 'delete' );

		$this->assertSame( 'fallback', $this->helper( $object_cache )->forget( 'foo', '', 'fallback' ) );
	}

	public function test_forget_returns_cached_value_and_deletes(): void {
		$object_cache = $this->createMock( ObjectCacheInterface::class );
		$object_cache
			->method( 'get' )
			->willReturnCallback(
				static function ( $key, $group, &$found ) {
					$found = true;
					return 'cached';
				}
			);
		$object_cache->expects( $this->once() )->method( 'delete' )->with( 'foo', '' )->willReturn( true );

		$this->assertSame( 'cached', $this->helper( $object_cache )->forget( 'foo' ) );
	}

	public function test_persist_runs_callback_on_miss(): void {
		$transient_cache = $this->createMock( TransientCacheInterface::class );
		$transient_cache->method( 'get' )->willReturn( false );
		$transient_cache->expects( $this->once() )
			->method( 'set' )
			->with( 'foo', 'computed', false, 30 )
			->willReturn( true );

		$result = $this->helper( null, $transient_cache )->persist(
			'foo',
			static function () {
				return 'computed';
			},
			false,
			30
		);

		$this->assertSame( 'computed', $result );
	}

	public function test_cease_returns_cached_value_and_deletes(): void {
		$transient_cache = $this->createMock( TransientCacheInterface::class );
		$transient_cache->method( 'get' )->willReturn( 'cached' );
		$transient_cache->expects( $this->once() )->method( 'delete' )->with( 'foo', false )->willReturn( true );

		$this->assertSame( 'cached', $this->helper( null, $transient_cache )->cease( 'foo' ) );
	}

	public function test_flush_group_delegates_to_object_cache(): void {
		$object_cache = $this->createMock( ObjectCacheInterface::class );
		$object_cache->expects( $this->once() )->method( 'flush_group' )->with( 'posts' )->willReturn( true );

		$this->assertTrue( $this->helper( $object_cache )->flush_group( 'posts' ) );
	}

	public function test_get_instance_returns_same_instance_per_prefix(): void {
		$a = Cache::get_instance( 'prefix_a' );
		$b = Cache::get_instance( 'prefix_a' );
		$c = Cache::get_instance( 'prefix_b' );

		$this->assertSame( $a, $b );
		$this->assertNotSame( $a, $c );
	}
}
