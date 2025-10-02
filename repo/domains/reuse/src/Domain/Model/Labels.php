<?php declare( strict_types = 1 );

namespace Wikibase\Repo\Domains\Reuse\Domain\Model;

use ArrayObject;

/**
 * @license GPL-2.0-or-later
 */
class Labels extends ArrayObject {

	public function __construct( Label ...$labels ) {
		parent::__construct(
			array_combine(
				array_map( fn( Label $l ) => $l->languageCode, $labels ),
				$labels
			)
		);
	}

	public function getLabelInLanguage( string $languageCode ): ?Label {
		return $this[$languageCode] ?? null;
	}
}
