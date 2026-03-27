<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Reuse\Infrastructure\GraphQL\Resolvers;

use GraphQL\Deferred;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Repo\Domains\Reuse\Application\UseCases\LookUpItemByExternalId\LookUpItemByExternalId;
use Wikibase\Repo\Domains\Reuse\Application\UseCases\LookUpItemByExternalId\LookUpItemByExternalIdRequest;
use Wikibase\Repo\Domains\Reuse\Infrastructure\GraphQL\Errors\GraphQLError;
use Wikibase\Repo\Domains\Reuse\Infrastructure\GraphQL\QueryContext;

/**
 * @license GPL-2.0-or-later
 */
class ItemByExternalIdResolver {
	use CirrusSearchEnabledTrait;

	public function __construct(
		private readonly LookUpItemByExternalId $useCase,
		private readonly ItemResolver $itemResolver,
	) {
	}

	/**
	 * @throws GraphQLError
	 */
	public function resolve( string $propertyId, string $externalId, QueryContext $context ): Deferred|array|null {
		if ( !self::isCirrusSearchEnabled() ) {
			throw GraphQLError::searchNotAvailable();
		}

		$itemIds = $this->useCase->execute(
			new LookUpItemByExternalIdRequest( new NumericPropertyId( $propertyId ), $externalId )
		)->itemIds;

		if ( count( $itemIds ) === 0 ) {
			return null;
		}

		if ( count( $itemIds ) > 1 ) {
			return $itemIds;
		}

		return $this->itemResolver->resolveItem( $itemIds[0]->getSerialization(), $context );
	}

}
