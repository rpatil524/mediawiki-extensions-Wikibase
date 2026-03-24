<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\Domains\Reuse\Application\UseCases\LookUpItemByExternalId;

use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Repo\Domains\Reuse\Application\UseCases\LookUpItemByExternalId\LookUpItemByExternalId;
use Wikibase\Repo\Domains\Reuse\Application\UseCases\LookUpItemByExternalId\LookUpItemByExternalIdRequest;
use Wikibase\Repo\Domains\Reuse\Domain\Services\ItemByExternalIdLookup;

/**
 * @covers \Wikibase\Repo\Domains\Reuse\Application\UseCases\LookUpItemByExternalId\LookUpItemByExternalId
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class LookUpItemByExternalIdTest extends TestCase {

	public function testSuccess(): void {
		$propertyId = new NumericPropertyId( 'P31' );
		$externalId = 'some-external-id';
		$itemIds = [ 'Q42' ];

		$lookup = $this->createMock( ItemByExternalIdLookup::class );
		$lookup->expects( $this->once() )
			->method( 'lookupByExternalId' )
			->with( $propertyId, $externalId )
			->willReturn( $itemIds );

		$response = ( new LookUpItemByExternalId( $lookup ) )
			->execute( new LookUpItemByExternalIdRequest( $propertyId, $externalId ) );

		$this->assertSame( $itemIds, $response->itemIds );
	}

}
