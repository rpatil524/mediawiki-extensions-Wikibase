<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Crud\Application\UseCases\GetItemAliases;

use Wikibase\Repo\Domains\Crud\Application\UseCases\GetLatestItemRevisionMetadata;
use Wikibase\Repo\Domains\Crud\Application\UseCases\ItemRedirect;
use Wikibase\Repo\Domains\Crud\Application\UseCases\UseCaseError;
use Wikibase\Repo\Domains\Crud\Domain\Services\ItemAliasesRetriever;

/**
 * @license GPL-2.0-or-later
 */
class GetItemAliases {

	private GetLatestItemRevisionMetadata $getLatestRevisionMetadata;
	private ItemAliasesRetriever $itemAliasesRetriever;
	private GetItemAliasesValidator $validator;

	public function __construct(
		GetLatestItemRevisionMetadata $getLatestRevisionMetadata,
		ItemAliasesRetriever $itemAliasesRetriever,
		GetItemAliasesValidator $validator
	) {
		$this->getLatestRevisionMetadata = $getLatestRevisionMetadata;
		$this->itemAliasesRetriever = $itemAliasesRetriever;
		$this->validator = $validator;
	}

	/**
	 * @throws UseCaseError
	 * @throws ItemRedirect
	 */
	public function execute( GetItemAliasesRequest $request ): GetItemAliasesResponse {
		$deserializedRequest = $this->validator->validateAndDeserialize( $request );

		[ $revisionId, $lastModified ] = $this->getLatestRevisionMetadata->execute( $deserializedRequest->getItemId() );

		return new GetItemAliasesResponse(
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Item validated and exists
			$this->itemAliasesRetriever->getAliases( $deserializedRequest->getItemId() ),
			$lastModified,
			$revisionId,
		);
	}

}
