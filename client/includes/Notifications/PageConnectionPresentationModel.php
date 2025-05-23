<?php

namespace Wikibase\Client\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Title\Title;
use Wikibase\Client\Hooks\EchoNotificationsHandlers;

/**
 * Presentation model for Echo notifications
 *
 * @license GPL-2.0-or-later
 * @author Matěj Suchánek
 */
class PageConnectionPresentationModel extends EchoEventPresentationModel {

	/**
	 * @param Event $event
	 *
	 * @return string|null
	 */
	public function callbackForBundleCount( Event $event ) {
		$title = $event->getTitle();
		if ( $title !== null ) {
			return $title->getPrefixedText();
		}
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return EchoNotificationsHandlers::NOTIFICATION_TYPE;
	}

	/**
	 * @inheritDoc
	 */
	public function canRender() {
		$title = $this->event->getTitle();
		return $title && $title->exists();
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessage() {
		$count = $this->getNotificationCountForOutput(
			false, // we need only other pages count
			[ $this, 'callbackForBundleCount' ]
		);

		$msgKey = $count > 0 ? "notification-bundle-header-{$this->type}" : "notification-header-{$this->type}";
		if ( $this->event->getAgent() !== null ) {
			$msg = $this->getMessageWithAgent( $msgKey );
		} else {
			// $1 is unused
			// $2 is for GENDER, force the site/language default
			$msg = $this->msg( $msgKey )->params( '', '[]' );
		}

		$truncated = $this->getTruncatedTitleText( $this->event->getTitle(), true );
		$msg->params( $truncated );

		if ( $count > 0 ) {
			$msg->numParams( $count );
		} else {
			// Old events did not have this parameter. Default to -1 for the PLURAL function.
			$msg->params( $this->event->getExtraParam( 'entity', -1 ) );
		}

		return $msg;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubjectMessageKey() {
		return "notification-subject-{$this->type}";
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryLink() {
		$title = $this->event->getTitle();
		return [
			'url' => $title->getFullURL(),
			'label' => $title->getFullText(),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		$ret = [];

		if ( $this->getBundleCount( true, [ $this, 'callbackForBundleCount' ] ) === 1 ) {
			$ret[] = $this->getAgentLink();
			$ret[] = [
				'url' => $this->event->getExtraParam( 'url' ),
				'label' => $this->msg(
					'notification-link-text-view-item',
					$this->getViewingUserForGender()
					)->text(),
				'description' => '',
				'icon' => 'changes',
				'prioritized' => true,
			];
		}

		$message = $this->msg( 'notification-page-connection-link',
			$this->event->getExtraParam( 'repoSiteName' ) );
		if ( !$message->isDisabled() ) {
			$title = Title::newFromText( $message->plain() );
			if ( $title && $title->exists() ) {
				$ret[] = [
					'url' => $title->getFullURL(),
					'label' => $this->msg( 'echo-learn-more' )->text(),
					'description' => '',
					'icon' => 'help',
					'prioritized' => false,
				];
			}
		}

		return $ret;
	}

}
