<?php
declare( strict_types = 1 );

namespace Wikibase\Client\Tests\Unit\ServiceWiring;

use Psr\Log\NullLogger;
use Wikibase\Client\Tests\Unit\ServiceWiringTestCase;
use Wikibase\Lib\Rdbms\TermsDomainDbFactory;
use Wikibase\Lib\Store\Sql\Terms\TermInLangIdsResolverFactory;

/**
 * @coversNothing
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class TermInLangIdsResolverFactoryTest extends ServiceWiringTestCase {

	public function testConstruction(): void {
		$this->mockService(
			'WikibaseClient.Logger',
			new NullLogger()
		);

		$this->mockService(
			'WikibaseClient.TermsDomainDbFactory',
			$this->createStub( TermsDomainDbFactory::class )
		);

		$this->assertInstanceOf(
			TermInLangIdsResolverFactory::class,
			$this->getService( 'WikibaseClient.TermInLangIdsResolverFactory' )
		);
	}

}
