<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Search\Infrastructure\Controllers;

use Wikibase\DataAccess\EntitySourceLookup;
use Wikibase\Repo\Api\EntitySearchHelper;

/**
 * @license GPL-2.0-or-later
 */
class DispatchingWbSearchEntitiesController {

	/**
	 * @param callable[] $callbacks entity type string => callable returning WbSearchEntitiesController
	 * @param EntitySearchHelper $fallbackSearchHelper
	 * @param EntitySourceLookup $entitySourceLookup
	 */
	public function __construct(
		private readonly array $callbacks,
		private readonly EntitySearchHelper $fallbackSearchHelper,
		private readonly EntitySourceLookup $entitySourceLookup
	) {
	}

	public function getControllerForEntityType( string $entityType ): WbSearchEntitiesController {
		if ( isset( $this->callbacks[$entityType] ) ) {
			return ( $this->callbacks[$entityType] )();
		}

		return new FallbackEntitySearchHelperController(
			$entityType,
			$this->fallbackSearchHelper,
			$this->entitySourceLookup
		);
	}

}
