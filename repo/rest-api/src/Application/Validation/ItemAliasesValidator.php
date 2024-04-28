<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\Validation;

use LogicException;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\Repo\RestApi\Application\Serialization\AliasesDeserializer;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\DuplicateAliasException;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\EmptyAliasException;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\InvalidAliasesInLanguageException;

/**
 * @license GPL-2.0-or-later
 */
class ItemAliasesValidator {
	public const CODE_EMPTY_ALIAS = 'alias-empty';
	public const CODE_TOO_LONG_ALIAS = 'alias-too-long';
	public const CODE_EMPTY_ALIAS_LIST = 'alias-list-empty';
	public const CODE_DUPLICATE_ALIAS = 'alias-duplicate';
	public const CODE_INVALID_ALIASES = 'invalid-aliases';
	public const CODE_INVALID_ALIAS = 'invalid-alias';
	public const CODE_INVALID_ALIAS_LIST = 'invalid-alias-list';

	public const CONTEXT_FIELD_ALIASES = 'aliases';
	public const CONTEXT_FIELD_ALIAS = 'alias';
	public const CONTEXT_FIELD_LIMIT = 'character-limit';
	public const CONTEXT_FIELD_LANGUAGE = 'language';

	private AliasesInLanguageValidator $aliasesInLanguageValidator;
	private LanguageCodeValidator $languageCodeValidator;
	private AliasesDeserializer $aliasesDeserializer;

	private ?AliasGroupList $deserializedAliases = null;

	public function __construct(
		AliasesInLanguageValidator $aliasesInLanguageValidator,
		LanguageCodeValidator $languageCodeValidator,
		AliasesDeserializer $aliasesDeserializer
	) {
		$this->aliasesInLanguageValidator = $aliasesInLanguageValidator;
		$this->aliasesDeserializer = $aliasesDeserializer;
		$this->languageCodeValidator = $languageCodeValidator;
	}

	public function validate( array $aliases ): ?ValidationError {
		if ( count( $aliases ) === 0 ) {
			$this->deserializedAliases = new AliasGroupList();
			return null;
		}
		if ( array_is_list( $aliases ) ) {
			return new ValidationError( self::CODE_INVALID_ALIASES, [ self::CONTEXT_FIELD_ALIASES => $aliases ] );
		}

		foreach ( $aliases as $languageCode => $aliasesInLanguage ) {
			$languageValidationError = $this->languageCodeValidator->validate( (string)$languageCode );
			if ( $languageValidationError ) {
				return new ValidationError(
					LanguageCodeValidator::CODE_INVALID_LANGUAGE_CODE,
					array_merge(
						$languageValidationError->getContext(),
						[ LanguageCodeValidator::CONTEXT_PATH_VALUE => self::CONTEXT_FIELD_ALIAS ]
					)
				);
			}

			// @phan-suppress-next-line PhanRedundantConditionInLoop
			if ( !is_array( $aliasesInLanguage ) ) {
				return new ValidationError(
					self::CODE_INVALID_ALIAS_LIST,
					[ self::CONTEXT_FIELD_LANGUAGE => $languageCode ]
				);
			}

			if ( count( $aliasesInLanguage ) === 0 ) {
				return new ValidationError(
					self::CODE_EMPTY_ALIAS_LIST,
					[ self::CONTEXT_FIELD_LANGUAGE => $languageCode ]
				);
			}
		}

		$aliasesDeserializationError = $this->deserializeAliases( $aliases );
		if ( $aliasesDeserializationError ) {
			return $aliasesDeserializationError;
		}

		return $this->validateAliasesInLanguage( $this->deserializedAliases );
	}

	private function deserializeAliases( array $aliases ): ?ValidationError {
		try {
			$this->deserializedAliases = $this->aliasesDeserializer->deserialize( $aliases );
		} catch ( EmptyAliasException $e ) {
			return new ValidationError(
				self::CODE_EMPTY_ALIAS,
				[ self::CONTEXT_FIELD_LANGUAGE => $e->getField() ]
			);
		} catch ( DuplicateAliasException $e ) {
			return new ValidationError(
				self::CODE_DUPLICATE_ALIAS,
				[ self::CONTEXT_FIELD_LANGUAGE => $e->getField(), self::CONTEXT_FIELD_ALIAS => $e->getValue() ]
			);
		} catch ( InvalidAliasesInLanguageException $e ) {
			return new ValidationError(
				self::CODE_INVALID_ALIAS,
				[ self::CONTEXT_FIELD_LANGUAGE => $e->getField(), self::CONTEXT_FIELD_ALIAS => $e->getValue() ]
			);
		}

		return null;
	}

	private function validateAliasesInLanguage( AliasGroupList $aliases ): ?ValidationError {
		foreach ( $aliases as $aliasesInLanguage ) {
			$aliasesInLanguageValidationError = $this->aliasesInLanguageValidator->validate( $aliasesInLanguage );
			if ( $aliasesInLanguageValidationError ) {
				return $aliasesInLanguageValidationError;
			}
		}

		return null;
	}

	public function getValidatedAliases(): AliasGroupList {
		if ( $this->deserializedAliases === null ) {
			throw new LogicException( 'getValidatedAliases() called before validate()' );
		}

		return $this->deserializedAliases;
	}

}