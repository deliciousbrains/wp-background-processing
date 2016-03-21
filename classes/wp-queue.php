<?php

if ( ! class_exists( 'WP_Queue' ) ) {
	class WP_Queue {

		/**
		 * @var WP_Queue
		 */
		protected static $instance;

		/**
		 * @var string
		 */
		public $table;

		/**
		 * @var int
		 */
		public $release_time = 60;

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * class via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			// Singleton
		}

		/**
		 * As this class is a singleton it should not be clone-able.
		 */
		protected function __clone() {
			// Singleton
		}

		/**
		 * As this class is a singleton it should not be able to be unserialized.
		 */
		protected function __wakeup() {
			// Singleton
		}

		/**
		 * Make this class a singleton.
		 *
		 * Use this instead of __construct()
		 *
		 * @return WP_Queue
		 */
		public static function get_instance() {
			if ( ! isset( static::$instance ) && ! ( self::$instance instanceof WP_Queue ) ) {
				static::$instance = new WP_Queue();

				static::$instance->init();
			}

			return static::$instance;
		}

		/**
		 * Init WP_Queue.
		 */
		protected function init() {
			global $wpdb;

			$this->table = $wpdb->prefix . 'queue';
		}

		/**
		 * Push a job onto the queue.
		 *
		 * @param WP_Job $job
		 * @param int    $delay
		 *
		 * @return $this
		 */
		public function push( WP_Job $job, $delay ) {
			global $wpdb;

			$data = array(
				'job'          => maybe_serialize( $job ),
				'available_at' => $this->datetime( $delay ),
				'created_at'   => $this->datetime(),
			);

			$wpdb->insert( $this->table, $data );

			return $this;
		}

		/**
		 * Release.
		 *
		 * @param object $job
		 * @param int    $delay
		 */
		public function release( $job, $delay = 0 ) {
			global $wpdb;

			$data = array(
				'attempts'     => $job->attempts + 1,
				'locked'       => 0,
				'locked_at'    => null,
				'available_at' => $this->datetime( $delay ),
			);
			$where = array(
				'id' => $job->id,
			);

			$wpdb->update( $this->table, $data, $where );
		}

		/**
		 * Delete.
		 *
		 * @param object $job
		 */
		public function delete( $job ) {
			global $wpdb;

			$where = array(
				'id' => $job->id,
			);

			$wpdb->delete( $this->table, $where );
		}

		/**
		 * Get MySQL datetime.
		 *
		 * @param int $offset Seconds, can pass negative int.
		 *
		 * @return string
		 */
		protected function datetime($offset = 0) {
			$timestamp = time() + $offset;

			return gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		/**
		 * Available jobs.
		 */
		public function available_jobs() {
			global $wpdb;

			$now = $this->datetime();
			$sql = $wpdb->prepare( "
				SELECT COUNT(*) FROM {$this->table}
				WHERE available_at <= %s"
			, $now );

			return $wpdb->get_var( $sql );
		}

		/**
		 * Get next job.
		 */
		public function get_next_job() {
			global $wpdb;

			$this->maybe_release_locked_jobs();

			$now = $this->datetime();
			$sql = $wpdb->prepare( "
				SELECT * FROM {$this->table}
				WHERE locked = 0
				AND available_at <= %s"
			, $now );

			return $wpdb->get_row( $sql );
		}

		/**
		 * Maybe release locked jobs.
		 */
		protected function maybe_release_locked_jobs() {
			global $wpdb;

			$expired = $this->datetime( - $this->release_time );

			$sql = $wpdb->prepare( "
				UPDATE {$this->table}
				SET attempts = attempts + 1, locked = 0, locked_at = NULL
				WHERE locked = 1
				AND locked_at <= %s"
			, $expired );

			$wpdb->query( $sql );
		}

		/**
		 * Lock job.
		 *
		 * @param object $job
		 */
		public function lock_job( $job ) {
			global $wpdb;

			$data  = array(
				'locked'    => 1,
				'locked_at' => $this->datetime(),
			);
			$where = array(
				'id' => $job->id,
			);

			$wpdb->update( $this->table, $data, $where );
		}
	}
}