<?php
/**
 * WP Background Process
 *
 * @package WP-Background-Processing
 */

/**
 * Abstract WP_Background_Process class.
 *
 * @abstract
 * @extends WP_Async_Request
 */
abstract class WP_Background_Process extends WP_Async_Request {

	/**
	 * Action
	 *
	 * (default value: 'background_process')
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'background_process';

	/**
	 * Start time of current process.
	 *
	 * (default value: 0)
	 *
	 * @var int
	 * @access protected
	 */
	protected $start_time = 0;

	/**
	 * Cron_hook_identifier
	 *
	 * @var string
	 * @access protected
	 */
	protected $cron_hook_identifier;

	/**
	 * Cron_interval_identifier
	 *
	 * @var string
	 * @access protected
	 */
	protected $cron_interval_identifier;

	/**
	 * Restrict object instantiation when using unserialize.
	 *
	 * @var bool|array
	 */
	protected $allowed_batch_data_classes = true;

	/**
	 * The status set when process is cancelling.
	 *
	 * @var int
	 */
	const STATUS_CANCELLED = 1;

	/**
	 * The status set when process is paused or pausing.
	 *
	 * @var int;
	 */
	const STATUS_PAUSED = 2;

	/**
	 * Initiate new background process.
	 *
	 * @param bool|array $allowed_batch_data_classes Optional. Array of class names that can be unserialized. Default true (any class).
	 */
	public function __construct( $allowed_batch_data_classes = true ) {
		parent::__construct();

		if ( empty( $allowed_batch_data_classes ) && false !== $allowed_batch_data_classes ) {
			$allowed_batch_data_classes = true;
		}

		if ( ! is_bool( $allowed_batch_data_classes ) && ! is_array( $allowed_batch_data_classes ) ) {
			$allowed_batch_data_classes = true;
		}

		// If allowed_batch_data_classes property set in subclass,
		// only apply override if not allowing any class.
		if ( true === $this->allowed_batch_data_classes || true !== $allowed_batch_data_classes ) {
			$this->allowed_batch_data_classes = $allowed_batch_data_classes;
		}

		$this->cron_hook_identifier     = $this->identifier . '_cron';
		$this->cron_interval_identifier = $this->identifier . '_cron_interval';

		add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
		add_filter( 'cron_schedules', array( $this, 'schedule_cron_healthcheck' ) );
	}

	/**
	 * Schedule the cron healthcheck and dispatch an async request to start processing the queue.
	 *
	 * @access public
	 * @return array|WP_Error|false HTTP Response array, WP_Error on failure, or false if not attempted.
	 */
	public function dispatch() {
		if ( $this->is_processing() ) {
			// Process already running.
			return false;
		}

		// Schedule the cron healthcheck.
		$this->schedule_event();

		// Perform remote post.
		return parent::dispatch();
	}

	/**
	 * Push to the queue.
	 *
	 * Note, save must be called in order to persist queued items to a batch for processing.
	 *
	 * @param mixed $data Data.
	 *
	 * @return $this
	 */
	public function push_to_queue( $data ) {
		$this->data[] = $data;

		return $this;
	}

	/**
	 * Save the queued items for future processing.
	 *
	 * @return $this
	 */
	public function save() {
		$key = $this->generate_key();

		if ( ! empty( $this->data ) ) {
			update_site_option( $key, $this->data );
		}

		// Clean out data so that new data isn't prepended with closed session's data.
		$this->data = array();

		return $this;
	}

	/**
	 * Update a batch's queued items.
	 *
	 * @param string $key  Key.
	 * @param array  $data Data.
	 *
	 * @return $this
	 */
	public function update( $key, $data ) {
		if ( ! empty( $data ) ) {
			update_site_option( $key, $data );
		}

		return $this;
	}

	/**
	 * Delete a batch of queued items.
	 *
	 * @param string $key Key.
	 *
	 * @return $this
	 */
	public function delete( $key ) {
		delete_site_option( $key );

		return $this;
	}

	/**
	 * Delete entire job queue.
	 */
	public function delete_all() {
		$batches = $this->get_batches();

		foreach ( $batches as $batch ) {
			$this->delete( $batch->key );
		}

		delete_site_option( $this->get_status_key() );

		$this->cancelled();
	}

	/**
	 * Cancel job on next batch.
	 */
	public function cancel() {
		update_site_option( $this->get_status_key(), self::STATUS_CANCELLED );

		// Just in case the job was paused at the time.
		$this->dispatch();
	}

	/**
	 * Has the process been cancelled?
	 *
	 * @return bool
	 */
	public function is_cancelled() {
		$status = get_site_option( $this->get_status_key(), 0 );

		return absint( $status ) === self::STATUS_CANCELLED;
	}

	/**
	 * Called when background process has been cancelled.
	 */
	protected function cancelled() {
		do_action( $this->identifier . '_cancelled' );
	}

	/**
	 * Pause job on next batch.
	 */
	public function pause() {
		update_site_option( $this->get_status_key(), self::STATUS_PAUSED );
	}

	/**
	 * Is the job paused?
	 *
	 * @return bool
	 */
	public function is_paused() {
		$status = get_site_option( $this->get_status_key(), 0 );

		return absint( $status ) === self::STATUS_PAUSED;
	}

	/**
	 * Called when background process has been paused.
	 */
	protected function paused() {
		do_action( $this->identifier . '_paused' );
	}

	/**
	 * Resume job.
	 */
	public function resume() {
		delete_site_option( $this->get_status_key() );

		$this->schedule_event();
		$this->dispatch();
		$this->resumed();
	}

	/**
	 * Called when background process has been resumed.
	 */
	protected function resumed() {
		do_action( $this->identifier . '_resumed' );
	}

	/**
	 * Is queued?
	 *
	 * @return bool
	 */
	public function is_queued() {
		return ! $this->is_queue_empty();
	}

	/**
	 * Is the tool currently active, e.g. starting, working, paused or cleaning up?
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->is_queued() || $this->is_processing() || $this->is_paused() || $this->is_cancelled();
	}

	/**
	 * Generate key for a batch.
	 *
	 * Generates a unique key based on microtime. Queue items are
	 * given a unique key so that they can be merged upon save.
	 *
	 * @param int    $length Optional max length to trim key to, defaults to 64 characters.
	 * @param string $key    Optional string to append to identifier before hash, defaults to "batch".
	 *
	 * @return string
	 */
	protected function generate_key( $length = 64, $key = 'batch' ) {
		$unique  = md5( microtime() . wp_rand() );
		$prepend = $this->identifier . '_' . $key . '_';

		return substr( $prepend . $unique, 0, $length );
	}

	/**
	 * Get the status key.
	 *
	 * @return string
	 */
	protected function get_status_key() {
		return $this->identifier . '_status';
	}

	/**
	 * Maybe process a batch of queued items.
	 *
	 * Checks whether data exists within the queue and that
	 * the process is not already running.
	 */
	public function maybe_handle() {
		// Don't lock up other requests while processing.
		session_write_close();

		if ( $this->is_processing() ) {
			// Background process already running.
			return $this->maybe_wp_die();
		}

		if ( $this->is_cancelled() ) {
			$this->clear_scheduled_event();
			$this->delete_all();

			return $this->maybe_wp_die();
		}

		if ( $this->is_paused() ) {
			$this->clear_scheduled_event();
			$this->paused();

			return $this->maybe_wp_die();
		}

		if ( $this->is_queue_empty() ) {
			// No data to process.
			return $this->maybe_wp_die();
		}

		check_ajax_referer( $this->identifier, 'nonce' );

		$this->handle();

		return $this->maybe_wp_die();
	}

	/**
	 * Is queue empty?
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		return empty( $this->get_batch() );
	}

	/**
	 * Is process running?
	 *
	 * Check whether the current process is already running
	 * in a background process.
	 *
	 * @return bool
	 *
	 * @deprecated 1.1.0 Superseded.
	 * @see        is_processing()
	 */
	protected function is_process_running() {
		return $this->is_processing();
	}

	/**
	 * Is the background process currently running?
	 *
	 * @return bool
	 */
	public function is_processing() {
		if ( get_site_transient( $this->identifier . '_process_lock' ) ) {
			// Process already running.
			return true;
		}

		return false;
	}

	/**
	 * Lock process.
	 *
	 * Lock the process so that multiple instances can't run simultaneously.
	 * Override if applicable, but the duration should be greater than that
	 * defined in the time_exceeded() method.
	 */
	protected function lock_process() {
		$this->start_time = time(); // Set start time of current process.

		$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60; // 1 minute
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

		set_site_transient( $this->identifier . '_process_lock', microtime(), $lock_duration );
	}

	/**
	 * Unlock process.
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
	 * Get batch.
	 *
	 * @return stdClass Return the first batch of queued items.
	 */
	protected function get_batch() {
		return array_reduce(
			$this->get_batches( 1 ),
			static function ( $carry, $batch ) {
				return $batch;
			},
			array()
		);
	}

	/**
	 * Get batches.
	 *
	 * @param int $limit Number of batches to return, defaults to all.
	 *
	 * @return array of stdClass
	 */
	public function get_batches( $limit = 0 ) {
		global $wpdb;

		if ( empty( $limit ) || ! is_int( $limit ) ) {
			$limit = 0;
		}

		$table        = $wpdb->options;
		$column       = 'option_name';
		$key_column   = 'option_id';
		$value_column = 'option_value';

		if ( is_multisite() ) {
			$table        = $wpdb->sitemeta;
			$column       = 'meta_key';
			$key_column   = 'meta_id';
			$value_column = 'meta_value';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$sql = '
			SELECT *
			FROM ' . $table . '
			WHERE ' . $column . ' LIKE %s
			ORDER BY ' . $key_column . ' ASC
			';

		$args = array( $key );

		if ( ! empty( $limit ) ) {
			$sql .= ' LIMIT %d';

			$args[] = $limit;
		}

		$items = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$batches = array();

		if ( ! empty( $items ) ) {
			$allowed_classes = $this->allowed_batch_data_classes;

			$batches = array_map(
				static function ( $item ) use ( $column, $value_column, $allowed_classes ) {
					$batch       = new stdClass();
					$batch->key  = $item->{$column};
					$batch->data = static::maybe_unserialize( $item->{$value_column}, $allowed_classes );

					return $batch;
				},
				$items
			);
		}

		return $batches;
	}

	/**
	 * Handle a dispatched request.
	 *
	 * Pass each queue item to the task handler, while remaining
	 * within server memory and time limit constraints.
	 */
	protected function handle() {
		$this->lock_process();

		/**
		 * Number of seconds to sleep between batches. Defaults to 0 seconds, minimum 0.
		 *
		 * @param int $seconds
		 */
		$throttle_seconds = max(
			0,
			apply_filters(
				$this->identifier . '_seconds_between_batches',
				apply_filters(
					$this->prefix . '_seconds_between_batches',
					0
				)
			)
		);

		do {
			$batch = $this->get_batch();

			foreach ( $batch->data as $key => $value ) {
				$task = $this->task( $value );

				if ( false !== $task ) {
					$batch->data[ $key ] = $task;
				} else {
					unset( $batch->data[ $key ] );
				}

				// Keep the batch up to date while processing it.
				if ( ! empty( $batch->data ) ) {
					$this->update( $batch->key, $batch->data );
				}

				// Let the server breathe a little.
				sleep( $throttle_seconds );

				// Batch limits reached, or pause or cancel request.
				if ( $this->time_exceeded() || $this->memory_exceeded() || $this->is_paused() || $this->is_cancelled() ) {
					break;
				}
			}

			// Delete current batch if fully processed.
			if ( empty( $batch->data ) ) {
				$this->delete( $batch->key );
			}
		} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() && ! $this->is_paused() && ! $this->is_cancelled() );

		$this->unlock_process();

		// Start next batch or complete process.
		if ( ! $this->is_queue_empty() ) {
			$this->dispatch();
		} else {
			$this->complete();
		}

		return $this->maybe_wp_die();
	}

	/**
	 * Memory exceeded?
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		return apply_filters( $this->identifier . '_memory_exceeded', $return );
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return wp_convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Time limit exceeded?
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
	 * Complete processing.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		delete_site_option( $this->get_status_key() );

		// Remove the cron healthcheck job from the cron schedule.
		$this->clear_scheduled_event();

		$this->completed();
	}

	/**
	 * Called when background process has completed.
	 */
	protected function completed() {
		do_action( $this->identifier . '_completed' );
	}

	/**
	 * Get the cron healthcheck interval in minutes.
	 *
	 * Default is 5 minutes, minimum is 1 minute.
	 *
	 * @return int
	 */
	public function get_cron_interval() {
		$interval = 5;

		if ( property_exists( $this, 'cron_interval' ) ) {
			$interval = $this->cron_interval;
		}

		$interval = apply_filters( $this->cron_interval_identifier, $interval );

		return is_int( $interval ) && 0 < $interval ? $interval : 5;
	}

	/**
	 * Schedule the cron healthcheck job.
	 *
	 * @access public
	 *
	 * @param mixed $schedules Schedules.
	 *
	 * @return mixed
	 */
	public function schedule_cron_healthcheck( $schedules ) {
		$interval = $this->get_cron_interval();

		if ( 1 === $interval ) {
			$display = __( 'Every Minute' );
		} else {
			$display = sprintf( __( 'Every %d Minutes' ), $interval );
		}

		// Adds an "Every NNN Minute(s)" schedule to the existing cron schedules.
		$schedules[ $this->cron_interval_identifier ] = array(
			'interval' => MINUTE_IN_SECONDS * $interval,
			'display'  => $display,
		);

		return $schedules;
	}

	/**
	 * Handle cron healthcheck event.
	 *
	 * Restart the background process if not already running
	 * and data exists in the queue.
	 */
	public function handle_cron_healthcheck() {
		if ( $this->is_processing() ) {
			// Background process already running.
			exit;
		}

		if ( $this->is_queue_empty() ) {
			// No data to process.
			$this->clear_scheduled_event();
			exit;
		}

		$this->dispatch();
	}

	/**
	 * Schedule the cron healthcheck event.
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event( time() + ( $this->get_cron_interval() * MINUTE_IN_SECONDS ), $this->cron_interval_identifier, $this->cron_hook_identifier );
		}
	}

	/**
	 * Clear scheduled cron healthcheck event.
	 */
	protected function clear_scheduled_event() {
		$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
		}
	}

	/**
	 * Cancel the background process.
	 *
	 * Stop processing queue items, clear cron job and delete batch.
	 *
	 * @deprecated 1.1.0 Superseded.
	 * @see        cancel()
	 */
	public function cancel_process() {
		$this->cancel();
	}

	/**
	 * Perform task with queued item.
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over.
	 *
	 * @return mixed
	 */
	abstract protected function task( $item );

	/**
	 * Maybe unserialize data, but not if an object.
	 *
	 * @param mixed      $data            Data to be unserialized.
	 * @param bool|array $allowed_classes Array of class names that can be unserialized.
	 *
	 * @return mixed
	 */
	protected static function maybe_unserialize( $data, $allowed_classes ) {
		if ( is_serialized( $data ) ) {
			$options = array();
			if ( is_bool( $allowed_classes ) || is_array( $allowed_classes ) ) {
				$options['allowed_classes'] = $allowed_classes;
			}

			return @unserialize( $data, $options ); // @phpcs:ignore
		}

		return $data;
	}
}
