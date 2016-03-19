<?php

if ( ! class_exists( 'WP_Http_Worker' ) ) {
	class WP_Http_Worker extends WP_Worker {

		/**
		 * WP_Http_Worker constructor.
		 */
		public function __construct() {
			parent::__construct();

			add_filter( 'query', array( $this, 'push_listener' ) );
		}

		/**
		 * Push listener.
		 *
		 * @param string $query
		 *
		 * @return string
		 */
		public function push_listener( $query ) {
			if ( false !== strpos( $query, 'INSERT INTO `' . $this->queue->table . '`' ) ) {
				error_log( $query );
			}

			return $query;
		}

	}
}