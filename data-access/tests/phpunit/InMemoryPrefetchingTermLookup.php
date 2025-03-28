<?php

declare( strict_types=1 );

namespace Wikibase\DataAccess\Tests;

use RuntimeException;
use Wikibase\DataAccess\PrefetchingTermLookup;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Term\TermTypes;

/**
 * A PrefetchingTermLookup looking up terms for a set of entities stored in memory,
 * and using no other / external data source.
 *
 * Provided for use in tests only.
 *
 * @license GPL-2.0-or-later
 */
class InMemoryPrefetchingTermLookup implements PrefetchingTermLookup {

	/** @var (string|string[])[][] */
	private $buffer;
	private array $entityData;
	private bool $loadEntitiesIfNotPrefetched;

	/**
	 * @param bool $loadEntitiesIfNotPrefetched If true, normal get terms methods
	 * will return data regardless of whether those terms were prefetched or not.
	 * If false, data must be prefetched to be returned.
	 * This can be used to test that the class is being used correctly
	 * (terms are always prefetched before being accessed).
	 */
	public function __construct( bool $loadEntitiesIfNotPrefetched = true ) {
		$this->loadEntitiesIfNotPrefetched = $loadEntitiesIfNotPrefetched;
	}

	/**
	 * Takes a list of entities that to provide termLookups for
	 *
	 * @param EntityDocument[] $entityData
	 */
	public function setData( array $entityData ) {
		foreach ( $entityData as $entityDatum ) {
			$this->entityData[$entityDatum->getId()->getSerialization()] = $entityDatum;
		}
	}

	/**
	 * @param array $entityIds
	 * @param array $termTypes
	 * @param array $languageCodes
	 */
	public function prefetchTerms( array $entityIds, array $termTypes, array $languageCodes ) {
		$this->bufferStubTermsForEntities( $entityIds, $termTypes, $languageCodes );
	}

	private function bufferStubTermsForEntities( array $entityIds, array $termTypes, array $languageCodes ) {
		foreach ( $entityIds as $id ) {
			foreach ( $termTypes as $type ) {
				foreach ( $languageCodes as $lang ) {
					if ( $type !== TermTypes::TYPE_ALIAS ) {
						$this->bufferNonAliasTerm( $id, $type, $lang );
					} else {
						throw new RuntimeException( 'Not Implemented' );
					}
				}
			}
		}
	}

	private function bufferNonAliasTerm( EntityId $id, string $type, string $lang ) {
		$this->buffer[$id->getSerialization()][$type][$lang] = $this->getFromEntityData( $id, $type, $lang );
	}

	private function getFromEntityData( EntityId $id, string $type, string $lang ): ?string {
		if ( !array_key_exists( $id->getSerialization(), $this->entityData ) ) {
			return null;
		}
		if ( $type === TermTypes::TYPE_LABEL ) {
			$termList = $this->entityData[$id->getSerialization()]->getLabels();
		}
		if ( $type === TermTypes::TYPE_DESCRIPTION ) {
			$termList = $this->entityData[$id->getSerialization()]->getDescriptions();
		}
		if ( $termList->hasTermForLanguage( $lang ) ) {
			return $termList->getByLanguage( $lang )->getText();
		}
		return null;
	}

	public function getPrefetchedTerms(): array {
		$terms = [];

		foreach ( $this->buffer as $entityTerms ) {
			foreach ( $entityTerms as $termsByLang ) {
				foreach ( $termsByLang as $term ) {
					$terms[] = $term;
				}
			}
		}

		return $terms;
	}

	/** @inheritDoc */
	public function getPrefetchedTerm( EntityId $entityId, $termType, $languageCode ) {
		$id = $entityId->getSerialization();
		return $this->buffer[$id][$termType][$languageCode] ?? null;
	}

	/** @inheritDoc */
	public function getLabel( EntityId $entityId, $languageCode ) {
		if ( $this->loadEntitiesIfNotPrefetched ) {
			return $this->getFromEntityData( $entityId, TermTypes::TYPE_LABEL, $languageCode );
		}
		return $this->getPrefetchedTerm( $entityId, TermTypes::TYPE_LABEL, $languageCode );
	}

	/** @inheritDoc */
	public function getLabels( EntityId $entityId, array $languageCodes ) {
		$labels = [];

		foreach ( $languageCodes as $lang ) {
			if ( $this->loadEntitiesIfNotPrefetched ) {
				$result = $this->getFromEntityData(
					$entityId,
					TermTypes::TYPE_LABEL,
					$lang
				);
			} else {
				$result = $this->getPrefetchedTerm(
					$entityId,
					TermTypes::TYPE_LABEL,
					$lang
				);
			}
			if ( $result !== null ) {
				$labels[$lang] = $result;
			}

		}
		return $labels;
	}

	/** @inheritDoc */
	public function getDescription( EntityId $entityId, $languageCode ) {
		if ( $this->loadEntitiesIfNotPrefetched ) {
			return $this->getFromEntityData( $entityId, TermTypes::TYPE_DESCRIPTION, $languageCode );
		}
		return $this->getPrefetchedTerm( $entityId, TermTypes::TYPE_DESCRIPTION, $languageCode );
	}

	/** @inheritDoc */
	public function getDescriptions( EntityId $entityId, array $languageCodes ) {
		$descriptions = [];

		foreach ( $languageCodes as $lang ) {
			if ( $this->loadEntitiesIfNotPrefetched ) {
				$result = $this->getFromEntityData( $entityId, TermTypes::TYPE_DESCRIPTION, $lang );
			} else {
				$result = $this->getPrefetchedTerm( $entityId, TermTypes::TYPE_DESCRIPTION, $lang );
			}
			if ( $result !== null ) {
				$descriptions[$lang] = $result;
			}
		}
		return $descriptions;
	}

	/** @inheritDoc */
	public function getPrefetchedAliases( EntityId $entityId, $languageCode ) {
		throw new RuntimeException( 'Not Implemented' );
	}

}
