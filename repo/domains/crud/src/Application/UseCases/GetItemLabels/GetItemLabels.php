<?php declare( strict_types = 1 );

namespace Wikibase\Repo\Domains\Crud\Application\UseCases\GetItemLabels;

use Wikibase\Repo\Domains\Crud\Application\UseCases\GetLatestItemRevisionMetadata;
use Wikibase\Repo\Domains\Crud\Application\UseCases\ItemRedirect;
use Wikibase\Repo\Domains\Crud\Application\UseCases\UseCaseError;
use Wikibase\Repo\Domains\Crud\Domain\Services\ItemLabelsRetriever;

/**
 * @license GPL-2.0-or-later
 */
class GetItemLabels {

	private GetLatestItemRevisionMetadata $getLatestRevisionMetadata;
	private ItemLabelsRetriever $itemLabelsRetriever;
	private GetItemLabelsValidator $validator;

	public function __construct(
		GetLatestItemRevisionMetadata $getLatestRevisionMetadata,
		ItemLabelsRetriever $itemLabelsRetriever,
		GetItemLabelsValidator $validator
	) {
		$this->getLatestRevisionMetadata = $getLatestRevisionMetadata;
		$this->itemLabelsRetriever = $itemLabelsRetriever;
		$this->validator = $validator;
	}

	/**
	 * @throws UseCaseError
	 * @throws ItemRedirect
	 */
	public function execute( GetItemLabelsRequest $request ): GetItemLabelsResponse {
		$itemId = $this->validator->validateAndDeserialize( $request )->getItemId();

		[ $revisionId, $lastModified ] = $this->getLatestRevisionMetadata->execute( $itemId );

		return new GetItemLabelsResponse(
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Item validated and exists
			$this->itemLabelsRetriever->getLabels( $itemId ),
			$lastModified,
			$revisionId,
		);
	}
}
