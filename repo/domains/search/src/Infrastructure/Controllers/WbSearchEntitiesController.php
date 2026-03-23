<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Search\Infrastructure\Controllers;

use Wikibase\Lib\Interactors\TermSearchResult;

/**
 * @license GPL-2.0-or-later
 */
interface WbSearchEntitiesController {

	/**
	 * @param string $text
	 * @param string $languageCode
	 * @param int $limit
	 * @param bool $strictLanguage
	 * @param string|null $profileContext
	 *
	 * @return TermSearchResult[]
	 */
	public function search(
		string $text,
		string $languageCode,
		int $limit,
		bool $strictLanguage,
		?string $profileContext
	): array;

}
