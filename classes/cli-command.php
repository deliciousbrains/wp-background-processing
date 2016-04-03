<?php

/**
 * Manage queue.
 *
 * @package wp-cli
 */
class CLI_Command extends WP_CLI_Command {

	/**
	 * Creates the queue table.
	 *
	 * @subcommand create-table
	 */
	public function create_table( $args, $assoc_args = array() ) {
		global $wpdb;

		$wpdb->hide_errors();

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->prefix}queue (
				id bigint(20) NOT NULL AUTO_INCREMENT,
                job text NOT NULL,
                attempts tinyint(1) NOT NULL DEFAULT 0,
                locked tinyint(1) NOT NULL DEFAULT 0,
                locked_at datetime DEFAULT NULL,
                available_at datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id)
				) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( $sql );

		WP_CLI::success( "Table {$wpdb->prefix}queue created." );
	}

	/**
	 * Listen to the queue.
	 */
	public function listen( $args, $assoc_args = array() ) {
		WP_CLI::log( 'Listening for queue jobs...' );

		$worker = new WP_Cli_Worker();

		while ( true ) {
			if ( $worker->should_run() ) {
				$name = $worker->get_job_name();

				if ( $worker->process_next_job() ) {
					WP_CLI::success( 'Processed: ' . $name );
				} else {
					WP_CLI::warning( 'Failed: ' . $name );
				}
			} else {
				sleep( 5 );
			}
		}
	}

}