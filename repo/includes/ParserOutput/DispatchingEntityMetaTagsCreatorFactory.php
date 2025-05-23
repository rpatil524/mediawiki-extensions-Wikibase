<?php

namespace Wikibase\Repo\ParserOutput;

use MediaWiki\Language\Language;
use Wikibase\View\DefaultMetaTagsCreator;
use Wikibase\View\EntityMetaTagsCreator;
use Wikimedia\Assert\Assert;

/**
 * A factory to create EntityMetaTags implementations by entity type based on callbacks.
 *
 * @license GPL-2.0-or-later
 */
class DispatchingEntityMetaTagsCreatorFactory {

	/**
	 * @var callable[]
	 */
	private $entityMetaTagsFactoryCallbacks;

	/**
	 * @param callable[] $entityMetaTagsFactoryCallbacks
	 */
	public function __construct( array $entityMetaTagsFactoryCallbacks ) {
		Assert::parameterElementType( 'callable', $entityMetaTagsFactoryCallbacks, '$entityMetaTagsFactoryCallbacks' );

		$this->entityMetaTagsFactoryCallbacks = $entityMetaTagsFactoryCallbacks;
	}

	public function newEntityMetaTags(
		string $entityType,
		Language $userLanguage
	): EntityMetaTagsCreator {
		if ( !isset( $this->entityMetaTagsFactoryCallbacks[$entityType] ) ) {
			return new DefaultMetaTagsCreator();
		}

		$entityMetaTags = $this->entityMetaTagsFactoryCallbacks[$entityType]( $userLanguage );

		Assert::postcondition(
			$entityMetaTags instanceof EntityMetaTagsCreator,
			'Callback must return an instance of EntityMetaTags'
		);

		return $entityMetaTags;
	}

}
