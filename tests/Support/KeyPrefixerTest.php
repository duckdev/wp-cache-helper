<?php

declare( strict_types=1 );

namespace DuckDev\Cache\Tests\Support;

use DuckDev\Cache\Exceptions\CacheException;
use DuckDev\Cache\Support\KeyPrefixer;
use DuckDev\Cache\Tests\TestCase;

final class KeyPrefixerTest extends TestCase {

	public function test_prefix_returns_constructor_value(): void {
		$this->assertSame( 'my_plugin', ( new KeyPrefixer( 'my_plugin' ) )->prefix() );
	}

	public function test_key_prepends_prefix(): void {
		$this->assertSame( 'my_plugin_foo', ( new KeyPrefixer( 'my_plugin' ) )->key( 'foo' ) );
	}

	public function test_group_falls_back_to_default_when_empty(): void {
		$this->assertSame( 'my_plugin_default', ( new KeyPrefixer( 'my_plugin' ) )->group( '' ) );
	}

	public function test_group_prefixes_supplied_name(): void {
		$this->assertSame( 'my_plugin_posts', ( new KeyPrefixer( 'my_plugin' ) )->group( 'posts' ) );
	}

	public function test_constructor_trims_prefix(): void {
		$this->assertSame( 'trimmed', ( new KeyPrefixer( '  trimmed  ' ) )->prefix() );
	}

	public function test_empty_prefix_throws(): void {
		$this->expectException( CacheException::class );

		new KeyPrefixer( '   ' );
	}
}
