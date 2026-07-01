<?php
/**
 * Object cache driver.
 *
 * Wraps {@see wp_cache_get()} / {@see wp_cache_set()} with two
 * additions the WordPress core API does not always provide:
 *
 * 1. **Prefix scoping.** Every key and group flows through a shared
 *    {@see KeyPrefixer}, so multiple consumers on the same site can
 *    not collide.
 * 2. **Group flush.** On WP 6.1+ with a backend that advertises
 *    `flush_group` support via {@see wp_cache_supports()}, calls go
 *    straight through to {@see wp_cache_flush_group()}. Otherwise we
 *    fall back to a per-group `version` sentinel stored inside the
 *    cache itself, stamping every stored value with the version that
 *    was current at write time — a flush is just an increment.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      2.0.0
 * @package    Cache
 * @subpackage Storage
 */

namespace DuckDev\Cache\Storage;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DuckDev\Cache\Contracts\ObjectCacheInterface;
use DuckDev\Cache\Support\KeyPrefixer;

/**
 * Class ObjectCache.
 */
class ObjectCache implements ObjectCacheInterface {

	/**
	 * Internal key that stores the group version sentinel.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private const VERSION_KEY = 'version';

	/**
	 * Key prefixer.
	 *
	 * @since 2.0.0
	 *
	 * @var KeyPrefixer
	 */
	private KeyPrefixer $prefixer;

	/**
	 * Cached result of the native group-flush capability check.
	 *
	 * @since 2.0.1
	 *
	 * @var bool|null
	 */
	private ?bool $native_flush_group = null;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param KeyPrefixer $prefixer Key prefixer shared with the rest of the library.
	 */
	public function __construct( KeyPrefixer $prefixer ) {
		$this->prefixer = $prefixer;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function get( string $key, string $group = '', ?bool &$found = null ) {
		$found = false;

		if ( ! $this->can_cache() ) {
			return false;
		}

		[ $prefixed_key, $group_key ] = $this->prefix( $key, $group );

		return $this->supports_native_flush_group()
			? $this->get_native( $prefixed_key, $group_key, $found )
			: $this->get_fallback( $prefixed_key, $group_key, $found );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function set( string $key, $value, string $group = '', int $expiration = 0 ): bool {
		if ( ! $this->can_cache() ) {
			return false;
		}

		[ $prefixed_key, $group_key ] = $this->prefix( $key, $group );

		return $this->supports_native_flush_group()
			? (bool) wp_cache_set( $prefixed_key, $value, $group_key, $expiration )
			: $this->set_fallback( $prefixed_key, $value, $group_key, $expiration );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function delete( string $key, string $group = '' ): bool {
		[ $prefixed_key, $group_key ] = $this->prefix( $key, $group );

		return (bool) wp_cache_delete( $prefixed_key, $group_key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function flush_group( string $group ): bool {
		$group_key = $this->prefixer->group( $group );

		return $this->supports_native_flush_group()
			? (bool) wp_cache_flush_group( $group_key )
			: $this->flush_group_fallback( $group_key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Falls back to the global `$wp_object_cache->flush()` method when
	 * `wp_cache_flush()` has been disabled by a drop-in.
	 *
	 * @since 2.0.0
	 */
	public function flush(): void {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();

			return;
		}

		global $wp_object_cache;

		if ( is_object( $wp_object_cache ) && is_callable( array( $wp_object_cache, 'flush' ) ) ) {
			$wp_object_cache->flush();
		}
	}

	/**
	 * Fetch a raw value from the object cache along with its found flag.
	 *
	 * Extracted so the by-reference `$found` out-param of
	 * {@see wp_cache_get()} can be tested via a subclass override
	 * without fighting Brain\Monkey's inability to relay references.
	 *
	 * @since 2.0.1
	 *
	 * @param string $key       Already-prefixed key.
	 * @param string $group_key Already-prefixed group key.
	 *
	 * @return array{0: mixed, 1: bool} `[value, found]`.
	 */
	protected function native_get( string $key, string $group_key ): array {
		$found = false;
		$value = wp_cache_get( $key, $group_key, false, $found );

		return array( $value, (bool) $found );
	}

	/**
	 * Native-backend read path.
	 *
	 * @since 2.0.1
	 *
	 * @param string $prefixed_key Already-prefixed key.
	 * @param string $group_key    Already-prefixed group key.
	 * @param bool   $found        Out-param: true on hit.
	 *
	 * @return mixed
	 */
	private function get_native( string $prefixed_key, string $group_key, ?bool &$found ) {
		[ $value, $native_found ] = $this->native_get( $prefixed_key, $group_key );

		if ( ! $native_found ) {
			return false;
		}

		$found = true;

		// Tolerate legacy envelopes left behind by the fallback path.
		return $this->is_envelope( $value ) ? $value['data'] : $value;
	}

	/**
	 * Version-sentinel fallback read path.
	 *
	 * @since 2.0.1
	 *
	 * @param string $prefixed_key Already-prefixed key.
	 * @param string $group_key    Already-prefixed group key.
	 * @param bool   $found        Out-param: true on hit.
	 *
	 * @return mixed
	 */
	private function get_fallback( string $prefixed_key, string $group_key, ?bool &$found ) {
		$version = $this->current_version( $group_key );

		// No version yet means the group is empty.
		if ( null === $version ) {
			return false;
		}

		$envelope = wp_cache_get( $prefixed_key, $group_key );

		// Miss, or a stale entry from a previous group version.
		if ( ! $this->is_envelope( $envelope ) || $envelope['version'] !== $version ) {
			return false;
		}

		$found = true;

		return $envelope['data'];
	}

	/**
	 * Version-sentinel fallback write path.
	 *
	 * @since 2.0.1
	 *
	 * @param string $prefixed_key Already-prefixed key.
	 * @param mixed  $value        Value to store.
	 * @param string $group_key    Already-prefixed group key.
	 * @param int    $expiration   TTL in seconds. `0` = no expiry.
	 *
	 * @return bool
	 */
	private function set_fallback( string $prefixed_key, $value, string $group_key, int $expiration ): bool {
		$version = $this->ensure_version( $group_key );

		return (bool) wp_cache_set( $prefixed_key, $this->wrap( $value, $version ), $group_key, $expiration );
	}

	/**
	 * Version-sentinel fallback flush path.
	 *
	 * @since 2.0.1
	 *
	 * @param string $group_key Already-prefixed group key.
	 *
	 * @return bool
	 */
	private function flush_group_fallback( string $group_key ): bool {
		// Lazily seed the counter so the first flush still works.
		if ( null === $this->current_version( $group_key ) ) {
			return (bool) wp_cache_set( $this->version_key(), 1, $group_key );
		}

		return false !== wp_cache_incr( $this->version_key(), 1, $group_key );
	}

	/**
	 * Whether the active object cache backend supports native group flush.
	 *
	 * Cached per instance — a mid-request drop-in swap is not a scenario
	 * we care about.
	 *
	 * @since 2.0.1
	 *
	 * @return bool
	 */
	private function supports_native_flush_group(): bool {
		if ( null === $this->native_flush_group ) {
			$this->native_flush_group = wp_cache_supports( 'flush_group' ) && function_exists( 'wp_cache_flush_group' );
		}

		return $this->native_flush_group;
	}

	/**
	 * Read the current version sentinel for a (prefixed) group.
	 *
	 * @since 2.0.0
	 *
	 * @param string $group_key Already-prefixed group key.
	 *
	 * @return int|null Null when the sentinel has not been seeded yet.
	 */
	private function current_version( string $group_key ): ?int {
		$version = wp_cache_get( $this->version_key(), $group_key );

		return is_numeric( $version ) ? (int) $version : null;
	}

	/**
	 * Return the current version, seeding it to 1 when missing.
	 *
	 * @since 2.0.1
	 *
	 * @param string $group_key Already-prefixed group key.
	 *
	 * @return int
	 */
	private function ensure_version( string $group_key ): int {
		$version = $this->current_version( $group_key );

		if ( null !== $version ) {
			return $version;
		}

		wp_cache_set( $this->version_key(), 1, $group_key );

		return 1;
	}

	/**
	 * Return prefixed (key, group_key) for the given raw pair.
	 *
	 * @since 2.0.1
	 *
	 * @param string $key   Raw key.
	 * @param string $group Raw group.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function prefix( string $key, string $group ): array {
		return array( $this->prefixer->key( $key ), $this->prefixer->group( $group ) );
	}

	/**
	 * Prefixed key that holds the version sentinel.
	 *
	 * @since 2.0.1
	 *
	 * @return string
	 */
	private function version_key(): string {
		return $this->prefixer->key( self::VERSION_KEY );
	}

	/**
	 * Wrap a value in a version-stamped envelope.
	 *
	 * @since 2.0.1
	 *
	 * @param mixed $value   Value to store.
	 * @param int   $version Current group version.
	 *
	 * @return array{data: mixed, version: int}
	 */
	private function wrap( $value, int $version ): array {
		return array(
			'data'    => $value,
			'version' => $version,
		);
	}

	/**
	 * Whether the given value looks like a fallback-path envelope.
	 *
	 * @since 2.0.1
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 */
	private function is_envelope( $value ): bool {
		return is_array( $value )
			&& array_key_exists( 'data', $value )
			&& array_key_exists( 'version', $value );
	}

	/**
	 * Whether the object cache is enabled for this prefix.
	 *
	 * Consumers can turn off caching for debugging by returning false
	 * from the `{prefix}_can_cache` filter.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function can_cache(): bool {
		/**
		 * Filter whether the object cache is enabled.
		 *
		 * @since 2.0.0
		 *
		 * @param bool   $can_cache Whether caching is enabled. Default true.
		 * @param string $type      Cache type — 'object' for this driver.
		 */
		return (bool) apply_filters( $this->prefixer->prefix() . '_can_cache', true, 'object' );
	}
}
