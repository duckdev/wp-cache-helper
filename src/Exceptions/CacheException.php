<?php
/**
 * Base library exception.
 *
 * Thrown for programmer-error cases such as constructing a
 * {@see \DuckDev\Cache\Support\KeyPrefixer} with an empty prefix.
 * Runtime cache misses are signalled with return values, not
 * exceptions.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      2.0.0
 * @package    Cache
 * @subpackage Exceptions
 */

namespace DuckDev\Cache\Exceptions;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use Exception;

/**
 * Class CacheException.
 */
class CacheException extends Exception {
}
