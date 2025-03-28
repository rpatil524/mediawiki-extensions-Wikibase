<?php

namespace Wikibase\Repo\Api;

use InvalidArgumentException;
use Wikibase\DataAccess\EntitySourceLookup;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Interactors\TermSearchResult;

/**
 * EntitySearchHelper decorator that adds an entity concept URI to the TermSearchResult meta data if not already set.
 * This works in conjunction with ApiEntitySearchHelper for federated properties that already includes the concept URI in the metadata.
 *
 * @license GPL-2.0-or-later
 */
class ConceptUriSearchHelper implements EntitySearchHelper {

	public const CONCEPTURI_META_DATA_KEY = TermSearchResult::CONCEPTURI_META_DATA_KEY;

	/**
	 * @var EntitySearchHelper
	 */
	private $searchHelper;

	/**
	 * @var EntitySourceLookup
	 */
	private $entitySourceLookup;

	public function __construct( EntitySearchHelper $searchHelper, EntitySourceLookup $entitySourceLookup ) {
		$this->searchHelper = $searchHelper;
		$this->entitySourceLookup = $entitySourceLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function getRankedSearchResults(
		$text,
		$languageCode,
		$entityType,
		$limit,
		$strictLanguage,
		?string $profileContext
	) {
		$results = $this->searchHelper->getRankedSearchResults(
			$text,
			$languageCode,
			$entityType,
			$limit,
			$strictLanguage,
			$profileContext
		);

		return array_map( function ( TermSearchResult $searchResult ) {
			// Do not set the concept URI if it is already set.
			if ( array_key_exists( self::CONCEPTURI_META_DATA_KEY, $searchResult->getMetaData() ) ) {
				return $searchResult;
			}

			$entityId = $searchResult->getEntityId();
			if ( $entityId === null ) {
				throw new InvalidArgumentException(
					'Invalid TermSearchResult:' .
					' if id is null, then ' . self::CONCEPTURI_META_DATA_KEY .
					' must be set in the metadata!'
				);
			}

			return new TermSearchResult(
				$searchResult->getMatchedTerm(),
				$searchResult->getMatchedTermType(),
				$entityId,
				$searchResult->getDisplayLabel(),
				$searchResult->getDisplayDescription(),
				array_merge(
					$searchResult->getMetaData(),
					[ self::CONCEPTURI_META_DATA_KEY => $this->getConceptUri( $entityId ) ]
				) );
		}, $results );
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return string
	 */
	private function getConceptUri( EntityId $entityId ) {
		$baseUri = $this->getConceptBaseUri( $entityId );
		return $baseUri . wfUrlencode( $entityId->getSerialization() );
	}

	private function getConceptBaseUri( EntityId $entityId ): string {
		return $this->entitySourceLookup->getEntitySourceById( $entityId )->getConceptBaseUri();
	}

}
