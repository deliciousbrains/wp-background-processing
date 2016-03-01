<?php

if ( ! class_exists( 'WP_Background_Process' ) ) {
	abstract class WP_Background_Process extends WP_Async_Request {

		/**
		 * @var string
		 */
		protected $action = 'background_process';

		/**
		 * Start time of current process
		 *
		 * @var int
		 */
		protected $start_time = 0;

		/**
		 * @var string
		 */
		protected $cron_hook_identifier;

		/**
		 * @var string
		 */
		protected $cron_interval_identifier;

		/**
		 * @var object
		 */
		protected $current_job;

		/**
		 * Time in seconds to release locked jobs back onto the queue.
		 * This allows failed jobs to be attempted again, if the
		 * queue worker timed out before releasing the job.
		 *
		 * @var int|false
		 */
		protected $release_time = 60;

		/**
		 * Initiate new background process
		 */
		public function __construct() {
			parent::__construct();

			$this->cron_hook_identifier     = $this->identifier . '_cron';
			$this->cron_interval_identifier = $this->identifier . '_cron_interval';

			add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
			add_filter( 'cron_schedules', array( $this, 'schedule_cron_healthcheck' ) );
		}

		/**
		 * Dispatch
		 *
		 * @return array|WP_Error
		 */
		public function dispatch() {
			// Schedule the cron healthcheck
			$this->schedule_event();

			// Perform remote post
			parent::dispatch();
		}

		/**
		 * Push to queue
		 *
		 * @param mixed $job
		 *
		 * @return $this
		 */
		public function push_to_queue( $job ) {
			global $wpdb;

			$table = $wpdb->prefix . 'queue';
			$data  = array(
				'action'     => $this->action,
				'data'       => maybe_serialize( $job ),
				'created_at' => current_time( 'mysql', true ),
			);

			$wpdb->insert( $table, $data );

			return $this;
		}

		/**
		 * Update queue
		 *
		 * @param object $data
		 *
		 * @return $this
		 */
		public function update( $data ) {
			if ( ! empty( $data ) ) {
//				update_site_option( $key, $data );
			}

			return $this;
		}

		/**
		 * Delete queue
		 *
		 * @param object $job
		 *
		 * @return $this
		 */
		public function delete( $job ) {
			global $wpdb;

			$table = $wpdb->prefix . 'queue';
			$where = array(
				'id' => $job->id,
			);

			$wpdb->delete( $table, $where );

			return $this;
		}

		/**
		 * Maybe process queue
		 *
		 * Checks whether data exists within the queue and that
		 * the process is not already running.
		 */
		public function maybe_handle() {
			if ( $this->is_process_running() ) {
				// Background process already running
				wp_die();
			}

			if ( $this->is_queue_empty() ) {
				// No data to process
				wp_die();
			}

			check_ajax_referer( $this->identifier, 'nonce' );

			$this->handle();

			wp_die();
		}

		/**
		 * Is queue empty
		 *
		 * @return bool
		 */
		protected function is_queue_empty() {
			global $wpdb;

			$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}queue
					WHERE locked = 0";

			$jobs = $wpdb->get_var( $sql );

			return ( $jobs > 0 ) ? false : true;
		}

		/**
		 * Is process running
		 *
		 * Check whether the current process is already running
		 * in a background process.
		 */
		protected function is_process_running() {
			if ( get_site_transient( $this->identifier . '_process_lock' ) ) {
				// Process already running
				return true;
			}

			return false;
		}

		/**
		 * Lock process
		 *
		 * Lock the process so that multiple instances can't run simultaneously.
		 * Override if applicable, but the duration should be greater than that
		 * defined in the time_exceeded() method.
		 */
		protected function lock_process() {
			$this->start_time = time(); // Set start time of current process

			$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60; // 1 minute
			$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

			set_site_transient( $this->identifier . '_process_lock', microtime(), $lock_duration );
		}

		/**
		 * Unlock process
		 *
		 * Unlock the process so that other instances can spawn.
		 *
		 * @return $this
		 */
		protected function unlock_process() {
			delete_site_transient( $this->identifier . '_process_lock' );

			return $this;
		}

		/**
		 * Get batch
		 *
		 * @return stdClass Return the first batch from the queue
		 */
		protected function get_job() {
			global $wpdb;

			$this->maybe_release_locked_jobs();

			$sql = "SELECT * FROM {$wpdb->prefix}queue
					WHERE locked = 0
					ORDER BY created_at ASC";

			$job = $wpdb->get_row( $sql );

			if ( ! empty( $job ) ) {
				$job->data = maybe_unserialize( $job->data );
			} else {
				$job = false;
			}

			$this->current_job = $job;

			return $job;
		}

		/**
		 * Handle
		 *
		 * Pass each queue item to the task handler, while remaining
		 * within server memory and time limit constraints.
		 */
		protected function handle() {
			$this->lock_process();

			do {
				$job = $this->get_job();

				$this->lock_job( $job );

				try {
					$this->task( $job->data );
				} catch ( Exception $e ) {
					// Release job onto queue
					break;
				}

				$this->delete( $job );
			} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() );

			$this->unlock_process();

			// Start next batch or complete process
			if ( ! $this->is_queue_empty() ) {
				$this->dispatch();
			} else {
				$this->complete();
			}

			wp_die();
		}

		/**
		 * Lock job
		 *
		 * @param object $job
		 *
		 * @return $this
		 */
		protected function lock_job( $job ) {
			global $wpdb;

			$table = $wpdb->prefix . 'queue';
			$data  = array(
				'locked'    => 1,
				'locked_at' => current_time( 'mysql', true ),
			);
			$where = array(
				'id' => $job->id,
			);

			$wpdb->update( $table, $data, $where );

			return $this;
		}

		/**
		 * Maybe release locked jobs.
		 */
		protected function maybe_release_locked_jobs()
		{
			if ( false === $this->release_time ) {
				return;
			}

			global $wpdb;

			$expired = gmdate( 'Y-m-d H:i:s', time() - $this->release_time );

			$sql = $wpdb->prepare( "
				UPDATE {$wpdb->prefix}queue
				SET attempts = attempts + 1, locked = 0, locked_at = NULL
				WHERE locked = 1
				AND locked_at <= %s"
			, $expired );

			$wpdb->query( $sql );
		}

		/**
		 * Memory exceeded
		 *
		 * Ensures the batch process never exceeds 90%
		 * of the maximum WordPress memory.
		 *
		 * @return bool
		 */
		protected function memory_exceeded() {
			$memory_limit   =  $this->get_memory_limit() * 0.9; // 90% of max memory
			$current_memory = memory_get_usage( true );
			$return         = false;

			if ( $current_memory >= $memory_limit ) {
				$return = true;
			}

			return apply_filters( $this->identifier . '_memory_exceeded', $return );
		}

		/**
		 * Get memory limit
		 *
		 * @return int
		 */
		protected function get_memory_limit() {
			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				// Sensible default
				$memory_limit = '128M';
			}

			if ( ! $memory_limit || -1 == $memory_limit ) {
				// Unlimited, set to 32GB
				$memory_limit = '32000M';
			}

			return intval( $memory_limit ) * 1024 * 1024;
		}

		/**
		 * Time exceeded
		 *
		 * Ensures the batch never exceeds a sensible time limit.
		 * A timeout limit of 30s is common on shared hosting.
		 *
		 * @return bool
		 */
		protected function time_exceeded() {
			$finish = $this->start_time + apply_filters( $this->identifier . '_default_time_limit', 20 ); // 20 seconds
			$return = false;

			if ( time() >= $finish ) {
				$return = true;
			}

			return apply_filters( $this->identifier . '_time_exceeded', $return );
		}

		/**
		 * Complete
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
		protected function complete() {
			// Unschedule the cron healthcheck
			$this->clear_scheduled_event();
		}

		/**
		 * Schedule cron healthcheck
		 *
		 * @param $schedules
		 *
		 * @return mixed
		 */
		public function schedule_cron_healthcheck( $schedules ) {
			$interval = apply_filters( $this->identifier . '_cron_interval', 5 );

			if ( property_exists( $this, 'cron_interval' ) ) {
				$interval = apply_filters( $this->identifier . '_cron_interval', $this->cron_interval_identifier );
			}

			// Adds every 5 minutes to the existing schedules.
			$schedules[ $this->identifier . '_cron_interval' ] = array(
				'interval' => MINUTE_IN_SECONDS * $interval,
				'display'  => sprintf( __( 'Every %d Minutes' ), $interval ),
			);

			return $schedules;
		}

		/**
		 * Handle cron healthcheck
		 *
		 * Restart the background process if not already running
		 * and data exists in the queue.
		 */
		public function handle_cron_healthcheck() {
			if ( $this->is_process_running() ) {
				// Background process already running
				exit;
			}

			if ( $this->is_queue_empty() ) {
				// No data to process
				$this->clear_scheduled_event();
				exit;
			}

			$this->handle();

			exit;
		}

		/**
		 * Schedule event
		 */
		protected function schedule_event() {
			if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
				wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier );
			}
		}

		/**
		 * Clear scheduled event
		 */
		protected function clear_scheduled_event() {
			$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
			}
		}

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @param mixed $item Queue item to iterate over
		 *
		 * @return mixed
		 */
		abstract protected function task( $item );

	}
}