<?php

namespace Wikibase\Client\Tests\Integration\Specials;

use Iterator;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use SpecialPageTestBase;
use Wikibase\Client\NamespaceChecker;
use Wikibase\Client\Specials\SpecialUnconnectedPages;
use Wikibase\Client\WikibaseClient;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \Wikibase\Client\Specials\SpecialUnconnectedPages
 *
 * @group WikibaseClient
 * @group SpecialPage
 * @group WikibaseSpecialPage
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Thiemo Kreuz
 */
class SpecialUnconnectedPagesTest extends SpecialPageTestBase {

	protected function setUp(): void {
		$this->setService(
			'WikibaseClient.NamespaceChecker',
			new NamespaceChecker(
				$this->getServiceContainer()->getNamespaceInfo(),
				[],
				[ $this->getDefaultWikitextNS(), $this->getDefaultWikitextNS() + 1 ]
			)
		);

		parent::setUp();
	}

	public function addDBDataOnce(): void {
		$namespace = $this->getDefaultWikitextNS();

		// Remove old stray pages.
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'page' )
			->where( IDatabase::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();

		$expectedUnconnectedTitle = Title::makeTitle( $namespace, 'SpecialUnconnectedPagesTest-expectedUnconnected' );
		$unconnectedTitle = Title::makeTitle( $namespace, 'SpecialUnconnectedPagesTest-unconnected' );
		$connectedTitle = Title::makeTitle( $namespace, 'SpecialUnconnectedPagesTest-connected' );
		$furtherUnconnectedTitle = Title::makeTitle( $namespace, 'SpecialUnconnectedPagesTest-unconnected2' );

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $expectedUnconnectedTitle );
		$page->insertOn( $this->getDb(), 100 );

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $unconnectedTitle );
		$page->insertOn( $this->getDb(), 200 );

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $connectedTitle );
		$page->insertOn( $this->getDb(), 300 );

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $furtherUnconnectedTitle );
		$page->insertOn( $this->getDb(), 400 );
	}

	private function insertPageProp(
		int $pageId,
		string $propName,
		string $value = '',
		float $sortKey = 0.0
	): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'page_props' )
			->row( [
				'pp_page' => $pageId,
				'pp_propname' => $propName,
				'pp_value' => $value,
				'pp_sortkey' => $sortKey,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function insertExpectedUnconnectedPagePageProp(): void {
		$this->insertPageProp( 100, 'expectedUnconnectedPage' );
	}

	private function insertUnexpectedUnconnectedPagePageProp(): void {
		$namespace = $this->getDefaultWikitextNS();
		$this->insertPageProp( 200, 'unexpectedUnconnectedPage', $namespace, $namespace );
		$this->insertPageProp( 400, 'unexpectedUnconnectedPage', $namespace, $namespace );
	}

	private function insertWikibaseItemPageProp(): void {
		$this->insertPageProp( 300, 'wikibase_item', 'Q12' );
	}

	protected function newSpecialPage(
		?NamespaceChecker $namespaceChecker = null
	): SpecialUnconnectedPages {
		$services = $this->getServiceContainer();
		return new SpecialUnconnectedPages(
			$services->getConnectionProvider(),
			$services->getNamespaceInfo(),
			$services->getTitleFactory(),
			WikibaseClient::getClientDomainDbFactory( $services ),
			$namespaceChecker ?: WikibaseClient::getNamespaceChecker( $services )
		);
	}

	public function testReallyDoQuery() {
		// Remove old stray page props
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'page_props' )
			->where( IDatabase::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();

		// Insert page props
		$this->insertWikibaseItemPageProp();
		$this->insertExpectedUnconnectedPagePageProp();
		$this->insertUnexpectedUnconnectedPagePageProp();

		$namespace = $this->getDefaultWikitextNS();
		$specialPage = $this->newSpecialPage();

		$expectedRows = [
			[
				'value' => '400',
				'namespace' => strval( $namespace ),
				'title' => 'SpecialUnconnectedPagesTest-unconnected2',
			],
			[
				'value' => '200',
				'namespace' => strval( $namespace ),
				'title' => 'SpecialUnconnectedPagesTest-unconnected',
			],
		];

		// First entry
		$res = $specialPage->reallyDoQuery( 1 );
		$this->assertSame( 1, $res->numRows() );
		$this->assertSame( $expectedRows[ 0 ], (array)$res->fetchObject() );

		// Continue with offset
		$res = $specialPage->reallyDoQuery( 10, 1 );
		$this->assertSame( 1, $res->numRows() );
		$this->assertSame( $expectedRows[ 1 ], (array)$res->fetchObject() );

		// Get all entries at once
		$res = $specialPage->reallyDoQuery( 5 );
		$this->assertSame( 2, $res->numRows() );
		$this->assertSame( $expectedRows, [ (array)$res->fetchObject(), (array)$res->fetchObject() ] );
	}

	public function testReallyDoQuery_noResults() {
		// Remove old stray page props
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'page_props' )
			->where( IDatabase::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();

		// Insert page props
		$this->insertWikibaseItemPageProp();
		$this->insertExpectedUnconnectedPagePageProp();
		$this->insertUnexpectedUnconnectedPagePageProp();

		$specialPage = $this->newSpecialPage();
		// Query another namespace
		$specialPage->getRequest()->setVal( 'namespace', $this->getDefaultWikitextNS() + 1 );

		$this->assertSame( 0, $specialPage->reallyDoQuery( 10 )->numRows() );
	}

	/**
	 * Integration test that ensures that the "unexpectedUnconnectedPage" page
	 * prop is used.
	 */
	public function testReallyDoQuery_unexpectedUnconnectedPage() {
		// Make sure only the "unexpectedUnconnectedPage" page prop exists
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'page_props' )
			->where( IDatabase::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
		$this->insertUnexpectedUnconnectedPagePageProp();

		$namespace = $this->getDefaultWikitextNS();
		$specialPage = $this->newSpecialPage();
		$specialPage->getRequest()->setVal( 'namespace', $namespace );
		$res = $specialPage->reallyDoQuery( 1, 1 );
		$this->assertSame( 1, $res->numRows() );
		$this->assertSame( [
				'value' => '200',
				'namespace' => strval( $namespace ),
				'title' => 'SpecialUnconnectedPagesTest-unconnected',
			],
			(array)$res->fetchObject()
		);
	}

	/**
	 * @dataProvider provideBuildNamespaceConditionals
	 */
	public function testBuildNamespaceConditionals( ?int $ns, array $expected ) {
		$namespaceInfo = $this->getServiceContainer()->getNamespaceInfo();
		$checker = new NamespaceChecker( $namespaceInfo, [ 2 ], [ 0, 4 ] );
		$page = $this->newSpecialPage( $checker );
		$page->getRequest()->setVal( 'namespace', $ns );
		$this->assertSame( $expected, $page->buildNamespaceConditionals() );
	}

	public static function provideBuildNamespaceConditionals(): Iterator {
		yield 'no namespace' => [
			null,
			[ 'pp_sortkey' => [ 0, -4 ] ],
		];
		yield 'included namespace' => [
			0,
			[ 'pp_sortkey' => 0 ],
		];
		yield 'included nonzero namespace' => [
			4,
			[ 'pp_sortkey' => -4 ],
		];
		yield 'excluded namespace' => [
			2,
			[ 'pp_sortkey' => [ 0, -4 ] ],
		];
	}

	public function testGetQueryInfo() {
		$page = $this->newSpecialPage();
		$queryInfo = $page->getQueryInfo();
		$this->assertIsArray( $queryInfo );
		$this->assertNotEmpty( $queryInfo );

		$this->assertStringContainsString(
			json_encode( 'unexpectedUnconnectedPage' ),
			json_encode( $queryInfo['join_conds']['page_props'] )
		);

		$this->assertArrayHasKey( 'conds', $queryInfo );
	}

	public function testReallyDoQueryReturnsEmptyResultWhenExceedingLimit() {
		$page = $this->newSpecialPage();
		$result = $page->reallyDoQuery( 1, 10001 );
		$this->assertSame( 0, $result->numRows() );
	}

	public function testFetchFromCacheReturnsEmptyResultWhenExceedingLimit() {
		$page = $this->newSpecialPage();
		$result = $page->fetchFromCache( 1, 10001 );
		$this->assertSame( 0, $result->numRows() );
	}

	public function testFormatResult() {
		$skin = $this->createMock( Skin::class );
		$result = new \stdClass();
		$result->value = 1;

		$services = $this->getServiceContainer();
		$namespaceInfo = $services->getNamespaceInfo();
		$namespaceChecker = new NamespaceChecker( $namespaceInfo, [] );

		$titleFactoryMock = $this->createMock( TitleFactory::class );

		$titleFactoryMock->method( 'newFromID' )
			->willReturn( null );

		$specialPage = new SpecialUnconnectedPages(
			$services->getConnectionProvider(),
			$services->getNamespaceInfo(),
			$titleFactoryMock,
			WikibaseClient::getClientDomainDbFactory( $services ),
			$namespaceChecker ?: WikibaseClient::getNamespaceChecker( $services )
		);

		$this->assertFalse( $specialPage->formatResult( $skin, $result ) );
	}

}
