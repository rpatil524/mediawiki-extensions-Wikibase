<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Reuse\Infrastructure\GraphQL;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL;
use MediaWiki\Config\Config;
use Wikibase\Repo\Domains\Reuse\Infrastructure\GraphQL\Schema\Schema;
use Wikibase\Repo\Domains\Reuse\Infrastructure\GraphQL\Validation\ValidResult;

/**
 * @license GPL-2.0-or-later
 */
class GraphQLService {
	public const LOAD_ITEM_COMPLEXITY = 10;
	public const MAX_QUERY_COMPLEXITY = self::LOAD_ITEM_COMPLEXITY * 50;
	public const SEARCH_ITEMS_COMPLEXITY = self::MAX_QUERY_COMPLEXITY;

	private QueryComplexityRule $queryComplexityRule;

	public function __construct(
		private readonly Schema $schema,
		private readonly Config $config,
		private readonly GraphQLErrorLogger $errorLogger,
		private readonly GraphQLTracking $tracking,
	) {
		$this->queryComplexityRule = new QueryComplexityRule( self::MAX_QUERY_COMPLEXITY );
	}

	public function query( string $query, array $variables = [], ?string $operationName = null ): array {
		$validationResult = GraphQLQueryValidator::validate( $query );
		$context = new QueryContext();

		if ( $validationResult instanceof ValidResult ) {
			$parsedQuery = $validationResult->parsedQuery;
			$result = GraphQL::executeQuery(
				$this->schema,
				$validationResult->parsedQuery,
				contextValue: $context,
				variableValues: $variables,
				operationName: $operationName,
				validationRules: [
					...GraphQL::getStandardValidationRules(),
					$this->queryComplexityRule,
				],
			);
		} else {
			$parsedQuery = null;
			$result = new ExecutionResult(
				errors: [ new Error( message: $validationResult->getMessage(), previous: $validationResult ) ]
			);
		}

		if ( $context->redirects ) {
			$result->extensions[ QueryContext::KEY_MESSAGE ] = QueryContext::MESSAGE_REDIRECTS;
			$result->extensions[ QueryContext::KEY_REDIRECTS ] = $context->redirects;
		}

		$this->tracking->trackUsage( $result, $parsedQuery, $operationName );
		$this->tracking->trackErrors( $this->queryComplexityRule, $result->errors );
		$this->errorLogger->logUnexpectedErrors( $result->errors );

		$includeDebugInfo = DebugFlag::INCLUDE_TRACE | DebugFlag::INCLUDE_DEBUG_MESSAGE;
		return $result->toArray(
			$this->config->get( 'ShowExceptionDetails' ) ? $includeDebugInfo : DebugFlag::NONE
		);
	}

}
