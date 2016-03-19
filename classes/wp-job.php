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
		 * @param mixed $job
		 * @param int   $delay
		 */
		public function push( $job, $delay = 0 ) {
			$this->queue->push( get_class( $this ), $job, $delay );
		}

		/**
		 * Release a job back onto the queue.
		 *
		 * @param int $delay
		 *
		 * @return stdClass
		 */
		protected function release( $delay = 0 ) {
			$job = new stdClass();
			$job->release = true;
			$job->delay = $delay;

			return $job;
		}

		/**
		 * Handle the job.
		 *
		 * @param mixed $data
		 */
		abstract public function handle( $data );

	}
}