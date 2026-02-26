<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Reuse\Infrastructure\GraphQL\Schema;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * @license GPL-2.0-or-later
 */
class ItemSearchFilterType extends InputObjectType {

	public function __construct( Types $types ) {
		$searchCondition = $types->getItemSearchConditionType();
			parent::__construct(
				[
					// phpcs:ignore Generic.Files.LineLength.TooLong
					'description' => 'Filter used to match items by their statements. Supports simple property/value matching or combining multiple filters with AND.',
					'fields' => [
						'and' => [
							// @phan-suppress-next-line PhanUndeclaredInvokeInCallable
							'type' => Type::listOf( Type::nonNull( $searchCondition ) ),
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'description' => 'Combine multiple conditions using AND operator. Requires at least two conditions, all of which must match. Cannot be used together with the property field.',
						],
						$searchCondition->getField( 'property' ),
						$searchCondition->getField( 'value' ),
					],
				]
			);
	}
}
