<?php

if ( ! class_exists( 'WP_Http_Worker' ) ) {
	class WP_Http_Worker extends WP_Worker {

		/**
		 * Has the worker been dispatched in this request?
		 *
		 * @var bool
		 */
		protected $dispatched = false;

		/**
		 * Timestamp of when this worker started processing the queue.
		 *
		 * @var int
		 */
		protected $start_time;

		/**
		 * WP_Http_Worker constructor
		 */
		public function __construct() {
			parent::__construct();

			// Cron health check
			add_action( 'http_worker_cron', array( $this, 'handle_cron' ) );
			add_filter( 'cron_schedules', array( $this, 'schedule_cron' ) );
			$this->maybe_schedule_cron();

			// Dispatch handlers
			add_action( 'wp_ajax_http_worker', array( $this, 'maybe_handle' ) );
			add_action( 'wp_ajax_nopriv_http_worker', array( $this, 'maybe_handle' ) );

			// Dispatch listener
			add_action( 'wp_queue_job_pushed', array( $this, 'maybe_dispatch_worker' ) );
		}

		/**
		 * Maybe handle
		 *
		 * Process the queue if no other HTTP worker is running and
		 * the current worker is within server memory and time limit constraints.
		 * Automatically dispatch another worker and kill the current process if
		 * jobs remain in the queue and server limits reached.
		 */
		public function maybe_handle() {
			check_ajax_referer( 'http_worker', 'nonce' );

			if ( $this->is_worker_running() ) {
				// Worker already running, die
				wp_die();
			}

			// Lock worker to prevent multiple instances spawning
			$this->lock_worker();

			// Loop over jobs while within server limits
			while ( ! $this->time_exceeded() && ! $this->memory_exceeded() ) {
				if ( $this->should_run() ) {
					$this->process_next_job();
				} else {
					break;
				}
			}

			// Unlock worker to allow another instance to be spawned
			$this->unlock_worker();

			if ( $this->queue->available_jobs() ) {
				// Job queue not empty, dispatch async worker request
				$this->dispatch();
			}

			wp_die();
		}

		/**
		 * Memory exceeded
		 *
		 * Ensures the worker process never exceeds 80%
		 * of the maximum allowed PHP memory.
		 *
		 * @return bool
		 */
		protected function memory_exceeded() {
			$memory_limit   = $this->get_memory_limit() * 0.8; // 80% of max memory
			$current_memory = memory_get_usage( true );
			$return         = false;

			if ( $current_memory >= $memory_limit ) {
				$return = true;
			}

			return apply_filters( 'http_worker_memory_exceeded', $return );
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
		 * Ensures the worker never exceeds a sensible time limit (20s by default).
		 * A timeout limit of 30s is common on shared hosting.
		 *
		 * @return bool
		 */
		protected function time_exceeded() {
			$finish = $this->start_time + apply_filters( 'http_worker_default_time_limit', 20 ); // 20 seconds
			$return = false;

			if ( time() >= $finish ) {
				$return = true;
			}

			return apply_filters( 'http_worker_time_exceeded', $return );
		}

		/**
		 * Maybe dispatch worker
		 *
		 * Dispatch a worker process if we haven't already in this request
		 * and no other HTTP workers are running.
		 *
		 * @param WP_Job $job
		 */
		public function maybe_dispatch_worker( $job ) {
			if ( $this->is_worker_running() ) {
				// HTTP worker already running, return
				return;
			}

			// Dispatch async worker request
			$this->dispatch();
		}

		/**
		 * Is worker running
		 *
		 * Check if another instance of the HTTP worker is running.
		 *
		 * @return bool
		 */
		protected function is_worker_running() {
			if ( get_site_transient( 'http_worker_lock' ) ) {
				// Process already running
				return true;
			}

			return false;
		}

		/**
		 * Lock worker
		 *
		 * Lock the HTTP worker to prevent multiple instances running.
		 */
		protected function lock_worker() {
			$this->start_time = time(); // Set start time of current worker

			$lock_duration = apply_filters( 'http_worker_lock_time', 60 ); // 60 seconds

			set_site_transient( 'http_worker_lock', microtime(), $lock_duration );
		}

		/**
		 * Unlock worker
		 *
		 * Unlock the HTTP worker to allow other instances to be spawned.
		 */
		protected function unlock_worker() {
			delete_site_transient( 'http_worker_lock' );
		}

		/**
		 * Dispatch
		 *
		 * Fire off a non-blocking async request if we haven't already
		 * in this request.
		 */
		protected function dispatch() {
			if ( $this->is_http_worker_disabled() ) {
				return;
			}

			if ( ! $this->dispatched ) {
				$this->async_request();
			}

			$this->dispatched = true;
		}

		/**
		 * Is HTTP worker disabled
		 *
		 * @return bool
		 */
		protected function is_http_worker_disabled() {
			if ( ! defined( 'DISABLE_WP_HTTP_WORKER' ) || true !== DISABLE_WP_HTTP_WORKER  ) {
				return false;
			}

			return true;
		}

		/**
		 * Async request
		 *
		 * Fire off a non-blocking request to admin-ajax.php.
		 *
		 * @return array|WP_Error
		 */
		protected function async_request() {
			$action = 'http_worker';

			$query_args = apply_filters( 'http_worker_query_args', array(
				'action' => $action,
				'nonce'  => wp_create_nonce( $action ),
			) );

			$query_url = apply_filters( 'http_worker_query_url', admin_url( 'admin-ajax.php' ) );

			$post_args = apply_filters( 'http_worker_post_args', array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'cookies'   => $_COOKIE,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			) );

			$url = add_query_arg( $query_args, $query_url );

			return wp_remote_post( esc_url_raw( $url ), $post_args );
		}

		/**
		 * Handle cron
		 *
		 * Restart the HTTP worker if not already running
		 * and data exists in the queue.
		 */
		public function handle_cron() {
			if ( $this->is_worker_running() ) {
				// Worker already running, die
				wp_die();
			}

			if ( ! $this->queue->available_jobs() ) {
				// No jobs on the queue to process, die
				wp_die();
			}

			$this->dispatch();

			exit;
		}

		/**
		 * Cron schedules
		 *
		 * @param $schedules
		 *
		 * @return mixed
		 */
		public function schedule_cron( $schedules ) {
			$interval = apply_filters( 'http_worker_cron_interval', 3 );

			// Adds every 3 minutes to the existing schedules.
			$schedules[ 'http_worker_cron_interval' ] = array(
				'interval' => MINUTE_IN_SECONDS * $interval,
				'display'  => sprintf( __( 'Every %d Minutes' ), $interval ),
			);

			return $schedules;
		}

		/**
		 * Maybe schedule cron
		 *
		 * Schedule health check cron if not disabled. Remove schedule if
		 * disabled and already scheduled.
		 */
		public function maybe_schedule_cron() {
			if ( $this->is_http_worker_disabled() ) {
				// Remove health check cron event, if scheduled
				$timestamp = wp_next_scheduled( 'http_worker_cron' );

				if ( wp_next_scheduled( 'http_worker_cron' ) ) {
					wp_unschedule_event( $timestamp, 'http_worker_cron' );
				}

				return;
			}

			if ( ! wp_next_scheduled( 'http_worker_cron' ) ) {
				// Schedule health check
				wp_schedule_event( time(), 'http_worker_cron_interval', 'http_worker_cron' );
			}
		}

	}
}