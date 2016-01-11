<?php
/*
Plugin Name: Example Background Processing
Plugin URI: https://github.com/A5hleyRich/wp-background-processing
Description: Background processing in WordPress.
Author: Ashley Rich
Version: 0.1
Author URI: https://deliciousbrains.com/
Text Domain: example-plugin
Domain Path: /languages/
*/

class Example_Background_Processing {

	/**
	 * @var WP_Example_Request
	 */
	protected $process_single;

	/**
	 * @var WP_Example_Process
	 */
	protected $process_all;

	/**
	 * Example_Background_Processing constructor.
	 */
	public function __construct() {
		require_once plugin_dir_path( __FILE__ ) . 'example-plugin-logger.php';
		require_once plugin_dir_path( __FILE__ ) . 'wp-async-request.php';
		require_once plugin_dir_path( __FILE__ ) . 'wp-background-process.php';
		require_once plugin_dir_path( __FILE__ ) . 'async-requests/wp-example-request.php';
		require_once plugin_dir_path( __FILE__ ) . 'background-processes/wp-example-process.php';

		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		add_action( 'init', array( $this, 'process_handler' ) );

		$this->process_single = new WP_Example_Request();
		$this->process_all    = new WP_Example_Process();
	}

	/**
	 * Admin bar
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_menu( array(
			'id'    => 'example-plugin',
			'title' => __( 'Process', 'example-plugin' ),
			'href'  => '#',
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'example-plugin',
			'id'     => 'example-plugin-single',
			'title'  => __( 'Single User', 'example-plugin' ),
			'href'   => wp_nonce_url( admin_url( '?process=single'), 'process' ),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'example-plugin',
			'id'     => 'example-plugin-all',
			'title'  => __( 'All Users', 'example-plugin' ),
			'href'   => wp_nonce_url( admin_url( '?process=all'), 'process' ),
		) );
	}

	/**
	 * Process handler
	 */
	public function process_handler() {
		if ( ! isset( $_GET['process'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'process') ) {
			return;
		}

		if ( 'single' === $_GET['process'] ) {
			$this->handle_single();
		}

		if ( 'all' === $_GET['process'] ) {
			$this->handle_all();
		}
	}

	/**
	 * Handle single
	 */
	protected function handle_single() {
		$names = $this->get_names();
		$rand  = array_rand( $names, 1 );
		$name  = $names[ $rand ];

		$this->process_single->data( array( 'name' => $name ) )->dispatch();
	}

	/**
	 * Handle all
	 */
	protected function handle_all() {
		$names = $this->get_names();

		foreach ( $names as $name ) {
			$this->process_all->push_to_queue( $name );
		}

		$this->process_all->save()->dispatch();
	}

	/**
	 * Get names
	 *
	 * @return array
	 */
	protected function get_names() {
		return array(
			'Grant Buel',
			'Bryon Pennywell',
			'Jarred Mccuiston',
			'Reynaldo Azcona',
			'Jarrett Pelc',
			'Blake Terrill',
			'Romeo Tiernan',
			'Marion Buckle',
			'Theodore Barley',
			'Carmine Hopple',
			'Suzi Rodrique',
			'Fran Velez',
			'Sherly Bolten',
			'Rossana Ohalloran',
			'Sonya Water',
			'Marget Bejarano',
			'Leslee Mans',
			'Fernanda Eldred',
			'Terina Calvo',
			'Dawn Partridge',
		);
	}

}

new Example_Background_Processing();