<?php
declare( strict_types = 1 );

namespace Wikibase\Client\Tests\Unit\ServiceWiring;

use DataValues\Deserializers\DataValueDeserializer;
use Wikibase\Client\Tests\Unit\ServiceWiringTestCase;
use Wikibase\DataModel\Deserializers\DeserializerFactory;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\DataTypeDefinitions;

/**
 * @coversNothing
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class BaseDataModelDeserializerFactoryTest extends ServiceWiringTestCase {

	public function testConstruction(): void {
		$this->mockService(
			'WikibaseClient.DataValueDeserializer',
			$this->createMock( DataValueDeserializer::class )
		);

		$this->mockService(
			'WikibaseClient.EntityIdParser',
			$this->createMock( EntityIdParser::class )
		);

		$this->mockService(
			'WikibaseClient.PropertyDataTypeLookup',
			$this->createMock( PropertyDataTypeLookup::class )
		);

		$dataTypeDefinitions = $this->createStub( DataTypeDefinitions::class );
		$dataTypeDefinitions->method( 'getValueTypes' )->willReturn( [] );
		$dataTypeDefinitions->method( 'getDeserializerBuilders' )->willReturn( [] );
		$this->mockService(
			'WikibaseClient.DataTypeDefinitions',
			$dataTypeDefinitions
		);

		$this->assertInstanceOf(
			DeserializerFactory::class,
			$this->getService( 'WikibaseClient.BaseDataModelDeserializerFactory' )
		);
	}

}
