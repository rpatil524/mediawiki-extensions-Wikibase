<?php declare( strict_types = 1 );

namespace Wikibase\Repo\Domains\Crud\Application\UseCases\GetPropertyAliasesInLanguage;

use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\AliasLanguageCodeRequest;
use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\PropertyIdRequest;
use Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation\UseCaseRequest;

/**
 * @license GPL-2.0-or-later
 */
class GetPropertyAliasesInLanguageRequest implements UseCaseRequest, PropertyIdRequest, AliasLanguageCodeRequest {

	private string $propertyId;
	private string $languageCode;

	public function __construct( string $propertyId, string $languageCode ) {
		$this->propertyId = $propertyId;
		$this->languageCode = $languageCode;
	}

	public function getPropertyId(): string {
		return $this->propertyId;
	}

	public function getLanguageCode(): string {
		return $this->languageCode;
	}

}
