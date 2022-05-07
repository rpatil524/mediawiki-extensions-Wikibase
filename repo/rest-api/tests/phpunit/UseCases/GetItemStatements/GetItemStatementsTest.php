<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\UseCases\GetItemStatements;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\RestApi\Domain\Model\LatestItemRevisionMetadataResult;
use Wikibase\Repo\RestApi\Domain\Services\ItemRevisionMetadataRetriever;
use Wikibase\Repo\RestApi\Domain\Services\ItemStatementsRetriever;
use Wikibase\Repo\RestApi\UseCases\GetItemStatements\GetItemStatements;
use Wikibase\Repo\RestApi\UseCases\GetItemStatements\GetItemStatementsRequest;
use Wikibase\Repo\Tests\NewStatement;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\RestApi\UseCases\GetItemStatements\GetItemStatements
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class GetItemStatementsTest extends TestCase {

	/**
	 * @var Stub|ItemRevisionMetadataRetriever
	 */
	private $itemRevisionMetadataRetriever;

	/**
	 * @var Stub|ItemStatementsRetriever
	 */
	private $statementsRetriever;

	protected function setUp(): void {
		parent::setUp();

		$this->itemRevisionMetadataRetriever = $this->createStub( ItemRevisionMetadataRetriever::class );
		$this->statementsRetriever = $this->createStub( ItemStatementsRetriever::class );
	}

	public function testGetItemStatements(): void {
		$itemId = new ItemId( 'Q123' );
		$revision = 987;
		$lastModified = '20201111070707';
		$statement1PropertyId = 'P123';
		$statement1Value = 'potato';
		$statement2PropertyId = 'P321';
		$statement2Value = 'banana';
		$statements = new StatementList(
			NewStatement::forProperty( $statement1PropertyId )
				->withValue( $statement1Value )
				->build(),
			NewStatement::forProperty( $statement2PropertyId )
				->withValue( $statement2Value )
				->build()
		);

		$this->itemRevisionMetadataRetriever = $this->createMock( ItemRevisionMetadataRetriever::class );
		$this->itemRevisionMetadataRetriever->expects( $this->once() )
			->method( 'getLatestRevisionMetadata' )
			->with( $itemId )
			->willReturn( LatestItemRevisionMetadataResult::concreteRevision( $revision, $lastModified ) );

		$this->statementsRetriever = $this->createMock( ItemStatementsRetriever::class );
		$this->statementsRetriever->expects( $this->once() )
			->method( 'getStatements' )
			->with( $itemId )
			->willReturn( $statements );

		$response = $this->newUseCase()->execute(
			new GetItemStatementsRequest( $itemId->getSerialization() )
		);

		$serializedStatements = $response->getStatements();
		$this->assertArrayHasKey( $statement1PropertyId, $serializedStatements );
		$this->assertSame( $statement1Value, $serializedStatements[$statement1PropertyId][0]['mainsnak']['datavalue']['value'] );
		$this->assertArrayHasKey( $statement2PropertyId, $serializedStatements );
		$this->assertSame( $statement2Value, $serializedStatements[$statement2PropertyId][0]['mainsnak']['datavalue']['value'] );

		$this->assertSame( $revision, $response->getRevisionId() );
		$this->assertSame( $lastModified, $response->getLastModified() );
	}

	private function newUseCase(): GetItemStatements {
		return new GetItemStatements(
			$this->statementsRetriever,
			$this->itemRevisionMetadataRetriever,
			WikibaseRepo::getBaseDataModelSerializerFactory()->newStatementListSerializer()
		);
	}

}