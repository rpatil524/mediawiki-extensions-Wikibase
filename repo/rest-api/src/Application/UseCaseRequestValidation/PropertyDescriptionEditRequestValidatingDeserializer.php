<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCaseRequestValidation;

use LogicException;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Term\Term;
use Wikibase\Repo\RestApi\Application\UseCases\UseCaseError;
use Wikibase\Repo\RestApi\Application\Validation\PropertyDescriptionValidator;
use Wikibase\Repo\RestApi\Domain\Services\PropertyWriteModelRetriever;

/**
 * @license GPL-2.0-or-later
 */
class PropertyDescriptionEditRequestValidatingDeserializer {

	private PropertyDescriptionValidator $validator;
	private PropertyWriteModelRetriever $propertyRetriever;

	public function __construct( PropertyDescriptionValidator $validator, PropertyWriteModelRetriever $propertyRetriever ) {
		$this->validator = $validator;
		$this->propertyRetriever = $propertyRetriever;
	}

	/**
	 * @throws UseCaseError
	 */
	public function validateAndDeserialize( PropertyDescriptionEditRequest $request ): Term {
		$property = $this->propertyRetriever->getPropertyWriteModel( new NumericPropertyId( $request->getPropertyId() ) );
		$language = $request->getLanguageCode();
		$description = $request->getDescription();

		// skip if property does not exist or description is unchanged
		if ( !$property ||
			 ( $property->getDescriptions()->hasTermForLanguage( $language ) &&
			   $property->getDescriptions()->getByLanguage( $language )->getText() === $description
			 )
		) {
			return new Term( $language, $description );
		}

		$validationError = $this->validator->validate(
			$language,
			$request->getDescription(),
			$property->getLabels()
		);

		if ( $validationError ) {
			$errorCode = $validationError->getCode();
			$context = $validationError->getContext();
			switch ( $errorCode ) {
				case PropertyDescriptionValidator::CODE_INVALID:
					throw new UseCaseError(
						UseCaseError::INVALID_DESCRIPTION,
						"Not a valid description: {$context[PropertyDescriptionValidator::CONTEXT_DESCRIPTION]}"
					);
				case PropertyDescriptionValidator::CODE_EMPTY:
					throw new UseCaseError(
						UseCaseError::DESCRIPTION_EMPTY,
						'Description must not be empty'
					);
				case PropertyDescriptionValidator::CODE_TOO_LONG:
					$limit = $context[PropertyDescriptionValidator::CONTEXT_LIMIT];
					throw new UseCaseError(
						UseCaseError::DESCRIPTION_TOO_LONG,
						"Description must be no more than $limit characters long",
						[
							UseCaseError::CONTEXT_VALUE => $context[PropertyDescriptionValidator::CONTEXT_DESCRIPTION],
							UseCaseError::CONTEXT_CHARACTER_LIMIT => $limit,
						]
					);
				case PropertyDescriptionValidator::CODE_LABEL_DESCRIPTION_EQUAL:
					throw new UseCaseError(
						UseCaseError::LABEL_DESCRIPTION_SAME_VALUE,
						"Label and description for language code '$language' can not have the same value",
						[ UseCaseError::CONTEXT_LANGUAGE => $context[PropertyDescriptionValidator::CONTEXT_LANGUAGE] ]
					);
				default:
					throw new LogicException( "Unexpected validation error code: $errorCode" );
			}
		}

		return new Term( $language, $request->getDescription() );
	}

}
