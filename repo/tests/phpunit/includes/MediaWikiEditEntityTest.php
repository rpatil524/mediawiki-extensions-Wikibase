<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests;

use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\TempUser\CreateStatus;
use MediaWiki\User\TempUser\TempUserCreator;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use ReflectionMethod;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Diff\EntityDiffer;
use Wikibase\DataModel\Services\Diff\EntityPatcher;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\Lib\Tests\MockRepository;
use Wikibase\Repo\EditEntity\EditEntity;
use Wikibase\Repo\EditEntity\EditFilterHookRunner;
use Wikibase\Repo\EditEntity\MediaWikiEditEntity;
use Wikibase\Repo\Store\EntityPermissionChecker;
use Wikibase\Repo\Store\EntityTitleStoreLookup;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Wikibase\Repo\EditEntity\MediaWikiEditEntity
 *
 * @group Wikibase
 *
 * @group Database
 *        ^--- needed just because we are using Title objects.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
class MediaWikiEditEntityTest extends MediaWikiIntegrationTestCase {

	private function getEntityTitleLookup(): EntityTitleStoreLookup {
		$titleLookup = $this->createMock( EntityTitleStoreLookup::class );

		$titleLookup->method( 'getTitleForId' )
			->willReturnCallback( static function ( EntityId $id ) {
				return Title::makeTitle(
					NS_MAIN,
					$id->getEntityType() . '/' . $id->getSerialization()
				);
			} );

		return $titleLookup;
	}

	/**
	 * @param bool[]|null $permissions
	 *
	 * @return EntityPermissionChecker
	 */
	private function getEntityPermissionChecker( ?array $permissions = null ): EntityPermissionChecker {
		$permissionChecker = $this->createMock( EntityPermissionChecker::class );

		$checkAction = static function ( $user, $action ) use ( $permissions ) {
			if ( $permissions === null
				|| ( isset( $permissions[$action] ) && $permissions[$action] )
			) {
				return Status::newGood( true );
			} else {
				return Status::newFatal( 'badaccess-group0' );
			}
		};

		$permissionChecker->method( 'getPermissionStatusForEntity' )
			->willReturnCallback( $checkAction );

		$permissionChecker->method( 'getPermissionStatusForEntityId' )
			->willReturnCallback( $checkAction );

		return $permissionChecker;
	}

	private function getMockEditFitlerHookRunner(
		?Status $status = null,
		$expects = null
	): EditFilterHookRunner {
		$runner = $this->getMockBuilder( EditFilterHookRunner::class )
			->onlyMethods( [ 'run' ] )
			->disableOriginalConstructor()
			->getMock();
		$runner->expects( $expects ?? $this->any() )
			->method( 'run' )
			->willReturn( $status ?? Status::newGood() );
		return $runner;
	}

	/**
	 * @param MockRepository $mockRepository
	 * @param EntityId $entityId
	 * @param EntityTitleStoreLookup $titleLookup
	 * @param User|null $user
	 * @param bool $baseRevId
	 * @param bool[]|null $permissions map of actions to bool, indicating which actions are allowed.
	 * @param EditFilterHookRunner|null $editFilterHookRunner
	 * @param string[]|null $localEntityTypes
	 *
	 * @return MediaWikiEditEntity
	 */
	private function makeEditEntity(
		MockRepository $mockRepository,
		?EntityId $entityId,
		EntityTitleStoreLookup $titleLookup,
		User $user,
		$baseRevId = 0,
		?array $permissions = null,
		?EditFilterHookRunner $editFilterHookRunner = null,
		?array $localEntityTypes = null
	): MediaWikiEditEntity {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );
		$context->setUser( $user );

		$permissionChecker = $this->getEntityPermissionChecker( $permissions );
		$repoSettings = WikibaseRepo::getSettings();
		$localEntityTypes = $localEntityTypes ?: WikibaseRepo::getLocalEntityTypes();

		return new MediaWikiEditEntity(
			$titleLookup,
			$mockRepository,
			$mockRepository,
			$permissionChecker,
			new EntityDiffer(),
			new EntityPatcher(),
			$entityId,
			$context,
			$editFilterHookRunner ?? $this->getMockEditFitlerHookRunner(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getTempUserCreator(),
			$repoSettings['maxSerializedEntitySize'],
			$localEntityTypes,
			$baseRevId
		);
	}

	private function getMockRepository( ?User $user = null, ?User $otherUser = null ): MockRepository {
		$repo = new MockRepository();

		$user ??= $this->getTestUser()->getUser();
		$otherUser ??= $this->getTestUser( [ 'bot' ] )->getUser();

		/** @var Item $item */
		$item = new Item( new ItemId( 'Q17' ) );
		$item->setLabel( 'en', 'foo' );
		$repo->putEntity( $item, 10, 0, $user );

		$item = new Item( new ItemId( 'Q17' ) );
		$item->setLabel( 'en', 'bar' );
		$repo->putEntity( $item, 11, 0, $otherUser );

		$item = new Item( new ItemId( 'Q17' ) );
		$item->setLabel( 'en', 'bar' );
		$item->setLabel( 'de', 'bar' );
		$repo->putEntity( $item, 12, 0, $user );

		$item = new Item( new ItemId( 'Q17' ) );
		$item->setLabel( 'en', 'test' );
		$item->setLabel( 'de', 'bar' );
		$item->setDescription( 'en', 'more testing' );
		$repo->putEntity( $item, 13, 0, $user );

		$redirect = new EntityRedirect(
			new ItemId( 'Q302' ),
			new ItemId( 'Q404' )
		);
		$repo->putRedirect( $redirect );

		return $repo;
	}

	public static function provideEditConflict(): iterable {
		/*
		 * Test Revisions:
		 * #10: label: [ 'en' => 'foo' ];
		 * #11: label: [ 'en' => 'bar' ]; // by other user
		 * #12: label: [ 'en' => 'bar', 'de' => 'bar' ];
		 * #13: label: [ 'en' => 'test', 'de' => 'bar' ], description: [ 'en' => 'more testing' ];
		*/

		yield 'no base rev given' => [
			'inputData' => null,
			'baseRevisionId' => 0,
			'expectedConflict' => false,
			'expectedFix' => false,
		];
		yield 'base rev == current' => [
			'inputData' => null,
			'baseRevisionId' => 13,
			'expectedConflict' => false,
			'expectedFix' => false,
		];
		yield 'user was last to edit' => [
			'inputData' => [
				'label' => [ 'de' => 'yarrr' ],
			],
			'baseRevisionId' => 12,
			'expectedConflict' => true,
			'expectedFix' => true,
			'expectedData' => [
				'label' => [ 'en' => 'test', 'de' => 'yarrr' ],
			],
		];
		yield 'user was last to edit, but introduces a new operand' => [
			'inputData' => [
				'label' => [ 'de' => 'yarrr' ],
			],
			'baseRevisionId' => 11,
			'expectedConflict' => true,
			'expectedFix' => false, // expected failure, diff operand change
			'expectedData' => null,
		];
		yield 'patch applied' => [
			'inputData' => [
				'label' => [ 'nl' => 'test', 'fr' => 'frrrrtt' ],
			],
			'baseRevisionId' => 10,
			'expectedConflict' => true,
			'expectedFix' => true,
			'expectedData' => [
				'label' => [ 'de' => 'bar', 'en' => 'test', 'nl' => 'test', 'fr' => 'frrrrtt' ],
			],
		];
		yield 'patch failed, expect a conflict' => [
			'inputData' => [
				'label' => [ 'nl' => 'test', 'de' => 'bar' ],
			],
			'baseRevisionId' => 10,
			'expectedConflict' => true,
			'expectedFix' => false,
			'expectedData' => null,
		];
		yield 'patch is empty, keep current (not base)' => [
			'inputData' => [
				'label' => [ 'en' => 'bar', 'de' => 'bar' ],
			],
			'baseRevisionId' => 12,
			'expectedConflict' => true,
			'expectedFix' => true,
			'expectedData' => [
				'label' => [ 'en' => 'test', 'de' => 'bar' ],
				'description' => [ 'en' => 'more testing' ],
			],
		];
	}

	/**
	 * @dataProvider provideEditConflict
	 */
	public function testEditConflict(
		?array $inputData,
		$baseRevisionId,
		$expectedConflict,
		$expectedFix,
		?array $expectedData = null
	) {
		$user = $this->getTestUser()->getUser();
		$repo = $this->getMockRepository( $user );

		$entityId = new ItemId( 'Q17' );
		$revision = $repo->getEntityRevision( $entityId, $baseRevisionId );
		/** @var Item $item */
		$item = $revision->getEntity();

		// change entity ----------------------------------
		if ( $inputData === null ) {
			$item = new Item( $item->getId() );
		} else {
			if ( !empty( $inputData['label'] ) ) {
				foreach ( $inputData['label'] as $k => $v ) {
					$item->setLabel( $k, $v );
				}
			}

			if ( !empty( $inputData['description'] ) ) {
				foreach ( $inputData['description'] as $k => $v ) {
					$item->setDescription( $k, $v );
				}
			}

			if ( !empty( $inputData['aliases'] ) ) {
				foreach ( $inputData['aliases'] as $k => $v ) {
					$item->setAliases( $k, $v );
				}
			}
		}

		// save entity ----------------------------------
		$titleLookup = $this->getEntityTitleLookup();
		$editEntity = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user, $baseRevisionId );

		if ( $baseRevisionId > 0 ) {
			$baseRevision = $editEntity->getBaseRevision();
			$this->assertSame( $baseRevisionId, $baseRevision->getRevisionId() );
			$this->assertEquals( $entityId, $baseRevision->getEntity()->getId() );
		}

		$conflict = $editEntity->hasEditConflict();
		$this->assertEquals( $expectedConflict, $conflict, 'hasEditConflict()' );

		$token = $user->getEditToken();
		$status = $editEntity->attemptSave( $item, "Testing", EDIT_UPDATE, $token );

		$expectedOk = !$expectedConflict || $expectedFix;
		$this->assertEquals( $expectedOk, $status->isOK(), 'unresolved conflict?' );

		if ( $expectedData !== null ) {
			$this->assertTrue( $status->isOK(), '$status->isOK()' );

			$revision = $status->getRevision();
			$newEntity = $revision->getEntity();
			$data = $this->fingerprintToPartialArray( $newEntity->getFingerprint() );

			foreach ( $expectedData as $key => $expectedValue ) {
				$actualValue = $data[$key];
				$this->assertArrayEquals( $expectedValue, $actualValue, false, true );
			}
		}
	}

	private function fingerprintToPartialArray( Fingerprint $fingerprint ): array {
		return [
			'label' => $fingerprint->getLabels()->toTextArray(),
			'description' => $fingerprint->getDescriptions()->toTextArray(),
		];
	}

	public function testAttemptSaveWithLateConflict() {
		$repo = $this->getMockRepository();

		$user = $this->getTestUser()->getUser();

		// create item
		$entity = new Item( new ItemId( 'Q42' ) );
		$entity->setLabel( 'en', 'Test' );

		$repo->putEntity( $entity, 0, 0, $user );

		// begin editing the entity
		$entity = new Item( new ItemId( 'Q42' ) );
		$entity->setLabel( 'en', 'Trust' );

		$titleLookup = $this->getEntityTitleLookup();
		$editEntity = $this->makeEditEntity( $repo, $entity->getId(), $titleLookup, $user );
		$editEntity->getLatestRevision(); // make sure EditEntity has page and revision

		// create independent Entity instance for the same entity, and modify and save it
		$entity2 = new Item( new ItemId( 'Q42' ) );
		$entity2->setLabel( 'en', 'Toast' );

		$user2 = $this->getTestUser()->getUser();
		$repo->putEntity( $entity2, 0, 0, $user2 );

		// now try to save the original edit. The conflict should still be detected
		$token = $user->getEditToken();
		$status = $editEntity->attemptSave( $entity, "Testing", EDIT_UPDATE, $token );

		$this->assertTrue( $editEntity->hasError(), 'Saving should have failed late' );
		$this->assertStatusError( 'edit-conflict', $status );
	}

	public static function provideCheckEditPermissions(): iterable {
		yield 'edit allowed for new item' => [
			'permissions' => [ 'read' => true, 'edit' => true, 'createpage' => true ],
			'create' => false,
			'expectedOK' => true,
		];
		yield 'edit not allowed for existing item' => [
			'permissions' => [ 'read' => true, 'edit' => false ],
			'create' => true,
			'expectedOK' => false,
		];
	}

	private function prepareItemForPermissionCheck( User $user, MockRepository $mockRepository, bool $create ): Item {
		$item = new Item();

		if ( $create ) {
			$item->setLabel( 'de', 'Test' );
			$mockRepository->putEntity( $item, 0, 0, $user );
		}

		return $item;
	}

	/**
	 * @dataProvider provideCheckEditPermissions
	 */
	public function testCheckEditPermissions( array $permissions, bool $create, bool $expectedOK ): void {
		$repo = $this->getMockRepository();

		$user = $this->getTestUser()->getUser();
		$item = $this->prepareItemForPermissionCheck( $user, $repo, $create );

		$titleLookup = $this->getEntityTitleLookup();
		$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user, 0, $permissions );
		TestingAccessWrapper::newFromObject( $edit )->checkEditPermissions( $item );

		$this->assertEquals( $expectedOK, $edit->getStatus()->isOK() );
		$this->assertNotEquals( $expectedOK, $edit->hasError( EditEntity::PERMISSION_ERROR ) );
	}

	/**
	 * @dataProvider provideCheckEditPermissions
	 */
	public function testAttemptSavePermissions( array $permissions, bool $create, bool $expectedOK ): void {
		$repo = $this->getMockRepository();
		$titleLookup = $this->getEntityTitleLookup();

		$user = $this->getTestUser()->getUser();
		$item = $this->prepareItemForPermissionCheck( $user, $repo, $create );

		$token = $user->getEditToken();
		$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user, 0, $permissions );

		$edit->attemptSave( $item, "testing", ( $item->getId() === null ? EDIT_NEW : EDIT_UPDATE ), $token );

		$this->assertEquals( $expectedOK, $edit->getStatus()->isOK(), var_export( $edit->getStatus()->getErrorsArray(), true ) );
		$this->assertNotEquals( $expectedOK, $edit->hasError( EditEntity::PERMISSION_ERROR ) );
	}

	public function testCheckLocalEntityTypes() {
		$item = new Item();
		$user = $this->getTestUser()->getUser();
		$token = $user->getEditToken();

		$edit = $this->makeEditEntity(
			$this->getMockRepository(),
			$item->getId(),
			$this->getEntityTitleLookup(),
			$user,
			0,
			null,
			null,
			[ 'property' ]
		);

		$status = $edit->attemptSave( $item, 'testing', EDIT_NEW, $token );
		$this->assertStatusError( 'wikibase-error-entity-not-local', $status );
	}

	public static function provideAttemptSaveRateLimit(): iterable {
		yield 'no limits' => [
			'limits' => [],
			'groups' => [],
			'edits' => [
				[ 'item' => 'foo', 'label' => 'foo', 'ok' => true ],
				[ 'item' => 'bar', 'label' => 'bar', 'ok' => true ],
				[ 'item' => 'foo', 'label' => 'Foo', 'ok' => true ],
				[ 'item' => 'bar', 'label' => 'Bar', 'ok' => true ],
			],
		];

		yield 'limits bypassed with noratelimit permission' => [
			'limits' => [
				'edit' => [
					'user' => [ 1, 60 ], // one edit per minute
				],
			],
			'groups' => [
				'sysop', // sysop has the noratelimit permission set in the test case
			],
			'edits' => [
				[ 'item' => 'foo', 'label' => 'foo', 'ok' => true ],
				[ 'item' => 'bar', 'label' => 'bar', 'ok' => true ],
				[ 'item' => 'foo', 'label' => 'Foo', 'ok' => true ],
				[ 'item' => 'bar', 'label' => 'Bar', 'ok' => true ],
			],
		];

		yield 'per-group limit overrides with less restrictive limit' => [
			'limits' => [
				'edit' => [
					'user' => [ 1, 60 ], // one edit per minute
					'kittens' => [ 10, 60 ], // one edit per minute
				],
			],
			'groups' => [
				'kittens',
			],
			'edits' => [
				[ 'item' => 'foo', 'label' => 'foo', 'ok' => true ],
				[ 'item' => 'bar', 'label' => 'bar', 'ok' => true ],
				[ 'item' => 'foo', 'label' => 'Foo', 'ok' => true ],
				[ 'item' => 'bar', 'label' => 'Bar', 'ok' => true ],
			],
		];

		yield 'edit limit applies' => [
			'limits' => [
				'edit' => [
					'user' => [ 1, 60 ], // one edit per minute
				],
			],
			'groups' => [],
			'edits' => [
				[ 'item' => 'foo', 'label' => 'foo', 'ok' => true ],
				[ 'item' => 'foo', 'label' => 'Foo', 'ok' => false ],
			],
		];

		yield 'edit limit also applies to creations' => [
			'limits' => [
				'edit' => [
					'user' => [ 1, 60 ], // one edit per minute
				],
				'create' => [
					'user' => [ 10, 60 ], // ten creations per minute
				],
			],
			'groups' => [],
			'edits' => [
				[ 'item' => 'foo', 'label' => 'foo', 'ok' => true ],
				[ 'item' => 'bar', 'label' => 'bar', 'ok' => false ],
				[ 'item' => 'foo', 'label' => 'Foo', 'ok' => false ],
			],
		];

		yield 'creation limit applies in addition to edit limit' => [
			'limits' => [
				'edit' => [
					'user' => [ 10, 60 ], // ten edits per minute
				],
				'create' => [
					'user' => [ 1, 60 ], // ...but only one creation
				],
			],
			'groups' => [],
			'edits' => [
				[ 'item' => 'foo', 'label' => 'foo', 'ok' => true ],
				[ 'item' => 'foo', 'label' => 'Foo', 'ok' => true ],
				[ 'item' => 'bar', 'label' => 'bar', 'ok' => false ],
			],
		];
	}

	/**
	 * @dataProvider provideAttemptSaveRateLimit
	 */
	public function testAttemptSaveRateLimit( array $limits, array $groups, array $edits ) {
		$repo = $this->getMockRepository();

		$this->overrideConfigValue( MainConfigNames::RateLimits, $limits );
		$this->setGroupPermissions( [
			'*' => [ 'edit' => true ],
			'sysop' => [ 'noratelimit' => true ],
		] );

		$user = $this->getTestUser( $groups )->getUser();

		$items = [];
		$titleLookup = $this->getEntityTitleLookup();

		foreach ( $edits as $e ) {
			$name = $e[ 'item' ];
			$label = $e[ 'label' ];
			$expectedOK = $e[ 'ok' ];

			if ( !isset( $items[$name] ) ) {
				$items[$name] = new Item();
			}
			$item = $items[$name];

			$item->setLabel( 'en', $label );

			// Reset the limit cache in the User object, which would prevent bumping
			// the same limit twice one the same instance.
			$user->clearInstanceCache( $user->mFrom );

			$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user );
			$edit->attemptSave( $item, "testing", ( $item->getId() === null ? EDIT_NEW : EDIT_UPDATE ), false );

			$this->assertEquals( $expectedOK, $edit->getStatus()->isOK(), var_export( $edit->getStatus()->getErrorsArray(), true ) );
			$this->assertNotEquals( $expectedOK, $edit->hasError( EditEntity::RATE_LIMIT_ERROR ) );
		}
	}

	public static function provideIsTokenOk(): iterable {
		yield 'newly generated valid token' => [
			'token' => true, // replaced with actually valid token in test
			'shouldWork' => true,
		];
		yield 'invalid token' => [
			'token' => 'xyz',
			'shouldWork' => false,
		];
		yield 'empty token' => [
			'token' => '',
			'shouldWork' => false,
		];
		yield 'no token' => [
			'token' => null,
			'shouldWork' => false,
		];
	}

	/**
	 * @dataProvider provideIsTokenOk
	 */
	public function testIsTokenOk( $token, bool $shouldWork ): void {
		$repo = $this->getMockRepository();
		$user = $this->getTestUser()->getUser();

		$item = new Item();
		$titleLookup = $this->getEntityTitleLookup();
		$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user );

		// check valid token --------------------
		if ( $token === true ) {
			$token = $user->getEditToken();
		}

		$this->assertEquals( $shouldWork, $edit->isTokenOK( $token ) );

		$this->assertEquals( $shouldWork, $edit->getStatus()->isOK() );
		$this->assertNotEquals( $shouldWork, $edit->hasError( EditEntity::TOKEN_ERROR ) );
	}

	public static function provideAttemptSaveWatch(): iterable {
		yield 'watch new' => [
			'watchdefault' => true,
			'watchcreations' => true,
			'new' => true,
			'watched' => false,
			'watch' => null,
			'expected' => true,
		];
		yield 'override watch new' => [
			'watchdefault' => true,
			'watchcreations' => true,
			'new' => true,
			'watched' => false,
			'watch' => false,
			'expected' => false,
		];

		yield 'watch edit' => [
			'watchdefault' => true,
			'watchcreations' => true,
			'new' => false,
			'watched' => false,
			'watch' => null,
			'expected' => true,
		];
		yield 'override watch edit' => [
			'watchdefault' => true,
			'watchcreations' => true,
			'new' => false,
			'watched' => false,
			'watch' => false,
			'expected' => false,
		];

		yield 'don’t watch edit' => [
			'watchdefault' => false,
			'watchcreations' => false,
			'new' => false,
			'watched' => false,
			'watch' => null,
			'expected' => false,
		];
		yield 'override don’t watch edit' => [
			'watchdefault' => false,
			'watchcreations' => false,
			'new' => false,
			'watched' => false,
			'watch' => true,
			'expected' => true,
		];

		yield 'watch watched' => [
			'watchdefault' => false,
			'watchcreations' => false,
			'new' => false,
			'watched' => true,
			'watch' => null,
			'expected' => true,
		];
		yield 'override watch watched' => [
			'watchdefault' => false,
			'watchcreations' => false,
			'new' => false,
			'watched' => true,
			'watch' => false,
			'expected' => false,
		];
	}

	/**
	 * @dataProvider provideAttemptSaveWatch
	 */
	public function testAttemptSaveWatch(
		bool $watchdefault,
		bool $watchcreations,
		bool $new,
		bool $watched,
		?bool $watch,
		bool $expected
	): void {
		$repo = $this->getMockRepository();

		$user = $this->getTestUser()->getUser();

		if ( $user->getId() === 0 ) {
			$user->addToDatabase();
		}

		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'watchdefault', $watchdefault );
		$userOptionsManager->setOption( $user, 'watchcreations', $watchcreations );

		$item = new Item();
		$item->setLabel( "en", "Test" );

		if ( !$new ) {
			$repo->putEntity( $item );
			$repo->updateWatchlist( $user, $item->getId(), $watched );
		}

		$titleLookup = $this->getEntityTitleLookup();
		$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user );
		$status = $edit->attemptSave( $item, "testing", $new ? EDIT_NEW : EDIT_UPDATE, false, $watch );

		$this->assertTrue( $status->isOK(), "edit failed: " . $status->getWikiText() ); // sanity

		$this->assertEquals( $expected, $repo->isWatching( $user, $item->getId() ), "watched" );
	}

	public function testAttemptSaveUnresolvedRedirect() {
		$repo = $this->getMockRepository();

		$user = $this->getTestUser()->getUser();

		if ( $user->getId() === 0 ) {
			$user->addToDatabase();
		}

		$item = new Item( new ItemId( 'Q302' ) );
		$item->setLabel( "en", "Test" );

		$titleLookup = $this->getEntityTitleLookup();
		$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user );
		$status = $edit->attemptSave( $item, "testing", EDIT_UPDATE, false );

		$this->assertFalse( $status->isOK() );
		$this->assertSame(
			'(wikibase-save-unresolved-redirect: Q302, Q404)',
			$status->getWikiText( null, null, 'qqx' )
		);
	}

	public function testIsNew() {
		$repo = $this->getMockRepository();
		$titleLookup = $this->getEntityTitleLookup();
		$item = new Item();
		$user = $this->getTestUser()->getUser();

		$isNew = new ReflectionMethod( MediaWikiEditEntity::class, 'isNew' );

		$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user );
		$this->assertTrue( $isNew->invoke( $edit ), 'New entity: No id' );

		$repo->assignFreshId( $item );
		$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user );
		$this->assertTrue( $isNew->invoke( $edit ), "New entity: Has an id, but doesn't exist, yet" );

		$repo->saveEntity( $item, 'testIsNew', $user );
		$edit = $this->makeEditEntity( $repo, $item->getId(), $titleLookup, $user );
		$this->assertFalse( $isNew->invoke( $edit ), "Entity exists" );
	}

	public static function provideHookRunnerReturnStatus(): iterable {
		yield 'good' => [ Status::newGood() ];
		yield 'fatal' => [ Status::newFatal( 'OMG' ) ];
	}

	/**
	 * @dataProvider provideHookRunnerReturnStatus
	 */
	public function testEditFilterHookRunnerInteraction( Status $hookReturnStatus ) {
		$user = $this->getTestUser()->getUser();
		$edit = $this->makeEditEntity(
			$this->getMockRepository(),
			null,
			$this->getEntityTitleLookup(),
			$user,
			0,
			null,
			$this->getMockEditFitlerHookRunner( $hookReturnStatus, $this->once() )
		);

		$saveStatus = $edit->attemptSave(
			new Item(),
			'some Summary',
			EDIT_MINOR,
			$user->getEditToken()
		);

		$this->assertEquals( $hookReturnStatus->isGood(), $saveStatus->isGood() );
	}

	public function testSaveWithTags() {
		$repo = $this->getMockRepository();
		$user = $this->getTestUser()->getUser();
		$edit = $this->makeEditEntity(
			$repo,
			null,
			$this->getEntityTitleLookup(),
			$user
		);

		$status = $edit->attemptSave(
			new Item(),
			'summary',
			EDIT_MINOR,
			$user->getEditToken(),
			null,
			[ 'mw-replace' ]
		);

		$this->assertStatusGood( $status );
		$entityRevision = $status->getRevision();
		$tags = $repo->getLogEntry( $entityRevision->getRevisionId() )['tags'];
		$this->assertSame( [ 'mw-replace' ], $tags );
	}

	public function testSaveWithAnonymousUser(): void {
		$tempUserCreator = $this->createMock( TempUserCreator::class );
		$tempUserCreator->method( 'shouldAutoCreate' )->willReturn( false );
		$this->setService( 'TempUserCreator', $tempUserCreator );
		$repo = $this->getMockRepository();
		$services = $this->getServiceContainer();
		$user = $services->getUserFactory()->newAnonymous();
		$edit = $this->makeEditEntity(
			$repo,
			null,
			$this->getEntityTitleLookup(),
			$user
		);

		$status = $edit->attemptSave(
			new Item(),
			'summary',
			0,
			$user->getEditToken()
		);
		$this->assertStatusGood( $status );
		$entityRevision = $status->getRevision();
		$editWasMadeByUser = $repo->userWasLastToEdit(
			$user,
			$entityRevision->getEntity()->getId(),
			$entityRevision->getRevisionId()
		);
		$this->assertTrue( $editWasMadeByUser, 'edit should have been made by $user' );
		$this->assertNull( $status->getValue()['savedTempUser'], 'no savedTempUser' );
		$this->assertSame( $user, $status->getValue()['context']->getUser() );
	}

	public function testSaveWithTempUser(): void {
		$tempUserCreator = $this->createMock( TempUserCreator::class );
		$tempUserCreator->method( 'shouldAutoCreate' )->willReturn( true );
		$tempUser = $this->getTestUser()->getUser();
		$tempUserCreator->method( 'create' )->willReturn( CreateStatus::newGood( $tempUser ) );
		$this->setService( 'TempUserCreator', $tempUserCreator );
		$repo = $this->getMockRepository();
		$services = $this->getServiceContainer();
		$anonUser = $services->getUserFactory()->newAnonymous();
		$edit = $this->makeEditEntity(
			$repo,
			null,
			$this->getEntityTitleLookup(),
			$anonUser
		);
		$originalContext = TestingAccessWrapper::newFromObject( $edit )->context;

		$status = $edit->attemptSave(
			new Item(),
			'summary',
			0,
			$anonUser->getEditToken()
		);
		$this->assertStatusGood( $status );
		$entityRevision = $status->getRevision();
		$entityId = $entityRevision->getEntity()->getId();
		$revisionId = $entityRevision->getRevisionId();
		$editWasMadeByAnonUser = $repo->userWasLastToEdit( $anonUser, $entityId, $revisionId );
		$editWasMadeByTempUser = $repo->userWasLastToEdit( $tempUser, $entityId, $revisionId );
		$this->assertTrue( $editWasMadeByTempUser, 'edit should have been made by $tempUser' );
		$this->assertFalse( $editWasMadeByAnonUser, 'edit should not have been made by $anonUser' );
		$this->assertSame( $tempUser, $status->getSavedTempUser() );
		$context = $status->getContext();
		$this->assertNotSame( $originalContext, $context );
		$this->assertSame( $anonUser, $originalContext->getUser() );
		$this->assertSame( $tempUser, $context->getUser() );
	}

}
