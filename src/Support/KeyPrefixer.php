<?php
/**
 * Key + group prefixing helper.
 *
 * Every cache key and group name handled by this library is prefixed
 * with the consumer-supplied prefix. The prefixer is shared between
 * the object cache and transient drivers so naming stays consistent
 * across both stores — and so a multi-prefix consumer (two Duck Dev
 * plugins on the same site) never collides.
 *
 * Pure value object: no WordPress calls, easy to unit-test.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      2.0.0
 * @package    Cache
 * @subpackage Support
 */

namespace DuckDev\Cache\Support;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use DuckDev\Cache\Exceptions\CacheException;

/**
 * Class KeyPrefixer.
 */
class KeyPrefixer {

	/**
	 * Consumer-supplied prefix.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prefix Non-empty prefix shared by every key and group.
	 *
	 * @throws CacheException When $prefix is empty after trimming.
	 */
	public function __construct( string $prefix ) {
		$prefix = trim( $prefix );

		if ( '' === $prefix ) {
			throw new CacheException( 'Cache prefix must be a non-empty string.' );
		}

		$this->prefix = $prefix;
	}

	/**
	 * Get the raw prefix.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function prefix(): string {
		return $this->prefix;
	}

	/**
	 * Build a prefixed cache key.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Caller-supplied key name.
	 *
	 * @return string
	 */
	public function key( string $name ): string {
		return $this->prefix . '_' . $name;
	}

	/**
	 * Build a prefixed group name.
	 *
	 * An empty $name resolves to the "default" group so that callers
	 * can pass through the empty default without special-casing it.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Caller-supplied group name.
	 *
	 * @return string
	 */
	public function group( string $name ): string {
		return $this->key( '' === $name ? 'default' : $name );
	}
}
