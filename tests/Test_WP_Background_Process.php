<?php
/**
 * Unit tests for WP_Background_Process.
 *
 * @package WP-Background-Processing
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class Test_WP_Background_Process
 */
class Test_WP_Background_Process extends WP_UnitTestCase {
	/**
	 * Instance of WP_Background_Process
	 *
	 * @var MockObject|WP_Background_Process|(WP_Background_Process&MockObject)
	 */
	private $wpbp;

	/**
	 * Performs set up before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->wpbp = $this->getMockForAbstractClass( WP_Background_Process::class );

		$this->wpbp->expects( $this->any() )
		           ->method( 'task' )
		           ->will( $this->returnValue( false ) );
	}

	/**
	 * Get a property value from WPBP regardless of accessibility.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	private function getWPBPProperty( string $name ) {
		try {
			$property = new ReflectionProperty( 'WP_Background_Process', $name );
		} catch ( Exception $e ) {
			return new WP_Error( $e->getCode(), $e->getMessage() );
		}
		$property->setAccessible( true );

		return $property->getValue( $this->wpbp );
	}

	/**
	 * Execute a method of WPBP regardless of accessibility.
	 *
	 * @param string $name Method name.
	 * @param mixed  $args None, one or more args to pass to method.
	 *
	 * @return mixed
	 */
	private function executeWPBPMethod( string $name, ...$args ) {
		try {
			$method = new ReflectionMethod( 'WP_Background_Process', $name );
			$method->setAccessible( true );

			return $method->invoke( $this->wpbp, ...$args );
		} catch ( Exception $e ) {
			return new WP_Error( $e->getCode(), $e->getMessage() );
		}
	}

	/**
	 * Test push_to_queue.
	 *
	 * @return void
	 */
	public function test_push_to_queue() {
		$this->assertClassHasAttribute( 'data', 'WP_Background_Process', 'class has data property' );
		$this->assertEmpty( $this->getWPBPProperty( 'data' ) );

		$this->wpbp->push_to_queue( 'wibble' );
		$this->assertNotEmpty( $this->getWPBPProperty( 'data' ) );
		$this->assertEquals( array( 'wibble' ), $this->getWPBPProperty( 'data' ) );

		$this->wpbp->push_to_queue( 'wobble' );
		$this->assertEquals( array( 'wibble', 'wobble' ), $this->getWPBPProperty( 'data' ) );
	}

	/**
	 * Test save.
	 *
	 * @return void
	 */
	public function test_save() {
		$this->assertClassHasAttribute( 'data', 'WP_Background_Process', 'class has data property' );
		$this->assertEmpty( $this->getWPBPProperty( 'data' ) );
		$this->assertEmpty( $this->wpbp->get_batches(), 'no batches until save' );

		$this->wpbp->push_to_queue( 'wibble' );
		$this->assertNotEmpty( $this->getWPBPProperty( 'data' ) );
		$this->assertEquals( array( 'wibble' ), $this->getWPBPProperty( 'data' ) );
		$this->wpbp->save();
		$this->assertEmpty( $this->getWPBPProperty( 'data' ), 'data emptied after save' );
		$this->assertNotEmpty( $this->wpbp->get_batches(), 'batches exist after save' );
	}

	/**
	 * Test get_batches.
	 *
	 * @return void
	 */
	public function test_get_batches() {
		$this->assertEmpty( $this->wpbp->get_batches(), 'no batches until save' );

		$this->wpbp->push_to_queue( 'wibble' );
		$this->assertNotEmpty( $this->getWPBPProperty( 'data' ) );
		$this->assertEquals( array( 'wibble' ), $this->getWPBPProperty( 'data' ) );
		$this->assertEmpty( $this->wpbp->get_batches(), 'no batches until save' );

		$this->wpbp->push_to_queue( 'wobble' );
		$this->assertEquals( array( 'wibble', 'wobble' ), $this->getWPBPProperty( 'data' ) );
		$this->assertEmpty( $this->wpbp->get_batches(), 'no batches until save' );

		$this->wpbp->save();
		$first_batch = $this->wpbp->get_batches();
		$this->assertNotEmpty( $first_batch );
		$this->assertCount( 1, $first_batch );

		$this->wpbp->push_to_queue( 'more wibble' );
		$this->wpbp->save();
		$this->assertCount( 2, $this->wpbp->get_batches() );

		$this->wpbp->push_to_queue( 'Wibble wobble all day long.' );
		$this->wpbp->save();
		$this->assertCount( 3, $this->wpbp->get_batches() );

		$this->assertEquals( $first_batch, $this->wpbp->get_batches( 1 ) );
		$this->assertNotEquals( $first_batch, $this->wpbp->get_batches( 2 ) );
		$this->assertCount( 2, $this->wpbp->get_batches( 2 ) );
		$this->assertCount( 3, $this->wpbp->get_batches( 3 ) );
		$this->assertCount( 3, $this->wpbp->get_batches( 5 ) );
	}

	/**
	 * Test get_batch.
	 *
	 * @return void
	 */
	public function test_get_batch() {
		$this->assertEmpty( $this->executeWPBPMethod( 'get_batch' ), 'no batches until save' );

		$this->wpbp->push_to_queue( 'wibble' );
		$this->assertNotEmpty( $this->getWPBPProperty( 'data' ) );
		$this->assertEquals( array( 'wibble' ), $this->getWPBPProperty( 'data' ) );
		$this->assertEmpty( $this->executeWPBPMethod( 'get_batch' ), 'no batches until save' );

		$this->wpbp->push_to_queue( 'wobble' );
		$this->assertEquals( array( 'wibble', 'wobble' ), $this->getWPBPProperty( 'data' ) );
		$this->assertEmpty( $this->executeWPBPMethod( 'get_batch' ), 'no batches until save' );

		$this->wpbp->save();
		$first_batch = $this->executeWPBPMethod( 'get_batch' );
		$this->assertNotEmpty( $first_batch );
		$this->assertInstanceOf( 'stdClass', $first_batch );
		$this->assertEquals( array( 'wibble', 'wobble' ), $first_batch->data );

		$this->wpbp->push_to_queue( 'more wibble' );
		$this->wpbp->save();
		$second_batch = $this->executeWPBPMethod( 'get_batch' );
		$this->assertNotEmpty( $second_batch );
		$this->assertInstanceOf( 'stdClass', $second_batch );
		$this->assertEquals( $first_batch, $second_batch, 'same 1st batch returned until deleted' );

		$this->wpbp->delete( $first_batch->key );
		$second_batch = $this->executeWPBPMethod( 'get_batch' );
		$this->assertNotEmpty( $second_batch );
		$this->assertInstanceOf( 'stdClass', $second_batch );
		$this->assertNotEquals( $first_batch, $second_batch, '2nd batch returned as 1st deleted' );
		$this->assertEquals( array( 'more wibble' ), $second_batch->data );
	}

	/**
	 * Test cancel.
	 *
	 * @return void
	 */
	public function test_cancel() {
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertFalse( $this->wpbp->is_cancelled() );
		$this->wpbp->cancel();
		$this->assertTrue( $this->wpbp->is_cancelled() );
	}

	/**
	 * Test pause.
	 *
	 * @return void
	 */
	public function test_pause() {
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertFalse( $this->wpbp->is_paused() );
		$this->wpbp->pause();
		$this->assertTrue( $this->wpbp->is_paused() );
	}

	/**
	 * Test resume.
	 *
	 * @return void
	 */
	public function test_resume() {
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertFalse( $this->wpbp->is_paused() );
		$this->wpbp->pause();
		$this->assertTrue( $this->wpbp->is_paused() );
		$this->wpbp->resume();
		$this->assertFalse( $this->wpbp->is_paused() );
	}

	/**
	 * Test delete.
	 *
	 * @return void
	 */
	public function test_delete() {
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$this->wpbp->push_to_queue( 'wobble' );
		$this->wpbp->save();
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$first_batch = $this->executeWPBPMethod( 'get_batch' );
		$this->wpbp->delete( $first_batch->key );
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$second_batch = $this->executeWPBPMethod( 'get_batch' );
		$this->assertNotEquals( $first_batch, $second_batch, '2nd batch returned as 1st deleted' );
	}

	/**
	 * Test delete_all.
	 *
	 * @return void
	 */
	public function test_delete_all() {
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$this->wpbp->push_to_queue( 'wobble' );
		$this->wpbp->save();
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$this->wpbp->delete_all();
		$this->assertCount( 0, $this->wpbp->get_batches() );
	}

	/**
	 * Test update.
	 *
	 * @return void
	 */
	public function test_update() {
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$this->wpbp->push_to_queue( 'wobble' );
		$this->wpbp->save();
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$first_batch = $this->executeWPBPMethod( 'get_batch' );
		$this->wpbp->update( $first_batch->key, array( 'Wibble wobble all day long!' ) );
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$updated_batch = $this->executeWPBPMethod( 'get_batch' );
		$this->assertNotEquals( $first_batch, $updated_batch, 'fetched updated batch different to 1st fetch' );
		$this->assertEquals( array( 'Wibble wobble all day long!' ), $updated_batch->data, 'fetched updated batch has expected data' );
	}

	/**
	 * Test maybe_handle when cancelling.
	 *
	 * @return void
	 */
	public function test_maybe_handle_cancelled() {
		// Cancelled status results in cleared batches and action fired.
		$cancelled_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_cancelled', function () use ( &$cancelled_fired ) {
			$cancelled_fired = true;
		} );
		// Paused action should not be fired though.
		$paused_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_paused', function () use ( &$paused_fired ) {
			$paused_fired = true;
		} );
		// Completed action should not be fired though.
		$completed_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_completed', function () use ( &$completed_fired ) {
			$completed_fired = true;
		} );
		add_filter( $this->getWPBPProperty( 'identifier' ) . '_wp_die', '__return_false' );
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$this->wpbp->push_to_queue( 'wobble' );
		$this->wpbp->save();
		$this->assertCount( 2, $this->wpbp->get_batches() );
		update_site_option( $this->executeWPBPMethod( 'get_status_key' ), WP_Background_Process::STATUS_CANCELLED );
		$this->assertTrue( $this->wpbp->is_cancelled(), 'is_cancelled' );
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$this->assertFalse( $cancelled_fired, 'cancelled action not fired yet' );
		$this->assertFalse( $paused_fired, 'paused action not fired yet' );
		$this->assertFalse( $completed_fired, 'completed action not fired yet' );
		$this->wpbp->maybe_handle();
		$this->assertCount( 0, $this->wpbp->get_batches() );
		$this->assertTrue( $cancelled_fired, 'cancelled action fired' );
		$this->assertFalse( $paused_fired, 'paused action still not fired yet' );
		$this->assertFalse( $completed_fired, 'completed action not fired yet' );
	}

	/**
	 * Test maybe_handle when pausing and resuming.
	 *
	 * @return void
	 */
	public function test_maybe_handle_paused_resumed() {
		// Cancelled action should not be fired.
		$cancelled_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_cancelled', function () use ( &$cancelled_fired ) {
			$cancelled_fired = true;
		} );
		// Paused action should fire and batches remain intact.
		$paused_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_paused', function () use ( &$paused_fired ) {
			$paused_fired = true;
		} );
		// Resumed action should fire on resume before batches handled.
		$resumed_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_resumed', function () use ( &$resumed_fired ) {
			$resumed_fired = true;
		} );
		// Completed action should fire after batches handled.
		$completed_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_completed', function () use ( &$completed_fired ) {
			$completed_fired = true;
		} );
		add_filter( $this->getWPBPProperty( 'identifier' ) . '_wp_die', '__return_false' );
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$this->wpbp->push_to_queue( 'wobble' );
		$this->wpbp->save();
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$this->wpbp->pause();
		$this->assertTrue( $this->wpbp->is_paused(), 'is_paused' );
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$this->assertFalse( $cancelled_fired, 'cancelled action not fired yet' );
		$this->assertFalse( $paused_fired, 'paused action not fired yet' );
		$this->assertFalse( $resumed_fired, 'resumed action not fired yet' );
		$this->assertFalse( $completed_fired, 'completed action not fired yet' );
		$this->wpbp->maybe_handle();
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$this->assertFalse( $cancelled_fired, 'cancelled action still not fired yet' );
		$this->assertTrue( $paused_fired, 'paused action fired' );
		$this->assertFalse( $resumed_fired, 'resumed action still not fired yet' );
		$this->assertFalse( $completed_fired, 'completed action not fired yet' );

		// Reset for resume and ensure dispatch does nothing to that maybe_handle can be monitored.
		$paused_fired = false;
		add_filter( 'pre_http_request', '__return_true' );
		$this->wpbp->resume();
		remove_filter( 'pre_http_request', '__return_true' );
		$this->assertFalse( $this->wpbp->is_paused(), 'not is_paused after resume' );
		$this->assertCount( 2, $this->wpbp->get_batches() );
		$this->assertFalse( $cancelled_fired, 'cancelled action not fired yet' );
		$this->assertFalse( $paused_fired, 'paused action not fired yet' );
		$this->assertTrue( $resumed_fired, 'resumed action fired' );
		$this->assertFalse( $completed_fired, 'completed action not fired yet' );

		// Don't expect resumed to be fired again, and batches to be handled with valid security.
		$resumed_fired     = false;
		$_REQUEST['nonce'] = wp_create_nonce( $this->getWPBPProperty( 'identifier' ) );
		$this->wpbp->maybe_handle();
		$this->assertCount( 0, $this->wpbp->get_batches(), 'after resume all batches processed with maybe_handle' );
		$this->assertFalse( $cancelled_fired, 'cancelled action still not fired yet' );
		$this->assertFalse( $paused_fired, 'paused action not fired yet' );
		$this->assertFalse( $resumed_fired, 'resumed action still not fired yet' );
		$this->assertTrue( $completed_fired, 'completed action fired' );
	}

	/**
	 * Test maybe_handle when handling a single batch.
	 *
	 * @return void
	 */
	public function test_maybe_handle_single_batch() {
		// Cancelled action should not be fired.
		$cancelled_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_cancelled', function () use ( &$cancelled_fired ) {
			$cancelled_fired = true;
		} );
		// Paused action should not be fired.
		$paused_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_paused', function () use ( &$paused_fired ) {
			$paused_fired = true;
		} );
		// Resumed action should not be fired.
		$resumed_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_resumed', function () use ( &$resumed_fired ) {
			$resumed_fired = true;
		} );
		// Completed action should fire after batches handled.
		$completed_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_completed', function () use ( &$completed_fired ) {
			$completed_fired = true;
		} );
		add_filter( $this->getWPBPProperty( 'identifier' ) . '_wp_die', '__return_false' );
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$this->assertFalse( $cancelled_fired, 'cancelled action not fired yet' );
		$this->assertFalse( $paused_fired, 'paused action not fired yet' );
		$this->assertFalse( $resumed_fired, 'resumed action not fired yet' );
		$this->assertFalse( $completed_fired, 'completed action not fired yet' );

		$_REQUEST['nonce'] = wp_create_nonce( $this->getWPBPProperty( 'identifier' ) );
		$this->wpbp->maybe_handle();
		$this->assertCount( 0, $this->wpbp->get_batches(), 'after resume all batches processed with maybe_handle' );
		$this->assertFalse( $cancelled_fired, 'cancelled action still not fired yet' );
		$this->assertFalse( $paused_fired, 'paused action not fired yet' );
		$this->assertFalse( $resumed_fired, 'resumed action still not fired yet' );
		$this->assertTrue( $completed_fired, 'completed action fired' );
	}

	/**
	 * Test maybe_handle when handling nothing.
	 *
	 * @return void
	 */
	public function test_maybe_handle_nothing() {
		// Cancelled action should not be fired.
		$cancelled_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_cancelled', function () use ( &$cancelled_fired ) {
			$cancelled_fired = true;
		} );
		// Paused action should not be fired.
		$paused_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_paused', function () use ( &$paused_fired ) {
			$paused_fired = true;
		} );
		// Resumed action should not be fired.
		$resumed_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_resumed', function () use ( &$resumed_fired ) {
			$resumed_fired = true;
		} );
		// Completed action should not be fired.
		$completed_fired = false;
		add_action( $this->getWPBPProperty( 'identifier' ) . '_completed', function () use ( &$completed_fired ) {
			$completed_fired = true;
		} );
		add_filter( $this->getWPBPProperty( 'identifier' ) . '_wp_die', '__return_false' );
		$this->assertCount( 0, $this->wpbp->get_batches() );
		$this->assertFalse( $cancelled_fired, 'cancelled action not fired yet' );
		$this->assertFalse( $paused_fired, 'paused action not fired yet' );
		$this->assertFalse( $resumed_fired, 'resumed action not fired yet' );
		$this->assertFalse( $completed_fired, 'completed action not fired yet' );

		$this->wpbp->maybe_handle();
		$this->assertCount( 0, $this->wpbp->get_batches(), 'after resume all batches processed with maybe_handle' );
		$this->assertFalse( $cancelled_fired, 'cancelled action still not fired yet' );
		$this->assertFalse( $paused_fired, 'paused action not fired yet' );
		$this->assertFalse( $resumed_fired, 'resumed action still not fired yet' );
		$this->assertFalse( $completed_fired, 'completed action not fired yet' );
	}

	/**
	 * Test is_processing.
	 *
	 * @return void
	 */
	public function test_is_processing() {
		$this->assertFalse( $this->wpbp->is_processing(), 'not processing yet' );
		$this->executeWPBPMethod( 'lock_process' );
		$this->assertTrue( $this->wpbp->is_processing(), 'processing' );

		// With batches to be processed, maybe_handle does nothing as "another instance is processing".
		add_filter( $this->getWPBPProperty( 'identifier' ) . '_wp_die', '__return_false' );
		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$this->wpbp->maybe_handle();
		$this->assertCount( 1, $this->wpbp->get_batches() );

		// Unlock and maybe_handle can process the batch.
		$this->executeWPBPMethod( 'unlock_process' );
		$this->assertFalse( $this->wpbp->is_processing(), 'not processing yet' );
		$this->assertCount( 1, $this->wpbp->get_batches() );
		$_REQUEST['nonce'] = wp_create_nonce( $this->getWPBPProperty( 'identifier' ) );
		$this->wpbp->maybe_handle();
		$this->assertCount( 0, $this->wpbp->get_batches() );
		$this->assertFalse( $this->wpbp->is_processing(), 'not left processing on complete' );
	}

	/**
	 * Test is_queued.
	 *
	 * @return void
	 */
	public function test_is_queued() {
		$this->assertFalse( $this->wpbp->is_queued(), 'nothing queued until save' );

		$this->wpbp->push_to_queue( 'wibble' );
		$this->assertFalse( $this->wpbp->is_queued(), 'nothing queued until save' );

		$this->wpbp->save();
		$this->assertTrue( $this->wpbp->is_queued(), 'queued items exist' );

		$this->wpbp->push_to_queue( 'wobble' );
		$this->wpbp->save();
		$this->assertTrue( $this->wpbp->is_queued(), 'queued items exist' );

		$this->wpbp->delete_all();
		$this->assertFalse( $this->wpbp->is_queued(), 'queue emptied' );
	}

	/**
	 * Test is_active.
	 *
	 * @return void
	 */
	public function test_is_active() {
		$this->assertFalse( $this->wpbp->is_active(), 'not queued, processing, paused or cancelling' );

		// Queued.
		$this->wpbp->push_to_queue( 'wibble' );
		$this->assertFalse( $this->wpbp->is_active(), 'nothing queued until save' );

		$this->wpbp->save();
		$this->assertTrue( $this->wpbp->is_active(), 'queued items exist, so now active' );

		$this->wpbp->delete_all();
		$this->assertFalse( $this->wpbp->is_active(), 'queue emptied, so no longer active' );

		// Processing.
		$this->executeWPBPMethod( 'lock_process' );
		$this->assertTrue( $this->wpbp->is_active(), 'processing, so now active' );

		$this->executeWPBPMethod( 'unlock_process' );
		$this->assertFalse( $this->wpbp->is_active(), 'not processing, so no longer active' );

		// Paused.
		$this->wpbp->pause();
		$this->assertTrue( $this->wpbp->is_active(), 'paused, so now active' );

		$this->wpbp->resume();
		$this->assertFalse( $this->wpbp->is_active(), 'not paused, nothing queued, so no longer active' );

		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertTrue( $this->wpbp->is_active(), 'queued items exist, so now active' );
		$this->wpbp->pause();
		$this->assertTrue( $this->wpbp->is_active(), 'paused, so still active' );
		add_filter( 'pre_http_request', '__return_true' );
		$this->wpbp->resume();
		remove_filter( 'pre_http_request', '__return_true' );
		$this->assertTrue( $this->wpbp->is_active(), 'resumed but with queued items, so still active' );
		$this->wpbp->delete_all();
		$this->assertFalse( $this->wpbp->is_active(), 'queue emptied, so no longer active' );

		// Cancelled.
		add_filter( 'pre_http_request', '__return_true' );
		$this->wpbp->cancel();
		remove_filter( 'pre_http_request', '__return_true' );
		$this->assertTrue( $this->wpbp->is_active(), 'cancelling, so now active' );

		add_filter( $this->getWPBPProperty( 'identifier' ) . '_wp_die', '__return_false' );
		$this->wpbp->maybe_handle();
		$this->assertFalse( $this->wpbp->is_active(), 'cancel handled, so no longer active' );

		$this->wpbp->push_to_queue( 'wibble' );
		$this->wpbp->save();
		$this->assertTrue( $this->wpbp->is_active(), 'queued items exist, so now active' );
		add_filter( 'pre_http_request', '__return_true' );
		$this->wpbp->cancel();
		remove_filter( 'pre_http_request', '__return_true' );
		$this->assertTrue( $this->wpbp->is_active(), 'cancelling, so still active' );
		$this->wpbp->maybe_handle();
		$this->assertFalse( $this->wpbp->is_active(), 'cancel handled, queue emptied, so no longer active' );
	}
}
