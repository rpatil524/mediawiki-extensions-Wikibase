<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Crud\Application\UseCases\GetProperty;

use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\PropertyFieldsRequest;
use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\PropertyIdRequest;
use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\UseCaseRequest;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\PropertyParts;

/**
 * @license GPL-2.0-or-later
 */
class GetPropertyRequest implements UseCaseRequest, PropertyIdRequest, PropertyFieldsRequest {

	private string $propertyId;
	private array $fields;

	public function __construct( string $propertyId, array $fields = PropertyParts::VALID_FIELDS ) {
		$this->propertyId = $propertyId;
		$this->fields = $fields;
	}

	public function getPropertyId(): string {
		return $this->propertyId;
	}

	public function getPropertyFields(): array {
		return $this->fields;
	}
}
