<?php

if ( ! class_exists( 'WP_Worker' ) ) {
	class WP_Worker {

		/**
		 * @var WP_Queue_Interface
		 */
		protected $queue;

		/**
		 * @var WP_Job
		 */
		protected $job;

		/**
		 * WP_Worker constructor.
		 *
		 * @param WP_Queue_Interface $queue
		 */
		public function __construct( $queue ) {
			$this->queue = $queue;
		}

		/**
		 * Process next job.
		 *
		 * @return bool
		 */
		public function process_next_job() {
			$raw_job = $this->queue->next_job();

			if ( is_null( $raw_job ) ) {
				return null;
			}

			$this->job = $this->queue->build_job( $raw_job );

			try {
				$this->job->handle();
			} catch ( Exception $e ) {
				$this->queue->release( $raw_job, $this->job );

				return false;
			}

			if ( $this->job->is_released() ) {
				$this->queue->release( $raw_job, $this->job, $this->job->release_delay() );
			} else {
				$this->queue->delete( $raw_job );
			}

			return true;
		}

		/**
		 * Get job name.
		 *
		 * @return string
		 */
		public function get_job_name() {
			return get_class( $this->job );
		}

	}
}