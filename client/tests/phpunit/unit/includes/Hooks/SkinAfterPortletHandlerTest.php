<?php

namespace Wikibase\Client\Tests\Unit\Hooks;

use MediaWiki\Context\IContextSource;
use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\Skin;
use MockTitleTrait;
use PHPUnit\Framework\TestCase;
use Wikibase\Client\Hooks\SkinAfterPortletHandler;
use Wikibase\Client\NamespaceChecker;
use Wikibase\Client\RepoItemLinkGenerator;
use Wikibase\Client\RepoLinker;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\Lib\SubEntityTypesMapper;

/**
 * @covers \Wikibase\Client\Hooks\SkinAfterPortletHandler
 *
 * @group WikibaseClient
 * @group WikibaseHooks
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Marius Hoch < hoo@online.de >
 */
class SkinAfterPortletHandlerTest extends TestCase {
	use MockTitleTrait;

	public function testDoSkinAfterPortlet_editLink() {
		$handler = $this->getHookHandler();

		$languageUrls = [ 'en' ];
		$noExternalLangLinks = null;

		$result = $handler->doSkinAfterPortlet(
			$this->getSkin( $noExternalLangLinks, $languageUrls )
		);

		$this->assertStringContainsString( '(wikibase-editlinks)', $result );
	}

	public function testDoSkinAfterPortlet_addLink() {
		$handler = $this->getHookHandler();

		$languageUrls = [];
		$noExternalLangLinks = null;

		$result = $handler->doSkinAfterPortlet(
			$this->getSkin( $noExternalLangLinks, $languageUrls )
		);

		$this->assertStringContainsString( '(wikibase-linkitem-addlinks)', $result );
	}

	public function testDoSkinAfterPortlet_nonViewAction() {
		$handler = $this->getHookHandler();

		$languageUrls = [ 'en' ];
		$noExternalLangLinks = null;
		$action = 'edit';

		$result = $handler->doSkinAfterPortlet(
			$this->getSkin( $noExternalLangLinks, $languageUrls, $action )
		);

		$this->assertNull( $result );
	}

	public function testDoSkinAfterPortlet_actionLinkSuppressed() {
		$handler = $this->getHookHandler();

		$languageUrls = [ 'en' ];
		$noExternalLangLinks = [ '*' ];

		$result = $handler->doSkinAfterPortlet(
			$this->getSkin( $noExternalLangLinks, $languageUrls )
		);

		$this->assertNull( $result );
	}

	private function getRepoLinker() {
		$baseUrl = 'http://www.example.com';
		$articlePath = '/wiki/$1';
		$scriptPath = '';

		return new RepoLinker(
			new EntitySourceDefinitions( [], new SubEntityTypesMapper( [] ) ),
			$baseUrl,
			$articlePath,
			$scriptPath
		);
	}

	private function getHookHandler() {
		$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		$linkGenerator = new RepoItemLinkGenerator(
			new NamespaceChecker( $namespaceInfo, [] ),
			$this->getRepoLinker(),
			new ItemIdParser(),
			'wikipedia',
			'enwiki'
		);

		return new SkinAfterPortletHandler( $linkGenerator );
	}

	/**
	 * @param string[]|null $noExternalLangLinks
	 * @param string[] $languageUrls
	 * @param string $action
	 * @return Skin
	 */
	private function getSkin( $noExternalLangLinks, $languageUrls, $action = 'view' ) {
		$skin = $this->createMock( Skin::class );

		$context = $this->getContext( $action );
		$output = new OutputPage( $context );
		$output->setProperty( 'wikibase_item', 'Q2013' );
		$output->setProperty( 'noexternallanglinks', $noExternalLangLinks );
		$title = $output->getTitle();

		$skin->method( 'getOutput' )
			->willReturn( $output );
		$skin->method( 'getTitle' )
			->willReturn( $title );
		$skin->method( 'getLanguages' )
			->willReturn( $languageUrls );
		$skin->method( 'getActionName' )
			->willReturn( $action );
		$skin->method( 'getContext' )
			->willReturn( $context );

		return $skin;
	}

	/**
	 * @param string $action
	 * @return IContextSource
	 */
	private function getContext( $action ) {
		$request = new FauxRequest( [ 'action' => $action ] );

		$title = $this->makeMockTitle( 'Page' );
		$lang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'qqx' );

		$context = $this->createMock( IContextSource::class );
		$context->method( 'getRequest' )
			->willReturn( $request );
		$context->method( 'getTitle' )
			->willReturn( $title );
		$context->method( 'getLanguage' )
			->willReturn( $lang );
		$context->method( 'msg' )
			->willReturnCallback( function ( $key ) use ( $lang ) {
				return ( new RawMessage( "($key)" ) )->inLanguage( $lang );
			} );
		$context->method( 'getConfig' )
			->willReturn(
				MediaWikiServices::getInstance()->getMainConfig()
			);
		return $context;
	}
}
