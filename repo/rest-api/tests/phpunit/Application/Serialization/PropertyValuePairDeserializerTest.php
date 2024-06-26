<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\RestApi\Application\Serialization;

use DataValues\StringValue;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use ValueValidators\Result;
use ValueValidators\ValueValidator;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Repo\DataTypeValidatorFactory;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\InvalidFieldException;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\MissingFieldException;
use Wikibase\Repo\RestApi\Application\Serialization\Exceptions\SerializationException;
use Wikibase\Repo\RestApi\Application\Serialization\PropertyValuePairDeserializer;
use Wikibase\Repo\RestApi\Application\Serialization\ValueDeserializer;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\RestApi\Application\Serialization\PropertyValuePairDeserializer
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class PropertyValuePairDeserializerTest extends TestCase {

	private const STRING_PROPERTY_ID = 'P123';
	private const URL_PROPERTY_ID = 'P789';
	private const ITEM_ID_PROPERTY_ID = 'P321';
	private const TIME_PROPERTY_ID = 'P456';
	private const GLOBECOORDINATE_PROPERTY_ID = 'P678';
	private const STRING_URI_PROPERTY_ID = 'https://example.com/P1';

	private DataTypeValidatorFactory $dataTypeValidatorFactory;
	private ValueDeserializer $valueDeserializer;

	protected function setUp(): void {
		parent::setUp();

		$successValidator = $this->createStub( ValueValidator::class );
		$successValidator->method( 'validate' )->willReturn( Result::newSuccess() );

		$this->dataTypeValidatorFactory = $this->createStub( DataTypeValidatorFactory::class );
		$this->dataTypeValidatorFactory->method( 'getValidators' )->willReturn( [ $successValidator ] );

		$this->valueDeserializer = $this->createStub( ValueDeserializer::class );
		$this->valueDeserializer->method( 'deserialize' )->willReturn( $this->createStub( StringValue::class ) );
	}

	/**
	 * @dataProvider validSerializationProvider
	 */
	public function testDeserialize( Snak $expectedSnak, array $serialization ): void {
		$this->assertEquals( $expectedSnak, $this->newDeserializer()->deserialize( $serialization ) );
	}

	public function validSerializationProvider(): Generator {
		yield 'no value for string property' => [
			new PropertyNoValueSnak( new NumericPropertyId( self::STRING_PROPERTY_ID ) ),
			[
				'value' => [ 'type' => 'novalue' ],
				'property' => [
					'id' => self::STRING_PROPERTY_ID,
				],
			],
		];

		yield 'some value for item id property' => [
			new PropertySomeValueSnak( new NumericPropertyId( self::ITEM_ID_PROPERTY_ID ) ),
			[
				'value' => [ 'type' => 'somevalue' ],
				'property' => [
					'id' => self::ITEM_ID_PROPERTY_ID,
				],
			],
		];

		yield 'non-numeric property id (e.g. federated property)' => [
			new PropertySomeValueSnak( $this->newUriPropertyId( self::STRING_URI_PROPERTY_ID ) ),
			[
				'value' => [ 'type' => 'somevalue' ],
				'property' => [
					'id' => self::STRING_URI_PROPERTY_ID,
				],
			],
		];

		yield 'value for string property' => [
			new PropertyValueSnak(
				new NumericPropertyId( self::STRING_PROPERTY_ID ),
				$this->createStub( StringValue::class )
			),
			[
				'value' => [ 'type' => 'value', 'content' => 'potato' ],
				'property' => [ 'id' => self::STRING_PROPERTY_ID ],
			],
		];
	}

	/**
	 * @dataProvider invalidSerializationProvider
	 */
	public function testDeserializationErrors( Exception $expectedException, array $serialization, string $basePath = '' ): void {
		$this->dataTypeValidatorFactory = WikibaseRepo::getDataTypeValidatorFactory();

		try {
			$this->newDeserializer()->deserialize( $serialization, $basePath );
			$this->fail( 'Expected exception was not thrown.' );
		} catch ( SerializationException $e ) {
			$this->assertEquals( $expectedException, $e );
		}
	}

	public static function invalidSerializationProvider(): Generator {
		yield 'invalid value field type' => [
			new InvalidFieldException( 'value', 42, '/value' ),
			[
				'value' => 42,
				'property' => [
					'id' => self::STRING_PROPERTY_ID,
				],
			],
		];

		yield 'invalid value type field' => [
			new InvalidFieldException( 'type', 'not-a-value-type', '/value/type' ),
			[
				'value' => [ 'content' => 'I am goat', 'type' => 'not-a-value-type' ],
				'property' => [
					'id' => self::STRING_PROPERTY_ID,
				],
			],
		];

		yield 'invalid property field type' => [
			new InvalidFieldException( 'property', 42, '/property' ),
			[
				'value' => [ 'type' => 'novalue' ],
				'property' => 42,
			],
		];

		yield 'invalid property id field' => [
			new InvalidFieldException( 'id', 'not-a-property-id', '/property/id' ),
			[
				'value' => [ 'type' => 'novalue' ],
				'property' => [ 'id' => 'not-a-property-id' ],
			],
		];

		yield 'invalid value type field type' => [
			new InvalidFieldException( 'type', true, '/value/type' ),
			[
				'value' => [ 'type' => true ],
				'property' => [
					'id' => self::STRING_PROPERTY_ID,
				],
			],
		];

		yield 'property id is a (valid) item id' => [
			new InvalidFieldException( 'id', 'Q123', '/property/id' ),
			[
				'value' => [ 'type' => 'novalue' ],
				'property' => [ 'id' => 'Q123' ],
			],
		];

		yield 'property does not exist' => [
			new InvalidFieldException( 'id', 'P666', '/property/id' ),
			[
				'value' => [ 'type' => 'novalue' ],
				'property' => [ 'id' => 'P666' ],
			],
		];

		yield 'property does not exist with path' => [
			new InvalidFieldException( 'id', 'P666', 'P123/0/property/id' ),
			[
				'value' => [ 'type' => 'novalue' ],
				'property' => [ 'id' => 'P666' ],
			],
			'P123/0',
		];

		yield 'missing value field' => [
			new MissingFieldException( 'value', '' ),
			[
				'property' => [
					'id' => self::STRING_PROPERTY_ID,
				],
			],
		];

		yield 'missing value type field' => [
			new MissingFieldException( 'type', '/value' ),
			[
				'value' => [ 'content' => 'I am goat' ],
				'property' => [
					'id' => self::STRING_PROPERTY_ID,
				],
			],
		];

		yield 'missing property field' => [
			new MissingFieldException( 'property', '' ),
			[
				'value' => [ 'type' => 'novalue' ],
			],
		];

		yield 'missing property id field' => [
			new MissingFieldException( 'id', '/property' ),
			[
				'value' => [ 'type' => 'novalue' ],
				'property' => [],
			],
		];

		yield 'missing property id field with path' => [
			new MissingFieldException( 'id', 'P123/1/property' ),
			[
				'value' => [ 'type' => 'novalue' ],
				'property' => [],
			],
			'P123/1',
		];
	}

	/**
	 * @dataProvider valueDeserializerExceptionProvider
	 */
	public function testValueDeserializerThrowsException( array $serialization, SerializationException $exception ): void {
		$this->valueDeserializer = $this->createMock( ValueDeserializer::class );
		$this->valueDeserializer->expects( $this->once() )
			->method( 'deserialize' )
			->with( 'string', $serialization['value'] )
			->willThrowException( $exception );

		try {
			$this->newDeserializer()->deserialize( $serialization );
			$this->fail( 'Expected exception was not thrown.' );
		} catch ( SerializationException $e ) {
			$this->assertEquals( $exception, $e );
		}
	}

	public static function valueDeserializerExceptionProvider(): Generator {
		yield 'invalid field' => [
			[
				'value' => [ 'type' => 'value', 'content' => 42 ],
				'property' => [ 'id' => self::STRING_PROPERTY_ID ],
			],
			new InvalidFieldException( 'content', 42 ),
		];

		yield 'missing field' => [
			[
				'value' => [ 'type' => 'value' ],
				'property' => [ 'id' => self::STRING_PROPERTY_ID ],
			],
			new MissingFieldException( 'content' ),
		];
	}

	private function newDeserializer(): PropertyValuePairDeserializer {
		$dataTypeLookup = new InMemoryDataTypeLookup();
		$dataTypeLookup->setDataTypeForProperty(
			new NumericPropertyId( self::STRING_PROPERTY_ID ),
			'string'
		);
		$dataTypeLookup->setDataTypeForProperty(
			$this->newUriPropertyId( self::URL_PROPERTY_ID ),
			'url'
		);
		$dataTypeLookup->setDataTypeForProperty(
			$this->newUriPropertyId( self::TIME_PROPERTY_ID ),
			'time'
		);
		$dataTypeLookup->setDataTypeForProperty(
			$this->newUriPropertyId( self::GLOBECOORDINATE_PROPERTY_ID ),
			'globe-coordinate'
		);
		$dataTypeLookup->setDataTypeForProperty(
			new NumericPropertyId( self::ITEM_ID_PROPERTY_ID ),
			'wikibase-item'
		);
		$dataTypeLookup->setDataTypeForProperty(
			$this->newUriPropertyId( self::STRING_URI_PROPERTY_ID ),
			'string'
		);

		$entityIdParser = $this->createStub( EntityIdParser::class );
		$entityIdParser->method( 'parse' )->willReturnCallback( function( $id ) {
			if ( substr( $id, 0, 4 ) === 'http' ) {
				return $this->newUriPropertyId( $id );
			}

			return WikibaseRepo::getEntityIdParser()->parse( $id );
		} );

		return new PropertyValuePairDeserializer(
			$entityIdParser,
			$dataTypeLookup,
			$this->valueDeserializer
		);
	}

	private function newUriPropertyId( string $uri ): PropertyId {
		$id = $this->createStub( PropertyId::class );
		$id->method( 'getEntityType' )->willReturn( Property::ENTITY_TYPE );
		$id->method( 'getSerialization' )->willReturn( $uri );

		return $id;
	}

}
