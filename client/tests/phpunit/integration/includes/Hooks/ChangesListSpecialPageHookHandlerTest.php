<?php

namespace Wikibase\Client\Tests\Unit\Hooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Html\FormOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\RecentChanges\ChangesListBooleanFilter;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Specials\SpecialRecentChanges;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikibase\Client\Hooks\ChangesListSpecialPageHookHandler;
use Wikimedia\Rdbms\Expression;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Wikibase\Client\Hooks\ChangesListSpecialPageHookHandler
 *
 * @group WikibaseClient
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Matthew Flaschen < mflaschen@wikimedia.org >
 */
class ChangesListSpecialPageHookHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var ExtensionRegistry
	 */
	protected $extensionRegistry;

	/**
	 * @var array
	 */
	protected $oldExtensionRegistryLoaded;

	protected function setUp(): void {
		parent::setUp();

		$this->extensionRegistry = TestingAccessWrapper::newFromObject(
			ExtensionRegistry::getInstance()
		);

		$this->oldExtensionRegistryLoaded = $this->extensionRegistry->loaded;

		// Forget about the other extensions.  Although ORES may be loaded,
		// since this is only unit-testing *our* listener, ORES's listener
		// hasn't run, so for all intents and purposes it's not loaded.
		$this->extensionRegistry->loaded = array_intersect_key(
			$this->extensionRegistry->loaded,
			[
				'WikibaseRepository' => [],
				'WikibaseClient' => [],
			]
		);
	}

	protected function tearDown(): void {
		parent::tearDown();

		$this->extensionRegistry->loaded = $this->oldExtensionRegistryLoaded;
	}

	public function testAddFilter() {
		$user = $this->createMock( User::class );
		$user->method( 'getName' )->willReturn( 'page hook test user' );

		/** @var SpecialRecentChanges $specialPage */
		$specialPage = MediaWikiServices::getInstance()->getSpecialPageFactory()
			->getPage( 'Recentchanges' );

		/** @var ChangesListSpecialPageHookHandler $hookHandler */
		$hookHandler = TestingAccessWrapper::newFromObject( new ChangesListSpecialPageHookHandler(
			$this->getDatabase(),
			true,
			false,
			MediaWikiServices::getInstance()->getUserOptionsLookup()
		) );

		$context = new RequestContext();
		$context->setUser( $user );
		$specialPage->setContext( $context );

		// Register built-in filters, since the Wikidata one uses its group
		$specialPage->setHookContainer( $this->createHookContainer() );
		$specialPage->setup( '' );

		$hookHandler->addFilter( $specialPage );

		$changeType = $specialPage->getFilterGroup( 'changeType' );
		/** @var ChangesListBooleanFilter $filter */
		$filter = $changeType->getFilter( 'hideWikibase' );

		// I could do all of getJsData(), but that would make it brittle to
		// unrelated changes.
		$expectedFields = [
			'label' => 'wikibase-rcfilters-hide-wikibase-label',
			'description' => 'wikibase-rcfilters-hide-wikibase-description',
			'showHide' => 'wikibase-rc-hide-wikidata',
			'default' => true,
		];

		$actualFields = [
			'label' => $filter->getLabel(),
			'description' => $filter->getDescription(),
			'showHide' => $filter->getShowHide(),
			'default' => $filter->getDefault(),
		];

		$this->assertSame(
			$expectedFields,
			$actualFields
		);
	}

	public function testOnChangesListSpecialPageQuery() {
		$tables = [ 'recentchanges' ];
		$fields = [ 'rc_id' ];
		$conds = [];
		$query_options = [];
		$join_conds = [];
		$opts = new FormOptions();

		$handler = new ChangesListSpecialPageHookHandler(
			$this->getDatabase(),
			true,
			false,
			MediaWikiServices::getInstance()->getUserOptionsLookup()
		);

		$handler->onChangesListSpecialPageQuery(
			'RecentChanges',
			$tables,
			$fields,
			$conds,
			$query_options,
			$join_conds,
			$opts
		);

		// this is just a sanity check
		$this->assertIsArray( $conds );
	}

	/**
	 * @dataProvider addWikibaseConditionsIfFilterUnavailableProvider
	 */
	public function testAddWikibaseConditionsIfFilterUnavailable( $expectedAddConditionsCalls, $hasWikibaseChangesEnabled ) {
		$hookHandlerMock = $this->getMockBuilder( ChangesListSpecialPageHookHandler::class )
			->setConstructorArgs(
				[
					$this->getDatabase(),
					true,
					false,
					$this->getUserOptionsLookup(),
				]
			)
			->onlyMethods( [
					'hasWikibaseChangesEnabled',
					'addWikibaseConditions',
				] )
			->getMock();

		$hookHandlerMock->method( 'hasWikibaseChangesEnabled' )
			->willReturn( $hasWikibaseChangesEnabled );

		$hookHandlerMock->expects( $this->exactly( $expectedAddConditionsCalls ) )
			->method( 'addWikibaseConditions' )
			->with( $this->isInstanceOf( IDatabase::class ), [] );

		$tables = [ 'recentchanges' ];
		$fields = [ 'rc_id' ];
		$query_options = [];
		$join_conds = [];
		$conds = [];

		$hookHandlerMock->onChangesListSpecialPageQuery( '', $tables, $fields,
			$conds, $query_options, $join_conds, new FormOptions() );
	}

	public static function addWikibaseConditionsIfFilterUnavailableProvider() {
		return [
			[
				0,
				true,
			],

			[
				1,
				false,
			],
		];
	}

	/**
	 * @dataProvider getOptionNameProvider
	 */
	public function testGetOptionName( $expected, $pageName ) {
		/** @var ChangesListSpecialPageHookHandler $hookHandler */
		$hookHandler = TestingAccessWrapper::newFromObject( new ChangesListSpecialPageHookHandler(
			$this->getDatabase(),
			true,
			false,
			MediaWikiServices::getInstance()->getUserOptionsLookup()
		) );

		$this->assertSame(
			$expected,
			$hookHandler->getOptionName( $pageName )
		);
	}

	public static function getOptionNameProvider() {
		return [
			[
				'wlshowwikibase',
				'Watchlist',
			],

			[
				'rcshowwikidata',
				'Recentchanges',
			],

			[
				'rcshowwikidata',
				'Recentchangeslinked',
			],
		];
	}

	public function testAddWikibaseConditions() {
		$db = $this->getDatabase();
		$hookHandler = new ChangesListSpecialPageHookHandler(
			$db,
			true,
			false,
			MediaWikiServices::getInstance()->getUserOptionsLookup()
		);

		$db->expects( $this->once() )
			->method( 'expr' )
			->with( 'rc_source', '!=', 'wb' )
			->willReturn( $this->createMock( Expression::class ) );

		$conds = [];
		$hookHandler->addWikibaseConditions(
			$db,
			$conds
		);

		$this->assertCount( 1, $conds );
	}

	/**
	 * @dataProvider hasWikibaseChangesEnabledWhenUsingEnhancedChangesProvider
	 */
	public function testHasWikibaseChangesEnabledWhenUsingEnhancedChanges(
		array $requestParams,
		array $userOptions,
		$pageName,
		$message
	) {
		/** @var ChangesListSpecialPageHookHandler $hookHandler */
		$hookHandler = TestingAccessWrapper::newFromObject( new ChangesListSpecialPageHookHandler(
			$this->getDatabase(),
			true,
			false,
			MediaWikiServices::getInstance()->getUserOptionsLookup()
		) );

		$this->assertSame(
			true,
			$hookHandler->hasWikibaseChangesEnabled(),
			$message
		);
	}

	public static function hasWikibaseChangesEnabledWhenUsingEnhancedChangesProvider() {
		return [
			[
				[],
				[ 'usenewrc' => 1 ],
				'Watchlist',
				'enhanced default pref for Watchlist',
			],
			[
				[],
				[ 'usenewrc' => 1 ],
				'RecentChanges',
				'enhanced default pref for RecentChanges',
			],
			[
				[ 'enhanced' => 1 ],
				[ 'usenewrc' => 0 ],
				'Watchlist',
				'enhanced not default but has enhanced=1 req param',
			],
			[
				[ 'enhanced' => 1 ],
				[ 'usenewrc' => 0 ],
				'RecentChanges',
				'enhanced not default but has enhanced=1 req param',
			],
			[
				[ 'enhanced' => 1 ],
				[ 'usenewrc' => 1 ],
				'Watchlist',
				'enhanced default and has enhanced=1 req param',
			],
			[
				[ 'enhanced' => 1 ],
				[ 'usenewrc' => 1 ],
				'RecentChangesLinked',
				'enhanced default and has enhanced=1 req param',
			],
		];
	}

	/**
	 * @dataProvider hasWikibaseChangesEnabled_withoutShowWikibaseEditsByDefaultPreferenceProvider
	 */
	public function testHasWikibaseChangesEnabled_withoutShowWikibaseEditsByDefaultPreference(
		array $requestParams,
		array $userOptions,
		$expectedFilterName,
		$expectedToggleDefault,
		$specialPageName
	) {
		/** @var ChangesListSpecialPageHookHandler $hookHandler */
		$hookHandler = TestingAccessWrapper::newFromObject( new ChangesListSpecialPageHookHandler(
			$this->getDatabase(),
			true,
			false,
			MediaWikiServices::getInstance()->getUserOptionsLookup()
		) );

		$this->assertSame(
			true,
			$hookHandler->hasWikibaseChangesEnabled()
		);
	}

	public static function hasWikibaseChangesEnabled_withoutShowWikibaseEditsByDefaultPreferenceProvider() {
		return [
			[
				[],
				[ 'usenewrc' => 0 ],
				'hideWikibase',
				true,
				'Watchlist',
			],
			[
				[ 'enhanced' => 0 ],
				[ 'usenewrc' => 1 ],
				'hideWikibase',
				true,
				'RecentChanges',
			],
			[
				[],
				[ 'usenewrc' => 0 ],
				'hideWikibase',
				true,
				'RecentChangesLinked',
			],
			[
				[ 'action' => 'submit', 'hideWikibase' => 0 ],
				[ 'usenewrc' => 0 ],
				'hideWikibase',
				false,
				'Watchlist',
			],
			[
				[ 'action' => 'submit', 'hideWikibase' => 1 ],
				[ 'usenewrc' => 0 ],
				'hideWikibase',
				true,
				'Watchlist',
			],
			[
				[ 'action' => 'submit' ],
				[ 'usenewrc' => 0 ],
				'hideWikibase',
				false,
				'Watchlist',
			],
		];
	}

	/**
	 * @dataProvider hasWikibaseChangesEnabled_withShowWikibaseEditsByDefaultPreferenceProvider
	 */
	public function testHasWikibaseChangesEnabled_withShowWikibaseEditsByDefaultPreference(
		array $requestParams,
		array $userOptions,
		$expectedFilterName,
		$expectedToggleDefault,
		$specialPageName
	) {
		/** @var ChangesListSpecialPageHookHandler $hookHandler */
		$hookHandler = TestingAccessWrapper::newFromObject( new ChangesListSpecialPageHookHandler(
			$this->getDatabase(),
			true,
			false,
			MediaWikiServices::getInstance()->getUserOptionsLookup()
		) );

		$this->assertSame(
			true,
			$hookHandler->hasWikibaseChangesEnabled()
		);
	}

	public static function hasWikibaseChangesEnabled_withShowWikibaseEditsByDefaultPreferenceProvider() {
		return [
			[
				[],
				[ 'wlshowwikibase' => 1, 'usenewrc' => 0 ],
				'hideWikibase',
				false,
				'Watchlist',
			],
			[
				[ 'enhanced' => 0 ],
				[ 'rcshowwikidata' => 1, 'usenewrc' => 1 ],
				'hideWikibase',
				false,
				'RecentChanges',
			],
			[
				[],
				[ 'rcshowwikidata' => 1, 'usenewrc' => 0 ],
				'hideWikibase',
				false,
				'RecentChangesLinked',
			],
			[
				[ 'action' => 'submit', 'hideWikibase' => 0 ],
				[ 'wlshowwikibase' => 1, 'usenewrc' => 0 ],
				'hideWikibase',
				false,
				'Watchlist',
			],
			[
				[ 'action' => 'submit' ],
				[ 'wlshowwikibase' => 1, 'usenewrc' => 0 ],
				'hideWikibase',
				false,
				'Watchlist',
			],
		];
	}

	/**
	 * @dataProvider hasWikibaseChangesEnabledWhenExternalRecentChangesDisabledProvider
	 */
	public function testHasWikibaseChangesEnabledWhenExternalRecentChangesDisabled( $specialPageName ) {
		/** @var ChangesListSpecialPageHookHandler $hookHandler */
		$hookHandler = TestingAccessWrapper::newFromObject( new ChangesListSpecialPageHookHandler(
			$this->getDatabase(),
			false,
			false,
			MediaWikiServices::getInstance()->getUserOptionsLookup()
		) );

		$this->assertSame(
			false,
			$hookHandler->hasWikibaseChangesEnabled()
		);
	}

	public static function hasWikibaseChangesEnabledWhenExternalRecentChangesDisabledProvider() {
		return [
			[ 'Watchlist' ],
			[ 'Recentchanges' ],
			[ 'Recentchangeslinked' ],
		];
	}

	/**
	 * @return IDatabase
	 */
	private function getDatabase() {
		$databaseBase = $this->createMock( IDatabase::class );

		$databaseBase->method( 'addQuotes' )
			->willReturnCallback( static function ( $input ) {
				return "'$input'";
			} );

		return $databaseBase;
	}

	/**
	 * @return UserOptionsLookup
	 */
	private function getUserOptionsLookup() {
		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		return $userOptionsLookup;
	}
}
