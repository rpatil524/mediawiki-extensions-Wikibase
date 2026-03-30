<?php

use Wikibase\DataModel\Entity\Item;
use Wikibase\Repo\ControllerRegistry;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\FallbackEntitySearchHelperController;
use Wikibase\Repo\Domains\Search\WbSearch;
use Wikibase\Repo\WikibaseRepo;

/**
 * Controller callback definitions for built-in entity types.
 *
 * @note Avoid instantiating objects here! Use callbacks (closures) instead.
 *
 * @license GPL-2.0-or-later
 */

return [
	Item::ENTITY_TYPE => [
		ControllerRegistry::WB_SEARCH_ENTITIES_CONTROLLER => static function (): FallbackEntitySearchHelperController {
			// This just serves as an example. The fallback implementation should no longer be used once T420683 is done.
			return new FallbackEntitySearchHelperController(
				Item::ENTITY_TYPE,
				WbSearch::getItemSearchHelper(),
				WikibaseRepo::getEntitySourceLookup()
			);
		},
	],
];
