<?php

if ( ! class_exists( 'WP_Job' ) ) {
	abstract class WP_Job {

		/**
		 * @var WP_Queue
		 */
		protected $queue;

		/**
		 * @var object
		 */
		protected $job;

		/**
		 * @var bool
		 */
		protected $released = false;

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
		 * Release a job back onto the queue
		 *
		 * @param mixed $data
		 * @param int   $delay
		 */
		public function release( $data = false, $delay = 0 ) {
			$this->released = true;

			if ( false === $data ) {
				// Pass original data back to queue
				$data = $this->job->data;
			}

			$this->queue->release( $this->job, $data, $delay );
		}

		/**
		 * Delete
		 */
		public function delete() {
			$this->queue->delete( $this->job );
		}

		/**
		 * Process.
		 *
		 * @param object $job
		 */
		public function process( $job ) {
			$this->job = $job;

			// Lock job to prevent multiple queue workers
			// processing the same job.
			$this->lock_job( $job );

			try {
				$this->handle( $job->data );

				if ( false === $this->released ) {
					$this->delete();
				}
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