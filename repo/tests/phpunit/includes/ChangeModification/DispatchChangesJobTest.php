<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\ChangeModification;

use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Changes\EntityChange;
use Wikibase\Lib\Changes\EntityDiffChangedAspects;
use Wikibase\Lib\Changes\ItemChange;
use Wikibase\Lib\Store\Sql\SqlChangeStore;
use Wikibase\Lib\WikibaseSettings;
use Wikibase\Repo\ChangeModification\DispatchChangesJob;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\ChangeModification\DispatchChangesJob
 *
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class DispatchChangesJobTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		$wiki = WikiMap::getCurrentWikiDbDomain()->getId();
		$newRepoSettings = $this->getConfVar( 'WBRepoSettings' );
		$newRepoSettings['localClientDatabases'] = [ $wiki => $wiki ];
		$this->overrideConfigValue( 'WBRepoSettings', $newRepoSettings );
	}

	public function testDispatchJobForSingleChangeToSingleWiki(): void {
		$this->skipIfClientNotEnabled();

		$testItemChange = $this->makeNewChange();
		$repoDb = WikibaseRepo::getRepoDomainDbFactory()->newRepoDb();
		$changeStore = new SqlChangeStore( $repoDb );
		$changeStore->saveChange( $testItemChange );

		$wiki = WikiMap::getCurrentWikiDbDomain()->getId();
		$dbw = $repoDb->connections()->getWriteConnection();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'wb_changes_subscription' )
			->row( [
				'cs_entity_id' => 'Q1',
				'cs_subscriber_id' => $wiki,
			] )
			->caller( __METHOD__ )
			->execute();

		$dispatchChangesJob = DispatchChangesJob::newFromGlobalState(
			null,
			[
				'entityId' => 'Q1',
			]
		);

		$dispatchChangesJob->run();

		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup( $wiki );
		$queuedJobs = $jobQueueGroup->get( 'EntityChangeNotification' )->getAllQueuedJobs();
		$job = $queuedJobs->current();
		$this->assertNotNull( $job );
		$actualItemChangeFields = $job->getParams()['changes'][0];
		$expectedItemChangeFields = $testItemChange->getFields();
		$expectedItemChangeFields['info'] = $testItemChange->getSerializedInfo();
		$this->assertSame( $expectedItemChangeFields, $actualItemChangeFields );
		$this->assertSame( 0,
			$dbw->newSelectQueryBuilder()
				->table( 'wb_changes' )
				->caller( __METHOD__ )->fetchRowCount(),
			'change should be deleted from `wb_changes`' );
	}

	public function testNoValidSubscribers(): void {
		$this->skipIfClientNotEnabled();

		$testItemChange = $this->makeNewChange();
		$changeStore = new SqlChangeStore(
			WikibaseRepo::getRepoDomainDbFactory()->newRepoDb()
		);
		$changeStore->saveChange( $testItemChange );

		$wiki = WikiMap::getCurrentWikiDbDomain()->getId();
		$dbw = WikibaseRepo::getRepoDomainDbFactory()->newRepoDb()->connections()->getWriteConnection();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'wb_changes_subscription' )
			->row( [
				'cs_entity_id' => 'Q1',
				'cs_subscriber_id' => 'client',
			] )
			->caller( __METHOD__ )
			->execute();

		$dispatchChangesJob = DispatchChangesJob::newFromGlobalState( null, [ 'entityId' => 'Q1' ] );

		$dispatchChangesJob->run();

		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup( $wiki );
		$this->assertTrue( $jobQueueGroup->get( 'EntityChangeNotification' )->isEmpty() );
	}

	public function testMissingId(): void {
		$this->skipIfClientNotEnabled();

		$wiki = WikiMap::getCurrentWikiDbDomain()->getId();
		$dbw = WikibaseRepo::getRepoDomainDbFactory()->newRepoDb()->connections()->getWriteConnection();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'wb_changes_subscription' )
			->row( [
				'cs_entity_id' => 'Q1',
				'cs_subscriber_id' => $wiki,
			] )
			->caller( __METHOD__ )
			->execute();

		$dispatchChangesJob = DispatchChangesJob::newFromGlobalState( null, [ 'entityId' => 'Q1' ] );

		$dispatchChangesJob->run();

		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup( $wiki );
		$this->assertTrue( $jobQueueGroup->get( 'EntityChangeNotification' )->isEmpty() );
	}

	public function testSitelinkAdded(): void {
		$this->skipIfClientNotEnabled();
		$wiki = WikiMap::getCurrentWikiDbDomain()->getId();

		$testItemId = new ItemId( 'Q50' );
		$testItemChange = new ItemChange( [
			'time' => '20210906122813',
			'info' => [
				'compactDiff' => new EntityDiffChangedAspects( [], [], [], [], [
					$wiki => [ null, 'some_page', false ],
				], false ),
				'metadata' => [
					'page_id' => 3,
					'rev_id' => 123,
					'parent_id' => 4,
					'comment' => '...',
					'user_text' => 'Admin',
					'central_user_id' => 0,
					'bot' => 0,
				],
			],
			'user_id' => '43',
			'revision_id' => '123',
			'object_id' => 'Q50',
			'type' => 'wikibase-item~update',
		] );
		$testItemChange->setEntityId( $testItemId );

		$changeStore = new SqlChangeStore(
			WikibaseRepo::getRepoDomainDbFactory()->newRepoDb()
		);
		$changeStore->saveChange( $testItemChange );

		$dispatchChangesJob = DispatchChangesJob::newFromGlobalState( null, [ 'entityId' => 'Q50' ] );

		$dispatchChangesJob->run();

		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup( $wiki );
		$this->assertFalse( $jobQueueGroup->get( 'EntityChangeNotification' )->isEmpty() );
	}

	private function makeNewChange(): EntityChange {
		$testItemId = new ItemId( 'Q1' );
		$testItemChange = new ItemChange( [
			'time' => '20210906122813',
			'info' => [
				'compactDiff' => new EntityDiffChangedAspects( [], [], [ 'P1' ], [], [], false ),
				'metadata' => [
					'page_id' => 3,
					'rev_id' => 123,
					'parent_id' => 4,
					'comment' => '/* wbsetclaim-update:2||1 */ [[Property:P1]]: string on first item: foo 1',
					'user_text' => 'Admin',
					'central_user_id' => 0,
					'bot' => 0,
				],
			],
			'user_id' => '43',
			'revision_id' => '123',
			'object_id' => 'Q1',
			'type' => 'wikibase-item~update',
		] );
		$testItemChange->setEntityId( $testItemId );

		return $testItemChange;
	}

	private function skipIfClientNotEnabled() {
		if ( !WikibaseSettings::isClientEnabled() ) {
			$this->markTestSkipped( 'Client is not enabled.' );
		}
	}
}
