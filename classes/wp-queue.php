<?php

if ( ! class_exists( 'WP_Queue' ) ) {
	class WP_Queue {

		/**
		 * @var string
		 */
		public $table;

		/**
		 * @var string
		 */
		public $failed_table;

		/**
		 * @var int
		 */
		public $release_time = 60;

		/**
		 * WP_Queue constructor
		 */
		public function __construct() {
			global $wpdb;

			$this->table        = $wpdb->prefix . 'queue';
			$this->failed_table = $wpdb->prefix . 'failed_jobs';
		}

		/**
		 * Push a job onto the queue.
		 *
		 * @param WP_Job $job
		 * @param int    $delay
		 *
		 * @return $this
		 */
		public function push( WP_Job $job, $delay = 0 ) {
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
			if ( $job->attempts >= 3 ) {
				$this->failed( $job );

				return;
			}

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
		 * Failed
		 *
		 * @param stdClass $job
		 */
		protected function failed( $job ) {
			global $wpdb;

			$wpdb->insert( $this->failed_table, array(
				'job'       => $job->job,
				'failed_at' => $this->datetime(),
			) );

			$payload = unserialize($job->job);

			if (method_exists($payload, 'failed')) {
				$payload->failed();
			}

			$this->delete( $job );
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
		 * Available jobs.
		 */
		public function failed_jobs() {
			global $wpdb;

			return $wpdb->get_var( "SELECT COUNT(*) FROM {$this->failed_table}" );
		}

		/**
		 * Restart failed jobs.
		 */
		public function restart_failed_jobs() {
			global $wpdb;

			$count = 0;
			$jobs  = $wpdb->get_results( "SELECT * FROM {$this->failed_table}" );

			foreach ( $jobs as $job ) {
				$this->push( maybe_unserialize( $job->job ) );
				$wpdb->delete( $this->failed_table, array(
					'id' => $job->id,
				) );

				$count++;
			}

			return $count;
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