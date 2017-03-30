<?php
/*
Plugin Name: WP Background Processing
Plugin URI: https://github.com/A5hleyRich/wp-background-processing
Description: Asynchronous requests and background processing in WordPress.
Author: Delicious Brains Inc.
Version: 1.0
Author URI: https://deliciousbrains.com/
*/

require_once plugin_dir_path( __FILE__ ) . 'classes/wp-job.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/workers/wp-worker.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/workers/wp-http-worker.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/queues/wp-queue-interface.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/queues/wp-database-queue.php';
require_once plugin_dir_path( __FILE__ ) . 'functions.php';

// Add WP CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/workers/wp-cli-worker.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/cli/queue-command.php';

	WP_CLI::add_command( 'queue', 'Queue_Command' );
}

global $wp_queue, $wpdb;
$wp_queue = apply_filters( 'wp_queue_instance', new WP_Database_Queue( $wpdb ) );

// Instantiate HTTP queue worker
new WP_Http_Worker( $wp_queue );