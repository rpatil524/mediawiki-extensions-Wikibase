<?php

namespace Wikibase\Repo\Tests\ParserOutput;

use DataValues\BooleanValue;
use DataValues\StringValue;
use MediaWiki\Parser\ParserOutput;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\ParserOutput\PageImagesDataUpdater;

/**
 * @covers \Wikibase\Repo\ParserOutput\PageImagesDataUpdater
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class PageImagesDataUpdaterTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @param string[] $propertyIds
	 *
	 * @return PageImagesDataUpdater
	 */
	private function newInstance( array $propertyIds ) {
		return new PageImagesDataUpdater( $propertyIds, 'page_image' );
	}

	/**
	 * @param StatementList $statements
	 * @param int $propertyId
	 * @param string $string
	 * @param int $rank
	 */
	private static function addStatement(
		StatementList $statements,
		$propertyId,
		$string,
		$rank = Statement::RANK_NORMAL
	) {
		$statement = new Statement(
			new PropertyValueSnak( $propertyId, new StringValue( $string ) )
		);
		$statement->setRank( $rank );
		$statements->addStatement( $statement );
	}

	/**
	 * @dataProvider constructorArgumentsProvider
	 */
	public function testConstructor( array $propertyIds ) {
		$instance = $this->newInstance( $propertyIds );
		$this->assertInstanceOf( PageImagesDataUpdater::class, $instance );
	}

	public static function constructorArgumentsProvider() {
		return [
			'Empty' => [ [] ],
			'Property ids' => [ [ 'P1', 'P9999' ] ],
			'Non-property ids' => [ [ 'Q1' ] ],
			'Invalid ids' => [ [ 'invalid' ] ],
		];
	}

	/**
	 * @dataProvider bestImageProvider
	 */
	public function testUpdateParserOutput(
		StatementList $statements,
		array $propertyIds,
		$expected
	) {
		$parserOutput = $this->createMock( ParserOutput::class );

		if ( $expected !== null ) {
			$parserOutput->expects( $this->once() )
				->method( 'setUnsortedPageProperty' )
				->with( 'page_image', $expected );
		} else {
			$parserOutput->expects( $this->never() )
				->method( 'setUnsortedPageProperty' );
		}

		$instance = $this->newInstance( $propertyIds );

		foreach ( $statements as $statement ) {
			$instance->processStatement( $statement );
		}

		$instance->updateParserOutput( $parserOutput );
	}

	public static function bestImageProvider() {
		$statements = new StatementList();

		self::addStatement( $statements, 1, '1.jpg' );

		$statements->addNewStatement( new PropertyNoValueSnak( 2 ) );
		$statements->addNewStatement( new PropertySomeValueSnak( 2 ) );
		$statements->addNewStatement( new PropertyValueSnak( 2, new BooleanValue( true ) ) );
		self::addStatement( $statements, 2, '' );
		self::addStatement( $statements, 2, '2.jpg', Statement::RANK_DEPRECATED );

		$statements->addNewStatement( new PropertySomeValueSnak( 3 ) );
		self::addStatement( $statements, 3, '3a.jpg' );
		self::addStatement( $statements, 3, '3b.jpg' );

		self::addStatement( $statements, 4, 'Four 1.jpg', Statement::RANK_DEPRECATED );
		self::addStatement( $statements, 4, 'Four 2.jpg' );
		self::addStatement( $statements, 4, 'Four 3.jpg' );

		self::addStatement( $statements, 5, '5a.jpg' );
		self::addStatement( $statements, 4, '5b.jpg', Statement::RANK_DEPRECATED );
		self::addStatement( $statements, 5, '5c.jpg', Statement::RANK_PREFERRED );
		self::addStatement( $statements, 5, '5d.jpg' );
		self::addStatement( $statements, 5, '5e.jpg', Statement::RANK_PREFERRED );

		return [
			// Find nothing for various reasons.
			'Ignore non-strings' => [ $statements, [ 'P2' ], null ],
			'Property not found' => [ $statements, [ 'P9999' ], null ],
			'Not a property id' => [ $statements, [ 'Q1' ], null ],
			'Invalid id' => [ $statements, [ 'invalid' ], null ],

			// Configuration
			'Ignore misconfiguration' => [ $statements, [ 'P1', 'P2', 'P1' ], '1.jpg' ],
			'Ignore keys' => [ $statements, [ 2 => 'P1', 1 => 'P2' ], '1.jpg' ],

			// Simple searches.
			'Find 1' => [ $statements, [ 'P1' ], '1.jpg' ],
			'Skip non-strings' => [ $statements, [ 'P3' ], '3a.jpg' ],
			'Skip missing ids' => [ $statements, [ 'P9999', 'P1' ], '1.jpg' ],
			'Skip item ids' => [ $statements, [ 'Q1', 'P1' ], '1.jpg' ],
			'Skip invalid ids' => [ $statements, [ 'invalid', 'P1' ], '1.jpg' ],

			'Increasing order' => [ $statements, [ 'P1', 'P2', 'P3' ], '1.jpg' ],
			'Decreasing order' => [ $statements, [ 'P3', 'P2', 'P1' ], '3a.jpg' ],

			// Ranks
			'Skip deprecated' => [ $statements, [ 'P4' ], 'Four_2.jpg' ],
			'Prefer preferred' => [ $statements, [ 'P5' ], '5c.jpg' ],
			'Rank does not overrule priority' => [ $statements, [ 'P1', 'P5' ], '1.jpg' ],
		];
	}

}
