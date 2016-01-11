<?php

class WP_Example_Request extends WP_Async_Request {

	use WP_Example_Logger;

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
		$message = $this->get_message( $_POST['name'] );

		$this->really_long_running_task();
		$this->log( $message );
	}

}