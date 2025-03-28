<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\Domains\Crud\Domain\ReadModel;

use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Statement\StatementGuid;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\PredicateProperty;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\Qualifiers;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\Rank;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\References;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\Statement;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\StatementList;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\Value;

/**
 * @covers \Wikibase\Repo\Domains\Crud\Domain\ReadModel\StatementList
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class StatementListTest extends TestCase {

	public function testGetStatementById(): void {
		$id = new StatementGuid( new ItemId( 'Q42' ), 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE' );
		$expectedStatement = $this->newStatementWithId( $id );

		$this->assertSame(
			$expectedStatement,
			( new StatementList( $expectedStatement ) )->getStatementById( $id )
		);
	}

	public function testGivenStatementWithIdDoesNotExist_getStatementByIdReturnsNull(): void {
		$actualStatementId = new StatementGuid( new ItemId( 'Q42' ), 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE' );
		$requestedStatementId = new StatementGuid( new ItemId( 'Q42' ), 'FFFFFFFF-BBBB-CCCC-DDDD-EEEEEEEEEEEE' );

		$this->assertNull(
			( new StatementList( $this->newStatementWithId( $actualStatementId ) ) )
				->getStatementById( $requestedStatementId )
		);
	}

	private function newStatementWithId( StatementGuid $id ): Statement {
		return new Statement(
			$id,
			new PredicateProperty( new NumericPropertyId( 'P123' ), 'string' ),
			new Value( Value::TYPE_SOME_VALUE ),
			Rank::normal(),
			new Qualifiers(),
			new References()
		);
	}
}
