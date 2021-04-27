<?php
/**
 * WP-Background Processing
 *
 * @package WP-Background-Processing
 */

/*
Plugin Name: WP Background Processing
Plugin URI: https://github.com/jetty-dev/wp-background-processing
Description: Asynchronous requests and background processing in WordPress.
Author: Jetty
Version: 1.0.2
Author URI: https://jettyapp.com/
GitHub Plugin URI: https://github.com/jetty-dev/wp-background-processing
GitHub Branch: master
*/

if ( ! class_exists( 'WP_Async_Request' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/wp-async-request.php';
}
if ( ! class_exists( 'WP_Background_Process' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'classes/wp-background-process.php';
}
