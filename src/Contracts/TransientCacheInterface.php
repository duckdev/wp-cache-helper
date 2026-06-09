<?php
/**
 * Transient cache contract.
 *
 * Abstraction over a per-prefix transient store. The default
 * implementation wraps the WordPress (site) transient API; tests
 * substitute a memory-only or mock implementation.
 *
 * Note that the transient API itself uses boolean `false` as the
 * miss sentinel — implementations MUST preserve that semantics so
 * `false === $value` comparisons keep working at call sites.
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
 * Interface TransientCacheInterface.
 */
interface TransientCacheInterface {

	/**
	 * Retrieve a transient value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key  Transient key (unprefixed).
	 * @param bool   $site Whether to use site transients (multisite-wide).
	 *
	 * @return mixed Transient value, or false on miss / when caching is disabled.
	 */
	public function get( string $key, bool $site = false );

	/**
	 * Persist a transient value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key        Transient key (unprefixed).
	 * @param mixed  $value      Value to store. Must be serializable.
	 * @param bool   $site       Whether to use site transients.
	 * @param int    $expiration Expiration in seconds. 0 means "no expiration".
	 *
	 * @return bool True on success.
	 */
	public function set( string $key, $value, bool $site = false, int $expiration = 0 ): bool;

	/**
	 * Delete a transient value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key  Transient key (unprefixed).
	 * @param bool   $site Whether to use site transients.
	 *
	 * @return bool True when the entry was removed.
	 */
	public function delete( string $key, bool $site = false ): bool;
}
