<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\Domains\Crud\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;

/**
 * @coversNothing
 *
 * @license GPL-2.0-or-later
 */
class ArchitectureTest {

	private const CRUD_DOMAIN = 'Wikibase\Repo\Domains\Crud';
	private const DOMAIN_MODEL = 'Wikibase\Repo\Domains\Crud\Domain\Model';
	private const DOMAIN_READMODEL = 'Wikibase\Repo\Domains\Crud\Domain\ReadModel';
	private const DOMAIN_SERVICES = 'Wikibase\Repo\Domains\Crud\Domain\Services';
	private const SERIALIZATION = 'Wikibase\Repo\Domains\Crud\Application\Serialization';
	private const VALIDATION = 'Wikibase\Repo\Domains\Crud\Application\Validation';
	private const USE_CASES = 'Wikibase\Repo\Domains\Crud\Application\UseCases';
	private const USE_CASE_REQUEST_VALIDATION = 'Wikibase\Repo\Domains\Crud\Application\UseCaseRequestValidation';

	public function testDomainModel(): Rule {
		return PHPat::rule()
			->classes(
				Selector::inNamespace( self::DOMAIN_MODEL ),
				Selector::inNamespace( self::DOMAIN_READMODEL )
			)
			->canOnlyDependOn()
			->classes( ...$this->allowedDomainModelDependencies() );
	}

	/**
	 * Domain models may depend on:
	 *  - DataModel namespaces containing entities and their parts
	 *  - other classes from their own namespace
	 */
	private function allowedDomainModelDependencies(): array {
		return [
			...$this->dataModelNamespaces(),
			Selector::inNamespace( self::DOMAIN_MODEL ),
			Selector::inNamespace( self::DOMAIN_READMODEL ),
		];
	}

	public function testDomainServices(): Rule {
		return PHPat::rule()
			->classes( Selector::inNamespace( self::DOMAIN_SERVICES ) )
			->canOnlyDependOn()
			->classes( ...$this->allowedDomainServicesDependencies() );
	}

	/**
	 * Domain services may depend on:
	 *  - the domain models namespace and everything it depends on
	 *  - some hand-picked DataModel services
	 *  - other classes from their own namespace
	 */
	private function allowedDomainServicesDependencies(): array {
		return [
			...$this->allowedDomainModelDependencies(),
			...$this->allowedDataModelServices(),
			Selector::inNamespace( self::DOMAIN_SERVICES ),
		];
	}

	public function testSerialization(): Rule {
		return PHPat::rule()
			->classes( Selector::inNamespace( self::SERIALIZATION ) )
			->canOnlyDependOn()
			->classes( ...$this->allowedSerializationDependencies() );
	}

	/**
	 * Serialization may depend on:
	 *  - the domain services namespace and everything it depends on
	 *  - the DataValues namespace
	 *  - other classes from its own namespace
	 */
	private function allowedSerializationDependencies(): array {
		return [
			...$this->allowedDomainServicesDependencies(),
			Selector::inNamespace( self::SERIALIZATION ),
		];
	}

	public function testValidation(): Rule {
		return PHPat::rule()
			->classes( Selector::inNamespace( self::VALIDATION ) )
			->canOnlyDependOn()
			->classes( ...$this->allowedValidationDependencies() );
	}

	/**
	 * Validation may depend on:
	 *  - the serialization namespace and everything it depends on
	 *  - other classes from its own namespace
	 */
	private function allowedValidationDependencies(): array {
		return [
			...$this->allowedSerializationDependencies(),
			Selector::inNamespace( self::VALIDATION ),
		];
	}

	public function testUseCases(): Rule {
		return PHPat::rule()
			->classes( Selector::inNamespace( self::USE_CASES ) )
			->canOnlyDependOn()
			->classes( ...$this->allowedUseCasesDependencies() );
	}

	/**
	 * Use cases may depend on:
	 *  - the validation namespace and everything it depends on
	 *  - the use case request validation namespace
	 *  - other classes from their own namespace
	 */
	private function allowedUseCasesDependencies(): array {
		return [
			...$this->allowedValidationDependencies(),
			Selector::inNamespace( self::USE_CASE_REQUEST_VALIDATION ),
			Selector::inNamespace( self::USE_CASES ),
		];
	}

	public function testUseCaseRequestValidation(): Rule {
		return PHPat::rule()
			->classes( Selector::inNamespace( self::USE_CASE_REQUEST_VALIDATION ) )
			->canOnlyDependOn()
			->classes( ...$this->allowedUseCaseRequestValidationDependencies() );
	}

	/**
	 * Use case request validation may depend on:
	 *  - the validation namespace and everything it depends on
	 *  - the use case namespace
	 *  - other classes from their own namespace
	 */
	private function allowedUseCaseRequestValidationDependencies(): array {
		return [
			...$this->allowedValidationDependencies(),
			Selector::inNamespace( self::USE_CASES ),
			Selector::inNamespace( self::USE_CASE_REQUEST_VALIDATION ),
		];
	}

	private function allowedDataModelServices(): array {
		return [
			Selector::classname( PropertyDataTypeLookup::class ),
			Selector::classname( PropertyDataTypeLookupException::class ),
			Selector::classname( StatementGuidParser::class ),
			Selector::classname( StatementGuidValidator::class ),
			Selector::classname( GuidGenerator::class ),
		];
	}

	private function dataModelNamespaces(): array {
		return [
			// These are listed in such a complicated way so that only DataModel entities and their parts are allowed without the
			// namespaces nested within DataModel like e.g. Wikibase\DataModel\Serializers.
			...array_map(
				fn( string $escapedNamespace ) => Selector::classname(
					'/^' . preg_quote( $escapedNamespace ) . '\\\\\w+$/',
					true
				),
				[
					'Wikibase\DataModel',
					'Wikibase\DataModel\Entity',
					'Wikibase\DataModel\Exception',
					'Wikibase\DataModel\Snak',
					'Wikibase\DataModel\Statement',
					'Wikibase\DataModel\Term',
				]
			),
			Selector::inNamespace( 'DataValues' ),
		];
	}

}
