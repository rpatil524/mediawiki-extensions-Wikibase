<?php

namespace Wikibase\Lib\Store\Sql\Terms;

use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityTermLookupBase;
use Wikibase\DataAccess\PrefetchingTermLookup;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * A {@link PrefetchingTermLookup} that only supports items,
 * using the new, normalized schema (starting at wbt_item_ids).
 *
 * @todo This currently loads all the terms in all languages,
 * since the TermIdsResolver interface doesn’t offer any filtering capabilities.
 *
 * @license GPL-2.0-or-later
 */
class PrefetchingItemTermLookup extends EntityTermLookupBase implements PrefetchingTermLookup {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var TermIdsResolver */
	private $termIdsResolver;

	/** @var IDatabase|null */
	private $dbr;

	/** @var bool|string */
	private $databaseDomain;

	/** @var array[] serialization -> terms array */
	private $terms = [];

	/**
	 * PrefetchingItemTermLookup constructor.
	 * @param ILoadBalancer $loadBalancer
	 * @param TermIdsResolver $termIdsResolver
	 * @param bool|string $databaseDomain
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		TermIdsResolver $termIdsResolver,
		$databaseDomain = false
	) {
		$this->loadBalancer = $loadBalancer;
		$this->termIdsResolver = $termIdsResolver;
		$this->databaseDomain = $databaseDomain;
	}

	protected function getTermsOfType( EntityId $entityId, $termType, array $languageCodes ) {
		$this->prefetchTerms( [ $entityId ], [ $termType ], $languageCodes );

		$ret = [];
		foreach ( $languageCodes as $languageCode ) {
			$term = $this->getPrefetchedTerm( $entityId, $termType, $languageCode );
			if ( $term !== false ) {
				$ret[$languageCode] = $term;
			}
		}
		return $ret;
	}

	/**
	 * @todo $termTypes and $languageCodes can not be null with data-model-service ~5.0
	 * Code calling this already always passes array here and the defaults should be removed soon
	 * Leaving the defaults in this method allows us to stay compatible with ~4.0 and ~5.0
	 * for a short period during migration and updates.
	 */
	public function prefetchTerms(
		array $entityIds,
		array $termTypes = null,
		array $languageCodes = null
	) {
		if ( $termTypes === null || $languageCodes === null ) {
			throw new \InvalidArgumentException( '$termTypes and $languageCodes can not be null' );
		}

		/** @var ItemId[] numeric ID -> ItemId */
		$itemIdsToFetch = [];
		foreach ( $entityIds as $entityId ) {
			if ( !( $entityId instanceof ItemId ) ) {
				throw new InvalidArgumentException(
					'Not an ItemId: ' . $entityId->getSerialization() );
			}
			if ( !array_key_exists( $entityId->getSerialization(), $this->terms ) ) {
				$itemIdsToFetch[$entityId->getNumericId()] = $entityId;
			}
		}

		if ( $itemIdsToFetch === [] ) {
			return;
		}

		MediaWikiServices::getInstance()->getStatsdDataFactory()->increment(
			'wikibase.repo.term_store.PrefetchingItemTermLookup_prefetchTerms'
		);
		$res = $this->getDbr()->select(
			'wbt_item_terms',
			[ 'wbit_item_id', 'wbit_term_in_lang_id' ],
			[ 'wbit_item_id' => array_keys( $itemIdsToFetch ) ],
			__METHOD__
		);
		/** @var int[] serialization -> term IDs */
		$groups = [];
		foreach ( $res as $row ) {
			$itemId = $itemIdsToFetch[$row->wbit_item_id];
			$groups[$itemId->getSerialization()][] = $row->wbit_term_in_lang_id;
		}

		$this->terms += $this->termIdsResolver->resolveGroupedTermIds( $groups );
	}

	public function getPrefetchedTerm( EntityId $entityId, $termType, $languageCode ) {
		$serialization = $entityId->getSerialization();
		if ( !( $entityId instanceof ItemId ) ) {
			throw new InvalidArgumentException( 'Not an ItemId: ' . $serialization );
		}
		if ( !array_key_exists( $serialization, $this->terms ) ) {
			return null;
		}
		return $this->terms[$serialization][$termType][$languageCode][0] ?? false;
	}

	private function getDbr(): IDatabase {
		if ( $this->dbr === null ) {
			$this->dbr = $this->loadBalancer->getConnection( ILoadBalancer::DB_REPLICA, [], $this->databaseDomain );
		}
		return $this->dbr;
	}

	public function getPrefetchedAliases( EntityId $entityId, $languageCode ) {
		$serialization = $entityId->getSerialization();
		if ( !( $entityId instanceof ItemId ) ) {
			throw new InvalidArgumentException( 'Not an ItemId: ' . $serialization );
		}
		if ( !array_key_exists( $serialization, $this->terms ) ) {
			return null;
		}
		return $this->terms[$serialization]['alias'][$languageCode] ?? false;
	}

}
