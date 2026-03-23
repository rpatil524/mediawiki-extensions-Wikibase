<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\Domains\Search\Infrastructure\Controllers;

use PHPUnit\Framework\TestCase;
use Wikibase\DataAccess\EntitySourceLookup;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\DispatchingWbSearchEntitiesController;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\FallbackEntitySearchHelperController;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesController;

/**
 * @covers \Wikibase\Repo\Domains\Search\Infrastructure\Controllers\DispatchingWbSearchEntitiesController
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class DispatchingWbSearchEntitiesControllerTest extends TestCase {

	public function testReturnsRegisteredControllerForKnownType(): void {
		$expectedController = $this->createStub( WbSearchEntitiesController::class );
		$dispatcher = $this->newController( [ 'item' => static fn() => $expectedController ] );

		$this->assertSame( $expectedController, $dispatcher->getControllerForEntityType( 'item' ) );
	}

	public function testFallsBackToTypeDispatchingControllerForUnknownType(): void {
		$this->assertInstanceOf(
			FallbackEntitySearchHelperController::class,
			$this->newController( [] )->getControllerForEntityType( 'property' )
		);
	}

	public function testFallbackControllerDelegatesToSearchHelperWithCorrectEntityType(): void {
		$fallbackHelper = $this->createMock( EntitySearchHelper::class );
		$fallbackHelper->expects( $this->once() )
			->method( 'getRankedSearchResults' )
			->with( 'foo', 'en', 'property', 10, false, null )
			->willReturn( [] );

		$dispatcher = $this->newController( [], $fallbackHelper );
		$controller = $dispatcher->getControllerForEntityType( 'property' );

		$controller->search( 'foo', 'en', 10, false, null );
	}

	private function newController(
		array $callbacks,
		?EntitySearchHelper $fallbackHelper = null
	): DispatchingWbSearchEntitiesController {
		return new DispatchingWbSearchEntitiesController(
			$callbacks,
			$fallbackHelper ?? $this->createStub( EntitySearchHelper::class ),
			$this->createStub( EntitySourceLookup::class )
		);
	}

}
