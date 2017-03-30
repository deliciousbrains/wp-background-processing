<?php

if ( ! interface_exists( 'WP_Queue_Interface' ) ) {

	interface WP_Queue_Interface {

		/**
		 * Push a job onto the queue.
		 *
		 * @param WP_Job $job
		 * @param int    $delay
		 *
		 * @return bool
		 */
		public function push( WP_Job $job, $delay = 0 );

		/**
		 * Push a raw job back onto the queue.
		 *
		 * @param mixed  $raw_job
		 * @param WP_Job $job
		 * @param int    $delay
		 *
		 * @return bool
		 */
		public function release( $raw_job, WP_Job $job, $delay = 0 );

		/**
		 * Delete a job from the queue.
		 *
		 * @param mixed $raw_job
		 *
		 * @return bool
		 */
		public function delete( $raw_job );

		/**
		 * Count available jobs.
		 *
		 * @return int
		 */
		public function available_jobs();

		/**
		 * Get next available job from the queue.
		 *
		 * @return mixed|null
		 */
		public function next_job();

		/**
		 * Build WP_Job from raw job.
		 *
		 * @param $raw_job
		 *
		 * @return WP_Job
		 */
		public function build_job( $raw_job );

		/**
		 * Mark a job as failed.
		 *
		 * @param mixed  $raw_job
		 * @param WP_Job $job
		 *
		 * @return bool
		 */
		public function fail( $raw_job, WP_Job $job );

		/**
		 * Count failed jobs.
		 *
		 * @return int
		 */
		public function failed_jobs();

		/**
		 * Restart failed jobs.
		 *
		 * @return int
		 */
		public function restart_failed_jobs();

	}

}