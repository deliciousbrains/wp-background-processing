<?php

if ( ! class_exists( 'WP_Worker' ) ) {
	class WP_Worker {

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
		 *
		 * @param WP_Queue $queue
		 */
		public function __construct( $queue ) {
			$this->queue = $queue;
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
			$job           = $this->queue->get_next_job();
			$this->payload = unserialize( $job->job );

			$this->queue->lock_job( $job );
			$this->payload->set_job( $job );

			try {
				$this->payload->handle();

				if ( $this->payload->is_released() ) {
					// Job manually released, release back onto queue
					$this->queue->release( $job, $this->payload->get_delay() );
				}

				if ( $this->payload->is_deleted() ) {
					// Job manually deleted, delete from queue
					$this->queue->delete( $job );
				}

				if ( ! $this->payload->is_deleted_or_released() ) {
					// Job completed, delete from queue
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