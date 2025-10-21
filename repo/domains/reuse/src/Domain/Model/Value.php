<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Reuse\Domain\Model;

use DataValues\DataValue;
use InvalidArgumentException;

/**
 * @license GPL-2.0-or-later
 */
class Value {

	public const TYPE_VALUE = 'value';
	public const TYPE_NO_VALUE = 'novalue';
	public const TYPE_SOME_VALUE = 'somevalue';

	/**
	 * @param string $valueType
	 * @param DataValue|null $content Guaranteed to be non-null if value type is "value", always null otherwise.
	 */
	public function __construct( public readonly string $valueType, public readonly ?DataValue $content = null ) {
		if ( !in_array( $valueType, [ self::TYPE_VALUE, self::TYPE_SOME_VALUE, self::TYPE_NO_VALUE ] ) ) {
			throw new InvalidArgumentException( '$valueType must be one of "value", "somevalue", "novalue"' );
		}
		if ( $valueType === self::TYPE_VALUE && !$content ) {
			throw new InvalidArgumentException( '$value must not be null if $valueType is "value"' );
		}
		if ( $valueType !== self::TYPE_VALUE && $content ) {
			throw new InvalidArgumentException( "There must not be a value if \$valueType is '$valueType'" );
		}
	}

}
