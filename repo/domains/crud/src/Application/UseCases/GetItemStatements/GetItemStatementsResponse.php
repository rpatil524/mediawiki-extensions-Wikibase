<?php declare( strict_types=1 );

namespace Wikibase\Repo\Domains\Crud\Application\UseCases\GetItemStatements;

use Wikibase\Repo\Domains\Crud\Domain\ReadModel\StatementList;

/**
 * @license GPL-2.0-or-later
 */
class GetItemStatementsResponse {

	private StatementList $statements;

	/**
	 * @var string timestamp in MediaWiki format 'YYYYMMDDhhmmss'
	 */
	private string $lastModified;

	private int $revisionId;

	public function __construct( StatementList $statements, string $lastModified, int $revisionId ) {
		$this->statements = $statements;
		$this->lastModified = $lastModified;
		$this->revisionId = $revisionId;
	}

	public function getStatements(): StatementList {
		return $this->statements;
	}

	public function getLastModified(): string {
		return $this->lastModified;
	}

	public function getRevisionId(): int {
		return $this->revisionId;
	}

}
