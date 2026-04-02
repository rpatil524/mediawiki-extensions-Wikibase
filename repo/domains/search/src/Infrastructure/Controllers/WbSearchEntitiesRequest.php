<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Search\Infrastructure\Controllers;

/**
 * @license GPL-2.0-or-later
 */
class WbSearchEntitiesRequest {

	public function __construct(
		public readonly string $text,
		public readonly string $searchLanguageCode,
		public readonly int $limit,
		public readonly bool $strictLanguage,
		public readonly ?string $profileContext
	) {
	}
}
