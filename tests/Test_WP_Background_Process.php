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
}
