<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Store\Sql\Terms;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Lib\Rdbms\TermsDomainDb;
use Wikibase\Lib\Store\Sql\Terms\TermTypeIds;
use Wikibase\Repo\Store\TermsCollisionDetector;

/**
 * Queries db term store for collisions on terms
 *
 * @license GPL-2.0-or-later
 */
class DatabaseTermsCollisionDetector implements TermsCollisionDetector {

	private string $entityType;

	private TermsDomainDb $db;

	/**
	 * @param string $entityType one of the two supported types: Item::ENTITY_TYPE or Property::ENTITY_TYPE
	 * @param TermsDomainDb $db
	 *
	 * @throws InvalidArgumentException when non supported entity type is given
	 */
	public function __construct(
		string $entityType,
		TermsDomainDb $db
	) {
		if ( !in_array( $entityType, [ Item::ENTITY_TYPE, Property::ENTITY_TYPE ] ) ) {
			throw new InvalidArgumentException(
				'$entityType must be a string, with either "item" or "property" as a value'
			);
		}

		$this->entityType = $entityType;
		$this->db = $db;
	}

	/**
	 * Returns an entity id that collides with given label in given language, if any
	 */
	public function detectLabelCollision(
		string $lang,
		string $label
	): ?EntityId {
		$entityId = $this->findEntityIdsWithTermInLang( $lang, $label, TermTypeIds::LABEL_TYPE_ID, true )[0] ?? null;

		return $this->makeEntityId( $entityId );
	}

	/**
	 * Returns an entity id that collides with given label and description in given languages, if any
	 */
	public function detectLabelAndDescriptionCollision(
		string $lang,
		string $label,
		string $description
	): ?EntityId {
		$entityIdsWithLabel = $this->findEntityIdsWithTermInLang( $lang, $label, TermTypeIds::LABEL_TYPE_ID );

		if ( !$entityIdsWithLabel ) {
			return null;
		}

		$entityId = $this->findEntityIdsWithTermInLang(
			$lang,
			$description,
			TermTypeIds::DESCRIPTION_TYPE_ID,
			true,
			$entityIdsWithLabel
		)[0] ?? null;

		return $this->makeEntityId( $entityId );
	}

	public function detectLabelsCollision( TermList $termList ): array {
		if ( $termList->isEmpty() ) {
			return [];
		}

		$lang = [];
		$labels = [];

		foreach ( $termList->getIterator() as $label ) {
			$lang[] = $label->getLanguageCode();
			$labels[] = $label->getText();
		}

		return $this->findEntityIdsWithTermsInLangs( $lang, $labels, TermTypeIds::LABEL_TYPE_ID );
	}

	/**
	 * @param mixed|null $numericEntityId
	 * @return EntityId|null
	 */
	private function makeEntityId( $numericEntityId ): ?EntityId {
		if ( !$numericEntityId ) {
			return null;
		}

		return $this->composeEntityId( $numericEntityId );
	}

	private function composeEntityId( string $numericEntityId ): EntityId {
		if ( $this->entityType === Item::ENTITY_TYPE ) {
			return ItemId::newFromNumber( $numericEntityId );
		} elseif ( $this->entityType === Property::ENTITY_TYPE ) {
			return NumericPropertyId::newFromNumber( $numericEntityId );
		}
	}

	private function findEntityIdsWithTermInLang(
		string $lang,
		string $text,
		int $termTypeId,
		bool $firstMatchOnly = false,
		array $filterOnEntityIds = []
	): array {
		$queryBuilder = $this->newSelectQueryBuilder();
		$queryBuilder->whereTerm( $termTypeId, $lang, $text );

		if ( $filterOnEntityIds ) {
			$queryBuilder->andWhere( [
				$queryBuilder->getEntityIdColumn() => $filterOnEntityIds,
			] );
		}
		$queryBuilder->caller( __METHOD__ );

		if ( $firstMatchOnly ) {
			$match = $queryBuilder->fetchField();
			return $match === false ? [] : [ $match ];
		} else {
			return $queryBuilder->fetchFieldValues();
		}
	}

	private function findEntityIdsWithTermsInLangs(
		array $lang,
		array $text,
		int $termTypeId
	): array {
		$queryBuilder = $this->newSelectQueryBuilder()
			->select( [ 'wbx_text', 'wbxl_language' ] )
			->distinct()
			->whereMultiTerm( $termTypeId, $lang, $text );
		$res = $queryBuilder->caller( __METHOD__ )->fetchResultSet();

		$values = [];
		foreach ( $res as $row ) {
			$dbEntityId = $row->{$queryBuilder->getEntityIdColumn()};
			$entityId = $this->makeEntityId( $dbEntityId );

			if ( !$entityId ) {
				throw new \RuntimeException( "Select result contains entityIds that are null." );
			}

			$values[$entityId->getSerialization()][] = new Term( $row->wbxl_language, $row->wbx_text );
		}

		return $values;
	}

	private function newSelectQueryBuilder(): EntityTermsSelectQueryBuilder {
		return new EntityTermsSelectQueryBuilder( $this->db, $this->entityType );
	}

}
