<?php

declare( strict_types = 1 );

namespace Wikibase\Lib\Tests;

use MediaWiki\Language\Language;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikibase\Lib\MediaWikiMessageInLanguageProvider;

/**
 * @covers \Wikibase\Lib\LanguageNameLookupFactory
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class LanguageNameLookupFactoryTest extends \MediaWikiIntegrationTestCase {

	public function testForLanguage(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CLDR' );

		$language = $this->createMock( Language::class );
		$language->expects( $this->once() )
			->method( 'getCode' )
			->willReturn( 'de' );
		$languageNameLookupFactory = new LanguageNameLookupFactory(
			$this->getServiceContainer()->getLanguageNameUtils(),
			new MediaWikiMessageInLanguageProvider()
		);

		$languageNameLookup = $languageNameLookupFactory->getForLanguage( $language );

		$this->assertSame( 'Englisch',
			$languageNameLookup->getName( 'en' ) );
	}

	public function testForLanguageCode(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CLDR' );

		$languageNameLookupFactory = new LanguageNameLookupFactory(
			$this->getServiceContainer()->getLanguageNameUtils(),
			new MediaWikiMessageInLanguageProvider()
		);

		$languageNameLookup = $languageNameLookupFactory->getForLanguageCode( 'de' );

		$this->assertSame( 'Englisch',
			$languageNameLookup->getName( 'en' ) );
	}

	public function testForAutonyms(): void {
		$languageNameLookupFactory = new LanguageNameLookupFactory(
			$this->getServiceContainer()->getLanguageNameUtils(),
			new MediaWikiMessageInLanguageProvider()
		);

		$languageNameLookup = $languageNameLookupFactory->getForAutonyms();

		$this->assertSame( 'English',
			$languageNameLookup->getName( 'en' ) );
	}

}
