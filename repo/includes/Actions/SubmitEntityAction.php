<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Actions;

use Article;
use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\Watchlist\WatchlistManager;
use Wikibase\Repo\AnonymousEditWarningBuilder;
use Wikibase\Repo\Content\EntityContent;
use Wikibase\Repo\Diff\EntityDiffVisualizerFactory;
use Wikibase\Repo\EditEntity\EditFilterHookRunner;
use Wikibase\Repo\SummaryFormatter;

/**
 * Handles the submit action for Wikibase entities.
 * This performs the undo and restore operations when requested.
 * Otherwise it will just show the normal entity view.
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Jens Ohlig
 * @author Daniel Kinzler
 */
class SubmitEntityAction extends EditEntityAction {

	/**
	 * {@link ObjectFactory} specification for this class,
	 * to be returned by {@link EntityHandler::getActionOverrides()} implementations.
	 */
	public const SPEC = [
		'class' => self::class,
		'services' => [
			'PermissionManager',
			'RevisionLookup',
			'UserOptionsLookup',
			'WatchlistManager',
			'WikiPageFactory',
			'WikibaseRepo.AnonymousEditWarningBuilder',
			'WikibaseRepo.EditFilterHookRunner',
			'WikibaseRepo.EntityDiffVisualizerFactory',
			'WikibaseRepo.SummaryFormatter',
		],
	];

	private UserOptionsLookup $userOptionsLookup;
	private WatchlistManager $watchlistManager;
	private WikiPageFactory $wikiPageFactory;
	private EditFilterHookRunner $editFilterHookRunner;

	public function __construct(
		Article $article,
		IContextSource $context,
		PermissionManager $permissionManager,
		RevisionLookup $revisionLookup,
		UserOptionsLookup $userOptionsLookup,
		WatchlistManager $watchlistManager,
		WikiPageFactory $wikiPageFactory,
		AnonymousEditWarningBuilder $anonymousEditWarningBuilder,
		EditFilterHookRunner $editFilterHookRunner,
		EntityDiffVisualizerFactory $entityDiffVisualizerFactory,
		SummaryFormatter $summaryFormatter
	) {
		parent::__construct(
			$article,
			$context,
			$permissionManager,
			$revisionLookup,
			$anonymousEditWarningBuilder,
			$entityDiffVisualizerFactory,
			$summaryFormatter
		);

		$this->userOptionsLookup = $userOptionsLookup;
		$this->watchlistManager = $watchlistManager;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->editFilterHookRunner = $editFilterHookRunner;
	}

	public function getName(): string {
		return 'submit';
	}

	public function doesWrites(): bool {
		return true;
	}

	/**
	 * Show the entity using parent::show(), unless an undo operation is requested.
	 * In that case $this->undo(); is called to perform the action after a permission check.
	 */
	public function show(): void {
		$request = $this->getRequest();

		if ( $request->getCheck( 'undo' ) || $request->getCheck( 'undoafter' ) || $request->getCheck( 'restore' ) ) {
			if ( $this->showPermissionError( 'read' ) || $this->showPermissionError( 'edit' ) ) {
				return;
			}

			$this->undo();
			return;
		}

		parent::show();
	}

	/**
	 * Perform the undo operation specified by the web request.
	 */
	public function undo(): void {
		$request = $this->getRequest();
		$undidRevId = $request->getInt( 'undo' );
		$undidAfterRevId = $request->getInt( 'undoafter' );
		$restoreId = $request->getInt( 'restore' );
		$title = $this->getTitle();

		if ( !$request->wasPosted() || !$request->getCheck( 'wpSave' ) ) {
			$args = [ 'action' => 'edit' ];

			if ( $undidRevId !== 0 ) {
				$args['undo'] = $undidRevId;
			}

			if ( $undidAfterRevId !== 0 ) {
				$args['undoafter'] = $undidAfterRevId;
			}

			if ( $restoreId !== 0 ) {
				$args['restore'] = $restoreId;
			}

			$undoUrl = $title->getLocalURL( $args );
			$this->getOutput()->redirect( $undoUrl );
			return;
		}

		$revisions = $this->loadRevisions();
		if ( !$revisions->isOK() ) {
			$this->showUndoErrorPage( $revisions );
			return;
		}

		/**
		 * @var RevisionRecord $olderRevision
		 * @var RevisionRecord $newerRevision
		 * @var RevisionRecord $latestRevision
		 */
		[ $olderRevision, $newerRevision, $latestRevision ] = $revisions->getValue();
		$patchedContent = $this->getPatchContent( $olderRevision, $newerRevision, $latestRevision );
		if ( !$patchedContent->isOK() ) {
			$this->showUndoErrorPage( $patchedContent );
			return;
		}
		$latestContent = $latestRevision->getContent( SlotRecord::MAIN );

		if ( $patchedContent->getValue()->equals( $latestContent ) ) {
			$status = Status::newGood();
			$status->warning( 'wikibase-empty-undo' );
		} else {
			$summary = $request->getText( 'wpSummary' );

			if ( $request->getCheck( 'restore' ) ) {
				$summary = $this->makeSummary(
					'restore',
					$olderRevision,
					$summary
				);
			} else {
				$summary = $this->makeSummary(
					'undo',
					$newerRevision,
					$summary
				);
			}

			$editToken = $request->getText( 'wpEditToken' );
			$status = $this->attemptSave( $title, $patchedContent->getValue(), $summary,
				$undidRevId, $undidAfterRevId ?: $restoreId, $editToken );
		}

		if ( $status->isOK() ) {
			$this->getOutput()->redirect( $title->getFullURL() );
		} else {
			$this->showUndoErrorPage( $status );
		}
	}

	/**
	 * @return Status containing EntityContent
	 */
	private function getPatchContent(
		RevisionRecord $olderRevision,
		RevisionRecord $newerRevision,
		RevisionRecord $latestRevision
	): Status {
		/**
		 * @var EntityContent $olderContent
		 * @var EntityContent $newerContent
		 * @var EntityContent $latestContent
		 */
		$olderContent = $olderRevision->getContent( SlotRecord::MAIN );
		$newerContent = $newerRevision->getContent( SlotRecord::MAIN );
		$latestContent = $latestRevision->getContent( SlotRecord::MAIN );
		'@phan-var EntityContent $olderContent';
		'@phan-var EntityContent $newerContent';
		'@phan-var EntityContent $latestContent';

		if ( $newerContent->isRedirect() !== $latestContent->isRedirect() ) {
			return Status::newFatal( $latestContent->isRedirect()
				? 'wikibase-undo-redirect-latestredirect'
				: 'wikibase-undo-redirect-latestnoredirect' );
		}

		return Status::newGood( $latestContent->getPatchedCopy( $newerContent->getDiff( $olderContent ) ) );
	}

	public function execute(): void {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new LogicException( 'Not applicable.' );
	}

	private function attemptSave(
		Title $title,
		EntityContent $content,
		string $summary,
		int $undidRevId,
		int $originalRevId,
		string $editToken
	): Status {
		$status = $this->getEditTokenStatus( $editToken );

		if ( !$status->isOK() ) {
			return $status;
		}

		$status = $this->getPermissionStatus( 'edit', $title );

		if ( !$status->isOK() ) {
			return $status;
		}

		$status = $this->editFilterHookRunner->run( $content, $this->getContext(), $summary );

		if ( !$status->isOK() ) {
			return $status;
		}

		// save edit
		$page = $this->wikiPageFactory->newFromTitle( $title );

		// NOTE: Constraint checks are performed automatically via EntityHandler::validateSave.
		$status = $page->doUserEditContent(
			$content,
			$this->getUser(),
			$summary,
			/* flags */ 0,
			$originalRevId ?: false,
			/* tags */ [],
			$undidRevId
		);

		if ( !$status->isOK() ) {
			return $status;
		}

		$this->doWatch( $title );

		return $status;
	}

	/**
	 * Checks the given permission.
	 *
	 * @return Status a status object representing the check's result.
	 */
	private function getPermissionStatus(
		string $permission,
		Title $title,
		string $rigor = PermissionManager::RIGOR_SECURE
	): Status {
		$errors = $this->permissionManager
			->getPermissionErrors( $permission, $this->getUser(), $title, $rigor );
		$status = Status::newGood();

		foreach ( $errors as $error ) {
			$status->fatal( ...$error );
			$status->setResult( false );
		}

		return $status;
	}

	/**
	 * Checks that the given token is valid.
	 */
	private function getEditTokenStatus( string $editToken ): Status {
		$status = Status::newGood();
		$user = $this->getUser();
		if ( !$user->matchEditToken( $editToken ) ) {
			$status = Status::newFatal( 'session_fail_preview' );
		}
		return $status;
	}

	/**
	 * Update watchlist.
	 */
	private function doWatch( Title $title ): void {
		$user = $this->getUser();

		if ( $user->isRegistered()
			&& $this->userOptionsLookup->getOption( $user, 'watchdefault' )
			&& !$this->watchlistManager->isWatchedIgnoringRights( $user, $title )
		) {
			$this->watchlistManager->addWatchIgnoringRights( $user, $title );
		}
	}

}
