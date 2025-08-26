<?php

declare( strict_types = 1 );

namespace Wikibase\Client\Tests\Integration\Hooks;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikibase\Client\Hooks\LanguageLinkBadgeDisplay;
use Wikibase\Client\Hooks\NoLangLinkHandler;
use Wikibase\Client\Hooks\SidebarHookHandler;
use Wikibase\Client\Hooks\SidebarLinkBadgeDisplay;
use Wikibase\Client\NamespaceChecker;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\Lib\SettingsArray;

/**
 * @covers \Wikibase\Client\Hooks\SidebarHookHandler
 *
 * @group WikibaseClient
 * @group Wikibase
 * @group WikibaseHooks
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class SidebarHookHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return LabelDescriptionLookup
	 */
	private function getLabelDescriptionLookup() {
		$labelLookup = $this->createMock( LabelDescriptionLookup::class );

		$labelLookup->method( 'getLabel' )
			  ->willReturn( 'o' );

		return $labelLookup;
	}

	/**
	 * @return SettingsArray
	 */
	private function newSettings() {
		$defaults = [
			'siteGlobalID' => 'enwiki',
			'languageLinkSiteGroup' => 'wikipedia',
			'namespaces' => [ NS_MAIN, NS_CATEGORY ],
			'otherProjectsLinks' => [ 'commonswiki' ],
		];

		return new SettingsArray( $defaults );
	}

	private function newSidebarHookHandler() {
		$en = $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' );
		$namespaces = $this->newSettings()->getSetting( 'namespaces' );
		$namespaceChecker = new NamespaceChecker(
			$this->getServiceContainer()->getNamespaceInfo(),
			[],
			$namespaces
		);

		$badgeDisplay = new LanguageLinkBadgeDisplay(
			new SidebarLinkBadgeDisplay(
				$this->getLabelDescriptionLookup(),
				[ 'Q17' => 'featured' ],
				$en
			)
		);

		return new SidebarHookHandler(
			WikibaseClient::getEntityIdLookup(),
			new ItemIdParser(),
			$badgeDisplay,
			$namespaceChecker,
			WikibaseClient::getRepoLinker()
		);
	}

	public function testOnSidebarBeforeOutput() {

		$title = Title::makeTitle( NS_MAIN, 'Oxygen' );

		$context = new RequestContext();

		$output = new OutputPage( $context );
		$output->setTitle( $title );
		$output->setProperty( 'wikibase_item', 'Q42' );
		$output->setProperty( 'wikibase-otherprojects-sidebar', [ [ 'id' => 'foo' ], [ 'id' => 'foo' ] ] );

		$context->setOutput( $output );
		$skin = $this->newSkin( $context );
		$sidebar = [];

		$handler = $this->newSidebarHookHandler();

		$handler->onSidebarBeforeOutput( $skin, $sidebar );

		$sidebarFilteredForWikibaseLink = array_filter( $sidebar['wikibase-otherprojects'], function( $link ){
			return $link['id'] === 't-wikibase';
		} );
		$this->assertCount( 1, $sidebarFilteredForWikibaseLink );

		$this->assertArrayNotHasKey( 'wikibase', $sidebar['TOOLBOX'] ?? [] );
	}

	private function primeParserOutput( ParserOutput $parserOutput, array $pageProps, array $extensionData, array $extensionDataAppend ) {
		foreach ( $pageProps as $name => $value ) {
			$parserOutput->setUnsortedPageProperty( $name, $value );
		}

		foreach ( $extensionData as $key => $value ) {
			$parserOutput->setExtensionData( $key, $value );
		}

		foreach ( $extensionDataAppend as $key => $value ) {
			foreach ( $value as $item ) {
				$parserOutput->appendExtensionData( $key, $item );
			}
		}
	}

	public function testOnOutputPageParserOutput() {
		$title = Title::makeTitle( NS_MAIN, 'Oxygen' );

		$sisterLinks = [
			[
				'msg' => 'wikibase-otherprojects-test',
				'class' => 'wb-otherproject-link wb-otherproject-test',
				'href' => 'http://acme.tests.com/wiki/Foo',
			],
		];

		$pageProps = [
			'wikibase_item' => 'Q1',
		];

		$extData = [
			'wikibase-otherprojects-sidebar' => $sisterLinks,
		];

		$extDataAppend = [
			NoLangLinkHandler::EXTENSION_DATA_KEY => [ '*' ],
		];

		$outputProps = [
			'noexternallanglinks' => [ '*' ],
			'wikibase_item' => 'Q1',
			'wikibase-otherprojects-sidebar' => $sisterLinks,
		];

		$handler = $this->newSidebarHookHandler();

		$parserOutput = new ParserOutput();

		$context = new RequestContext();
		$outputPage = new OutputPage( $context );
		$outputPage->setTitle( $title );

		$this->primeParserOutput( $parserOutput, $pageProps, $extData, $extDataAppend );

		$handler->onOutputPageParserOutput( $outputPage, $parserOutput );

		$this->assertOutputPageProperties( $outputProps, $outputPage );
	}

	public function testOnSkinTemplateGetLanguageLink() {
		$badges = [
			'en' => [
				'class' => 'badge-Q3',
				'label' => 'Lesenswerter Artikel',
			],
		];

		$link = [
			'href' => 'http://acme.com',
			'class' => 'foo',
		];

		$expected = [
			'href' => 'http://acme.com',
			'class' => 'foo badge-Q3',
			'itemtitle' => 'Lesenswerter Artikel',
		];

		$languageLinkTitle = Title::makeTitle( NS_MAIN, 'Test', '', 'en' );

		$dummy = Title::makeTitle( NS_MAIN, 'Dummy' );

		$context = new RequestContext();
		$output = new OutputPage( $context );
		$output->setProperty( 'wikibase_badges', $badges );

		$handler = $this->newSidebarHookHandler();
		$handler->onSkinTemplateGetLanguageLink( $link, $languageLinkTitle, $dummy, $output );

		$this->assertEquals( $expected, $link );
	}

	/**
	 * @param IContextSource $context
	 *
	 * @return Skin
	 */
	private function newSkin( IContextSource $context ) {
		$skin = $this->createMock( Skin::class );

		$skin->method( 'getContext' )
			->willReturn( $context );

		$skin->method( 'getOutput' )
			->willReturn( $context->getOutput() );

		$skin->method( 'msg' )
			->willReturn( new Message( "" ) );

		return $skin;
	}

	/**
	 * Call the buildOtherProjectsSidebar() function on the SidebarHookHandlers object under test.
	 *
	 * @param array|null $projects A list of projects
	 * @param string $itemId
	 *
	 * @return array The resulting sidebar array
	 */
	private function callBuildOtherProjectsSidebar( $projects, $itemId = 'Q42' ) {
		$title = Title::makeTitle( NS_MAIN, 'Oxygen' );

		$context = new RequestContext();

		$output = new OutputPage( $context );
		$output->setTitle( $title );
		$output->setProperty( 'wikibase_item', $itemId );
		$output->setProperty( 'wikibase-otherprojects-sidebar', $projects );

		$context->setOutput( $output );
		$skin = $this->newSkin( $context );

		$sidebar = [];

		$handler = $this->newSidebarHookHandler();

		$sidebar = $handler->buildOtherProjectsSidebar( $skin );

		return $sidebar;
	}

	public function testBuildOtherProjectsSidebar() {
		$projects = [ 'foo' => 'bar' ];

		$this->assertIsArray( $this->callBuildOtherProjectsSidebar( $projects ) );
	}

	public function testBuildOtherProjectsSidebar_noItem() {
		$sidebar = $this->callBuildOtherProjectsSidebar( null, null );

		$this->assertNull( $sidebar );
	}

	public function testBuildOtherProjectsSidebar_empty() {
		$projects = [];
		$sidebar = $this->callBuildOtherProjectsSidebar( $projects );

		$this->assertNull( $sidebar );
	}

	private function assertOutputPageProperties( $props, OutputPage $outputPage ) {
		$this->assertIsArray( $props );

		foreach ( $props as $key => $value ) {
			$this->assertEquals( $value, $outputPage->getProperty( $key ), 'OutputProperty: ' . $key );
		}
	}

}
