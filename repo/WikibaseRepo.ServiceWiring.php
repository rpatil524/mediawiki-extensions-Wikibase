<?php

declare( strict_types = 1 );

use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\MonolingualTextValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnknownValue;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use ValueParsers\NullParser;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataAccess\EntitySourceDefinitionsConfigParser;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Services\Diff\EntityDiffer;
use Wikibase\DataModel\Services\Diff\EntityPatcher;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\Lib\Changes\EntityChangeFactory;
use Wikibase\Lib\DataTypeDefinitions;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Lib\DataValueFactory;
use Wikibase\Lib\EntityTypeDefinitions;
use Wikibase\Lib\Formatters\CachingKartographerEmbeddingHandler;
use Wikibase\Lib\Formatters\OutputFormatValueFormatterFactory;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Modules\PropertyValueExpertsModule;
use Wikibase\Lib\SettingsArray;
use Wikibase\Lib\WikibaseSettings;
use Wikibase\Repo\EntitySourceDefinitionsLegacyRepoSettingsParser;
use Wikibase\Repo\FederatedProperties\FederatedPropertiesEntitySourceDefinitionsConfigParser;
use Wikibase\Repo\Notifications\RepoEntityChange;
use Wikibase\Repo\Notifications\RepoItemChange;
use Wikibase\Repo\Rdf\ValueSnakRdfBuilderFactory;
use Wikibase\Repo\ValueParserFactory;
use Wikibase\Repo\WikibaseRepo;

/** @phpcs-require-sorted-array */
return [

	'WikibaseRepo.ContentModelMappings' => function ( MediaWikiServices $services ): array {
		$map = WikibaseRepo::getEntityTypeDefinitions( $services )
			->get( EntityTypeDefinitions::CONTENT_MODEL_ID );

		$services->getHookContainer()
			->run( 'WikibaseContentModelMapping', [ &$map ] );

		return $map;
	},

	'WikibaseRepo.DataTypeDefinitions' => function ( MediaWikiServices $services ): DataTypeDefinitions {
		$baseDataTypes = require __DIR__ . '/../lib/WikibaseLib.datatypes.php';
		$repoDataTypes = require __DIR__ . '/WikibaseRepo.datatypes.php';

		$dataTypes = array_merge_recursive( $baseDataTypes, $repoDataTypes );

		$services->getHookContainer()->run( 'WikibaseRepoDataTypes', [ &$dataTypes ] );

		// TODO get $settings from $services
		$settings = WikibaseSettings::getRepoSettings();

		return new DataTypeDefinitions(
			$dataTypes,
			$settings->getSetting( 'disabledDataTypes' )
		);
	},

	'WikibaseRepo.DataTypeFactory' => function ( MediaWikiServices $services ): DataTypeFactory {
		return new DataTypeFactory(
			WikibaseRepo::getDataTypeDefinitions( $services )->getValueTypes()
		);
	},

	'WikibaseRepo.DataValueDeserializer' => function ( MediaWikiServices $services ): DataValueDeserializer {
		return new DataValueDeserializer( [
			'string' => StringValue::class,
			'unknown' => UnknownValue::class,
			'globecoordinate' => GlobeCoordinateValue::class,
			'monolingualtext' => MonolingualTextValue::class,
			'quantity' => QuantityValue::class,
			'time' => TimeValue::class,
			'wikibase-entityid' => function ( $value ) use ( $services ) {
				// TODO this should perhaps be factored out into a class
				if ( isset( $value['id'] ) ) {
					try {
						return new EntityIdValue( WikibaseRepo::getEntityIdParser( $services )->parse( $value['id'] ) );
					} catch ( EntityIdParsingException $parsingException ) {
						throw new InvalidArgumentException(
							'Can not parse id \'' . $value['id'] . '\' to build EntityIdValue with',
							0,
							$parsingException
						);
					}
				} else {
					return EntityIdValue::newFromArray( $value );
				}
			},
		] );
	},

	'WikibaseRepo.DataValueFactory' => function ( MediaWikiServices $services ): DataValueFactory {
		return new DataValueFactory( WikibaseRepo::getDataValueDeserializer( $services ) );
	},

	'WikibaseRepo.EntityChangeFactory' => function ( MediaWikiServices $services ): EntityChangeFactory {
		//TODO: take this from a setting or registry.
		$changeClasses = [
			Item::ENTITY_TYPE => RepoItemChange::class,
			// Other types of entities will use EntityChange
		];

		return new EntityChangeFactory(
			WikibaseRepo::getEntityDiffer( $services ),
			WikibaseRepo::getEntityIdParser( $services ),
			$changeClasses,
			RepoEntityChange::class,
			WikibaseRepo::getLogger( $services )
		);
	},

	'WikibaseRepo.EntityDiffer' => function ( MediaWikiServices $services ): EntityDiffer {
		$entityDiffer = new EntityDiffer();
		$entityTypeDefinitions = WikibaseRepo::getEntityTypeDefinitions( $services );
		$builders = $entityTypeDefinitions->get( EntityTypeDefinitions::ENTITY_DIFFER_STRATEGY_BUILDER );
		foreach ( $builders as $builder ) {
			$entityDiffer->registerEntityDifferStrategy( $builder() );
		}
		return $entityDiffer;
	},

	'WikibaseRepo.EntityIdParser' => function ( MediaWikiServices $services ): EntityIdParser {
		return new DispatchingEntityIdParser(
			WikibaseRepo::getEntityTypeDefinitions( $services )->getEntityIdBuilders()
		);
	},

	'WikibaseRepo.EntityPatcher' => function ( MediaWikiServices $services ): EntityPatcher {
		$entityPatcher = new EntityPatcher();
		$entityTypeDefinitions = WikibaseRepo::getEntityTypeDefinitions( $services );
		$builders = $entityTypeDefinitions->get( EntityTypeDefinitions::ENTITY_PATCHER_STRATEGY_BUILDER );
		foreach ( $builders as $builder ) {
			$entityPatcher->registerEntityPatcherStrategy( $builder() );
		}
		return $entityPatcher;
	},

	'WikibaseRepo.EntitySourceDefinitions' => function ( MediaWikiServices $services ): EntitySourceDefinitions {
		$settings = WikibaseRepo::getSettings( $services );
		$entityTypeDefinitions = WikibaseRepo::getEntityTypeDefinitions( $services );

		if ( $settings->hasSetting( 'entitySources' ) && !empty( $settings->getSetting( 'entitySources' ) ) ) {
			$configParser = new EntitySourceDefinitionsConfigParser();

			return $configParser->newDefinitionsFromConfigArray(
				$settings->getSetting( 'entitySources' ),
				$entityTypeDefinitions
			);
		}

		$parser = new EntitySourceDefinitionsLegacyRepoSettingsParser();

		if ( $settings->getSetting( 'federatedPropertiesEnabled' ) ) {
			$configParser = new FederatedPropertiesEntitySourceDefinitionsConfigParser( $settings );

			return $configParser->initializeDefaults(
				$parser->newDefinitionsFromSettings( $settings, $entityTypeDefinitions ),
				$entityTypeDefinitions
			);
		}

		return $parser->newDefinitionsFromSettings( $settings, $entityTypeDefinitions );
	},

	'WikibaseRepo.EntityTypeDefinitions' => function ( MediaWikiServices $services ): EntityTypeDefinitions {
		$baseEntityTypes = require __DIR__ . '/../lib/WikibaseLib.entitytypes.php';
		$repoEntityTypes = require __DIR__ . '/WikibaseRepo.entitytypes.php';

		$entityTypes = array_merge_recursive( $baseEntityTypes, $repoEntityTypes );

		$services->getHookContainer()->run( 'WikibaseRepoEntityTypes', [ &$entityTypes ] );

		return new EntityTypeDefinitions( $entityTypes );
	},

	'WikibaseRepo.KartographerEmbeddingHandler' => function ( MediaWikiServices $services ): ?CachingKartographerEmbeddingHandler {
		$settings = WikibaseRepo::getSettings( $services );
		$config = $services->getMainConfig();
		if (
			$settings->getSetting( 'useKartographerGlobeCoordinateFormatter' ) &&
			ExtensionRegistry::getInstance()->isLoaded( 'Kartographer' ) &&
			$config->has( 'KartographerEnableMapFrame' ) &&
			$config->get( 'KartographerEnableMapFrame' )
		) {
			return new CachingKartographerEmbeddingHandler(
				$services->getParserFactory()->create()
			);
		} else {
			return null;
		}
	},

	'WikibaseRepo.LocalEntitySource' => function ( MediaWikiServices $services ): EntitySource {
		$localEntitySourceName = WikibaseRepo::getSettings( $services )->getSetting( 'localEntitySourceName' );
		$sources = WikibaseRepo::getEntitySourceDefinitions( $services )->getSources();
		foreach ( $sources as $source ) {
			if ( $source->getSourceName() === $localEntitySourceName ) {
				return $source;
			}
		}

		throw new LogicException( 'No source configured: ' . $localEntitySourceName );
	},

	'WikibaseRepo.LocalEntityTypes' => function ( MediaWikiServices $services ): array {
		$localSource = WikibaseRepo::getLocalEntitySource( $services );
		$subEntityTypes = WikibaseRepo::getEntityTypeDefinitions( $services )
				->get( EntityTypeDefinitions::SUB_ENTITY_TYPES );

		// Expands the array of local entity types with sub types
		return array_reduce(
			$localSource->getEntityTypes(),
			function ( $types, $localTypeName ) use ( $subEntityTypes ) {
				$types[] = $localTypeName;
				if ( array_key_exists( $localTypeName, $subEntityTypes ) ) {
					$types = array_merge( $types, $subEntityTypes[$localTypeName] );
				}
				return $types;
			},
			[]
		);
	},

	'WikibaseRepo.Logger' => function ( MediaWikiServices $services ): LoggerInterface {
		return LoggerFactory::getInstance( 'Wikibase' );
	},

	'WikibaseRepo.PropertyValueExpertsModule' => function ( MediaWikiServices $services ): PropertyValueExpertsModule {
		return new PropertyValueExpertsModule( WikibaseRepo::getDataTypeDefinitions( $services ) );
	},

	'WikibaseRepo.Settings' => function ( MediaWikiServices $services ): SettingsArray {
		return WikibaseSettings::getRepoSettings();
	},

	'WikibaseRepo.StatementGuidParser' => function ( MediaWikiServices $services ): StatementGuidParser {
		return new StatementGuidParser( WikibaseRepo::getEntityIdParser( $services ) );
	},

	'WikibaseRepo.StatementGuidValidator' => function ( MediaWikiServices $services ): StatementGuidValidator {
		return new StatementGuidValidator( WikibaseRepo::getEntityIdParser( $services ) );
	},

	'WikibaseRepo.ValueFormatterFactory' => function ( MediaWikiServices $services ): OutputFormatValueFormatterFactory {
		$formatterFactoryCBs = WikibaseRepo::getDataTypeDefinitions( $services )
			->getFormatterFactoryCallbacks( DataTypeDefinitions::PREFIXED_MODE );

		return new OutputFormatValueFormatterFactory(
			$formatterFactoryCBs,
			$services->getContentLanguage(),
			new LanguageFallbackChainFactory()
		);
	},

	'WikibaseRepo.ValueParserFactory' => function ( MediaWikiServices $services ): ValueParserFactory {
		$dataTypeDefinitions = WikibaseRepo::getDataTypeDefinitions( $services );
		$callbacks = $dataTypeDefinitions->getParserFactoryCallbacks();

		// For backwards-compatibility, also register parsers under legacy names,
		// for use with the deprecated 'parser' parameter of the wbparsevalue API module.
		$prefixedCallbacks = $dataTypeDefinitions->getParserFactoryCallbacks(
			DataTypeDefinitions::PREFIXED_MODE
		);
		if ( isset( $prefixedCallbacks['VT:wikibase-entityid'] ) ) {
			$callbacks['wikibase-entityid'] = $prefixedCallbacks['VT:wikibase-entityid'];
		}
		if ( isset( $prefixedCallbacks['VT:globecoordinate'] ) ) {
			$callbacks['globecoordinate'] = $prefixedCallbacks['VT:globecoordinate'];
		}
		// 'null' is not a datatype. Kept for backwards compatibility.
		$callbacks['null'] = function() {
			return new NullParser();
		};

		return new ValueParserFactory( $callbacks );
	},

	'WikibaseRepo.ValueSnakRdfBuilderFactory' => function ( MediaWikiServices $services ): ValueSnakRdfBuilderFactory {
		return new ValueSnakRdfBuilderFactory(
			WikibaseRepo::getDataTypeDefinitions( $services )
				->getRdfBuilderFactoryCallbacks( DataTypeDefinitions::PREFIXED_MODE )
		);
	},

];
