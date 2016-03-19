<?php

if ( ! class_exists( 'WP_Job' ) ) {
	abstract class WP_Job {

		/**
		 * @var WP_Queue
		 */
		protected $queue;

		/**
		 * WP_Job constructor.
		 */
		public function __construct() {
			$this->queue = WP_Queue::get_instance();
		}

		/**
		 * Push a job onto the queue.
		 *
		 * @param mixed $data
		 * @param int   $delay
		 */
		public function push( $data, $delay = 0 ) {
			$this->queue->push( get_class( $this ), $data, $delay );
		}

		/**
		 * Process.
		 *
		 * @param object $job
		 */
		public function process( $job ) {
			// Lock job to prevent multiple queue workers
			// processing the same job.
			$this->lock_job( $job );

			try {
				$this->handle( $job->data );
				// Delete from queue
			} catch ( Exception $e ) {
				// Release onto queue
			}
		}

		/**
		 * Lock job.
		 *
		 * @param object $job
		 */
		protected function lock_job( $job ) {
			$this->queue->lock_job( $job->id );
		}

		/**
		 * Handle the job.
		 *
		 * @param mixed $data
		 */
		abstract public function handle( $data );

	}
}