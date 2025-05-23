<?php declare( strict_types=1 );

namespace Wikibase\Repo\Tests\Domains\Crud\Application\UseCases\SetPropertyLabel;

use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\Property as PropertyWriteModel;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\Repo\Domains\Crud\Application\UseCases\AssertPropertyExists;
use Wikibase\Repo\Domains\Crud\Application\UseCases\AssertUserIsAuthorized;
use Wikibase\Repo\Domains\Crud\Application\UseCases\SetPropertyLabel\SetPropertyLabel;
use Wikibase\Repo\Domains\Crud\Application\UseCases\SetPropertyLabel\SetPropertyLabelRequest;
use Wikibase\Repo\Domains\Crud\Application\UseCases\SetPropertyLabel\SetPropertyLabelValidator;
use Wikibase\Repo\Domains\Crud\Application\UseCases\UseCaseError;
use Wikibase\Repo\Domains\Crud\Application\UseCases\UseCaseException;
use Wikibase\Repo\Domains\Crud\Domain\Model\EditMetadata;
use Wikibase\Repo\Domains\Crud\Domain\Model\LabelEditSummary;
use Wikibase\Repo\Domains\Crud\Domain\Model\User;
use Wikibase\Repo\Domains\Crud\Domain\ReadModel\Label;
use Wikibase\Repo\Domains\Crud\Domain\Services\PropertyUpdater;
use Wikibase\Repo\Domains\Crud\Domain\Services\PropertyWriteModelRetriever;
use Wikibase\Repo\Tests\Domains\Crud\Application\UseCaseRequestValidation\TestValidatingRequestDeserializer;
use Wikibase\Repo\Tests\Domains\Crud\Infrastructure\DataAccess\InMemoryPropertyRepository;

/**
 * @covers \Wikibase\Repo\Domains\Crud\Application\UseCases\SetPropertyLabel\SetPropertyLabel
 * @group Wikibase
 * @license GPL-2.0-or-later
 */
class SetPropertyLabelTest extends TestCase {

	private SetPropertyLabelValidator $validator;
	private PropertyWriteModelRetriever $propertyRetriever;
	private PropertyUpdater $propertyUpdater;
	private AssertPropertyExists $assertPropertyExists;
	private AssertUserIsAuthorized $assertUserIsAuthorized;

	protected function setUp(): void {
		parent::setUp();

		$this->validator = new TestValidatingRequestDeserializer();
		$this->propertyRetriever = $this->createStub( PropertyWriteModelRetriever::class );
		$this->propertyUpdater = $this->createStub( PropertyUpdater::class );
		$this->assertPropertyExists = $this->createStub( AssertPropertyExists::class );
		$this->assertUserIsAuthorized = $this->createStub( AssertUserIsAuthorized::class );
	}

	public function testAddLabel(): void {
		$propertyId = new NumericPropertyId( 'P1' );
		$langCode = 'en';
		$newLabelText = 'New label';
		$editTags = TestValidatingRequestDeserializer::ALLOWED_TAGS;
		$isBot = false;
		$comment = 'testAddLabel Comment';
		$property = new PropertyWriteModel( $propertyId, null, 'string' );

		$propertyRepo = new InMemoryPropertyRepository();
		$propertyRepo->addProperty( $property );
		$this->propertyRetriever = $propertyRepo;
		$this->propertyUpdater = $propertyRepo;

		$response = $this->newUseCase()->execute(
			new SetPropertyLabelRequest( "$propertyId", $langCode, $newLabelText, $editTags, $isBot, $comment, null )
		);

		$this->assertEquals( new Label( $langCode, $newLabelText ), $response->getLabel() );
		$this->assertSame( $propertyRepo->getLatestRevisionId( $propertyId ), $response->getRevisionId() );
		$this->assertSame( $propertyRepo->getLatestRevisionTimestamp( $propertyId ), $response->getLastModified() );
		$this->assertEquals(
			new EditMetadata(
				$editTags,
				$isBot,
				LabelEditSummary::newAddSummary( $comment, new Term( $langCode, $newLabelText ) )
			),
			$propertyRepo->getLatestRevisionEditMetadata( $propertyId )
		);
		$this->assertFalse( $response->wasReplaced() );
	}

	public function testReplaceLabel(): void {
		$propertyId = new NumericPropertyId( 'P1' );
		$langCode = 'en';
		$updatedLabelText = 'Replaced label';
		$editTags = TestValidatingRequestDeserializer::ALLOWED_TAGS;
		$isBot = false;
		$comment = 'testReplaceLabel Comment';
		$property = new PropertyWriteModel(
			$propertyId,
			new Fingerprint( new TermList( [ new Term( 'en', 'Label to replace' ) ] ) ),
			'string'
		);

		$propertyRepo = new InMemoryPropertyRepository();
		$propertyRepo->addProperty( $property );
		$this->propertyRetriever = $propertyRepo;
		$this->propertyUpdater = $propertyRepo;

		$response = $this->newUseCase()->execute(
			new SetPropertyLabelRequest( "$propertyId", $langCode, $updatedLabelText, $editTags, $isBot, $comment, null )
		);

		$this->assertEquals( new Label( $langCode, $updatedLabelText ), $response->getLabel() );
		$this->assertSame( $propertyRepo->getLatestRevisionId( $propertyId ), $response->getRevisionId() );
		$this->assertSame( $propertyRepo->getLatestRevisionTimestamp( $propertyId ), $response->getLastModified() );
		$this->assertEquals(
			new EditMetadata(
				$editTags,
				$isBot,
				LabelEditSummary::newReplaceSummary( $comment, new Term( $langCode, $updatedLabelText ) )
			),
			$propertyRepo->getLatestRevisionEditMetadata( $propertyId )
		);
		$this->assertTrue( $response->wasReplaced() );
	}

	public function testGivenInvalidRequest_throwsUseCaseException(): void {
		$expectedException = new UseCaseException( 'invalid-label-test' );
		$this->validator = $this->createStub( SetPropertyLabelValidator::class );
		$this->validator->method( 'validateAndDeserialize' )->willThrowException( $expectedException );
		try {
			$this->newUseCase()->execute(
				new SetPropertyLabelRequest( 'P123', 'en', 'label', [], false, null, null )
			);
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseException $e ) {
			$this->assertSame( $expectedException, $e );
		}
	}

	public function testGivenPropertyNotFound_throws(): void {
		$expectedException = $this->createStub( UseCaseException::class );
		$this->assertPropertyExists->method( 'execute' )
			->willThrowException( $expectedException );
		try {
			$this->newUseCase()->execute(
				new SetPropertyLabelRequest( 'P999', 'en', 'test label', [], false, null, null )
			);
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseException $e ) {
			$this->assertSame( $expectedException, $e );
		}
	}

	public function testGivenEditIsUnauthorized_throwsUseCaseError(): void {
		$propertyId = new NumericPropertyId( 'P123' );

		$expectedError = new UseCaseError(
			UseCaseError::PERMISSION_DENIED_UNKNOWN_REASON,
			'You have no permission to edit this item.'
		);
		$this->assertUserIsAuthorized = $this->createMock( AssertUserIsAuthorized::class );
		$this->assertUserIsAuthorized->method( 'checkEditPermissions' )
			->with( $propertyId, User::newAnonymous() )
			->willThrowException( $expectedError );

		try {
			$this->newUseCase()->execute(
				new SetPropertyLabelRequest( "$propertyId", 'en', 'test label', [], false, null, null )
			);
			$this->fail( 'this should not be reached' );
		} catch ( UseCaseError $e ) {
			$this->assertSame( $expectedError, $e );
		}
	}

	private function newUseCase(): SetPropertyLabel {
		return new SetPropertyLabel(
			$this->validator,
			$this->propertyRetriever,
			$this->propertyUpdater,
			$this->assertPropertyExists,
			$this->assertUserIsAuthorized
		);
	}

}
