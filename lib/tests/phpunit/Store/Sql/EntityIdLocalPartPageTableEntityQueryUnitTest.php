<?php

namespace Wikibase\Lib\Tests\Store\Sql;

use Error;
use MediaWiki\Storage\NameTableStore;
use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\Sql\EntityIdLocalPartPageTableEntityQuery;
use Wikibase\Lib\Store\Sql\PageTableEntityQueryBase;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @group Wikibase
 * @group WikibaseLib
 *
 * @covers \Wikibase\Lib\Store\Sql\EntityIdLocalPartPageTableEntityQuery
 * @covers \Wikibase\Lib\Store\Sql\PageTableEntityQueryBase
 *
 * @license GPL-2.0-or-later
 */
class EntityIdLocalPartPageTableEntityQueryUnitTest extends TestCase {

	private function getMockEntityId( string $type, string $idString ): EntityId {
		$id = $this->createMock( EntityId::class );
		$id->method( 'getSerialization' )->willReturn( $idString );
		$id->method( 'getEntityType' )->willReturn( $type );
		return $id;
	}

	public function testSelectRowsSimple_noSlottedEntities() {
		$query = new EntityIdLocalPartPageTableEntityQuery(
			new EntityNamespaceLookup( [ 'item' => 0 ], [] ),
			$this->createMock( NameTableStore::class )
		);

		$database = $this->createMock( IDatabase::class );
		$database->method( 'makeList' )
			->willReturnCallback( static function ( array $a, $mode ) {
				// For regular entities no slot_role_id should be requested
				if ( $a === [ 'page_title' => 'Q1', 'page_namespace' => 0 ] && $mode === LIST_AND ) {
					return 'Q1-condition';
				} elseif ( $a === [ "Q1-condition" ] && $mode === LIST_OR ) {
					return 'combined-condition';
				} else {
					throw new Error( 'Unexpected makeList() call' );
				}
			} );
		$database->method( 'newSelectQueryBuilder' )
			->willReturnCallback( fn() => new SelectQueryBuilder( $database ) );
		// Extra fields, tables and joins passed to the method should also be requested
		// No join on the slots table should happen, as we are not looking at a slotted entity
		$database->method( 'select' )
			->with(
			[ "page", "revision" => "revision" ],
			[ "extraField", "page_title" ],
			[ "combined-condition" ],
			PageTableEntityQueryBase::class . "::selectRows",
			[],
			[ 'revision' => [ 'JOIN', [ 'rev_id=extraField' ] ] ]
		)->willReturn(
			new FakeResultWrapper(
				[
					(object)[ 'page_title' => 'Q1' ],
				]
			)
		);

		$rows = $query->selectRows(
			[ 'extraField' ],
			[ 'rev_id=extraField' ],
			[ $this->getMockEntityId( 'item', 'Q1' ) ],
			$database
		);

		// Result should be indexed by entity ID / by page_title
		$this->assertEquals(
			[
				'Q1' => (object)[ 'page_title' => 'Q1' ],
			],
			$rows
		);
	}

	public function testSelectRowsCombination() {
		$slotRoleStore = $this->createMock( NameTableStore::class );
		$slotRoleStore->method( 'getId' )
			->with( 'otherSlot' )
			->willReturn( 76 );

		$query = new EntityIdLocalPartPageTableEntityQuery(
			new EntityNamespaceLookup( [ 'item' => 0, 'other' => 2 ], [ 'other' => 'otherSlot' ] ),
			$slotRoleStore
		);

		$database = $this->createMock( IDatabase::class );
		$database->method( 'makeList' )
			->willReturnCallback( static function ( array $a, $mode ) {
				if ( $a === [ 'page_title' => 'Q1', 'page_namespace' => 0 ] && $mode === LIST_AND ) {
					// For regular entities no slot_role_id should be requested
					return 'Q1-condition';
				} elseif ( $a === [ "page_title" => "ooo123", "page_namespace" => 2, "slot_role_id" => 76 ] && $mode == LIST_AND ) {
					// For entities in slots the slot role ID should be in the query
					return 'ooo123-condition';
				} elseif ( $a === [ "Q1-condition", "ooo123-condition" ] && $mode === LIST_OR ) {
					return 'combined-condition';
				} else {
					throw new Error( 'Unexpected makeList() call' );
				}
			} );
		$database->method( 'newSelectQueryBuilder' )
			->willReturnCallback( fn() => new SelectQueryBuilder( $database ) );
		// Extra fields, tables and joins passed to the method should also be requested
		$database->method( 'select' )
			->with(
			[ "page", "revision" => "revision", "slots" => "slots" ],
			[ "extraField", "page_title" ],
			[ "combined-condition" ],
			PageTableEntityQueryBase::class . "::selectRows",
			[],
			[
				'revision' => [ 'JOIN', [ 'rev_id=extraField' ] ],
				"slots" => [ "JOIN", "rev_id=slot_revision_id" ],
			]
		)->willReturn(
			new FakeResultWrapper(
				[
					(object)[ 'page_title' => 'Q1' ],
					(object)[ 'page_title' => 'ooo123' ],
				]
			)
		);

		$rows = $query->selectRows(
			[ 'extraField' ],
			[ 'rev_id=extraField' ],
			[ $this->getMockEntityId( 'item', 'Q1' ), $this->getMockEntityId( 'other', 'ooo123' ) ],
			$database
		);

		// Result should be indexed by entity ID / by page_title
		$this->assertEquals(
			[
				'Q1' => (object)[ 'page_title' => 'Q1' ],
				'ooo123' => (object)[ 'page_title' => 'ooo123' ],
			],
			$rows
		);
	}

}
