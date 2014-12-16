<?php

namespace Wikibase\Test;

use SiteList;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\EntityIdFormatter;
use Wikibase\Lib\EntityIdFormatterFactory;
use Wikibase\Lib\SnakFormatter;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\Lib\Store\EntityInfoTermLookup;
use Wikibase\Lib\Store\LanguageLabelLookup;
use Wikibase\Repo\View\EntityViewFactory;

/**
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class EntityViewFactoryTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider newEntityViewProvider
	 */
	public function testNewEntityView( $expectedClass, $entityType ) {
		$entityViewFactory = $this->getEntityViewFactory();

		$languageFallback = new LanguageFallbackChain( array() );
		$termLookup = new EntityInfoTermLookup( new EntityInfo( array() ) );
		$labelLookup = new LanguageLabelLookup( $termLookup, 'de' );

		$entityView = $entityViewFactory->newEntityView(
			$entityType,
			'de',
			$languageFallback,
			$labelLookup
		);

		$this->assertInstanceOf( $expectedClass, $entityView );
	}

	public function newEntityViewProvider() {
		return array(
			array( 'Wikibase\Repo\View\ItemView', 'item' ),
			array( 'Wikibase\Repo\View\PropertyView', 'property' )
		);
	}

	public function testNewEntityView_withInvalidType() {
		$entityViewFactory = $this->getEntityViewFactory();

		$this->setExpectedException( 'InvalidArgumentException' );

		$entityViewFactory->newEntityView(
			'kittens',
			'de'
		);
	}

	private function getEntityViewFactory() {
		return new EntityViewFactory(
			$this->getEntityIdFormatterFactory(),
			$this->getSnakFormatterFactory(),
			$this->getMock( 'Wikibase\Lib\Store\EntityLookup' ),
			$this->getSiteStore(),
			$this->getMock( 'DataTypes\DataTypeFactory' ),
			array(),
			array(),
			array()
		);
	}

	private function getEntityIdFormatterFactory() {
		$entityIdFormatter = $this->getMockBuilder( 'Wikibase\Lib\EntityIdFormatter' )
			->disableOriginalConstructor()
			->getMock();

		$formatterFactory = $this->getMock( 'Wikibase\Lib\EntityIdFormatterFactory' );
		$formatterFactory->expects( $this->any() )
			->method( 'getOutputFormat' )
			->will( $this->returnValue( SnakFormatter::FORMAT_HTML ) );

		$formatterFactory->expects( $this->any() )
			->method( 'getEntityIdFormater' )
			->will( $this->returnValue( $entityIdFormatter ) );

		return $formatterFactory;
	}

	private function getSnakFormatterFactory() {
		$snakFormatter = $this->getMock( 'Wikibase\Lib\SnakFormatter' );

		$snakFormatter->expects( $this->any() )
			->method( 'getFormat' )
			->will( $this->returnValue( SnakFormatter::FORMAT_HTML ) );

		$snakFormatterFactory = $this->getMockBuilder( 'Wikibase\Lib\OutputFormatSnakFormatterFactory' )
			->disableOriginalConstructor()
			->getMock();

		$snakFormatterFactory->expects( $this->any() )
			->method( 'getSnakFormatter' )
			->will( $this->returnValue( $snakFormatter ) );

		return $snakFormatterFactory;
	}

	private function getSiteStore() {
		$siteStore = $this->getMock( 'SiteStore' );

		$siteStore->expects( $this->any() )
			->method( 'getSites' )
			->will( $this->returnValue( new SiteList() ) );

		return $siteStore;
	}

}
