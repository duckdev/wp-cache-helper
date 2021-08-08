<?php
/**
 * Cache helper class for WordPress object cache and transients.
 *
 * This class is a helper or wrapper for WordPress object cache and
 * transients. Using this class, you can clear a cache group instead
 * of flushing entire cache. For transients this class will help you
 * to prefix the keys.
 *
 * @since      1.0.0
 * @author     Joel James <me@joelsays.com>
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @copyright  Copyright (c) 2020, Joel James
 * @link       https://github/duckdev/wp-cache-helper/
 * @package    Cache
 * @see        https://core.trac.wordpress.org/ticket/4476
 * @subpackage Cache
 */

namespace DuckDev\Cache;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * Class Cache
 *
 * @since   1.0.0
 * @package DuckDev\Cache
 */
class Cache {

	/**
	 * The prefix for all our keys.
	 *
	 * @var string $prefix
	 * @since 1.0.0
	 */
	protected $prefix = 'wpmudev_cache';

	/**
	 * Cache default group name.
	 *
	 * @var string $group
	 * @since 1.0.0
	 */
	protected $group = 'default';

	/**
	 * Cache version key.
	 *
	 * @var string $version_key
	 * @since 1.0.0
	 */
	protected $version_key = 'version';

	/**
	 * Retrieve a value from the object cache.
	 *
	 * If it doesn't exist, run the $callback to generate and cache the value.
	 *
	 * @param string   $key      The cache key.
	 * @param callable $callback The callback used to generate and cache the value.
	 * @param string   $group    Optional. The cache group. Default is empty.
	 * @param int      $expire   Optional. The number of seconds before the cache entry should expire.
	 *                           Default is 0 (as long as possible).
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed The value returned from $callback, pulled from the cache when available.
	 */
	public function remember( $key, $callback, $group = '', $expire = 0 ) {
		// Get from cache first.
		$cached = $this->get_cache( $key, $group, false, $found );

		// Found in cache.
		if ( ! empty( $found ) ) {
			return $cached;
		}

		// Or run the callback and get value.
		$value = $callback();

		// Save to cache.
		if ( ! is_wp_error( $value ) ) {
			$this->set_cache( $key, $value, $group, $expire );
		}

		return $value;
	}

	/**
	 * Retrieve and subsequently delete a value from the object cache.
	 *
	 * @param string $key     The cache key.
	 * @param string $group   Optional. The cache group. Default is empty.
	 * @param mixed  $default Optional. The default value to return if the given key doesn't
	 *                        exist in the object cache. Default is null.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed The cached value, when available, or $default.
	 */
	public function forget( $key, $group = '', $default = null ) {
		// Get from cache.
		$cached = $this->get_cache( $key, $group, false, $found );

		if ( ! empty( $found ) ) {
			// Delete from cache if found.
			$this->delete_cache( $key, $group );

			return $cached;
		}

		return $default;
	}

	/**
	 * Retrieve a value from persistent cache (transients).
	 *
	 * If it doesn't exist, run the $callback to generate and cache the value.
	 *
	 * @param string   $key      The transient key.
	 * @param callable $callback The callback used to generate and cache the value.
	 * @param bool     $site     Should use site transients.
	 * @param int      $expire   Optional. The number of seconds before the cache entry should expire.
	 *                           Default is 0 (as long as possible).
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed The value returned from $callback, pulled from transients when available.
	 */
	public function persist( $key, $callback, $site = false, $expire = 0 ) {
		// Get transient.
		$cached = $this->get_transient( $key, $site );

		if ( false !== $cached ) {
			return $cached;
		}

		// If not found, call function.
		$value = $callback();

		if ( ! is_wp_error( $value ) ) {
			// Store to transients.
			$this->set_transient( $key, $value, $site, $expire );
		}

		return $value;
	}

	/**
	 * Retrieve and subsequently delete a value from the transient cache.
	 *
	 * @param string $key     The transient key.
	 * @param bool   $site    Should use site transients.
	 * @param mixed  $default Optional. The default value to return if the given key doesn't
	 *                        exist in transients. Default is null.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed The cached value, when available, or $default.
	 */
	public function cease( $key, $site = false, $default = null ) {
		// Get from transient.
		$cached = $this->get_transient( $key, $site );

		if ( false !== $cached ) {
			// Delete if found.
			$this->delete_transient( $key, $site );

			return $cached;
		}

		return $default;
	}

	/**
	 * Flush all items in a group object cache.
	 *
	 * We can not delete the cache by group. So we are using a version
	 * number to track the cache items.
	 *
	 * @param string $group Group name.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool
	 */
	public function flush_group( $group ) {
		// Increment the version to invalidate group.
		return wp_cache_incr( $this->key( $this->version_key ), 1, $group );
	}

	/**
	 * Wrapper for wp_cache_get function to use group.
	 *
	 * Use this to get the cache values set using set_cache method but
	 * have the ability to flush by group.
	 *
	 * @param int|string $key       The key under which the cache contents are stored.
	 * @param string     $group     Optional. Where the cache contents are grouped.
	 * @param bool       $force     Optional. Whether to force an update of the local
	 *                              cache from the persistent cache. Default false.
	 * @param bool       $found     Optional. Whether the key was found in the cache (passed by reference).
	 *                              Disambiguate a return of false, a storable value. Default null.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool|mixed False on failure to retrieve contents or the cache
	 *                      contents on success
	 */
	public function get_cache( $key, $group = '', $force = false, &$found = null ) {
		// Check if caching disabled.
		if ( ! $this->can_cache() ) {
			$found = false;

			return false;
		}

		// Set group name.
		$group = $this->get_group( $group );

		// Get the current cache version.
		$version = wp_cache_get( $this->key( $this->version_key ), $group );

		// Continue if version is not set.
		if ( ! empty( $version ) ) {
			// Get the cache value.
			$data = wp_cache_get( $this->key( $key ), $group, $force, $found );

			// Return only data.
			if ( isset( $data['version'] ) && $version === $data['version'] && ! empty( $data['data'] ) ) {
				return $data['data'];
			} elseif ( isset( $data['version'] ) && $version !== $data['version'] ) {
				// Invalid version.
				$found = false;
			}
		}

		return false;
	}

	/**
	 * Wrapper for wp_cache_set to use group.
	 *
	 * Set cache using this method so that we can delete them without
	 * flushing the object cache as whole. This cache can be deleted
	 * using normal wp_cache_delete also.
	 *
	 * @param int|string $key       The cache key to use for retrieval later.
	 * @param mixed      $data      The contents to store in the cache.
	 * @param string     $group     Optional. Where to group the cache contents.
	 *                              Enables the same key to be used across groups.
	 * @param int        $expire    Optional. When to expire the cache contents, in seconds.
	 *                              Default 0 (no expiration).
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool False on failure, true on success.
	 */
	public function set_cache( $key, $data, $group = '', $expire = 0 ) {
		// Check if caching disabled.
		if ( ! $this->can_cache() ) {
			return false;
		}

		// Set group.
		$group = $this->get_group( $group );

		// Get the current version.
		$version = wp_cache_get( $this->key( $this->version_key ), $group );

		// In case version is not set, set now.
		if ( empty( $version ) ) {
			// In case version is not set, use default 1.
			$version = 1;

			// Set cache version.
			wp_cache_set( $this->key( $this->version_key ), $version, $group );
		}

		// Add to cache array with version.
		$data = array(
			'data'    => $data,
			'version' => $version,
		);

		// Set to WP cache.
		return wp_cache_set( $this->key( $key ), $data, $group, $expire );
	}

	/**
	 * Wrapper for get_site_transient and get_transient.
	 *
	 * Use this to get transients with our cache key prefixes.
	 *
	 * @param int|string $key  The transient key.
	 * @param bool       $site Should use site transients.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed Value of transient.
	 */
	public function get_transient( $key, $site = false ) {
		// Check if caching disabled.
		if ( ! $this->can_cache( 'transient' ) ) {
			return false;
		}

		// Prefix key.
		$key = $this->key( $key );

		return $site ? get_site_transient( $key ) : get_transient( $key );
	}

	/**
	 * Wrapper for set_site_transient and set_transient.
	 *
	 * Use this to set transients with our cache key prefixes.
	 *
	 * @param int|string $key    The transient key.
	 * @param mixed      $value  Value to store.
	 * @param bool       $site   Should use site transients.
	 * @param int        $expire Optional. Time until expiration in seconds. Default 0 (no expiration).
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool True if the value was set, false otherwise.
	 */
	public function set_transient( $key, $value, $site = false, $expire = 0 ) {
		// Check if caching disabled.
		if ( ! $this->can_cache( 'transient' ) ) {
			return false;
		}

		// Prefix key.
		$key = $this->key( $key );

		// Set transient.
		return $site ? set_site_transient( $key, $value, $expire ) : set_transient( $key, $value, $expire );
	}

	/**
	 * Wrapper for wp_cache_delete to use prefix.
	 *
	 * Always use this wrapper to delete cache set by our class.
	 * Otherwise, you will have to manually prefix all your keys.
	 *
	 * @param int|string $key       The cache key to use for retrieval later.
	 * @param string     $group     Optional. Where to group the cache contents.
	 *                              Enables the same key to be used across groups.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool True on successful removal, false on failure.
	 */
	public function delete_cache( $key, $group = '' ) {
		// Delete the cache.
		return wp_cache_delete( $this->key( $key ), $this->get_group( $group ) );
	}

	/**
	 * Wrapper for delete_site_transient and delete_transient.
	 *
	 * Always use this wrapper to delete transients set by our class.
	 * Otherwise, you will have to manually prefix all your keys.
	 *
	 * @param int|string $key  The transient key.
	 * @param bool       $site Should use site transients.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool True if the transient was deleted, false otherwise.
	 */
	public function delete_transient( $key, $site = false ) {
		$key = $this->key( $key );

		return $site ? delete_site_transient( $key ) : delete_transient( $key );
	}

	/**
	 * Flush the entire object cache.
	 *
	 * WARNING: Use this only when absolutely necessary.
	 * This is here because object cache flushes can be prevented.
	 * If in case wp_cache_flush function is disabled we will try
	 * to flush it directly.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function flush() {
		global $wp_object_cache;

		// In some cases.
		if ( is_object( $wp_object_cache ) && is_callable( array( $wp_object_cache, 'flush' ) ) ) {
			$wp_object_cache->flush();
		} elseif ( is_callable( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Get group name for cache item.
	 *
	 * We will always store object cache under a group so that
	 * we can easily clear our own caches at once.
	 * Group names will always be prefixed.
	 *
	 * @param string $group Cache group name.
	 *
	 * @since  1.0.0
	 * @access protected
	 *
	 * @return string
	 */
	protected function get_group( $group ) {
		$group = empty( $group ) ? 'default' : $group;

		return $this->key( $group );
	}

	/**
	 * Get key with our prefix.
	 *
	 * Always use this to generate keys.
	 *
	 * @param string $name Key name.
	 *
	 * @since  1.0.0
	 * @access protected
	 *
	 * @return string
	 */
	protected function key( $name ) {
		return "{$this->prefix}_{$name}";
	}

	/**
	 * Check if we can cache the objects.
	 *
	 * Use the filter to disable cache/transients for debugging
	 * purpose.
	 *
	 * @param string $type Cache type (object or transients).
	 *
	 * @since  1.0.0
	 * @access protected
	 *
	 * @return bool $enable
	 */
	protected function can_cache( $type = 'object' ) {
		/**
		 * Make caching enabled status filterable.
		 *
		 * @param bool   $can_cache Is cache enabled.
		 * @param string $type      Cache type (object or transients).
		 *
		 * @since 1.0.0
		 */
		return apply_filters( "{$this->prefix}_can_cache", true, $type );
	}
}
