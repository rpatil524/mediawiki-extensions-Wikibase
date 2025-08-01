<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Crud\Domain\Services;

/**
 * @license GPL-2.0-or-later
 */
interface TagsRetriever {

	public function getAllowedTags(): array;

}
