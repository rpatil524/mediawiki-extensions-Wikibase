<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\UseCases;

/**
 * @license GPL-2.0-or-later
 */
class ErrorResponse {
	public const INVALID_ITEM_ID = 'invalid-item-id';
	public const INVALID_FIELD = 'invalid-field';
	public const ITEM_NOT_FOUND = 'item-not-found';
	public const UNEXPECTED_ERROR = 'unexpected-error';

	private $code;
	private $message;

	public function __construct( string $code, string $message ) {
		$this->code = $code;
		$this->message = $message;
	}

	public function getCode(): string {
		return $this->code;
	}

	public function getMessage(): string {
		return $this->message;
	}
}