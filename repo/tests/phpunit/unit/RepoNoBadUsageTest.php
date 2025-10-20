<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\Unit;

use Wikibase\Lib\Tests\NoBadUsageTestBase;

/**
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @coversNothing
 */
class RepoNoBadUsageTest extends NoBadUsageTestBase {

	protected static function getBadPatternsWithAllowedUsages(): array {
		return [
			// don’t reference client in repo
			'WikibaseClient::' => [
				'includes/ChangeModification/DispatchChangesJob.php' => 1, // guarded by isClientEnabled()
			],
			'WikibaseClient.' => [
				'tests/phpunit/includes/Content/EntityHandlerTestCase.php' => 1, // mock, guarded by isClientEnabled()
			],
			'Wikibase\\Client\\' => [
				'includes/ChangeModification/DispatchChangesJob.php' => 1, // see above
			],
			'Wikibase\\\\Client\\\\' => [],
			// don’t use MediaWiki RDBMS – use our RDBMS instead (DomainDb etc.)
			'/\b(get|I|)LBFactory(?:;)/' => [
				'tests/phpunit/unit/ServiceWiringTestCase.php' => true, // mock
			],
			'/\b((get)?(DB)?|I|)LoadBalancer(Factory)?(?!::|;)/' => [
				'WikibaseRepo.ServiceWiring.php' => 2, // RepoDomainDbFactory+TermsDomainDbFactory service wiring
				'tests/phpunit/includes/Store/Sql/WikiPageEntityMetaDataLookupTest.php' => true, // mock
				'tests/phpunit/unit/ServiceWiringTestCase.php' => true, // mock
				'maintenance/DumpEntities.php' => 1, // To set the dump db group
			],
		];
	}

	protected static function getBaseDir(): string {
		return __DIR__ . '/../../../';
	}

	protected static function getThisFile(): string {
		return __FILE__;
	}

}
