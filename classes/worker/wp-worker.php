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
			$this->queue->process_next_job();
		}

	}
}