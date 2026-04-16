<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\Domains\Reuse\Domain\Services;

use PHPUnit\Framework\TestCase;
use Wikibase\DataAccess\Tests\InMemoryPrefetchingTermLookup;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Repo\Domains\Reuse\Domain\Model\ItemLabelsWithFallbackBatch;
use Wikibase\Repo\Domains\Reuse\Domain\Model\Label;
use Wikibase\Repo\Domains\Reuse\Domain\Services\ItemLabelsWithLanguageFallbackBatchRetriever;
use Wikibase\Repo\Domains\Reuse\Infrastructure\DataAccess\LanguageFallbackChainFactoryFallbackLanguagesProvider;
use Wikibase\Repo\Domains\Reuse\Infrastructure\DataAccess\PrefetchingTermLookupBatchLabelsDescriptionsRetriever;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\Domains\Reuse\Domain\Services\ItemLabelsWithLanguageFallbackBatchRetriever
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class ItemLabelsWithLanguageFallbackBatchRetrieverTest extends TestCase {

	private Item $enDeItem;
	private Item $enOnlyItem;
	private Item $frOnlyItem;

	public function setUp(): void {
		$this->enDeItem = new Item(
			new ItemId( 'Q1' ),
			new Fingerprint( new TermList( [ new Term( 'en', 'instance of' ), new Term( 'de', 'ist ein(e)' ) ] ) ),
		);
		$this->enOnlyItem = new Item(
			new ItemId( 'Q2' ),
			new Fingerprint( new TermList( [ new Term( 'en', 'subclass of' ) ] ) ),
		);
		$this->frOnlyItem = new Item(
			new ItemId( 'Q3' ),
			new Fingerprint( new TermList( [ new Term( 'fr', 'situé dans' ) ] ) ),
		);
	}

	public function testReturnsExactMatchWhenAvailable(): void {
		$result = $this->newRetriever()->getItemLabelsWithLanguageFallback(
			[ $this->enDeItem->getId() ],
			[ 'de' ]
		);

		$this->assertEquals(
			new ItemLabelsWithFallbackBatch( [
				$this->enDeItem->getId()->getSerialization() => [ 'de' => $this->labelOf( $this->enDeItem, 'de' ) ],
			] ),
			$result
		);
	}

	public function testReturnsFallbackLabelWhenNoExactMatchExists(): void {
		$result = $this->newRetriever()->getItemLabelsWithLanguageFallback(
			[ $this->enOnlyItem->getId() ],
			[ 'de' ]
		);

		$this->assertEquals(
			new ItemLabelsWithFallbackBatch( [
				$this->enOnlyItem->getId()->getSerialization() => [ 'de' => $this->labelOf( $this->enOnlyItem, 'en' ) ],
			] ),
			$result
		);
	}

	public function testReturnsNullWhenNoLabelInFallbackChain(): void {
		$result = $this->newRetriever()->getItemLabelsWithLanguageFallback(
			[ $this->frOnlyItem->getId() ],
			[ 'de' ]
		);

		$this->assertEquals(
			new ItemLabelsWithFallbackBatch( [
				$this->frOnlyItem->getId()->getSerialization() => [ 'de' => null ],
			] ),
			$result
		);
	}

	public function testHandlesMultipleItemsAndLanguages(): void {
		$result = $this->newRetriever()->getItemLabelsWithLanguageFallback(
			[
				$this->enDeItem->getId(),
				$this->enOnlyItem->getId(),
				$this->frOnlyItem->getId(),
			],
			[ 'de', 'fr' ]
		);

		$this->assertEquals(
			new ItemLabelsWithFallbackBatch( [
				$this->enDeItem->getId()->getSerialization() => [
					'de' => $this->labelOf( $this->enDeItem, 'de' ),
					'fr' => $this->labelOf( $this->enDeItem, 'en' ),
				],
				$this->enOnlyItem->getId()->getSerialization() => [
					'de' => $this->labelOf( $this->enOnlyItem, 'en' ),
					'fr' => $this->labelOf( $this->enOnlyItem, 'en' ),
				],
				$this->frOnlyItem->getId()->getSerialization() => [
					'de' => null,
					'fr' => $this->labelOf( $this->frOnlyItem, 'fr' ),
				],
			] ),
			$result
		);
	}

	private function labelOf( Item $item, string $languageCode ): Label {
		return new Label(
			$languageCode,
			$item->getFingerprint()->getLabels()->getByLanguage( $languageCode )->getText()
		);
	}

	private function newRetriever(): ItemLabelsWithLanguageFallbackBatchRetriever {
		$lookup = new InMemoryPrefetchingTermLookup();
		$lookup->setData( [ $this->enDeItem, $this->enOnlyItem, $this->frOnlyItem ] );

		return new ItemLabelsWithLanguageFallbackBatchRetriever(
			new PrefetchingTermLookupBatchLabelsDescriptionsRetriever( $lookup ),
			new LanguageFallbackChainFactoryFallbackLanguagesProvider( WikibaseRepo::getLanguageFallbackChainFactory() ),
		);
	}

}
