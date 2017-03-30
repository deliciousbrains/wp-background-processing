<?php

if ( ! class_exists( 'WP_Cli_Worker' ) ) {
	class WP_Cli_Worker extends WP_Worker {

		/**
		 * Listen for jobs.
		 */
		public function listen() {
			WP_CLI::log( 'Listening for queue jobs...' );

			while ( true ) {
				$process = $this->process_next_job();

				if ( true === $process ) {
					WP_CLI::success( 'Processed: ' . $this->get_job_name() );

					continue;
				}

				if ( false === $process ) {
					WP_CLI::warning( 'Failed: ' . $this->get_job_name() );

					continue;
				}

				sleep( 5 );
			}
		}

		/**
		 * Run a single job.
		 */
		public function work() {
			$process = $this->process_next_job();

			if ( true === $process ) {
				WP_CLI::success( 'Processed: ' . $this->get_job_name() );

				return;
			}

			if ( false === $process ) {
				WP_CLI::warning( 'Failed: ' . $this->get_job_name() );

				return;
			}

			WP_CLI::log( 'No jobs to process...' );
		}

	}
}