<?php

declare( strict_types = 1 );
namespace Wikibase\Repo\Tests\Hooks;

use DifferenceEngine;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\IContextSource;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use Wikibase\DataAccess\PrefetchingTermLookup;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Term\TermTypes;
use Wikibase\Lib\ContentLanguages;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\LanguageWithConversion;
use Wikibase\Lib\Store\LinkTargetEntityIdLookup;
use Wikibase\Lib\TermLanguageFallbackChain;
use Wikibase\Repo\Hooks\DifferenceEngineViewHeaderHookHandler;
use Wikibase\Repo\Hooks\SummaryParsingPrefetchHelper;

/**
 * @covers \Wikibase\Repo\Hooks\DifferenceEngineViewHeaderHookHandler
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class DifferenceEngineViewHeaderHookHandlerTest extends TestCase {

	/** @var PrefetchingTermLookup */
	private $prefetchingLookup;
	/** @var Item */
	private $entity;

	/**
	 * @var LinkTargetEntityIdLookup
	 */
	private $linkTargetEntityIdLookup;

	/**
	 * @var LanguageFallbackChainFactory
	 */
	private $languageFallbackChainFactory;

	/**
	 * @var TermLanguageFallbackChain
	 */
	private $languageFallback;

	protected function setUp(): void {
		$this->prefetchingLookup = $this->createMock( PrefetchingTermLookup::class );
		$this->languageFallbackChainFactory = $this->createMock( LanguageFallbackChainFactory::class );
		$this->linkTargetEntityIdLookup = $this->createMock( LinkTargetEntityIdLookup::class );

		$stubContentLanguages = $this->createStub( ContentLanguages::class );
		$stubContentLanguages->method( 'hasLanguage' )
			->willReturn( true );

		$this->languageFallback = new TermLanguageFallbackChain( [
			LanguageWithConversion::factory( 'sv' ),
			LanguageWithConversion::factory( 'de' ),
			LanguageWithConversion::factory( 'en' ),
		], $stubContentLanguages );

		$this->languageFallbackChainFactory->method( 'newFromContext' )
			->withAnyParameters()
			->willReturn( $this->languageFallback );
	}

	public function testPrefetchesTerms() {

		$itemId = new ItemId( "Q1" );
		$this->entity = new Item( $itemId );

		$this->entity->getStatements()->addStatement( new Statement( new PropertyNoValueSnak( new NumericPropertyId( "P32456" ) ) ) );
		$this->entity->getStatements()->addStatement( new Statement( new PropertyNoValueSnak( new NumericPropertyId( "P12345" ) ) ) );

		$this->linkTargetEntityIdLookup->expects( $this->once() )
			->method( 'getEntityId' )
			->willReturn( $itemId );

		$rows = $this->getRevisionRecords();

		$this->prefetchingLookup->expects( $this->once() )
			->method( 'prefetchTerms' )
			->with(
				[
					new NumericPropertyId( "P32456" ),
					new ItemId( 'Q101' ),
					new NumericPropertyId( "P12345" ),
					new ItemId( 'Q102' ),
				],
				[ TermTypes::TYPE_LABEL, TermTypes::TYPE_DESCRIPTION ],
				[ 'sv', 'de', 'en' ]
			);

		$diffEngine = $this->getMockedDiffEngine( $rows[0], $rows[1], 'Q1' );

		$hook = $this->getNewHookHandler();

		$hook->onDifferenceEngineViewHeader( $diffEngine );
	}

	public function testPrefetchesTermsEntityIdNotFoundByTitle() {

		$itemId = new ItemId( "Q1" );
		$this->entity = new Item( $itemId );

		$this->entity->getStatements()->addStatement( new Statement( new PropertyNoValueSnak( 32456 ) ) );
		$this->entity->getStatements()->addStatement( new Statement( new PropertyNoValueSnak( 12345 ) ) );

		$rows = $this->getRevisionRecords();
		$this->linkTargetEntityIdLookup->expects( $this->once() )
			->method( 'getEntityId' )
			->willReturn( null );

		$this->prefetchingLookup->expects( $this->never() )
			->method( 'prefetchTerms' );

		$diffEngine = $this->getMockedDiffEngine( $rows[0], $rows[1], 'Q1' );

		$hook = $this->getNewHookHandler();

		$hook->onDifferenceEngineViewHeader( $diffEngine );
	}

	public function testPrefetchesTermsOldRevisionNotSet() {

		$itemId = new ItemId( "Q1" );
		$this->entity = new Item( $itemId );

		$this->entity->getStatements()->addStatement( new Statement( new PropertyNoValueSnak( new NumericPropertyId( "P4321" ) ) ) );

		$this->linkTargetEntityIdLookup->expects( $this->once() )
			->method( 'getEntityId' )
			->willReturn( $itemId );

		$rows = $this->getRevisionRecords();

		$this->prefetchingLookup->expects( $this->once() )
			->method( 'prefetchTerms' )
			->with(
				[
					new NumericPropertyId( "P4321" ),
					new ItemId( 'Q101' ),
				],
				[ TermTypes::TYPE_LABEL, TermTypes::TYPE_DESCRIPTION ],
				[ 'sv', 'de', 'en' ]
			);

		$diffEngine = $this->getMockedDiffEngine( null, $rows[0], 'Q1' );

		$hook = $this->getNewHookHandler();

		$hook->onDifferenceEngineViewHeader( $diffEngine );
	}

	private function getNewHookHandler() {
		return new DifferenceEngineViewHeaderHookHandler(
			$this->languageFallbackChainFactory,
			$this->linkTargetEntityIdLookup,
			new SummaryParsingPrefetchHelper( $this->prefetchingLookup )
		);
	}

	private function getRevisionRecords() {
		$availableProperties = array_map( function( $snak ) {
			return $snak->getPropertyId();
		}, $this->entity->getStatements()->getAllSnaks() );

		$i = 100;
		$rows = array_map( function ( $prop ) use ( &$i ) {
			$i++;
			$object = new MutableRevisionRecord( Title::newFromTextThrow( $prop->getSerialization() ) );
			$object->setComment( new CommentStoreComment(
				null,
				"[[Property:{$prop->getSerialization()}]] - [[Q$i]]"
			) );
			return $object;
		}, $availableProperties );

		return $rows;
	}

	private function getMockedDiffEngine( $getOldRevision, $getNewRevision, $titleText ) {
		$diffEngine = $this->createMock( DifferenceEngine::class );
		$diffEngine->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( Title::newFromTextThrow( $titleText ) );

		$diffEngine->method( 'getOldRevision' )
			->willReturn( $getOldRevision );

		$diffEngine->method( 'getNewRevision' )
			->willReturn( $getNewRevision );

		$diffEngine->method( 'getContext' )
			->willReturn( $this->createMock( IContextSource::class ) );

		$diffEngine->method( 'loadRevisionData' )
			->willReturn( true );

		return $diffEngine;
	}
}
