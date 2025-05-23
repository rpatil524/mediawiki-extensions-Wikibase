<?php declare( strict_types = 1 );

namespace Wikibase\Repo\Domains\Crud\Application\UseCases\GetPropertyLabels;

use Wikibase\Repo\Domains\Crud\Domain\ReadModel\Labels;

/**
 * @license GPL-2.0-or-later
 */
class GetPropertyLabelsResponse {

	private Labels $labels;
	private string $lastModified;
	private int $revisionId;

	public function __construct( Labels $labels, string $lastModified, int $revisionId ) {
		$this->labels = $labels;
		$this->lastModified = $lastModified;
		$this->revisionId = $revisionId;
	}

	public function getLabels(): Labels {
		return $this->labels;
	}

	public function getLastModified(): string {
		return $this->lastModified;
	}

	public function getRevisionId(): int {
		return $this->revisionId;
	}

}
