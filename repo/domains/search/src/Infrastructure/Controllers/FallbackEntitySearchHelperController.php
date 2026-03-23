<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Search\Infrastructure\Controllers;

use Wikibase\DataAccess\EntitySourceLookup;
use Wikibase\Repo\Api\ConceptUriSearchHelper;
use Wikibase\Repo\Api\EntitySearchHelper;

/**
 * @license GPL-2.0-or-later
 */
class FallbackEntitySearchHelperController implements WbSearchEntitiesController {

	private readonly EntitySearchHelper $searchHelper;

	public function __construct(
		private readonly string $entityType,
		EntitySearchHelper $searchHelper,
		EntitySourceLookup $entitySourceLookup
	) {
		$this->searchHelper = new ConceptUriSearchHelper( $searchHelper, $entitySourceLookup );
	}

	public function search(
		string $text,
		string $languageCode,
		int $limit,
		bool $strictLanguage,
		?string $profileContext
	): array {
		return $this->searchHelper->getRankedSearchResults(
			$text,
			$languageCode,
			$this->entityType,
			$limit,
			$strictLanguage,
			$profileContext
		);
	}

}
