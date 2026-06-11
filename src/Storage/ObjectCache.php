<?php
/**
 * Object cache driver.
 *
 * Wraps {@see wp_cache_get()} / {@see wp_cache_set()} with two
 * additions the WordPress core API does not provide:
 *
 * 1. **Prefix scoping.** Every key and group flows through a shared
 *    {@see KeyPrefixer}, so multiple consumers on the same site can
 *    not collide.
 * 2. **Version-based group flush.** WordPress core has no group
 *    flush ({@see https://core.trac.wordpress.org/ticket/4476}). We
 *    keep a per-group `version` counter inside the cache itself and
 *    stamp every stored value with the version that was current at
 *    write time. A flush is just an increment — old values become
 *    unreadable without touching them.
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

		$group_key = $this->prefixer->group( $group );
		$version   = $this->current_version( $group_key );

		// No version yet means the group is empty.
		if ( null === $version ) {
			return false;
		}

		$envelope = wp_cache_get( $this->prefixer->key( $key ), $group_key );

		// A miss returns false; a hit always stores an array envelope with a 'version' key.
		if ( ! is_array( $envelope ) || ! array_key_exists( 'version', $envelope ) ) {
			return false;
		}

		// Stale entry from a previous group version — treat as a miss.
		if ( $envelope['version'] !== $version ) {
			return false;
		}

		$found = true;

		return $envelope['data'] ?? null;
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

		$group_key = $this->prefixer->group( $group );
		$version   = $this->current_version( $group_key );

		// First write into the group — initialise the version sentinel.
		if ( null === $version ) {
			$version = 1;
			wp_cache_set( $this->prefixer->key( self::VERSION_KEY ), $version, $group_key );
		}

		$envelope = array(
			'data'    => $value,
			'version' => $version,
		);

		return (bool) wp_cache_set( $this->prefixer->key( $key ), $envelope, $group_key, $expiration );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function delete( string $key, string $group = '' ): bool {
		return (bool) wp_cache_delete( $this->prefixer->key( $key ), $this->prefixer->group( $group ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function flush_group( string $group ): bool {
		$group_key = $this->prefixer->group( $group );

		// Lazily seed the counter so the first flush still works.
		if ( null === $this->current_version( $group_key ) ) {
			return (bool) wp_cache_set( $this->prefixer->key( self::VERSION_KEY ), 1, $group_key );
		}

		return false !== wp_cache_incr( $this->prefixer->key( self::VERSION_KEY ), 1, $group_key );
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
	 * Read the current version sentinel for a (prefixed) group.
	 *
	 * @since 2.0.0
	 *
	 * @param string $group_key Already-prefixed group key.
	 *
	 * @return int|null Null when the sentinel has not been seeded yet.
	 */
	private function current_version( string $group_key ): ?int {
		$version = wp_cache_get( $this->prefixer->key( self::VERSION_KEY ), $group_key );

		return is_numeric( $version ) ? (int) $version : null;
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
