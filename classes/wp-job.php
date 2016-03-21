<?php

if ( ! class_exists( 'WP_Job' ) ) {
	abstract class WP_Job {

		/**
		 * @var bool
		 */
		protected $released = false;

		/**
		 * @var int
		 */
		protected $delay = 0;

		/**
		 * Release a job back onto the queue
		 *
		 * @param int $delay
		 */
		protected function release( $delay = 0 ) {
			$this->released = true;
			$this->delay    = $delay;
		}

		/**
		 * Is released.
		 *
		 * @return bool
		 */
		public function is_released() {
			return $this->released;
		}

		/**
		 * Get delay.
		 *
		 * @return int
		 */
		public function get_delay() {
			return $this->delay;
		}

		/**
		 * Handle the job.
		 */
		abstract public function handle();

	}
}