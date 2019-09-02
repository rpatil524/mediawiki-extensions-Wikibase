<?php

namespace Wikibase\Repo\Tests\ChangeOp;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\ChangeOp\GenericChangeOpResult;

/**
 * @covers \Wikibase\Repo\ChangeOp\GenericChangeOpResult
 *
 * @group Wikibase
 * @group ChangeOp
 */
class GenericChangeOpResultTest extends \PHPUnit\Framework\TestCase {

	public function testGetEntityId() {
		$itemId = new ItemId( 'Q123' );
		$changeOpResult = new GenericChangeOpResult( $itemId, false );

		$this->assertSame( $itemId, $changeOpResult->getEntityId() );
	}

	public function testEntityIdCanBeNull() {
		$changeOpResult = new GenericChangeOpResult( null, false );
		$this->assertNull( $changeOpResult->getEntityId() );
	}

	public function testIsEntityChange() {
		$resultWithChange = new GenericChangeOpResult( new ItemId( 'Q123' ), true );
		$resultWithoutChange = new GenericChangeOpResult( new ItemId( 'Q321' ), false );

		$this->assertTrue( $resultWithChange->isEntityChanged() );
		$this->assertFalse( $resultWithoutChange->isEntityChanged() );
	}

}
