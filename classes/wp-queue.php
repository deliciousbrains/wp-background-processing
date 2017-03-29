<?php

if ( ! class_exists( 'WP_Queue' ) ) {
	class WP_Queue {

		/**
		 * @var wpdb
		 */
		protected $database;

		/**
		 * @var string
		 */
		protected $table;

		/**
		 * @var int
		 */
		protected $release_time = 60;

		/**
		 * @var int
		 */
		protected $max_attempts = 3;

		/**
		 * WP_Queue constructor
		 *
		 * @param wpdb $database
		 */
		public function __construct( wpdb $database ) {
			$this->database = $database;
			$this->table    = $database->prefix . 'queue';
		}

		/**
		 * Push a job onto the queue.
		 *
		 * @param WP_Job $job
		 * @param int    $delay
		 *
		 * @return false|int
		 */
		public function push( WP_Job $job, $delay = 0 ) {
			$data = array(
				'job'          => maybe_serialize( $job ),
				'available_at' => $this->datetime( $delay ),
				'created_at'   => $this->datetime(),
			);

			return $this->database->insert( $this->table, $data );
		}

		/**
		 * Release a job back onto the queue.
		 *
		 * @param mixed $job
		 * @param int   $delay
		 *
		 * @return false|int
		 */
		public function release( $job, $delay = 0 ) {
			$attempts = $job->attempts + 1;

			if ( $attempts >= $this->max_attempts ) {
				return $this->delete( $job );
			}

			$data = array(
				'attempts'     => $attempts,
				'locked'       => 0,
				'locked_at'    => null,
				'available_at' => $this->datetime( $delay ),
			);

			return $this->database->update( $this->table, $data, array( 'id' => $job->id ) );
		}

		/**
		 * Delete a job from the queue.
		 *
		 * @param mixed $job
		 *
		 * @return false|int
		 */
		public function delete( $job ) {
			$where = array(
				'id' => $job->id,
			);

			return $this->database->delete( $this->table, $where );
		}

		/**
		 * Get MySQL datetime.
		 *
		 * @param int $offset Seconds, can pass negative int.
		 *
		 * @return string
		 */
		protected function datetime( $offset = 0 ) {
			$timestamp = time() + $offset;

			return gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		/**
		 * Count available jobs.
		 *
		 * @return null|string
		 */
		public function available_jobs() {
			$sql = $this->database->prepare( "
				SELECT COUNT(*) FROM {$this->table}
				WHERE available_at <= %s", $this->datetime() );

			return $this->database->get_var( $sql );
		}

		/**
		 * Get next job.
		 */
		public function next_job() {
			$this->maybe_release_locked_jobs();

			$sql = $this->database->prepare( "
				SELECT * FROM {$this->table}
				WHERE locked = 0
				AND available_at <= %s", $this->datetime() );

			return $this->database->get_row( $sql );
		}

		/**
		 * Maybe release locked jobs.
		 *
		 * @return false|int
		 */
		protected function maybe_release_locked_jobs() {
			$expired = $this->datetime( -$this->release_time );

			$sql = $this->database->prepare( "
				UPDATE {$this->table}
				SET attempts = attempts + 1, locked = 0, locked_at = NULL
				WHERE locked = 1
				AND locked_at <= %s", $expired );

			return $this->database->query( $sql );
		}

		/**
		 * Lock a job.
		 *
		 * @param mixed $job
		 *
		 * @return false|int
		 */
		public function lock_job( $job ) {
			$data = array(
				'locked'    => 1,
				'locked_at' => $this->datetime(),
			);

			return $this->database->update( $this->table, $data, array( 'id' => $job->id ) );
		}
	}
}