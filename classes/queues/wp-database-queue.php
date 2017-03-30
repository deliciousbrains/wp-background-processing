<?php

if ( ! class_exists( 'WP_Database_Queue' ) ) {
	class WP_Database_Queue implements WP_Queue_Interface {

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
		 * WP_Database_Queue constructor.
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
		 * @return bool
		 */
		public function push( WP_Job $job, $delay = 0 ) {
			$data = array(
				'job'          => maybe_serialize( $job ),
				'available_at' => $this->datetime( $delay ),
				'created_at'   => $this->datetime(),
			);

			if ( $this->database->insert( $this->table, $data ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Push a raw job back onto the queue.
		 *
		 * @param mixed  $raw_job
		 * @param WP_Job $job
		 * @param int    $delay
		 *
		 * @return bool
		 */
		public function release( $raw_job, WP_Job $job, $delay = 0 ) {
			$attempts = $raw_job->attempts + 1;

			if ( $attempts >= $this->max_attempts ) {
				return $this->delete( $raw_job );
			}

			$data = array(
				'job'          => maybe_serialize( $job ),
				'attempts'     => $attempts,
				'locked'       => 0,
				'locked_at'    => null,
				'available_at' => $this->datetime( $delay ),
			);

			if ( $this->database->update( $this->table, $data, array( 'id' => $raw_job->id ) ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Delete a job from the queue.
		 *
		 * @param mixed $raw_job
		 *
		 * @return bool
		 */
		public function delete( $raw_job ) {
			$where = array(
				'id' => $raw_job->id,
			);

			if ( $this->database->delete( $this->table, $where ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Count available jobs.
		 *
		 * @return int
		 */
		public function available_jobs() {
			$sql = $this->database->prepare( "
				SELECT COUNT(*) FROM {$this->table}
				WHERE available_at <= %s", $this->datetime() );

			return (int) $this->database->get_var( $sql );
		}

		/**
		 * Get next available job from the queue.
		 *
		 * @return mixed
		 */
		public function next_job() {
			$this->maybe_release_locked_jobs();

			$sql = $this->database->prepare( "
				SELECT * FROM {$this->table}
				WHERE locked = 0
				AND available_at <= %s", $this->datetime() );

			$raw_job = $this->database->get_row( $sql );

			if ( ! is_null( $raw_job ) ) {
				$this->lock_job( $raw_job );
			}

			return $raw_job;
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
		 * Build WP_Job from raw job.
		 *
		 * @param $raw_job
		 *
		 * @return WP_Job
		 */
		public function build_job( $raw_job ) {
			$job = unserialize( $raw_job->job );

			$job->set_attempts( $raw_job->attempts );

			return $job;
		}

		/**
		 * Lock a job.
		 *
		 * @param mixed $raw_job
		 *
		 * @return false|int
		 */
		protected function lock_job( $raw_job ) {
			$data = array(
				'locked'    => 1,
				'locked_at' => $this->datetime(),
			);

			if ( $this->database->update( $this->table, $data, array( 'id' => $raw_job->id ) ) ) {
				return true;
			}

			return false;
		}
	}
}