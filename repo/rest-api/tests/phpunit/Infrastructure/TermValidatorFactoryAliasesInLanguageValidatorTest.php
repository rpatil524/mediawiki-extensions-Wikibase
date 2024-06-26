<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\Infrastructure;

use Generator;
use MediaWiki\Languages\LanguageNameUtils;
use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Term\AliasGroup;
use Wikibase\Repo\RestApi\Application\Validation\AliasesInLanguageValidator;
use Wikibase\Repo\RestApi\Application\Validation\ValidationError;
use Wikibase\Repo\RestApi\Infrastructure\TermValidatorFactoryAliasesInLanguageValidator;
use Wikibase\Repo\Store\TermsCollisionDetectorFactory;
use Wikibase\Repo\Validators\TermValidatorFactory;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\RestApi\Infrastructure\TermValidatorFactoryAliasesInLanguageValidator
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class TermValidatorFactoryAliasesInLanguageValidatorTest extends TestCase {

	private const MAX_LENGTH = 50;

	public function testValid(): void {
		$this->assertNull( $this->newValidator()->validate( new AliasGroup( 'en', [ 'valid alias' ] ) ) );
	}

	/**
	 * @dataProvider provideInvalidAliases
	 */
	public function testGivenInvalidAliases_returnsValidationError(
		AliasGroup $aliasesInLanguage,
		string $errorCode,
		array $errorContext = []
	): void {
		$this->assertEquals(
			new ValidationError( $errorCode, $errorContext ),
			$this->newValidator()->validate( $aliasesInLanguage )
		);
	}

	public static function provideInvalidAliases(): Generator {
		$language = 'en';
		$alias = str_repeat( 'a', self::MAX_LENGTH + 1 );
		yield 'alias too long' => [
			new AliasGroup( $language, [ $alias ] ),
			AliasesInLanguageValidator::CODE_TOO_LONG,
			[
				AliasesInLanguageValidator::CONTEXT_VALUE => $alias,
				AliasesInLanguageValidator::CONTEXT_LANGUAGE => $language,
				AliasesInLanguageValidator::CONTEXT_LIMIT => self::MAX_LENGTH,
			],
		];

		$language = 'en';
		$alias = "alias with tab character \t not allowed";
		yield 'alias has invalid character' => [
			new AliasGroup( $language, [ $alias ] ),
			AliasesInLanguageValidator::CODE_INVALID,
			[
				AliasesInLanguageValidator::CONTEXT_VALUE => $alias,
				AliasesInLanguageValidator::CONTEXT_LANGUAGE => $language,
				AliasesInLanguageValidator::CONTEXT_PATH => 'en/0',
			],
		];
	}

	private function newValidator(): TermValidatorFactoryAliasesInLanguageValidator {
		return new TermValidatorFactoryAliasesInLanguageValidator( $this->newTermValidatorFactory() );
	}

	private function newTermValidatorFactory(): TermValidatorFactory {
		return new TermValidatorFactory(
			self::MAX_LENGTH,
			WikibaseRepo::getTermsLanguages()->getLanguages(),
			$this->createStub( EntityIdParser::class ),
			$this->createStub( TermsCollisionDetectorFactory::class ),
			WikibaseRepo::getTermLookup(),
			$this->createStub( LanguageNameUtils::class )
		);
	}

}
