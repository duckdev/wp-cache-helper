<?php
/**
 * Minimal WP_Error stub for the test suite.
 *
 * @package DuckDev\Cache\Tests
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		public $errors = array();

		public function __construct( $code = '', $message = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][] = $message;
			}
		}
	}
}
