<?php

declare( strict_types = 1 );

namespace Wikibase\Client\Tests\Integration\Hooks;

use MediaWiki\Page\PageIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikibase\Client\Hooks\DescriptionProviderHookHandler;
use Wikibase\Client\Store\DescriptionLookup;

/**
 * @covers \Wikibase\Client\Hooks\DescriptionProviderHookHandler
 * @group Database
 * @group Wikibase
 * @license GPL-2.0-or-later
 */
class DescriptionProviderHookHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideDescriptionProviderTestData
	 */
	public function testDescriptionProvider(
		$pageIdentities,
		$allowLocalShortDesc,
		$forceLocalShortDesc,
		$lookupArguments,
		$lookupResults,
		$hookResults
	) {
		$descriptionLookup = $this->createMock( DescriptionLookup::class );

		$descriptionLookup->expects( $this->once() )
			->method( 'getDescriptions' )
			->with( $this->anything(), $lookupArguments )
			->willReturn( $lookupResults );

		$results = [ 1 => null, 2 => null ];
		$handler = new DescriptionProviderHookHandler(
			$allowLocalShortDesc,
			$forceLocalShortDesc,
			$descriptionLookup
		);
		$handler->onSearchResultProvideDescription( $pageIdentities, $results );
		$this->assertEquals( $hookResults, $results );
	}

	public static function provideDescriptionProviderTestData() {
		$pageIdentities = [
			1 => PageIdentityValue::localIdentity( 1, NS_MAIN, 'One' ),
			2 => PageIdentityValue::localIdentity( 2, NS_MAIN, 'Two' ),
		];
		yield [
			$pageIdentities,
			true,
			false,
			[ DescriptionLookup::SOURCE_CENTRAL, DescriptionLookup::SOURCE_LOCAL ],
			[ 1 => 'description' ],
			[ 1 => 'description', 2 => null ],
		];
		yield [
			$pageIdentities,
			false,
			false,
			[ DescriptionLookup::SOURCE_CENTRAL ],
			[ 2 => 'description' ],
			[ 1 => null, 2 => 'description' ],
		];
		yield [
			$pageIdentities,
			true,
			true,
			[ DescriptionLookup::SOURCE_LOCAL ],
			[ 2 => 'description' ],
			[ 1 => null, 2 => 'description' ],
		];
	}
}
