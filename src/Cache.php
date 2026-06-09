<?php
/**
 * Cache helper container / library entry point.
 *
 * Single class consumers interact with directly. It wires up:
 *
 *   KeyPrefixer (prefix + group naming)
 *     ├── ObjectCache   (wp_cache_* + version-based group flush)
 *     └── TransientCache ((site_)transient wrapper)
 *
 * Construction has no side effects beyond holding references. The
 * `remember()` / `forget()` / `persist()` / `cease()` helpers — the
 * historical API — are thin convenience wrappers over the drivers;
 * consumers wanting finer control can reach for {@see object_cache()}
 * or {@see transient_cache()} directly.
 *
 * Each container instance is scoped to a single prefix. Two consumers
 * sharing this library on the same site stay isolated because their
 * keys, groups, and `{prefix}_can_cache` filter are all namespaced.
 *
 * @link    https://github.com/duckdev/wp-cache-helper
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @author  Joel James <me@joelsays.com>
 * @since   1.0.0
 * @package Cache
 * @see     https://core.trac.wordpress.org/ticket/4476
 */

namespace DuckDev\Cache;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DuckDev\Cache\Contracts\ObjectCacheInterface;
use DuckDev\Cache\Contracts\TransientCacheInterface;
use DuckDev\Cache\Storage\ObjectCache;
use DuckDev\Cache\Storage\TransientCache;
use DuckDev\Cache\Support\KeyPrefixer;

/**
 * Class Cache.
 */
class Cache {

	/**
	 * Object cache driver.
	 *
	 * @since 2.0.0
	 *
	 * @var ObjectCacheInterface
	 */
	private ObjectCacheInterface $object_cache;

	/**
	 * Transient cache driver.
	 *
	 * @since 2.0.0
	 *
	 * @var TransientCacheInterface
	 */
	private TransientCacheInterface $transient_cache;

	/**
	 * Constructor.
	 *
	 * Drivers default to the bundled WordPress-backed implementations.
	 * Pass custom drivers (e.g. in-memory ones) to override — useful
	 * for tests and for consumers that want to swap the storage layer.
	 *
	 * @since 2.0.0
	 *
	 * @param string                       $prefix          Non-empty prefix shared by every key and group.
	 * @param ObjectCacheInterface|null    $object_cache    Optional. Object cache driver.
	 * @param TransientCacheInterface|null $transient_cache Optional. Transient cache driver.
	 */
	public function __construct(
		string $prefix,
		?ObjectCacheInterface $object_cache = null,
		?TransientCacheInterface $transient_cache = null
	) {
		$prefixer              = new KeyPrefixer( $prefix );
		$this->object_cache    = $object_cache ?? new ObjectCache( $prefixer );
		$this->transient_cache = $transient_cache ?? new TransientCache( $prefixer );
	}

	/**
	 * Get (or create) the container for a given prefix.
	 *
	 * Subsequent calls with the same prefix return the same instance,
	 * so consumers can grab the container from anywhere without
	 * threading a reference through.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prefix Non-empty prefix.
	 *
	 * @return self
	 */
	public static function get_instance( string $prefix ): self {
		static $instances = array();

		if ( ! isset( $instances[ $prefix ] ) ) {
			$instances[ $prefix ] = new self( $prefix );
		}

		return $instances[ $prefix ];
	}

	/**
	 * Retrieve a value from the object cache, computing it on miss.
	 *
	 * Distinguishes a legitimately cached falsy value (`0`, `''`,
	 * `[]`, `false`) from a true miss via the driver's `$found`
	 * out-parameter — so the callback only runs when nothing was
	 * cached.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Computes the value on a miss.
	 * @param string   $group      Optional. Cache group. Default empty.
	 * @param int      $expiration Optional. Expiration in seconds. Default 0.
	 *
	 * @return mixed Cached value, or the callback's return value.
	 */
	public function remember( string $key, callable $callback, string $group = '', int $expiration = 0 ) {
		$found  = false;
		$cached = $this->object_cache->get( $key, $group, $found );

		if ( $found ) {
			return $cached;
		}

		$value = $callback();

		// Don't cache transient errors.
		if ( ! ( function_exists( 'is_wp_error' ) && is_wp_error( $value ) ) ) {
			$this->object_cache->set( $key, $value, $group, $expiration );
		}

		return $value;
	}

	/**
	 * Retrieve and delete a value from the object cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Cache key.
	 * @param string $group   Optional. Cache group. Default empty.
	 * @param mixed  $default Optional. Returned on miss. Default null.
	 *
	 * @return mixed Cached value, or $default.
	 */
	public function forget( string $key, string $group = '', $default = null ) {
		$found  = false;
		$cached = $this->object_cache->get( $key, $group, $found );

		if ( $found ) {
			$this->object_cache->delete( $key, $group );

			return $cached;
		}

		return $default;
	}

	/**
	 * Retrieve a transient value, computing it on miss.
	 *
	 * Note: transients can't represent a legitimately cached boolean
	 * `false` — that's a WordPress limitation, not something this
	 * library can paper over. Callers that need to cache `false`
	 * should use the object cache instead.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $key        Transient key.
	 * @param callable $callback   Computes the value on a miss.
	 * @param bool     $site       Whether to use site transients.
	 * @param int      $expiration Optional. Expiration in seconds. Default 0.
	 *
	 * @return mixed Cached value, or the callback's return value.
	 */
	public function persist( string $key, callable $callback, bool $site = false, int $expiration = 0 ) {
		$cached = $this->transient_cache->get( $key, $site );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = $callback();

		if ( ! ( function_exists( 'is_wp_error' ) && is_wp_error( $value ) ) ) {
			$this->transient_cache->set( $key, $value, $site, $expiration );
		}

		return $value;
	}

	/**
	 * Retrieve and delete a transient value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Transient key.
	 * @param bool   $site    Whether to use site transients.
	 * @param mixed  $default Optional. Returned on miss. Default null.
	 *
	 * @return mixed Cached value, or $default.
	 */
	public function cease( string $key, bool $site = false, $default = null ) {
		$cached = $this->transient_cache->get( $key, $site );

		if ( false !== $cached ) {
			$this->transient_cache->delete( $key, $site );

			return $cached;
		}

		return $default;
	}

	/**
	 * Flush every item stored in a group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group Group name.
	 *
	 * @return bool
	 */
	public function flush_group( string $group ): bool {
		return $this->object_cache->flush_group( $group );
	}

	/**
	 * Flush the entire object cache.
	 *
	 * WARNING: clears every group, including those owned by other
	 * consumers. Use only when absolutely necessary.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->object_cache->flush();
	}

	/**
	 * Access the underlying object cache driver.
	 *
	 * @since 2.0.0
	 *
	 * @return ObjectCacheInterface
	 */
	public function object_cache(): ObjectCacheInterface {
		return $this->object_cache;
	}

	/**
	 * Access the underlying transient cache driver.
	 *
	 * @since 2.0.0
	 *
	 * @return TransientCacheInterface
	 */
	public function transient_cache(): TransientCacheInterface {
		return $this->transient_cache;
	}
}
