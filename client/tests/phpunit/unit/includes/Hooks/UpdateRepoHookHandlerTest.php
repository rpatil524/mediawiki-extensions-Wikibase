<?php

declare( strict_types = 1 );

namespace Wikibase\Client\Tests\Unit\Hooks;

use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueue;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Wikibase\Client\Hooks\UpdateRepoHookHandler;
use Wikibase\Client\NamespaceChecker;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Rdbms\ClientDomainDb;
use Wikibase\Lib\Rdbms\ReplicationWaiter;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * @covers \Wikibase\Client\Hooks\UpdateRepoHookHandler
 *
 * @group WikibaseClient
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class UpdateRepoHookHandlerTest extends TestCase {

	public static function doArticleDeleteCompleteProvider() {
		yield 'Success' => [
			'expectsSuccess' => true,
			'propagateChangesToRepo' => true,
			'itemId' => new ItemId( 'Q42' ),
		];
		yield 'propagateChangesToRepo set to false' => [
			'expectsSuccess' => false,
			'propagateChangesToRepo' => false,
			'itemId' => new ItemId( 'Q42' ),
		];
		yield 'Not connected to an item' => [
			'expectsSuccess' => false,
			'propagateChangesToRepo' => true,
			'itemId' => null,
		];
	}

	/**
	 * @dataProvider doArticleDeleteCompleteProvider
	 */
	public function testDoArticleDeleteComplete(
		bool $expectsSuccess,
		bool $propagateChangesToRepo,
		?ItemId $itemId
	): void {
		$handler = $this->newUpdateRepoHookHandlers(
			true,
			$expectsSuccess ? 'UpdateRepoOnDelete' : null,
			$propagateChangesToRepo,
			$itemId,
		);

		$this->assertTrue(
			$handler->onPageDeleteComplete(
				PageIdentityValue::localIdentity( 1, 0, "something" ),
				$this->createMock( User::class ),
				"some reason",
				0,
				$this->createMock( RevisionRecord::class ),
				$this->createMock( ManualLogEntry::class ),
				1
			)
		);
	}

	public static function doPageMoveCompleteProvider() {
		yield 'Regular move with redirect' => [
			'jobName' => 'UpdateRepoOnMove',
			'isWikibaseEnabled' => true,
			'propagateChangesToRepo' => true,
			'itemId' => new ItemId( 'Q42' ),
			'redirectExists' => true,
		];
		yield 'Move without redirect' => [
			'jobName' => 'UpdateRepoOnMove',
			'isWikibaseEnabled' => true,
			'propagateChangesToRepo' => true,
			'itemId' => new ItemId( 'Q42' ),
			'redirectExists' => false,
		];
		yield 'Moved into non-Wikibase NS with redirect' => [
			'jobName' => null,
			'isWikibaseEnabled' => false,
			'propagateChangesToRepo' => true,
			'itemId' => new ItemId( 'Q42' ),
			'redirectExists' => true,
		];
		yield 'Moved into non-Wikibase NS without redirect' => [
			'jobName' => 'UpdateRepoOnDelete',
			'isWikibaseEnabled' => false,
			'propagateChangesToRepo' => true,
			'itemId' => new ItemId( 'Q42' ),
			'redirectExists' => false,
		];
		yield 'propagateChangesToRepo set to false' => [
			'jobName' => null,
			'isWikibaseEnabled' => true,
			'propagateChangesToRepo' => false,
			'itemId' => new ItemId( 'Q42' ),
			'redirectExists' => true,
		];
		yield 'Not connected to an item' => [
			'jobName' => null,
			'isWikibaseEnabled' => true,
			'propagateChangesToRepo' => false,
			'itemId' => null,
			'redirectExists' => true,
		];
	}

	/**
	 * @dataProvider doPageMoveCompleteProvider
	 */
	public function testDoPageMoveComplete(
		?string $jobName,
		bool $isWikibaseEnabled,
		bool $propagateChangesToRepo,
		?ItemId $itemId,
		bool $redirectExists
	): void {
		$handler = $this->newUpdateRepoHookHandlers(
			$isWikibaseEnabled,
			$jobName,
			$propagateChangesToRepo,
			$itemId
		);
		$oldTitle = $this->getTitle();
		$newTitle = $this->getTitle();

		$this->assertTrue(
			$handler->onPageMoveComplete(
				$oldTitle,
				$newTitle,
				$this->createMock( User::class ),
				0,
				$redirectExists ? 1 : 0,
				'',
				$this->createMock( RevisionRecord::class )
			)
		);
	}

	private function getTitle(): Title {
		// get a Title mock with all methods mocked except the magics __get and __set to
		// allow the DeprecationHelper trait methods to work and handle non-existing class variables
		// correctly, see UpdateRepoHookHandlers.php:doArticleDeleteComplete
		$title = $this->createPartialMock(
			Title::class,
			array_diff( get_class_methods( Title::class ), [ '__get', '__set' ] )
		);
		$title->method( 'getPrefixedText' )
			->willReturn( 'UpdateRepoHookHandlersTest' );

		return $title;
	}

	private function newUpdateRepoHookHandlers(
		bool $isWikibaseEnabled,
		?string $jobName,
		bool $propagateChangesToRepo,
		?ItemId $itemId
	): UpdateRepoHookHandler {
		$namespaceChecker = $this->createMock( NamespaceChecker::class );
		$namespaceChecker->method( 'isWikibaseEnabled' )
			->willReturn( $isWikibaseEnabled );

		$jobQueue = $this->getMockBuilder( JobQueue::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'supportsDelayedJobs' ] )
			->getMockForAbstractClass();
		$jobQueue->method( 'supportsDelayedJobs' )
			->willReturn( true );

		$jobQueueGroupFactory = $this->createMock( JobQueueGroupFactory::class );
		if ( $jobName !== null ) {
			$jobQueueGroup = $this->createMock( JobQueueGroup::class );
			$jobQueueGroup->expects( $this->once() )
				->method( 'push' )
				->with( $this->isInstanceOf( IJobSpecification::class ) );
			$jobQueueGroup->expects( $this->once() )
				->method( 'get' )
				->with( $jobName )
				->willReturn( $jobQueue );
			$jobQueueGroupFactory->expects( $this->once() )
				->method( 'makeJobQueueGroup' )
				->with( 'entitySourceDbName' )
				->willReturn( $jobQueueGroup );
		} else {
			$jobQueueGroupFactory->expects( $this->never() )
				->method( 'makeJobQueueGroup' );
		}

		$entitySource = $this->createConfiguredMock( DatabaseEntitySource::class, [
			'getDatabaseName' => 'entitySourceDbName',
		] );

		$siteLinkLookup = $this->createMock( SiteLinkLookup::class );
		$siteLinkLookup->method( 'getItemIdForLink' )
			->with( 'clientwiki', 'UpdateRepoHookHandlersTest' )
			->willReturn( $itemId );

		$replicationWaiter = $this->createMock( ReplicationWaiter::class );
		$replicationWaiter->method( 'getMaxLag' )
			->willReturn( [ '', -1, 0 ] );

		$clientDb = $this->createMock( ClientDomainDb::class );
		$clientDb->method( 'replication' )
			->willReturn( $replicationWaiter );

		$titleFactory = $this->createConfiguredMock( TitleFactory::class, [
			'newFromPageIdentity' => $this->getTitle(),
			'newFromLinkTarget' => $this->getTitle(),
		] );

		return new UpdateRepoHookHandler(
			$namespaceChecker,
			$jobQueueGroupFactory,
			$titleFactory,
			$entitySource,
			$siteLinkLookup,
			new NullLogger(),
			$clientDb,
			'clientwiki',
			$propagateChangesToRepo
		);
	}

}
