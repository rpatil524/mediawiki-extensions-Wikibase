<?php

namespace Wikibase\Repo\Hooks;

use MediaWiki\Hook\ChangesListInitRowsHook;
use MediaWiki\RecentChanges\ChangesList;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Wikibase\DataAccess\PrefetchingTermLookup;
use Wikibase\DataModel\Services\Term\TermBuffer;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Lib\TermIndexEntry;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Hook handlers for triggering prefetching of labels.
 *
 * Wikibase uses the HtmlPageLinkRendererEnd hook handler
 *
 * @see HtmlPageLinkRendererEndHookHandler
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class LabelPrefetchHookHandler implements ChangesListInitRowsHook {

	/**
	 * @var TermBuffer
	 */
	private $buffer;

	/**
	 * @var EntityIdLookup
	 */
	private $idLookup;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var string[]
	 */
	private $termTypes;

	/**
	 * @var LanguageFallbackChainFactory
	 */
	private $languageFallbackChainFactory;

	/**
	 * @var SummaryParsingPrefetchHelper
	 */
	private $summaryParsingPrefetchHelper;

	/**
	 * @return self
	 */
	public static function factory(
		TitleFactory $titleFactory,
		EntityIdLookup $entityIdLookup,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		PrefetchingTermLookup $prefetchingTermLookup,
		TermBuffer $termBuffer
	): self {
		$termTypes = [ TermIndexEntry::TYPE_LABEL, TermIndexEntry::TYPE_DESCRIPTION ];

		return new self(
			$termBuffer,
			$entityIdLookup,
			$titleFactory,
			$termTypes,
			$languageFallbackChainFactory,
			new SummaryParsingPrefetchHelper( $prefetchingTermLookup )
		);
	}

	/**
	 * @param TermBuffer $buffer
	 * @param EntityIdLookup $idLookup
	 * @param TitleFactory $titleFactory
	 * @param string[] $termTypes
	 * @param LanguageFallbackChainFactory $languageFallbackChainFactory
	 * @param SummaryParsingPrefetchHelper $summaryParsingPrefetchHelper
	 */
	public function __construct(
		TermBuffer $buffer,
		EntityIdLookup $idLookup,
		TitleFactory $titleFactory,
		array $termTypes,
		LanguageFallbackChainFactory $languageFallbackChainFactory,
		SummaryParsingPrefetchHelper $summaryParsingPrefetchHelper
	) {
		$this->buffer = $buffer;
		$this->idLookup = $idLookup;
		$this->titleFactory = $titleFactory;
		$this->termTypes = $termTypes;
		$this->languageFallbackChainFactory = $languageFallbackChainFactory;
		$this->summaryParsingPrefetchHelper = $summaryParsingPrefetchHelper;
	}

	/**
	 * @param ChangesList $list
	 * @param IResultWrapper|\stdClass[] $rows
	 */
	public function onChangesListInitRows( $list, $rows ): void {
		try {
			$titles = $this->getChangedTitles( $rows );
			$entityIds = $this->idLookup->getEntityIds( $titles );
			$languageCodes = $this->languageFallbackChainFactory->newFromContext( $list )
				->getFetchLanguageCodes();
			$this->buffer->prefetchTerms( $entityIds, $this->termTypes, $languageCodes );

			$this->summaryParsingPrefetchHelper->prefetchTermsForMentionedEntities(
				$rows,
				$languageCodes,
				$this->termTypes
			);

		} catch ( StorageException $ex ) {
			wfLogWarning( __METHOD__ . ': ' . $ex->getMessage() );
		}
	}

	/**
	 * @param IResultWrapper|\stdClass[] $rows
	 *
	 * @return Title[]
	 */
	private function getChangedTitles( $rows ) {
		$titles = [];

		foreach ( $rows as $row ) {
			$titles[] = $this->titleFactory->makeTitle( $row->rc_namespace, $row->rc_title );
		}

		return $titles;
	}
}
