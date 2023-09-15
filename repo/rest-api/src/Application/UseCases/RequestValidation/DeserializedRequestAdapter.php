<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCases\RequestValidation;

use LogicException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementGuid;
use Wikibase\DataModel\Term\Term;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedEditMetadataRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedItemDescriptionEditRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedItemFieldsRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedItemIdRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedItemLabelEditRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedLanguageCodeRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedPropertyIdFilterRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedPropertyIdRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedStatementIdRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedStatementSerializationRequest;
use Wikibase\Repo\RestApi\Application\UseCases\EditMetadataRequest;
use Wikibase\Repo\RestApi\Application\UseCases\ItemDescriptionEditRequest;
use Wikibase\Repo\RestApi\Application\UseCases\ItemFieldsRequest;
use Wikibase\Repo\RestApi\Application\UseCases\ItemIdRequest;
use Wikibase\Repo\RestApi\Application\UseCases\ItemLabelEditRequest;
use Wikibase\Repo\RestApi\Application\UseCases\LanguageCodeRequest;
use Wikibase\Repo\RestApi\Application\UseCases\PatchRequest;
use Wikibase\Repo\RestApi\Application\UseCases\PropertyIdFilterRequest;
use Wikibase\Repo\RestApi\Application\UseCases\PropertyIdRequest;
use Wikibase\Repo\RestApi\Application\UseCases\StatementIdRequest;
use Wikibase\Repo\RestApi\Application\UseCases\StatementSerializationRequest;
use Wikibase\Repo\RestApi\Domain\Model\UserProvidedEditMetadata;

/**
 * @license GPL-2.0-or-later
 */
class DeserializedRequestAdapter implements
	DeserializedItemIdRequest,
	DeserializedPropertyIdRequest,
	DeserializedStatementIdRequest,
	DeserializedPropertyIdFilterRequest,
	DeserializedLanguageCodeRequest,
	DeserializedItemFieldsRequest,
	DeserializedStatementSerializationRequest,
	DeserializedEditMetadataRequest,
	DeserializedItemLabelEditRequest,
	DeserializedItemDescriptionEditRequest
{
	private array $deserializedRequest;

	public function __construct( array $deserializedRequest ) {
		$this->deserializedRequest = $deserializedRequest;
	}

	public function getItemId(): ItemId {
		return $this->getRequestField( ItemIdRequest::class );
	}

	public function getPropertyId(): NumericPropertyId {
		return $this->getRequestField( PropertyIdRequest::class );
	}

	public function getStatementId(): StatementGuid {
		return $this->getRequestField( StatementIdRequest::class );
	}

	public function getPropertyIdFilter(): ?PropertyId {
		return $this->getRequestField( PropertyIdFilterRequest::class );
	}

	public function getLanguageCode(): string {
		return $this->getRequestField( LanguageCodeRequest::class );
	}

	public function getItemFields(): array {
		return $this->getRequestField( ItemFieldsRequest::class );
	}

	public function getEditMetadata(): UserProvidedEditMetadata {
		return $this->getRequestField( EditMetadataRequest::class );
	}

	public function getStatement(): Statement {
		return $this->getRequestField( StatementSerializationRequest::class );
	}

	public function getPatch(): array {
		return $this->getRequestField( PatchRequest::class );
	}

	public function getLabel(): Term {
		return $this->getRequestField( ItemLabelEditRequest::class );
	}

	public function getDescription(): Term {
		return $this->getRequestField( ItemDescriptionEditRequest::class );
	}

	/**
	 * @return mixed
	 */
	private function getRequestField( string $field ) {
		if ( !array_key_exists( $field, $this->deserializedRequest ) ) {
			throw new LogicException( "'$field' is not part of the request" );
		}

		return $this->deserializedRequest[$field];
	}
}