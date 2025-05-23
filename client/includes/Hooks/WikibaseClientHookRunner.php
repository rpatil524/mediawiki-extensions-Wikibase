<?php

namespace Wikibase\Client\Hooks;

use MediaWiki\HookContainer\HookContainer;
use Wikibase\Client\Usage\UsageAccumulator;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\Changes\EntityChange;

/**
 * Handle Changes' hooks
 * @author dang
 * @license GPL-2.0-or-later
 */
class WikibaseClientHookRunner implements
	WikibaseClientDataTypesHook,
	WikibaseClientEntityTypesHook,
	WikibaseClientSiteLinksForItemHook,
	WikibaseHandleChangeHook,
	WikibaseHandleChangesHook
{

	/** @var HookContainer */
	private $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * Hook runner for the 'WikibaseHandleChange' hook
	 *
	 * @param EntityChange $change
	 * @param array $rootJobParams
	 * @return bool
	 */
	public function onWikibaseHandleChange( $change, array $rootJobParams = [] ) {
		return $this->hookContainer->run(
			'WikibaseHandleChange',
			[ $change, $rootJobParams ]
		);
	}

	/**
	 * Hook runner for the 'WikibaseHandleChanges' hook
	 *
	 * @param array $changes
	 * @param array $rootJobParams
	 * @return bool
	 */
	public function onWikibaseHandleChanges( array $changes, array $rootJobParams = [] ) {
		return $this->hookContainer->run(
			'WikibaseHandleChanges',
			[ $changes, $rootJobParams ]
		);
	}

	/** @inheritDoc */
	public function onWikibaseClientSiteLinksForItem(
		Item $item,
		array &$siteLinks,
		UsageAccumulator $usageAccumulator
	): void {
		$this->hookContainer->run( 'WikibaseClientSiteLinksForItem',
			[
				$item,
				&$siteLinks,
				$usageAccumulator,
			],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onWikibaseClientDataTypes( array &$dataTypeDefinitions ): void {
		$this->hookContainer->run(
			'WikibaseClientDataTypes',
			[ &$dataTypeDefinitions ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onWikibaseClientEntityTypes( array &$entityTypeDefinitions ): void {
		 $this->hookContainer->run(
			 'WikibaseClientEntityTypes',
			 [ &$entityTypeDefinitions ],
			 [ 'abortable' => false ]
		 );
	}
}
