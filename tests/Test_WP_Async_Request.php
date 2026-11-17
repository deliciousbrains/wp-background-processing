<?php
/**
 * Unit tests for WP_Async_Request.
 *
 * @package WP-Background-Processing
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class Test_WP_Async_Request
 */
class Test_WP_Async_Request extends WP_UnitTestCase {
	/**
	 * Instance of WP_Async_Request
	 *
	 * @var MockObject|WP_Async_Request|(WP_Async_Request&MockObject)
	 */
	private $wpar;

	/**
	 * Cookies before the test.
	 *
	 * @var array
	 */
	private $original_cookies;

	/**
	 * Performs set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->original_cookies = $_COOKIE;
		$this->wpar             = $this->getMockForAbstractClass( WP_Async_Request::class );
	}

	/**
	 * Performs tear down after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_COOKIE = $this->original_cookies;

		parent::tearDown();
	}

	/**
	 * Cookies sent with the async request must not carry the slashes WordPress adds to $_COOKIE.
	 *
	 * @return void
	 */
	public function test_dispatch_sends_unslashed_cookies() {
		$json = '{"first_name":"Jan"}';

		// WordPress adds slashes to $_COOKIE in wp_magic_quotes(), which has run by the time tests start.
		$this->assertGreaterThan( 0, did_action( 'sanitize_comment_cookies' ) );
		$_COOKIE = array( 'json_cookie' => wp_slash( $json ) );

		$this->assertSame( array( 'json_cookie' => $json ), $this->dispatch_and_get_cookies() );
	}

	/**
	 * Before WordPress slashes $_COOKIE (e.g. dispatching on plugins_loaded), cookies are sent unchanged.
	 *
	 * @return void
	 */
	public function test_dispatch_before_magic_quotes_keeps_cookies() {
		global $wp_actions;

		// PHP decodes a cookie sent as "C:%5Ctemp" into a real backslash.
		$cookies = array( 'dir' => 'C:\\temp' );
		$_COOKIE = $cookies;

		// As if dispatching before wp_magic_quotes(). WP_UnitTestCase restores $wp_actions after each test.
		unset( $wp_actions['sanitize_comment_cookies'] );

		$this->assertSame( $cookies, $this->dispatch_and_get_cookies() );
	}

	/**
	 * Dispatch an async request and return the cookies it would have sent.
	 *
	 * @return array|null
	 */
	private function dispatch_and_get_cookies() {
		$sent = null;
		add_filter(
			'pre_http_request',
			function ( $response, $args ) use ( &$sent ) {
				$sent = $args['cookies'];

				return array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			2
		);

		$this->wpar->dispatch();

		return $sent;
	}
}
