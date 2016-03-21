<?php

if ( ! class_exists( 'WP_Worker' ) ) {
	abstract class WP_Worker {

		/**
		 * @var WP_Queue
		 */
		protected $queue;

		/**
		 * WP_Worker constructor.
		 */
		public function __construct() {
			$this->queue = WP_Queue::get_instance();
		}

		/**
		 * Should run
		 *
		 * @return bool
		 */
		public function should_run() {
			if ( $this->queue->available_jobs() ) {
				return true;
			}

			return false;
		}

		/**
		 * Process next job.
		 */
		public function process_next_job() {
			$job        = $this->queue->get_next_job();
			$queue_item = unserialize( $job->job );

			$this->queue->lock_job( $job );

			try {
				$queue_item->handle();

				if ( $queue_item->is_released() ) {
					$this->queue->release( $job, $queue_item->get_delay() );
				} else {
					$this->queue->delete( $job );
				}
			} catch ( Exception $e ) {
				error_log( 'Error!' );
			}
		}

	}
}