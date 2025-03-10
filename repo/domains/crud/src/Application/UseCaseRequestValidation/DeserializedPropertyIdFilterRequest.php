<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation;

use Wikibase\DataModel\Entity\PropertyId;

/**
 * @license GPL-2.0-or-later
 */
interface DeserializedPropertyIdFilterRequest {
	public function getPropertyIdFilter(): ?PropertyId;
}
