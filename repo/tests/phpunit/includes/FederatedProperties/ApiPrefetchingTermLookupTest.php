<?php

declare( strict_types = 1 );
namespace Wikibase\Repo\Tests\FederatedProperties;

use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Wikibase\DataModel\Term\TermTypes;
use Wikibase\Lib\FederatedProperties\FederatedPropertyId;
use Wikibase\Repo\FederatedProperties\ApiEntityLookup;
use Wikibase\Repo\FederatedProperties\ApiPrefetchingTermLookup;
use Wikibase\Repo\FederatedProperties\FederatedPropertiesException;
use Wikibase\Repo\FederatedProperties\GenericActionApiClient;
use Wikibase\Repo\Tests\HttpResponseMockerTrait;

/**
 * @covers \Wikibase\Repo\FederatedProperties\ApiPrefetchingTermLookup
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class ApiPrefetchingTermLookupTest extends TestCase {

	use HttpResponseMockerTrait;

	/** @var string[] */
	private static array $responseDataFiles = [
		'q42-en' => 'api-prefetching-term-lookup-test-data-q42-en.json',
		'p18-en' => 'api-prefetching-term-lookup-test-data-p18-en.json',
		'p18-de' => 'api-prefetching-term-lookup-test-data-p18-de.json',
		'p18-en-de' => 'api-prefetching-term-lookup-test-data-p18-en-de.json',
		'p31-en' => 'api-prefetching-term-lookup-test-data-p31-en.json',
		'p31-de' => 'api-prefetching-term-lookup-test-data-p31-de.json',
		'p31-en-de' => 'api-prefetching-term-lookup-test-data-p31-en-de.json',
		'p18-p31-en' => 'api-prefetching-term-lookup-test-data-p18-p31-en.json',
		'p18-p31-de' => 'api-prefetching-term-lookup-test-data-p18-p31-de.json',
		'p18-p31-en-de' => 'api-prefetching-term-lookup-test-data-p18-p31-en-de.json',
	];

	/** @var string[] */
	private array $data = [];

	private FederatedPropertyId $fp18;
	private FederatedPropertyId $fp31;
	private const CONCEPT_BASE_URI = 'http://wikidata.org/entity/';

	protected function setUp(): void {
		$this->fp18 = new FederatedPropertyId( 'http://wikidata.org/entity/P18', 'P18' );
		$this->fp31 = new FederatedPropertyId( 'http://wikidata.org/entity/P31', 'P31' );

		parent::setUp();
		// Load data files once at the start of tests rather than for each test case
		foreach ( self::$responseDataFiles as $key => $file ) {
			$content = file_get_contents( __DIR__ . '/../../data/federatedProperties/' . $file );
			$this->data[$file] = json_decode( $content );
		}
	}

	/**
	 * @return array [ entityIdString, languages, responseDataFile, expectedLabels[] ]
	 */
	public static function entityIdsWithLanguagesAndExpectedLabelsProvider() {
		return [
			'p18-en' => [
				new FederatedPropertyId( 'http://wikidata.org/entity/P18', 'P18' ),
				[ 'en' ],
				self::$responseDataFiles[ 'p18-en' ],
				[ 'en' => 'image' ],
			],
			'p18-en-de' => [
				new FederatedPropertyId( 'http://wikidata.org/entity/P18', 'P18' ),
				[ 'en', 'de' ],
				self::$responseDataFiles[ 'p18-en-de' ],
				[ 'en' => 'image', 'de' => 'Bild' ],
			],
			'p31-en' => [
				new FederatedPropertyId( 'http://wikidata.org/entity/P31', 'P31' ),
				[ 'en' ],
				self::$responseDataFiles[ 'p31-en' ],
				[ 'en' => 'instance of' ],
			],
			'p31-en-de' => [
				new FederatedPropertyId( 'http://wikidata.org/entity/P31', 'P31' ),
				[ 'en', 'de' ],
				self::$responseDataFiles[ 'p31-en-de' ],
				[ 'en' => 'instance of', 'de' => 'ist ein(e)' ],
			],
		];
	}

	/**
	 * @dataProvider entityIdsWithLanguagesAndExpectedLabelsProvider
	 */
	public function testGetLabels( FederatedPropertyId $entityId, $languages, $responseFile, $expectedLabels ) {
		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup(
				$this->newMockApi( [ $entityId->getRemoteIdSerialization() ], $responseFile )
			)
		);
		$labels = $apiLookup->getLabels( $entityId, $languages );
		$this->assertEquals( $expectedLabels, $labels );
	}

	/**
	 * @dataProvider descriptionsTestProvider
	 */
	public function testGetDescriptions( FederatedPropertyId $entityId, $languages, $responseFile, $expectedDescriptions ) {
		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup(
				$this->newMockApi( [ $entityId->getRemoteIdSerialization() ], $responseFile )
			)
		);

		$this->assertEquals(
			$expectedDescriptions,
			$apiLookup->getDescriptions( $entityId, $languages )
		);
	}

	public static function descriptionsTestProvider() {
		yield 'en description' => [
			new FederatedPropertyId( 'http://wikidata.org/entity/P18', 'P18' ),
			[ 'en' ],
			self::$responseDataFiles[ 'p18-en-de' ],
			[ 'en' => 'image of relevant illustration of the subject' ],
		];
		yield 'en and de descriptions' => [
			new FederatedPropertyId( 'http://wikidata.org/entity/P18', 'P18' ),
			[ 'en', 'de' ],
			self::$responseDataFiles[ 'p18-en-de' ],
			[ 'en' => 'image of relevant illustration of the subject', 'de' => 'Foto, Grafik etc. des Objekts' ],
		];
	}

	public function testGetPrefetchedAliases() {
		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup(
				$this->createMock( GenericActionApiClient::class )
			)
		);

		$this->expectException( LogicException::class );
		$apiLookup->getPrefetchedAliases( new FederatedPropertyId( 'http://wikidata.org/entity/P1', 'P1' ), 'someLanguage' );
	}

	public function testPrefetchTermsAndGetPrefetchedTerm() {
		$api = $this->newMockApi(
			[ 'P18', 'P31' ],
			self::$responseDataFiles[ 'p18-p31-en' ]
		);

		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup( $api )
		);

		$apiLookup->prefetchTerms( [ $this->fp18, $this->fp31 ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );

		// verify that both P18 and P31 are buffered
		$this->assertSame( 'image', $apiLookup->getLabel( $this->fp18, 'en' ) );
		$this->assertSame( 'instance of', $apiLookup->getLabel( $this->fp31, 'en' ) );
	}

	public function testConsecutivePrefetch() {
		$api = $this->createMock( GenericActionApiClient::class );
		// expect two API request
		$expectedReturnMap = [
			[
				$this->getRequestParameters( [ 'P18' ] ),
				$this->newMockResponse( json_encode( $this->data[ self::$responseDataFiles[ 'p18-en' ] ] ), 200 ),
			],
			[
				$this->getRequestParameters( [ 'P31' ] ),
				$this->newMockResponse( json_encode( $this->data[ self::$responseDataFiles[ 'p31-en' ] ] ), 200 ),
			],
		];
		$api->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->willReturnCallback( function ( $params ) use ( &$expectedReturnMap ) {
				$curExpectedMap = array_shift( $expectedReturnMap );
				$this->assertSame( $curExpectedMap[0], $params );
				return $curExpectedMap[1];
			} );

		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup( $api )
		);
		$apiLookup->prefetchTerms( [ $this->fp18 ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
		$this->assertSame( 'image', $apiLookup->getLabel( $this->fp18, 'en' ) );

		$apiLookup->prefetchTerms( [ $this->fp31 ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
		// verify that P18 is still buffered
		$this->assertSame( 'image', $apiLookup->getLabel( $this->fp18, 'en' ) );
		// verify that P31 has been added to buffer
		$this->assertSame( 'instance of', $apiLookup->getLabel( $this->fp31, 'en' ) );
	}

	public function testConsecutivePrefetch_alreadyInBuffer() {
		$api = $this->createMock( GenericActionApiClient::class );
		// expect two API request
		$expectedReturnMap = [
			[
				$this->getRequestParameters( [ 'P18' ] ),
				$this->newMockResponse( json_encode( $this->data[ self::$responseDataFiles[ 'p18-en' ] ] ), 200 ),
			],
			// the second request will NOT ask for P18, that has already been fetched
			[
				$this->getRequestParameters( [ 'P31' ] ),
				$this->newMockResponse( json_encode( $this->data[ self::$responseDataFiles[ 'p31-en' ] ] ), 200 ),
			],
		];
		$api->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->willReturnCallback( function ( $params ) use ( &$expectedReturnMap ) {
				$curExpectedMap = array_shift( $expectedReturnMap );
				$this->assertSame( $curExpectedMap[0], $params );
				return $curExpectedMap[1];
			} );

		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup( $api )
		);

		// prefetch P18 first and verify the label
		$apiLookup->prefetchTerms( [ $this->fp18 ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
		$this->assertSame( 'image', $apiLookup->getLabel( $this->fp18, 'en' ) );

		// ask to prefetch P18 and P31, but only P31 will be requested from API here
		$apiLookup->prefetchTerms( [ $this->fp18, $this->fp31 ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
		// verify that P18 is still buffered
		$this->assertSame( 'image', $apiLookup->getLabel( $this->fp18, 'en' ) );
		// verify that P31 has been added to buffer
		$this->assertSame( 'instance of', $apiLookup->getLabel( $this->fp31, 'en' ) );
	}

	public function testConsecutivePrefetch_newLanguage() {
		$api = $this->newMockApi(
			[ 'P18' ],
			self::$responseDataFiles[ 'p18-en-de' ]
		);

		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup( $api )
		);

		// prefetch only language 'en' for P18 first
		$apiLookup->prefetchTerms( [ $this->fp18 ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );

		$this->assertSame( 'image', $apiLookup->getLabel( $this->fp18, 'en' ) );

		// prefetch only language 'de' for P18 next
		$apiLookup->prefetchTerms( [ $this->fp18 ], [ TermTypes::TYPE_LABEL ], [ 'de' ] );

		// verify that P18-en is still buffered
		$this->assertSame( 'image', $apiLookup->getLabel( $this->fp18, 'en' ) );
		// verify that P18-de has been added to buffer

		$this->assertSame( 'Bild', $apiLookup->getLabel( $this->fp18, 'de' ) );
	}

	public function testGetPrefetchedTerm_notPrefetched() {
		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup(
				$this->createMock( GenericActionApiClient::class )
			)
		);
		$this->assertNull( $apiLookup->getPrefetchedTerm( $this->fp18, TermTypes::TYPE_LABEL, 'en' ) );
	}

	public function testGetPrefetchedTerm_doesNotExist() {
		// en and de are requested, but only return en (pretend neither entity has a de label)
		$api = $this->newMockApi(
			[ 'P18', 'P31' ],
			self::$responseDataFiles[ 'p18-p31-en' ]
		);

		$apiLookup = new ApiPrefetchingTermLookup( new ApiEntityLookup( $api ) );
		$apiLookup->prefetchTerms(
			[ $this->fp18, $this->fp31 ],
			[ TermTypes::TYPE_LABEL ],
			[ 'en', 'de' ]
		);

		$this->assertFalse( $apiLookup->getPrefetchedTerm( $this->fp18, TermTypes::TYPE_LABEL, 'de' ) );
	}

	public function testPrefetchTerms_sameTermsTwice() {
		$api = $this->newMockApi(
			[ 'P18' ],
			self::$responseDataFiles[ 'p18-en' ]
		);
		$apiLookup = new ApiPrefetchingTermLookup( new ApiEntityLookup( $api ) );

		$apiLookup->prefetchTerms( [ $this->fp18 ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
		$apiLookup->prefetchTerms( [ $this->fp18 ], [ TermTypes::TYPE_LABEL ], [ 'en' ] );
		$this->assertTrue( true ); // no error
	}

	public function testGetPrefetchedTerm_shouldNotFatalOnFailedRequests() {
		$federatedPropertiesException = new FederatedPropertiesException();

		$api = $this->createMock( GenericActionApiClient::class );
		$api->expects( $this->once() )
			->method( 'get' )
			->with( $this->getRequestParameters( [ 'P18' ] ) )
			->willThrowException( $federatedPropertiesException );
		$logger = new TestLogger();

		$apiLookup = new ApiPrefetchingTermLookup(
			new ApiEntityLookup( $api ),
			$logger
		);
		$apiLookup->prefetchTerms(
			[ $this->fp18 ],
			[ TermTypes::TYPE_LABEL ],
			[ 'en', 'de' ]
		);

		$logger->hasWarningThatContains( 'Prefetching failed for federated properties:' );
	}

	private function getRequestParameters( $ids ) {
		$params = [
				'action' => 'wbgetentities',
				'ids' => implode( '|', $ids ),
				'props' => 'labels|descriptions|datatype',
				'format' => 'json',
			];

		return $params;
	}

	private function newMockApi( $ids, $responseDataFile ) {
		$api = $this->createMock( GenericActionApiClient::class );
		$api->expects( $this->once() )
			->method( 'get' )
			->with( $this->getRequestParameters( $ids ) )
			->willReturn( $this->newMockResponse( json_encode( $this->data[ $responseDataFile ] ), 200 ) );

		return $api;
	}

}
