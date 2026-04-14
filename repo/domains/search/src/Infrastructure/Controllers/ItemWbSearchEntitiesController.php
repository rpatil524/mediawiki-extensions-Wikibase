<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Search\Infrastructure\Controllers;

use Wikibase\DataAccess\EntitySourceLookup;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Repo\Domains\Search\Application\UseCases\ItemPrefixSearch\ItemPrefixSearch;
use Wikibase\Repo\Domains\Search\Application\UseCases\ItemPrefixSearch\ItemPrefixSearchRequest;
use Wikibase\Repo\Domains\Search\Domain\Model\ItemSearchResult;

/**
 * @license GPL-2.0-or-later
 */
class ItemWbSearchEntitiesController implements WbSearchEntitiesController {

	public function __construct(
		private readonly ItemPrefixSearch $itemPrefixSearch,
		private readonly EntitySourceLookup $entitySourceLookup
	) {
	}

	public function search( WbSearchEntitiesRequest $request ): array {
		$response = $this->itemPrefixSearch->execute(
			new ItemPrefixSearchRequest(
				$request->text,
				$request->searchLanguageCode,
				$request->limit,
				0,
				$request->resultLanguage,
			)
		);
		return array_map(
			fn( ItemSearchResult $r ) => $this->convertResult( $r ),
			iterator_to_array( $response->results )
		);
	}

	private function convertResult( ItemSearchResult $result ): TermSearchResult {
		$matchedData = $result->getMatchedData();
		$entityId = $result->getItemId();

		$label = $result->getLabel();
		$description = $result->getDescription();

		return new TermSearchResult(
			new Term( $matchedData->getLanguageCode() ?? 'qid', $matchedData->getText() ),
			$matchedData->getType(),
			$entityId,
			$label ? new Term( $label->getLanguageCode(), $label->getText() ) : null,
			$description ? new Term( $description->getLanguageCode(), $description->getText() ) : null,
			[ TermSearchResult::CONCEPTURI_META_DATA_KEY =>
				$this->entitySourceLookup->getEntitySourceById( $entityId )->getConceptBaseUri()
				. wfUrlencode( $entityId->getSerialization() ) ]
		);
	}

}
