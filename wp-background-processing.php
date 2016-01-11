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