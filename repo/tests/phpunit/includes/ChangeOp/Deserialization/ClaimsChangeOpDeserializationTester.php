<?php

// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingParamTag -- Test trait, no param tag for test functions

namespace Wikibase\Repo\Tests\ChangeOp\Deserialization;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Lib\Summary;
use Wikibase\Repo\ChangeOp\ChangeOpDeserializer;
use Wikibase\Repo\WikibaseRepo;

/**
 * Set of test methods that can be reused in ClaimsChangeOpDeserializerTest and tests for
 * ChangeOpDeserializers of entities that have claims.
 *
 * @license GPL-2.0-or-later
 */
trait ClaimsChangeOpDeserializationTester {

	/**
	 * @dataProvider setStatementProvider
	 */
	public function testGivenNewStatementChangeRequest_setsStatement( $changeRequest, EntityDocument $entity, $property ) {
		$changeOp = $this->getChangeOpDeserializer()->createEntityChangeOp( $changeRequest );
		$changeOp->apply( $entity, new Summary() );

		$this->assertFalse(
			$entity->getStatements()->getByPropertyId( $property )->isEmpty()
		);
	}

	public static function setStatementProvider(): array {
		$property = new NumericPropertyId( 'P7' );
		$statement = new Statement( new PropertyNoValueSnak( $property ) );
		$statementSerialization = self::getStatementSerializer()->serialize( $statement );
		$entity = self::getEntity();

		return [
			'numeric index format' => [ [ 'claims' => [ $statementSerialization ] ], $entity, $property ],
			'associative format' => [ [ 'claims' => [ 'P7' => [ $statementSerialization ] ] ], $entity, $property ],
		];
	}

	/**
	 * @dataProvider deleteStatementProvider
	 */
	public function testGivenRemoveChangeRequest_removesStatement( $changeRequest, EntityDocument $entity, $property ) {
		$changeOp = $this->getChangeOpDeserializer()->createEntityChangeOp( $changeRequest );
		$changeOp->apply( $entity, new Summary() );

		$this->assertTrue( $entity->getStatements()->getByPropertyId( $property )->isEmpty() );
	}

	public static function deleteStatementProvider(): array {
		$property = new NumericPropertyId( 'P7' );
		$statement = new Statement( new PropertyNoValueSnak( $property ) );
		$statement->setGuid( 'test-guid' );
		$entity = self::getEntity();
		$entity->setStatements( new StatementList( $statement ) );

		return [
			'numeric index format' => [
				[ 'claims' => [
					[ 'remove' => '', 'id' => $statement->getGuid() ],
				] ],
				$entity,
				$property,
			],
			'associative format' => [
				[ 'claims' => [
					'P7' => [ [ 'remove' => '', 'id' => $statement->getGuid() ] ],
				] ],
				$entity->copy(),
				$property,
			],
		];
	}

	/**
	 * @dataProvider editStatementProvider
	 */
	public function testGivenEditChangeRequest_statementGetsChanged( $changeRequest, EntityDocument $entity ) {
		$changeOp = $this->getChangeOpDeserializer()->createEntityChangeOp( $changeRequest );
		$changeOp->apply( $entity, new Summary() );

		$this->assertCount( 1, $entity->getStatements()->toArray() );
		$this->assertSame(
			'bar',
			$entity->getStatements()->toArray()[0]
				->getMainSnak()
				->getDataValue()
				->getValue()
		);
	}

	public static function editStatementProvider(): array {
		$property = new NumericPropertyId( 'P7' );
		$statement = new Statement( new PropertyValueSnak( $property, new StringValue( 'foo' ) ) );
		$entity = self::getEntity();
		$statement->setGuid( ( new GuidGenerator() )->newGuid( $entity->getId() ) );
		$entity->setStatements( new StatementList( $statement ) );
		$statementSerialization = self::getStatementSerializer()->serialize( $statement );
		$statementSerialization['mainsnak']['datavalue']['value'] = 'bar';

		return [
			'numeric index format' => [
				[ 'claims' => [ $statementSerialization ] ],
				$entity,
			],
			'associative format' => [
				[ 'claims' => [ 'P7' => [ $statementSerialization ] ] ],
				$entity,
			],
		];
	}

	/**
	 * @return StatementListProvider|EntityDocument
	 */
	private static function getStatementSerializer() {
		return WikibaseRepo::getBaseDataModelSerializerFactory()->newStatementSerializer();
	}

	/**
	 * @return StatementListProvider|EntityDocument
	 */
	abstract protected static function getEntity();

	/**
	 * @return ChangeOpDeserializer
	 */
	abstract protected function getChangeOpDeserializer();

}
