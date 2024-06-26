<?php

declare( strict_types=1 );

namespace Wikibase\Client\Tests\Integration\Store\Sql;

use MediaWikiIntegrationTestCase;
use Onoi\MessageReporter\MessageReporter;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use Wikibase\Client\Store\Sql\BulkSubscriptionUpdater;
use Wikibase\Client\Usage\Sql\EntityUsageTable;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Reporting\ExceptionHandler;
use Wikibase\Lib\WikibaseSettings;

/**
 * @covers \Wikibase\Client\Store\Sql\BulkSubscriptionUpdater
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseUsageTracking
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class BulkSubscriptionUpdaterTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		if ( !WikibaseSettings::isRepoEnabled() ) {
			$this->markTestSkipped( "Skipping because WikibaseClient doesn't have a local wb_changes_subscription table." );
		}

		parent::setUp();
	}

	/**
	 * @param int $batchSize
	 *
	 * @return BulkSubscriptionUpdater
	 */
	private function getBulkSubscriptionUpdater( $batchSize = 10 ): BulkSubscriptionUpdater {
		return new BulkSubscriptionUpdater(
			WikibaseClient::getClientDomainDbFactory()->newLocalDb(),
			WikibaseClient::getRepoDomainDbFactory()->newRepoDb(),
			'testwiki',
			$batchSize
		);
	}

	public function testPurgeSubscriptions(): void {
		$this->putSubscriptions( [
			[ 'P11', 'dewiki' ],
			[ 'Q11', 'dewiki' ],
			[ 'Q22', 'dewiki' ],
			[ 'Q22', 'frwiki' ],
			[ 'P11', 'testwiki' ],
			[ 'Q11', 'testwiki' ],
			[ 'Q22', 'testwiki' ],
		] );

		$updater = $this->getBulkSubscriptionUpdater( 2 );
		$updater->setProgressReporter( $this->getMessageReporter( $this->exactly( 2 ) ) );

		$updater->purgeSubscriptions();

		$actual = $this->fetchAllSubscriptions();
		sort( $actual );

		$expected = [
			'dewiki@P11',
			'dewiki@Q11',
			'dewiki@Q22',
			'frwiki@Q22',
		];

		$this->assertEquals( $expected, $actual );
	}

	public function testPurgeSubscriptions_startItem(): void {
		$this->putSubscriptions( [
			[ 'P11', 'dewiki' ],
			[ 'Q11', 'dewiki' ],
			[ 'Q22', 'dewiki' ],
			[ 'Q22', 'frwiki' ],
			[ 'P11', 'testwiki' ],
			[ 'Q11', 'testwiki' ],
			[ 'Q22', 'testwiki' ],
		] );

		$updater = $this->getBulkSubscriptionUpdater( 2 );
		$updater->setProgressReporter( $this->getMessageReporter( $this->once() ) );

		$updater->purgeSubscriptions( new ItemId( 'Q20' ) );

		$actual = $this->fetchAllSubscriptions();
		sort( $actual );

		$expected = [
			'dewiki@P11',
			'dewiki@Q11',
			'dewiki@Q22',
			'frwiki@Q22',
			'testwiki@P11',
			'testwiki@Q11',
		];

		$this->assertEquals( $expected, $actual );
	}

	public function testUpdateSubscriptions(): void {
		$this->putSubscriptions( [
			[ 'P11', 'dewiki' ],
			[ 'Q11', 'dewiki' ],
			[ 'Q22', 'dewiki' ],
			[ 'Q22', 'frwiki' ],
		] );
		$this->putEntityUsage( [
			[ 'P11', 11 ],
			[ 'Q11', 11 ],
			[ 'Q22', 22 ],
			[ 'Q22', 33 ],
		] );

		$updater = $this->getBulkSubscriptionUpdater( 2 );
		$updater->setProgressReporter( $this->getMessageReporter( $this->exactly( 2 ) ) );

		$updater->updateSubscriptions();

		$actual = $this->fetchAllSubscriptions();
		sort( $actual );

		$expected = [
			'dewiki@P11',
			'dewiki@Q11',
			'dewiki@Q22',
			'frwiki@Q22',
			'testwiki@P11',
			'testwiki@Q11',
			'testwiki@Q22',
		];

		$this->assertEquals( $expected, $actual );
	}

	public function testUpdateSubscriptions_startItem(): void {
		$this->putSubscriptions( [
			[ 'P11', 'dewiki' ],
			[ 'Q11', 'dewiki' ],
			[ 'Q22', 'dewiki' ],
			[ 'Q22', 'frwiki' ],
		] );
		$this->putEntityUsage( [
			[ 'P11', 11 ],
			[ 'Q11', 11 ],
			[ 'Q22', 22 ],
			[ 'Q22', 33 ],
		] );

		$updater = $this->getBulkSubscriptionUpdater( 2 );
		$updater->setProgressReporter( $this->getMessageReporter( $this->once() ) );

		$updater->updateSubscriptions( new ItemId( 'Q20' ) );

		$actual = $this->fetchAllSubscriptions();
		sort( $actual );

		$expected = [
			'dewiki@P11',
			'dewiki@Q11',
			'dewiki@Q22',
			'frwiki@Q22',
			'testwiki@Q22',
		];

		$this->assertEquals( $expected, $actual );
	}

	private function putEntityUsage( array $entries ): void {
		$rows = [];
		foreach ( $entries as [ $entityId, $pageId ] ) {
			$rows[] = [
				'eu_entity_id' => $entityId,
				'eu_aspect' => 'X',
				'eu_page_id' => (int)$pageId,
			];
		}
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( EntityUsageTable::DEFAULT_TABLE_NAME )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	private function putSubscriptions( array $entries ): void {
		$rows = [];
		foreach ( $entries as [ $entityId, $subscriberId ] ) {
			$rows[] = [
				'cs_entity_id' => $entityId,
				'cs_subscriber_id' => $subscriberId,
			];
		}
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'wb_changes_subscription' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	private function fetchAllSubscriptions(): array {
		$res = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'cs_subscriber_id', 'cs_entity_id' ] )
			->from( 'wb_changes_subscription' )
			->caller( __METHOD__ )->fetchResultSet();

		$subscriptions = [];
		foreach ( $res as $row ) {
			$subscriptions[] = $row->cs_subscriber_id . '@' . $row->cs_entity_id;
		}

		return $subscriptions;
	}

	private function getExceptionHandler( InvokedCount $matcher ): ExceptionHandler {
		$mock = $this->createMock( ExceptionHandler::class );
		$mock->expects( $matcher )
			->method( 'handleException' );

		return $mock;
	}

	private function getMessageReporter( InvokedCount $matcher ): MessageReporter {
		$mock = $this->createMock( MessageReporter::class );
		$mock->expects( $matcher )
			->method( 'reportMessage' );

		return $mock;
	}

}
