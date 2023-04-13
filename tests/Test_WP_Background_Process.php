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
}
