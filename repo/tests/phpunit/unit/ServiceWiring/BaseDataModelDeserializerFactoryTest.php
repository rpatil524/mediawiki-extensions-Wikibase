<?php
declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\Unit\ServiceWiring;

use DataValues\Deserializers\DataValueDeserializer;
use Wikibase\DataModel\Deserializers\DeserializerFactory;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Repo\Tests\Unit\ServiceWiringTestCase;

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
			'WikibaseRepo.DataValueDeserializer',
			$this->createMock( DataValueDeserializer::class )
		);

		$this->mockService(
			'WikibaseRepo.EntityIdParser',
			$this->createMock( EntityIdParser::class )
		);

		$this->mockService(
			'WikibaseRepo.PropertyDataTypeLookup',
			$this->createMock( PropertyDataTypeLookup::class )
		);

		$dataTypeDefinitions = $this->createStub( DataTypeDefinitions::class );
		$dataTypeDefinitions->method( 'getValueTypes' )->willReturn( [] );
		$dataTypeDefinitions->method( 'getDeserializerBuilders' )->willReturn( [] );
		$this->mockService(
			'WikibaseRepo.DataTypeDefinitions',
			$dataTypeDefinitions
		);

		$this->assertInstanceOf(
			DeserializerFactory::class,
			$this->getService( 'WikibaseRepo.BaseDataModelDeserializerFactory' )
		);
	}

}
