<?php

namespace Wikibase\Repo\Tests\Content;

use DataValues\Serializers\DataValueSerializer;
use DummySearchIndexFieldDefinition;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Actions\Action;
use MediaWiki\Cache\LinkBatch;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\Page\WikiPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Site\Site;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use SearchEngine;
use Serializers\Serializer;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Serializers\SerializerFactory;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\SettingsArray;
use Wikibase\Lib\SubEntityTypesMapper;
use Wikibase\Lib\WikibaseSettings;
use Wikibase\Repo\Content\EntityContent;
use Wikibase\Repo\Content\EntityHandler;
use Wikibase\Repo\Content\EntityInstanceHolder;
use Wikibase\Repo\Validators\EntityValidator;
use Wikibase\Repo\Validators\ValidatorErrorLocalizer;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\Content\EntityHandler
 *
 * @group Wikibase
 * @group WikibaseEntity
 * @group WikibaseEntityHandler
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
abstract class EntityHandlerTestCase extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setService(
			'WikibaseRepo.EntityTypeDefinitions',
			$this->getEntityTypeDefinitions()
		);
		if ( WikibaseSettings::isClientEnabled() ) {
			$this->setService( 'WikibaseClient.SiteGroup', Site::GROUP_NONE );
		}
	}

	abstract public function getModelId();

	/**
	 * @param SettingsArray|null $settings
	 *
	 * @throws \Exception
	 */
	protected function getWikibaseRepo( ?SettingsArray $settings = null ) {
		$repoSettings = WikibaseRepo::getSettings()->getArrayCopy();

		if ( $settings ) {
			$repoSettings = array_merge( $repoSettings, $settings->getArrayCopy() );
		}
		$repoSettings['localEntitySourceName'] = 'test';

		$entityTypeDefinitions = $this->getEntityTypeDefinitions();
		$entityNamespaceIdsAndSlots = [];
		$namespaceId = 100;
		foreach ( $entityTypeDefinitions->getEntityTypes() as $entityType ) {
			$entityNamespaceIdsAndSlots[$entityType] = [ 'namespaceId' => $namespaceId, 'slot' => SlotRecord::MAIN ];
			$namespaceId += 100;
		}

		$this->setService( 'WikibaseRepo.Settings', new SettingsArray( $repoSettings ) );

		$this->setService( 'WikibaseRepo.EntitySourceDefinitions', new EntitySourceDefinitions(
			[ new DatabaseEntitySource(
				'test',
				'testdb',
				$entityNamespaceIdsAndSlots,
				'',
				'',
				'',
				''
			) ],
			new SubEntityTypesMapper( [] )
		) );
	}

	/**
	 * When overloading this you probably want to merge the parent call results in
	 * (i.e. inherit the wikibase entity types) unless you are sure of what you are doing.
	 * Mind that in the real world this is done by the WikibaseRepoEntityTypes hook.
	 */
	protected static function getEntityTypeDefinitionsConfiguration(): array {
		return wfArrayPlus2d(
			require __DIR__ . '/../../../../WikibaseRepo.entitytypes.php',
			require __DIR__ . '/../../../../../lib/WikibaseLib.entitytypes.php'
		);
	}

	/**
	 * Get the EntityTypeDefinitions configured in getEntityTypeDefinitionsConfiguration()
	 */
	final protected static function getEntityTypeDefinitions(): EntityTypeDefinitions {
		return new EntityTypeDefinitions(
			static::getEntityTypeDefinitionsConfiguration()
		);
	}

	/**
	 * @param SettingsArray|null $settings
	 *
	 * @return EntityHandler
	 */
	abstract protected function getHandler( ?SettingsArray $settings = null );

	/**
	 * Create a new entity content from the given document.
	 *
	 * This function is called from data providers and must not rely on services being set up;
	 * in particular, it must not call {@link getHandler}.
	 *
	 * @param EntityDocument|null $entity
	 *
	 * @return EntityContent
	 */
	abstract protected static function newEntityContent( ?EntityDocument $entity = null ): EntityContent;

	/**
	 * Create a new entity redirect content from the given IDs, if possible.
	 *
	 * This function is called from data providers and must not rely on services being set up;
	 * in particular, it must not call {@link getHandler}.
	 *
	 * @param EntityId $id
	 * @param EntityId $target
	 *
	 * @return EntityContent|null
	 */
	abstract protected static function newRedirectContent( EntityId $id, EntityId $target ): ?EntityContent;

	/**
	 * @param EntityId|null $id
	 *
	 * @return EntityDocument
	 */
	abstract protected static function newEntity( ?EntityId $id = null );

	/**
	 * Returns EntityContents that can be serialized by the EntityHandler deriving class.
	 *
	 * @return array[]
	 */
	abstract public static function contentProvider(): array;

	public function testGetSpecialPageForCreation() {
		$specialPageName = $this->getHandler()->getSpecialPageForCreation();
		$this->assertTrue( $specialPageName === null || is_string( $specialPageName ) );
	}

	public function testGivenNonEntityContent_serializeContentThrowsException() {
		$handler = $this->getHandler();
		$content = new WikitextContent( '' );
		$this->expectException( InvalidArgumentException::class );
		$handler->serializeContent( $content );
	}

	/**
	 * @dataProvider contentProvider
	 */
	public function testSerialization( EntityContent $content ) {
		$handler = $this->getHandler();

		foreach ( [ CONTENT_FORMAT_JSON, CONTENT_FORMAT_SERIALIZED ] as $format ) {
			$this->assertTrue( $content->equals(
				$handler->unserializeContent( $handler->serializeContent( $content, $format ), $format )
			) );
		}
	}

	public function testCanBeUsedOn() {
		$handler = $this->getHandler();

		$this->assertTrue( $handler->canBeUsedOn( Title::makeTitle( $handler->getEntityNamespace(), "1234" ) ),
							'It should be possible to create this kind of entity in the respective entity namespace!'
						);

		$this->assertFalse( $handler->canBeUsedOn( Title::makeTitle( NS_MEDIAWIKI, "Foo" ) ),
							'It should be impossible to create an entity outside the respective entity namespace!'
						);
	}

	public function testIsParserCacheSupported() {
		$this->assertTrue( $this->getHandler()->isParserCacheSupported() );
	}

	public function testGetEntityNamespace() {
		$handler = $this->getHandler();
		$this->assertGreaterThanOrEqual( 0, $handler->getEntityNamespace() );
	}

	public function testGetEntitySlotRole() {
		$handler = $this->getHandler();
		$this->assertIsString( $handler->getEntitySlotRole() );
	}

	public function testGetPageLanguage() {
		$handler = $this->getHandler();
		$title = Title::makeTitle( $handler->getEntityNamespace(), "1234567" );

		// NOTE: currently, this tests whether getPageLanguage will always return the content language, even
		//      if the user language is different. It's unclear whether this is actually the desired behavior,
		//      since Wikibase Entities are inherently multilingual, so they have no actual "page language".

		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		// test whatever is there
		$this->assertEquals( $contLang->getCode(), $handler->getPageLanguage( $title )->getCode() );

		// test fr
		$this->setUserLang( 'fr' );
		$handler = $this->getHandler();
		$this->assertEquals( $contLang->getCode(), $handler->getPageLanguage( $title )->getCode() );

		// test nl
		$this->setUserLang( 'nl' );
		$frCode = 'fr';
		$this->overrideConfigValue( MainConfigNames::LanguageCode, $frCode );
		$handler = $this->getHandler();
		$this->assertEquals( $frCode, $handler->getPageLanguage( $title )->getCode() );
	}

	public function testGetPageViewLanguage() {
		global $wgLang;

		$handler = $this->getHandler();
		$title = Title::makeTitle( $handler->getEntityNamespace(), "1234567" );

		// NOTE: we expect getPageViewLanguage to return the user language, because Wikibase Entities
		//      are always shown in the user language.

		// test whatever is there
		$this->assertEquals( $wgLang->getCode(), $handler->getPageViewLanguage( $title )->getCode() );

		// test fr
		$this->setUserLang( 'fr' );
		$handler = $this->getHandler();
		$this->assertEquals( $wgLang->getCode(), $handler->getPageViewLanguage( $title )->getCode() );

		// test nl
		$this->setUserLang( 'nl' );
		$handler = $this->getHandler();
		$this->assertEquals( $wgLang->getCode(), $handler->getPageViewLanguage( $title )->getCode() );
	}

	public function testLocalizedModelName() {
		$name = ContentHandler::getLocalizedName( $this->getModelId() );

		$this->assertNotEquals( $this->getModelId(), $name, "localization of model name" );
	}

	public static function provideGetUndoContent(): iterable {
		/** @var LabelsProvider $e2 */
		/** @var LabelsProvider $e3 */
		/** @var LabelsProvider $e4 */
		/** @var LabelsProvider $e5 */
		/** @var LabelsProvider $e5u4 */
		/** @var LabelsProvider $e5u4u3 */

		if ( !static::newEntity() instanceof LabelsProvider ) {
			self::markTestSkipped( 'provideGetUndoContent only works for entities that have labels field' );
		}

		$e1 = static::newEntity();
		$c1 = static::newEntityContent( $e1 );

		$e2 = static::newEntity();
		$e2->getLabels()->setTextForLanguage( 'en', 'Foo' );
		$c2 = static::newEntityContent( $e2 );

		$e3 = static::newEntity();
		$e3->getLabels()->setTextForLanguage( 'en', 'Foo' );
		$e3->getLabels()->setTextForLanguage( 'de', 'Fuh' );
		$c3 = static::newEntityContent( $e3 );

		$e4 = static::newEntity();
		$e4->getLabels()->setTextForLanguage( 'en', 'Foo' );
		$e4->getLabels()->setTextForLanguage( 'de', 'Fuh' );
		$e4->getLabels()->setTextForLanguage( 'fr', 'Fu' );
		$c4 = static::newEntityContent( $e4 );

		$e5 = static::newEntity();
		$e5->getLabels()->setTextForLanguage( 'en', 'F00' );
		$e5->getLabels()->setTextForLanguage( 'de', 'Fuh' );
		$e5->getLabels()->setTextForLanguage( 'fr', 'Fu' );
		$c5 = static::newEntityContent( $e5 );

		$e5u4 = static::newEntity();
		$e5u4->getLabels()->setTextForLanguage( 'en', 'F00' );
		$e5u4->getLabels()->setTextForLanguage( 'de', 'Fuh' );

		$e5u4u3 = static::newEntity();
		$e5u4u3->getLabels()->setTextForLanguage( 'en', 'F00' );

		return [
			[ $c5, $c5, $c4, static::newEntityContent( $e4 ), "undo last edit" ],
			[ $c5, $c4, $c3, static::newEntityContent( $e5u4 ), "undo previous edit" ],

			[ $c5, $c5, $c3, static::newEntityContent( $e3 ), "undo last two edits" ],
			[ $c5, $c4, $c2, static::newEntityContent( $e5u4u3 ), "undo past two edits" ],

			[ $c5, $c2, $c1, null, "undo conflicting edit" ],
			[ $c5, $c3, $c1, null, "undo two edits with conflict" ],
		];
	}

	/**
	 * @dataProvider provideGetUndoContent
	 *
	 * @param EntityContent $latestContent
	 * @param EntityContent $newerContent
	 * @param EntityContent $olderContent
	 * @param EntityContent|null $expected
	 * @param string $message
	 */
	public function testGetUndoContent(
		EntityContent $latestContent,
		EntityContent $newerContent,
		EntityContent $olderContent,
		?EntityContent $expected,
		$message
	) {
		$handler = $this->getHandler();
		$undo = $handler->getUndoContent(
			$latestContent,
			$newerContent,
			$olderContent,
			$latestContent->equals( $newerContent )
		);

		if ( $expected ) {
			$this->assertInstanceOf( EntityContent::class, $undo, $message );
			$this->assertTrue( $expected->equals( $undo ), $message );
		} else {
			$this->assertFalse( $undo, $message );
		}
	}

	public function testGetEntityType() {
		$handler = $this->getHandler();
		$content = $this->newEntityContent();
		$entity = $content->getEntity();

		$this->assertEquals( $entity->getType(), $handler->getEntityType() );
	}

	public function testMakeEntityContent() {
		$entity = $this->newEntity();

		$handler = $this->getHandler();
		$content = $handler->makeEntityContent( new EntityInstanceHolder( $entity ) );

		$this->assertEquals( $this->getModelId(), $content->getModel() );
		$this->assertSame( $entity, $content->getEntity() );
	}

	public function testMakeEmptyContent() {
		$content = $this->getHandler()->makeEmptyContent();
		$this->assertTrue( $content->isEmpty(), 'isEmpty' );

		$this->expectException( LogicException::class );
		$content->getEntity();
	}

	public function testMakeRedirectContent() {
		// We don't support title based redirects.
		$this->expectException( LogicException::class );

		$handler = $this->getHandler();
		$handler->makeRedirectContent( Title::makeTitle( $handler->getEntityNamespace(), 'X11' ) );
	}

	public function testMakeEmptyEntity() {
		$handler = $this->getHandler();
		$entity = $handler->makeEmptyEntity();

		$this->assertTrue( $entity->isEmpty(), 'isEmpty' );
		$this->assertEquals( $handler->getEntityType(), $entity->getType(), 'entity type' );
	}

	abstract public static function entityIdProvider();

	/**
	 * @dataProvider entityIdProvider
	 */
	public function testMakeEntityId( $idString ) {
		$handler = $this->getHandler();
		$id = $handler->makeEntityId( $idString );

		$this->assertEquals( $handler->getEntityType(), $id->getEntityType() );
		$this->assertEquals( $idString, $id->getSerialization() );
	}

	/**
	 * TODO: figure out why making this static breaks Lexeme test
	 * @return Serializer
	 */
	protected static function getEntitySerializer() {
		$newSerializerFactory = new SerializerFactory( new DataValueSerializer() );
		$newSerializer = $newSerializerFactory->newEntitySerializer();
		return $newSerializer;
	}

	public static function exportTransformProvider() {
		$entity = static::newEntity();

		$internalSerializer = WikibaseRepo::getStorageEntitySerializer();
		$oldBlob = json_encode( $internalSerializer->serialize( $entity ) );

		// fake several old formats
		$type = $entity->getType();
		$id = $entity->getId()->getSerialization();
		// replace "type":"item","id":"q7" with "entity":["item",7]
		$veryOldBlob = preg_replace(
			'/"type":"\w+"(,"datatype":"\w+")?,"id":"\w\d+"/',
			'"entity":["' . strtolower( $type ) . '",' . substr( $id, 1 ) . ']$1',
			$oldBlob
		);
		// replace "entity":["item",7] with "entity":"q7"
		$veryVeryOldBlob = preg_replace(
			'/"entity":\["\w+",\d+\]/',
			'"entity":"' . strtolower( $id ) . '"',
			$veryOldBlob
		);

		// sanity (cannot compare $veryOldBlob and $oldBlob until we have the new serialization in place)
		if ( $veryVeryOldBlob === $veryOldBlob /* || $veryOldBlob === $oldBlob */ ) {
			throw new RuntimeException( 'Failed to fake very old serialization format based on oldish serialization format.' );
		}

		// make new style blob
		$newSerializer = self::getEntitySerializer();
		$newBlob = json_encode( $newSerializer->serialize( $entity ) );

		return [
			'old serialization / ancient id format' => [ $veryVeryOldBlob, $newBlob ],
			'old serialization / new silly id format' => [ $veryOldBlob, $newBlob ],
			'old serialization / old serializer format' => [ $oldBlob, $newBlob ],
			'new serialization format, keep as is' => [ $newBlob, $newBlob ],
		];
	}

	/**
	 * @dataProvider exportTransformProvider
	 */
	public function testExportTransform( $blob, $expected ) {
		$settings = new SettingsArray();
		$settings->setSetting( 'transformLegacyFormatOnExport', true );

		$handler = $this->getHandler( $settings );
		$actual = $handler->exportTransform( $blob );

		$this->assertEquals( $expected, $actual );
	}

	public function testExportTransform_neverRecodeNonLegacyFormat() {
		$settings = new SettingsArray();
		$settings->setSetting( 'transformLegacyFormatOnExport', true );
		$this->getWikibaseRepo( $settings ); // updates services as needed

		$entity = $this->newEntity();
		$entitySerializer = WikibaseRepo::getStorageEntitySerializer();
		$expected = json_encode( $entitySerializer->serialize( $entity ) );

		$handler = $this->getHandler( $settings );
		$actual = $handler->exportTransform( $expected );

		$this->assertEquals( $expected, $actual );
	}

	public function testGetLegacyExportFormatDetector() {
		$detector = $this->getHandler()->getLegacyExportFormatDetector();

		if ( $detector === null ) {
			$this->markTestSkipped( 'handler has no legacy export format detector' );
		} else {
			$this->assertIsCallable( $detector );
		}
	}

	public static function forCreationParamProvider() {
		return [
			[ true ],
			[ false ],
		];
	}

	/**
	 * @dataProvider forCreationParamProvider
	 */
	public function testGetOnSaveValidators( $forCreation ) {
		$handler = $this->getHandler();

		$validators = $handler->getOnSaveValidators( $forCreation, $this->newEntity()->getId() );

		$this->assertIsArray( $validators );

		foreach ( $validators as $validator ) {
			$this->assertInstanceOf( EntityValidator::class, $validator );
		}
	}

	public function testGetValidationErrorLocalizer() {
		$localizer = $this->getHandler()->getValidationErrorLocalizer();
		$this->assertInstanceOf( ValidatorErrorLocalizer::class, $localizer );
	}

	/**
	 * @param Title $title
	 * @return RequestContext
	 */
	protected function getContext( Title $title ) {
		$context = new RequestContext();
		$context->setLanguage( 'qqx' );
		$context->setTitle( $title );

		return $context;
	}

	public function testShowMissingEntity() {
		$handler = $this->getHandler();

		$title = Title::makeTitle( $handler->getEntityNamespace(), 'MISSING' );

		$context = $this->getContext( $title );
		$handler->showMissingEntity( $title, $context );

		$this->assertStringContainsString( '(wikibase-noentity)', $context->getOutput()->getHTML() );
	}

	public function testSupportsSections() {
		$this->assertFalse( $this->getHandler()->supportsSections() );
	}

	public function testSupportsCategories() {
		$this->assertFalse( $this->getHandler()->supportsCategories() );
	}

	public function testSupportsRedirects() {
		$this->assertFalse( $this->getHandler()->supportsRedirects() );
	}

	public function testSupportsDirectEditing() {
		$this->assertFalse( $this->getHandler()->supportsDirectEditing() );
	}

	public function testSupportsDirectApiEditing() {
		$this->assertFalse( $this->getHandler()->supportsDirectApiEditing() );
	}

	public function testGetAutosummary() {
		$this->assertSame( '', $this->getHandler()->getAutosummary( null, null, 0 ) );
	}

	abstract protected function getExpectedSearchIndexFields();

	public function testFieldsForSearchIndex() {
		$handler = $this->getHandler();

		$searchEngine = $this->createMock( SearchEngine::class );

		$searchEngine->method( 'makeSearchFieldMapping' )
			->willReturnCallback( static function ( $name, $type ) {
				return new DummySearchIndexFieldDefinition( $name, $type );
			} );

		$fields = $handler->getFieldsForSearchIndex( $searchEngine );
		$expectedFields = $this->getExpectedSearchIndexFields();
		if ( empty( $expectedFields ) ) {
			$this->assertSame( [], $fields );
		} else {
			foreach ( $expectedFields as $expected ) {
				$this->assertInstanceOf( \SearchIndexField::class, $fields[$expected] );
				$mapping = $fields[$expected]->getMapping( $searchEngine );
				$this->assertEquals( $expected, $mapping['name'] );
			}
		}
	}

	abstract protected function getTestContent();

	protected function getTitle(
		EntityHandler $handler,
		string $titleString = 'dummy title string'
	): Title {
		return Title::makeTitle(
			$handler->getEntityNamespace(),
			$titleString
		);
	}

	protected function getMockArticle(
		EntityHandler $handler
	): Article {
		$wikiPage = $this->getMockWikiPage( $handler );

		$article = $this->createMock( Article::class );
		$article->method( 'getTitle' )
			->willReturn( $wikiPage->getTitle() );
		$article->method( 'getPage' )
			->willReturn( $wikiPage );
		$article->method( 'getContext' )
			->willReturn( $this->getContext( $wikiPage->getTitle() ) );

		return $article;
	}

	protected function getMockWikiPage(
		EntityHandler $handler
	): WikiPage {
		$title = $this->getTitle( $handler );

		$page = $this->getMockBuilder( WikiPage::class )
			->setConstructorArgs( [ Title::makeTitle( NS_MAIN, 'Q1' ) ] )
			->getMock();

		// XXX: The RevisionRecord is needed by a WikibaseMediaInfo hook
		// Tests fail when run with WikibaseMediaInfo unless Page::getRevisionRecord
		// actually returns something.
		// Introduced in https://gerrit.wikimedia.org/r/#/c/mediawiki/extensions/Wikibase/+/464365/
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'hasSlot' )
			->willReturn( false );

		$pageId = 1;
		$revisionRecord->method( 'getContent' )->willReturn( $this->getTestContent() );
		$revisionRecord->method( 'getPageId' )->willReturn( $pageId );

		$page->method( 'getId' )->willReturn( $pageId );
		$page->method( 'getContent' )->willReturn( $this->getTestContent() );
		$page->method( 'getTitle' )->willReturn( $title );
		$page->method( 'getRevisionRecord' )->willReturn( $revisionRecord );

		return $page;
	}

	abstract public function testDataForSearchIndex();

	public function testGetActionOverrides() {
		$handler = $this->getHandler();
		$overrides = $handler->getActionOverrides();

		foreach ( $overrides as $name => $spec ) {
			if ( is_string( $spec ) ) {
				$this->assertTrue(
					is_subclass_of( $spec, Action::class ),
					'Override for ' . $name . ' must be an action class, found ' . $spec
				);
			} elseif ( is_callable( $spec ) ) {
				$article = $this->getMockArticle( $handler );
				$action = $spec( $article, $article->getContext() );
				$this->assertTrue(
					is_subclass_of( $action, Action::class ),
					'Callback for action ' . $name . ' must return an Action instance!'
				);
			} elseif ( is_array( $spec ) ) {
				$action = $this->getServiceContainer()->getObjectFactory()
					->createObject( $spec, [
						'extraArgs' => [ $article, $article->getContext() ],
					] );
				$this->assertInstanceOf( Action::class, $action );
			} else {
				$this->fail( 'Expected a class name or callback as action override for ' . $name );
			}
		}
	}

	/**
	 * @return EntityContent An entirely empty content object with no EntityHolder and no entity.
	 */
	abstract protected static function newEmptyContent();

	public static function providePageProperties() {
		yield 'empty' => [
			static::newEmptyContent(),
			[ 'wb-claims' => null ],
		];

		$blankContent = static::newEntityContent();
		yield 'blank' => [
			$blankContent,
			[ 'wb-claims' => 0 ],
		];

		$contentWithLabel = static::newEntityContent();
		// Entity that didn't extend LabelsProvider to set/get a labels
		// should be ingnored in this test case.
		if ( $contentWithLabel->getEntity() instanceof LabelsProvider ) {
			self::setLabel( $contentWithLabel->getEntity(), 'en', 'Foo' );

			yield 'labels' => [
				$contentWithLabel,
				[ 'wb-claims' => 0 ],
			];
		}
	}

	/**
	 * @dataProvider providePageProperties
	 */
	public function testPageProperties( EntityContent $content, array $expectedProps ) {
		$lb = $this->createMock( LinkBatch::class );
		$lb->method( 'doQuery' )->willReturn( false );
		$lbFactory = $this->createMock( LinkBatchFactory::class );
		$lbFactory->method( 'newLinkBatch' )->willReturn( $lb );
		$this->setService( 'LinkBatchFactory', $lbFactory );
		$title = Title::makeTitle( NS_MAIN, 'Foo' );
		$title->setContentModel( CONTENT_MODEL_WIKITEXT );
		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput( $content, $title, null, null, false );

		foreach ( $expectedProps as $name => $expected ) {
			$actual = $parserOutput->getPageProperty( $name );
			if ( $expected === null ) {
				$this->assertNull( $actual, "page property $name" );
			} else {
				$this->assertSame( (string)$expected, (string)$actual, "page property $name" );
			}
		}
	}

	private static function setLabel( EntityDocument $entity, $languageCode, $text ) {
		if ( !( $entity instanceof LabelsProvider ) ) {
			throw new InvalidArgumentException( '$entity must be a LabelsProvider' );
		}

		$entity->getLabels()->setTextForLanguage( $languageCode, $text );
	}
}
