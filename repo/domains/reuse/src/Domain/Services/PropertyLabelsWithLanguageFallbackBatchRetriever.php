<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Reuse\Domain\Services;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\Domains\Reuse\Domain\Model\Label;
use Wikibase\Repo\Domains\Reuse\Domain\Model\Labels;
use Wikibase\Repo\Domains\Reuse\Domain\Model\PropertyLabelsWithFallbackBatch;

/**
 * @license GPL-2.0-or-later
 */
class PropertyLabelsWithLanguageFallbackBatchRetriever {

	public function __construct(
		private readonly BatchPropertyLabelsRetriever $labelsRetriever,
		private readonly LanguageFallbackChainProvider $languageFallbackChainProvider
	) {
	}

	/**
	 * @param PropertyId[] $propertyIds
	 * @param string[] $languageCodes
	 */
	public function getPropertyLabelsWithLanguageFallback(
		array $propertyIds,
		array $languageCodes
	): PropertyLabelsWithFallbackBatch {
		$languagesToFetch = array_unique( array_merge( ...array_map(
			fn( string $langCode ) => $this->languageFallbackChainProvider->getFallbackLanguages( $langCode ),
			$languageCodes
		) ) );

		$fetchedLabels = $this->labelsRetriever->getPropertyLabels( $propertyIds, $languagesToFetch );
		$result = [];
		foreach ( $propertyIds as $propertyId ) {
			$propertyLabels = $fetchedLabels->getPropertyLabels( $propertyId );
			foreach ( $languageCodes as $requestedLang ) {
				$result[$propertyId->getSerialization()][$requestedLang] = $this->getBestMatchingLabel(
					$requestedLang,
					$propertyLabels
				);
			}
		}

		return new PropertyLabelsWithFallbackBatch( $result );
	}

	private function getBestMatchingLabel( string $requestedLang, Labels $propertyLabels ): ?Label {
		$fallbackLanguages = $this->languageFallbackChainProvider->getFallbackLanguages( $requestedLang );
		foreach ( $fallbackLanguages as $language ) {
			$matchedLabel = $propertyLabels->getLabelInLanguage( $language );
			if ( $matchedLabel !== null ) {
				return $matchedLabel;
			}
		}

		return null;
	}

}
