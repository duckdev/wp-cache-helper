<?php
/**
 * Object cache contract.
 *
 * Abstraction over a per-prefix, per-group object cache backed by the
 * WordPress object cache API. The default implementation in this
 * library wraps {@see wp_cache_get()} / {@see wp_cache_set()} and adds
 * version-based group flushing — but consumers depend on the interface
 * so tests can substitute a memory-only or mock implementation.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      2.0.0
 * @package    Cache
 * @subpackage Contracts
 */

namespace DuckDev\Cache\Contracts;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * Interface ObjectCacheInterface.
 */
interface ObjectCacheInterface {

	/**
	 * Retrieve a cached value.
	 *
	 * Implementations MUST set the second-argument $found flag so that
	 * callers can distinguish a legitimately cached falsy value (`0`,
	 * `''`, `[]`, `false`) from a true cache miss.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key   Cache key (unprefixed).
	 * @param string $group Optional. Cache group (unprefixed). Default empty.
	 * @param bool   $found Out-parameter — true when the key was found.
	 *
	 * @return mixed Cached value, or false on miss / when caching is disabled.
	 */
	public function get( string $key, string $group = '', ?bool &$found = null );

	/**
	 * Persist a value in the object cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key        Cache key (unprefixed).
	 * @param mixed  $value      Value to cache.
	 * @param string $group      Optional. Cache group (unprefixed). Default empty.
	 * @param int    $expiration Expiration in seconds. 0 means "no expiration".
	 *
	 * @return bool True on success.
	 */
	public function set( string $key, $value, string $group = '', int $expiration = 0 ): bool;

	/**
	 * Delete a cached value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key   Cache key (unprefixed).
	 * @param string $group Optional. Cache group (unprefixed). Default empty.
	 *
	 * @return bool True when the entry was removed.
	 */
	public function delete( string $key, string $group = '' ): bool;

	/**
	 * Flush every item stored under a group.
	 *
	 * WordPress core does not support group flushing
	 * ({@see https://core.trac.wordpress.org/ticket/4476}), so
	 * implementations are expected to use a version sentinel that
	 * invalidates older entries without touching the underlying cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string $group Group name (unprefixed).
	 *
	 * @return bool True on success.
	 */
	public function flush_group( string $group ): bool;

	/**
	 * Flush the entire object cache.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function flush(): void;
}
