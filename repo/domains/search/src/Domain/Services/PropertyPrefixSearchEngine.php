<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Search\Domain\Services;

use Wikibase\Repo\Domains\Search\Domain\Model\PropertySearchResults;

/**
 * @license GPL-2.0-or-later
 */
interface PropertyPrefixSearchEngine {
	public function suggestProperties(
		string $searchTerm,
		string $languageCode,
		int $limit,
		int $offset
	): PropertySearchResults;
}
