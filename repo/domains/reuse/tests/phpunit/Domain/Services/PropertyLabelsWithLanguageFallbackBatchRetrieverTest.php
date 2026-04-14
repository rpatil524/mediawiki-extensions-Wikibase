<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\Domains\Reuse\Domain\Services;

use PHPUnit\Framework\TestCase;
use Wikibase\DataAccess\Tests\InMemoryPrefetchingTermLookup;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Repo\Domains\Reuse\Domain\Model\Label;
use Wikibase\Repo\Domains\Reuse\Domain\Model\PropertyLabelsWithFallbackBatch;
use Wikibase\Repo\Domains\Reuse\Domain\Services\PropertyLabelsWithLanguageFallbackBatchRetriever;
use Wikibase\Repo\Domains\Reuse\Infrastructure\DataAccess\LanguageFallbackChainFactoryFallbackLanguagesProvider;
use Wikibase\Repo\Domains\Reuse\Infrastructure\DataAccess\PrefetchingTermLookupBatchLabelsDescriptionsRetriever;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\Domains\Reuse\Domain\Services\PropertyLabelsWithLanguageFallbackBatchRetriever
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class PropertyLabelsWithLanguageFallbackBatchRetrieverTest extends TestCase {

	private Property $enDeProperty;
	private Property $enOnlyProperty;
	private Property $frOnlyProperty;

	public function setUp(): void {
		$this->enDeProperty = new Property(
			new NumericPropertyId( 'P1' ),
			new Fingerprint( new TermList( [ new Term( 'en', 'instance of' ), new Term( 'de', 'ist ein(e)' ) ] ) ),
			'string'
		);
		$this->enOnlyProperty = new Property(
			new NumericPropertyId( 'P2' ),
			new Fingerprint( new TermList( [ new Term( 'en', 'subclass of' ) ] ) ),
			'string'
		);
		$this->frOnlyProperty = new Property(
			new NumericPropertyId( 'P3' ),
			new Fingerprint( new TermList( [ new Term( 'fr', 'situé dans' ) ] ) ),
			'string'
		);
	}

	public function testReturnsExactMatchWhenAvailable(): void {
		$result = $this->newRetriever()->getPropertyLabelsWithLanguageFallback(
			[ $this->enDeProperty->getId() ],
			[ 'de' ]
		);

		$this->assertEquals(
			new PropertyLabelsWithFallbackBatch( [
				$this->enDeProperty->getId()->getSerialization() => [ 'de' => $this->labelOf( $this->enDeProperty, 'de' ) ],
			] ),
			$result
		);
	}

	public function testReturnsFallbackLabelWhenNoExactMatchExists(): void {
		$result = $this->newRetriever()->getPropertyLabelsWithLanguageFallback(
			[ $this->enOnlyProperty->getId() ],
			[ 'de' ]
		);

		$this->assertEquals(
			new PropertyLabelsWithFallbackBatch( [
				$this->enOnlyProperty->getId()->getSerialization() => [ 'de' => $this->labelOf( $this->enOnlyProperty, 'en' ) ],
			] ),
			$result
		);
	}

	public function testReturnsNullWhenNoLabelInFallbackChain(): void {
		$result = $this->newRetriever()->getPropertyLabelsWithLanguageFallback(
			[ $this->frOnlyProperty->getId() ],
			[ 'de' ]
		);

		$this->assertEquals(
			new PropertyLabelsWithFallbackBatch( [
				$this->frOnlyProperty->getId()->getSerialization() => [ 'de' => null ],
			] ),
			$result
		);
	}

	public function testHandlesMultiplePropertiesAndLanguages(): void {
		$result = $this->newRetriever()->getPropertyLabelsWithLanguageFallback(
			[
				$this->enDeProperty->getId(),
				$this->enOnlyProperty->getId(),
				$this->frOnlyProperty->getId(),
			],
			[ 'de', 'fr' ]
		);

		$this->assertEquals(
			new PropertyLabelsWithFallbackBatch( [
				$this->enDeProperty->getId()->getSerialization() => [
					'de' => $this->labelOf( $this->enDeProperty, 'de' ),
					'fr' => $this->labelOf( $this->enDeProperty, 'en' ),
				],
				$this->enOnlyProperty->getId()->getSerialization() => [
					'de' => $this->labelOf( $this->enOnlyProperty, 'en' ),
					'fr' => $this->labelOf( $this->enOnlyProperty, 'en' ),
				],
				$this->frOnlyProperty->getId()->getSerialization() => [
					'de' => null,
					'fr' => $this->labelOf( $this->frOnlyProperty, 'fr' ),
				],
			] ),
			$result
		);
	}

	private function labelOf( Property $property, string $languageCode ): Label {
		return new Label(
			$languageCode,
			$property->getFingerprint()->getLabels()->getByLanguage( $languageCode )->getText()
		);
	}

	private function newRetriever(): PropertyLabelsWithLanguageFallbackBatchRetriever {
		$lookup = new InMemoryPrefetchingTermLookup();
		$lookup->setData( [ $this->enDeProperty, $this->enOnlyProperty, $this->frOnlyProperty ] );

		return new PropertyLabelsWithLanguageFallbackBatchRetriever(
			new PrefetchingTermLookupBatchLabelsDescriptionsRetriever( $lookup ),
			new LanguageFallbackChainFactoryFallbackLanguagesProvider( WikibaseRepo::getLanguageFallbackChainFactory() ),
		);
	}

}
