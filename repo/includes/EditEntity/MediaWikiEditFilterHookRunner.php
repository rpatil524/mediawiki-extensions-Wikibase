<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\EditEntity;

use InvalidArgumentException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use RuntimeException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityRedirect;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Repo\Content\EntityContent;
use Wikibase\Repo\Content\EntityContentFactory;
use Wikibase\Repo\Hooks\WikibaseRepoHookRunner;
use Wikibase\Repo\Store\EntityTitleStoreLookup;

/**
 * Class to run the MediaWiki EditFilterMergedContent hook.
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class MediaWikiEditFilterHookRunner implements EditFilterHookRunner {

	private EntityNamespaceLookup $namespaceLookup;
	private EntityTitleStoreLookup $titleLookup;
	private EntityContentFactory $entityContentFactory;
	private HookContainer $hookContainer;
	private WikibaseRepoHookRunner $hookRunner;

	public function __construct(
		EntityNamespaceLookup $namespaceLookup,
		EntityTitleStoreLookup $titleLookup,
		EntityContentFactory $entityContentFactory,
		HookContainer $hookContainer
	) {
		$this->namespaceLookup = $namespaceLookup;
		$this->titleLookup = $titleLookup;
		$this->entityContentFactory = $entityContentFactory;
		$this->hookContainer = $hookContainer;
		$this->hookRunner = new WikibaseRepoHookRunner( $hookContainer );
	}

	/**
	 * Call EditFilterMergedContent hook, if registered.
	 *
	 * @param EntityDocument|EntityRedirect|EntityContent|null $new The entity or redirect (content) we are trying to save
	 * @param IContextSource $context The request context for the edit
	 * @param string $summary The edit summary
	 *
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 * @return Status
	 */
	public function run( $new, IContextSource $context, string $summary ) {
		$filterStatus = Status::newGood();

		if ( !$this->hookContainer->isRegistered( 'EditFilterMergedContent' ) ) {
			return $filterStatus;
		}

		if ( $new instanceof EntityDocument ) {
			$entityContent = $this->entityContentFactory->newFromEntity( $new );
			$entityType = $new->getType();
			$context = $this->getContextForEditFilter(
				$context,
				$new->getId(),
				$entityType
			);

		} elseif ( $new instanceof EntityRedirect ) {
			$entityContent = $this->entityContentFactory->newFromRedirect( $new );
			if ( $entityContent === null ) {
				throw new RuntimeException(
					'Cannot get EntityContent from EntityRedirect of type ' .
					$new->getEntityId()->getEntityType()
				);
			}

			$entityId = $new->getEntityId();
			$entityType = $entityId->getEntityType();

			$context = $this->getContextForEditFilter(
				$context,
				$entityId,
				$entityType
			);
		} elseif ( $new instanceof EntityContent ) {
			$entityContent = $new;
			$entityId = $entityContent->getEntityId();
			$entityType = $entityId->getEntityType();

			$context = $this->getContextForEditFilter(
				$context,
				$entityId,
				$entityType
			);
		} else {
			throw new InvalidArgumentException( '$new must be instance of EntityDocument, EntityRedirect or EntityContent' );
		}

		$slotRole = $this->namespaceLookup->getEntitySlotRole( $entityType );

		if ( !$this->hookRunner->onEditFilterMergedContent(
			$context, $entityContent, $filterStatus, $summary, $context->getUser(), false, $slotRole
		) ) {
			// Error messages etc. were handled inside the hook.
			$filterStatus->setResult( false, $filterStatus->getValue() );
		}

		return $filterStatus;
	}

	private function getContextForEditFilter(
		IContextSource $context,
		?EntityId $entityId,
		string $entityType
	): IContextSource {
		$context = new DerivativeContext( $context );
		if ( $entityId !== null ) {
			$title = $this->titleLookup->getTitleForId( $entityId );
		} else {
			$title = null;
		}
		if ( $title === null ) {
			// This constructs a "fake" title of the form Property:NewProperty,
			// where the title text is assumed to be name of the special page used
			// to create entities of the given type. This is used by the
			// HtmlPageLinkRendererEndHookHandler::internalDoHtmlPageLinkRendererEnd to replace
			// the link to the fake title with a link to the respective special page.
			// The effect is that e.g. the AbuseFilter log will show a link to
			// "Special:NewProperty" instead of "Property:NewProperty", while
			// the AbuseFilter itself will get a Title object with the correct
			// namespace IDs for Property entities.
			$namespace = $this->namespaceLookup->getEntityNamespace( $entityType );
			$title = Title::makeTitle( $namespace, 'New' . ucfirst( $entityType ) );
		}

		$context->setTitle( $title );

		return $context;
	}

}
