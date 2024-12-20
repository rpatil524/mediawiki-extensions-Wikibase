<?php

namespace Wikibase\Lib\Tests\Store;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikibase\Lib\Store\WikiPagePropertyOrderProvider;

/**
 * @covers \Wikibase\Lib\Store\WikiPagePropertyOrderProvider
 * @covers \Wikibase\Lib\Store\WikiTextPropertyOrderProvider
 *
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 * @author Marius Hoch
 */
class WikiPagePropertyOrderProviderTest extends MediaWikiIntegrationTestCase {

	public static function provideGetPropertyOrder() {
		return WikiTextPropertyOrderProviderTestHelper::provideGetPropertyOrder();
	}

	/**
	 * @dataProvider provideGetPropertyOrder
	 */
	public function testGetPropertyOrder( $text, $expected ) {
		$title = Title::makeTitle( NS_MEDIAWIKI, 'Wikibase-SortedProperties' );
		$this->makeWikiPage( $title, $text );
		$instance = new WikiPagePropertyOrderProvider(
			$this->getServiceContainer()->getWikiPageFactory(),
			$title
		);
		$propertyOrder = $instance->getPropertyOrder();
		$this->assertSame( $expected, $propertyOrder );
	}

	private function makeWikiPage( $title, $text ) {
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$wikiPage->doUserEditContent(
			new WikitextContent( $text ),
			$this->getTestUser()->getUser(),
			'test'
		);
	}

	public function testGetPropertyOrder_pageDoesNotExist() {
		$instance = new WikiPagePropertyOrderProvider(
			$this->getServiceContainer()->getWikiPageFactory(),
			Title::makeTitle( NS_MEDIAWIKI, 'WikiPagePropertyOrderProviderTest-DoesNotExist' )
		);
		$propertyOrder = $instance->getPropertyOrder();
		$this->assertSame( null, $propertyOrder );
	}

}
