<?php

class WP_Example_Request extends WP_Async_Request {

	/**
	 * @var string
	 */
	protected $action = 'example_request';

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
		// Spoof long running process
		sleep( 5 );

		error_log( $_POST['name'] );
	}

}