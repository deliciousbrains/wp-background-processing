<?php
/*
Plugin Name: WP Background Processing
Plugin URI: https://github.com/A5hleyRich/wp-background-processing
Description: Asynchronous requests and background processing in WordPress.
Author: Delicious Brains Inc.
Version: 1.0
Author URI: https://deliciousbrains.com/
*/

require_once plugin_dir_path( __FILE__ ) . 'classes/wp-async-request.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/wp-background-process.php';

require_once plugin_dir_path( __FILE__ ) . 'classes/wp-job.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/wp-queue.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/worker/wp-worker.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/worker/wp-cli-worker.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/worker/wp-http-worker.php';

// Add WP CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/cli-command.php';

	WP_CLI::add_command( 'queue', 'CLI_Command' );
}

// Instantiate HTTP queue worker
new WP_Http_Worker();