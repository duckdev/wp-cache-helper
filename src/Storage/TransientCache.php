<?php
/**
 * Transient cache driver.
 *
 * Wraps {@see get_transient()} / {@see set_transient()} (and their
 * `site_` siblings) so every key is automatically scoped to the
 * consumer's prefix. Multisite transients are opt-in per call via
 * the `$site` flag.
 *
 * Cache misses are reported with boolean `false`, matching the
 * WordPress transient convention.
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

use DuckDev\Cache\Contracts\TransientCacheInterface;
use DuckDev\Cache\Support\KeyPrefixer;

/**
 * Class TransientCache.
 */
class TransientCache implements TransientCacheInterface {

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
	public function get( string $key, bool $site = false ) {
		if ( ! $this->can_cache() ) {
			return false;
		}

		$key = $this->prefixer->key( $key );

		return $site ? get_site_transient( $key ) : get_transient( $key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function set( string $key, $value, bool $site = false, int $expiration = 0 ): bool {
		if ( ! $this->can_cache() ) {
			return false;
		}

		$key = $this->prefixer->key( $key );

		return (bool) ( $site
			? set_site_transient( $key, $value, $expiration )
			: set_transient( $key, $value, $expiration ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 2.0.0
	 */
	public function delete( string $key, bool $site = false ): bool {
		$key = $this->prefixer->key( $key );

		return (bool) ( $site ? delete_site_transient( $key ) : delete_transient( $key ) );
	}

	/**
	 * Whether transient writes/reads are enabled for this prefix.
	 *
	 * Mirrors the filter exposed by the object cache driver so callers
	 * can disable both stores with a single toggle.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function can_cache(): bool {
		/** This filter is documented in src/Storage/ObjectCache.php */
		return (bool) apply_filters( $this->prefixer->prefix() . '_can_cache', true, 'transient' );
	}
}
