<?php

declare( strict_types=1 );

namespace Wikibase\Client\Usage\Sql;

use Exception;
use InvalidArgumentException;
use Onoi\MessageReporter\MessageReporter;
use Onoi\MessageReporter\NullMessageReporter;
use Wikibase\Client\Usage\EntityUsage;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\Rdbms\ClientDomainDb;
use Wikibase\Lib\Reporting\ExceptionHandler;
use Wikibase\Lib\Reporting\LogWarningExceptionHandler;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Implements initial population (priming) for the wbc_entity_usage table,
 * based on "wikibase_item" entries in the page_props table.
 *
 * All usages will be marked as EntityUsage::ALL_USAGE ("X"), since we do not know
 * which aspects are actually used beyond the sitelinks aspect. The "X" aspect
 * will cause the page to be purged for any kind of change to the respective
 * data item; once the page is re-parse, the "X" aspect would be removed with
 * whatever aspect(s) are actually used on the page.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class EntityUsageTableBuilder {

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var string
	 */
	private $usageTableName;

	/**
	 * @var int
	 */
	private $batchSize;

	/**
	 * @var ExceptionHandler
	 */
	private $exceptionHandler;

	/**
	 * @var MessageReporter
	 */
	private $progressReporter;

	/**
	 * @var ClientDomainDb
	 */
	private $domainDb;

	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		EntityIdParser $idParser,
		ClientDomainDb $domainDb,
		int $batchSize = 1000,
		?string $usageTableName = null
	) {
		if ( $batchSize < 1 ) {
			throw new InvalidArgumentException( '$batchSize must be an integer >= 1' );
		}

		$this->idParser = $idParser;
		$this->domainDb = $domainDb;
		$this->batchSize = $batchSize;
		$this->usageTableName = $usageTableName ?: EntityUsageTable::DEFAULT_TABLE_NAME;

		$this->exceptionHandler = new LogWarningExceptionHandler();
		$this->progressReporter = new NullMessageReporter();
	}

	public function setProgressReporter( MessageReporter $progressReporter ): void {
		$this->progressReporter = $progressReporter;
	}

	public function setExceptionHandler( ExceptionHandler $exceptionHandler ): void {
		$this->exceptionHandler = $exceptionHandler;
	}

	/**
	 * Fill the usage table with rows based on entries in page_props.
	 */
	public function fillUsageTable( int $fromPageId = 0 ): void {
		do {
			$count = $this->processUsageBatch( $fromPageId );
			$this->progressReporter->reportMessage( "Filling usage table: processed $count pages, starting with page #$fromPageId." );
		} while ( $count > 0 );
	}

	/**
	 * @param int &$fromPageId Page ID to start from. Will be updated with the next unprocessed ID,
	 *        to be used as the starting point of the next batch. Pages are processed in order
	 *        of their ID.
	 *
	 * @return int The number of entity usages inserted.
	 */
	private function processUsageBatch( int &$fromPageId = 0 ): int {
		$this->domainDb->replication()->wait();
		$this->domainDb->autoReconfigure();

		$connections = $this->domainDb->connections();
		$dbw = $connections->getWriteConnection();

		$entityPerPage = $this->getUsageBatch( $dbw, $fromPageId );

		if ( !$entityPerPage ) {
			return 0;
		}

		$count = $this->insertUsageBatch( $dbw, $entityPerPage );

		// Update $fromPageId to become the first page ID of the next batch.
		$fromPageId = max( array_keys( $entityPerPage ) ) + 1;

		return $count;
	}

	/**
	 * @param IDatabase $dbw
	 * @param EntityId[] $entityPerPage
	 *
	 * @return int The number of rows inserted.
	 */
	private function insertUsageBatch( IDatabase $dbw, array $entityPerPage ): int {
		$dbw->startAtomic( __METHOD__ );

		$c = 0;
		foreach ( $entityPerPage as $pageId => $entityId ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( $this->usageTableName )
				->ignore()
				->row( [
					'eu_page_id' => (int)$pageId,
					'eu_aspect' => EntityUsage::ALL_USAGE,
					'eu_entity_id' => $entityId->getSerialization(),
				] )
				->caller( __METHOD__ )->execute();

			$c++;
		}

		$dbw->endAtomic( __METHOD__ );
		return $c;
	}

	/**
	 * @return EntityId[] An associative array mapping page IDs to Entity IDs.
	 */
	private function getUsageBatch( IReadableDatabase $db, int $fromPageId = 0 ): array {
		$res = $db->newSelectQueryBuilder()
			->select( [ 'pp_page', 'pp_value' ] )
			->from( 'page_props' )
			->where( [
				'pp_propname' => 'wikibase_item',
				$db->expr( 'pp_page', '>=', $fromPageId ),
			] )
			->orderBy( 'pp_page' )
			->limit( $this->batchSize )
			->caller( __METHOD__ )->fetchResultSet();

		return $this->slurpEntityIds( $res );
	}

	/**
	 * @return EntityId[] An associative array mapping page IDs to Entity IDs.
	 */
	private function slurpEntityIds( IResultWrapper $res ): array {
		$entityPerPage = [];

		foreach ( $res as $row ) {
			try {
				$entityId = $this->idParser->parse( $row->pp_value );
				$entityPerPage[$row->pp_page] = $entityId;
			} catch ( Exception $ex ) {
				$this->exceptionHandler->handleException(
					$ex,
					'badEntityId',
					__METHOD__ . ': ' . 'Failed to parse entity ID: ' .
						$row->pp_value . ' at page ' .
						$row->pp_page
				);
			}
		}

		return $entityPerPage;
	}

}
