<?php

if ( ! class_exists( 'WP_Worker' ) ) {
	abstract class WP_Worker {

		/**
		 * @var WP_Queue
		 */
		protected $queue;

		/**
		 * @var WP_Job
		 */
		protected $payload;

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
		 *
		 * @return bool
		 */
		public function process_next_job() {
			$job     = $this->queue->get_next_job();
			$queue_item    = unserialize( $job->job );
			$this->payload = $queue_item;

			$this->queue->lock_job( $job );

			try {
				$queue_item->handle();

				if ( $queue_item->is_released() ) {
					$this->queue->release( $job, $queue_item->get_delay() );
				} else {
					$this->queue->delete( $job );
				}
			} catch ( Exception $e ) {
				$this->queue->release( $job );

				return false;
			}

			return true;
		}

		/**
		 * Get job name.
		 *
		 * @return object
		 */
		public function get_job_name() {
			return get_class( $this->payload );
		}

	}
}