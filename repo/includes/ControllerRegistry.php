<?php

declare( strict_types = 1 );

namespace Wikibase\Repo;

/**
 * Registry of controller callbacks per entity type.
 *
 * @license GPL-2.0-or-later
 */
class ControllerRegistry {

	public const WB_SEARCH_ENTITIES_CONTROLLER = 'wbsearchentities-controller';

	/** @param array<string, array<string, callable>> $controllerDefinitions */
	public function __construct( private readonly array $controllerDefinitions ) {
	}

	/**
	 * @param string $field one of the constants declared in this class
	 * @return array<string, callable> map of entity type => callback for that field
	 */
	public function get( string $field ): array {
		$result = [];
		foreach ( $this->controllerDefinitions as $type => $def ) {
			if ( isset( $def[$field] ) ) {
				$result[$type] = $def[$field];
			}
		}

		return $result;
	}

}
