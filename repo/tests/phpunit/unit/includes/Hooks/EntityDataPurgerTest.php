<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\Unit\Hooks;

use MediaWiki\Cache\HTMLCacheUpdater;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\WikiPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Repo\Hooks\EntityDataPurger;
use Wikibase\Repo\LinkedData\EntityDataUriManager;

/**
 * @covers \Wikibase\Repo\Hooks\EntityDataPurger
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class EntityDataPurgerTest extends MediaWikiUnitTestCase {

	private function mockJobQueueGroupNoop(): JobQueueGroup {
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->never() )
			->method( 'push' );
		return $jobQueueGroup;
	}

	public function testGivenEntityIdLookupReturnsNull_handlerDoesNothing() {
		$title = Title::makeTitle( NS_PROJECT, 'About' );
		$entityIdLookup = $this->createMock( EntityIdLookup::class );
		$entityIdLookup->expects( $this->once() )
			->method( 'getEntityIdForTitle' )
			->with( $title )
			->willReturn( null );
		$entityDataUriManager = $this->createMock( EntityDataUriManager::class );
		$entityDataUriManager->expects( $this->never() )
			->method( 'getPotentiallyCachedUrls' );
		$htmlCacheUpdater = $this->createMock( HTMLCacheUpdater::class );
		$htmlCacheUpdater->expects( $this->never() )
			->method( 'purgeUrls' );
		$purger = new EntityDataPurger(
			$entityIdLookup,
			$entityDataUriManager,
			$htmlCacheUpdater,
			$this->mockJobQueueGroupNoop(),
			$this->createMock( TitleFactory::class )
		);

		$purger->onArticleRevisionVisibilitySet( $title, [ 1, 2, 3 ], [] );
	}

	public function testGivenEntityIdLookupReturnsId_handlerPurgesCache() {
		$title = Title::makeTitle( WB_NS_ITEM, 'Q1' );
		$entityId = new ItemId( 'Q1' );
		$entityIdLookup = $this->createMock( EntityIdLookup::class );
		$entityIdLookup->expects( $this->once() )
			->method( 'getEntityIdForTitle' )
			->with( $title )
			->willReturn( $entityId );
		$entityDataUriManager = $this->createMock( EntityDataUriManager::class );
		$entityDataUriManager->expects( $this->once() )
			->method( 'getPotentiallyCachedUrls' )
			->with( $entityId, 1 )
			->willReturn( [ 'urlA/Q1/1', 'urlB/Q1/1' ] );
		$htmlCacheUpdater = $this->createMock( HTMLCacheUpdater::class );
		$htmlCacheUpdater->expects( $this->once() )
			->method( 'purgeUrls' )
			->with( [ 'urlA/Q1/1', 'urlB/Q1/1' ] );
		$purger = new EntityDataPurger(
			$entityIdLookup,
			$entityDataUriManager,
			$htmlCacheUpdater,
			$this->mockJobQueueGroupNoop(),
			$this->createMock( TitleFactory::class )
		);

		$purger->onArticleRevisionVisibilitySet( $title, [ 1 ], [] );
	}

	public function testGivenMultipleRevisions_handlerPurgesCacheOnce() {
		$title = Title::makeTitle( WB_NS_ITEM, 'Q1' );
		$entityId = new ItemId( 'Q1' );
		$entityIdLookup = $this->createMock( EntityIdLookup::class );
		$entityIdLookup->expects( $this->once() )
			->method( 'getEntityIdForTitle' )
			->with( $title )
			->willReturn( $entityId );
		$entityDataUriManager = $this->createMock( EntityDataUriManager::class );
		$entityDataUriManager
			->method( 'getPotentiallyCachedUrls' )
			->willReturnMap( [
				[ $entityId, 1, [ 'urlA/Q1/1', 'urlB/Q1/1' ] ],
				[ $entityId, 2, [ 'urlA/Q1/2', 'urlB/Q1/2' ] ],
				[ $entityId, 3, [ 'urlA/Q1/3', 'urlB/Q1/3' ] ],
			] );
		$htmlCacheUpdater = $this->createMock( HTMLCacheUpdater::class );
		$htmlCacheUpdater->expects( $this->once() )
			->method( 'purgeUrls' )
			->with( [
				'urlA/Q1/1', 'urlB/Q1/1',
				'urlA/Q1/2', 'urlB/Q1/2',
				'urlA/Q1/3', 'urlB/Q1/3',
			] );
		$purger = new EntityDataPurger(
			$entityIdLookup,
			$entityDataUriManager,
			$htmlCacheUpdater,
			$this->mockJobQueueGroupNoop(),
			$this->createMock( TitleFactory::class )
		);

		$purger->onArticleRevisionVisibilitySet( $title, [ 1, 2, 3 ], [] );
	}

	public function testDeletionHandlerPushesJob() {
		$title = Title::makeTitle( 0, 'Q123' );
		$titleFactory = $this->createConfiguredMock( TitleFactory::class, [
			 'newFromPageIdentity' => $title,
		] );
		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->method( 'getTitle' )
			->willReturn( $title );

		$entityIdLookup = $this->createMock( EntityIdLookup::class );
		$entityIdLookup->method( 'getEntityIdForTitle' )
			->with( $title )
			->willReturn( new ItemId( 'Q123' ) );
		$entityDataUriManager = $this->createMock( EntityDataUriManager::class );
		$entityDataUriManager->expects( $this->never() )
			->method( 'getPotentiallyCachedUrls' );
		$htmlCacheUpdater = $this->createMock( HTMLCacheUpdater::class );
		$htmlCacheUpdater->expects( $this->never() )
			->method( 'purgeUrls' );

		$actualJob = null;
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->once() )
			->method( 'lazyPush' )
			->willReturnCallback( function ( IJobSpecification $job ) use ( &$actualJob ) {
				$actualJob = $job;
			} );

		$purger = new EntityDataPurger(
			$entityIdLookup,
			$entityDataUriManager,
			$htmlCacheUpdater,
			$jobQueueGroup,
			$titleFactory
		);

		$purger->onPageDeleteComplete(
			PageIdentityValue::localIdentity( 1, 0, "nothing" ),
			// unused
			$this->createMock( Authority::class ),
			"no reason",
			123,
			// unused
			$this->createMock( RevisionRecord::class ),
			$this->createMock( ManualLogEntry::class ),
			1
		);

		$this->assertSame( 'PurgeEntityData', $actualJob->getType() );
		$this->assertArrayContains( [
			'namespace' => 0,
			'title' => 'Q123',
			'pageId' => 123,
			'entityId' => 'Q123',
		], $actualJob->getParams() );
	}
}
