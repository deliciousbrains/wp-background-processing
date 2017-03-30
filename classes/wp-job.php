<?php

if ( ! class_exists( 'WP_Job' ) ) {
	abstract class WP_Job {

		/**
		 * @var int
		 */
		private $attempts = 0;

		/**
		 * @var bool|int
		 */
		private $released = false;

		/**
		 * @var int
		 */
		private $release_delay = 0;

		/**
		 * Release a job back onto the queue.
		 *
		 * @param int $delay
		 */
		protected function release( $delay = 0 ) {
			$this->released      = true;
			$this->release_delay = $delay;
		}

		/**
		 * Is the job released?
		 *
		 * @return bool|int
		 */
		public function is_released() {
			return $this->released;
		}

		/**
		 * Get release delay.
		 *
		 * @return int
		 */
		public function release_delay() {
			return $this->release_delay;
		}

		/**
		 * Get job attempts.
		 *
		 * @return int
		 */
		public function attempts() {
			return $this->attempts;
		}

		/**
		 * Set job attempts.
		 *
		 * @param int $attempts
		 */
		public function set_attempts( $attempts ) {
			$this->attempts = $attempts;
		}

		/**
		 * Determine which properties should be serialized.
		 *
		 * @return array
		 */
		public function __sleep() {
			$properties = get_object_vars( $this );

			unset( $properties['attempts'], $properties['released'], $properties['release_delay'] );

			return array_keys( $properties );
		}

		/**
		 * Handle the job.
		 */
		abstract public function handle();

	}
}