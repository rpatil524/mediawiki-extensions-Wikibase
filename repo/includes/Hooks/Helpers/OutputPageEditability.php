<?php

namespace Wikibase\Repo\Hooks\Helpers;

use MediaWiki\Output\OutputPage;

/**
 * Determined (likely) editability of an OutputPage by inspecting this god object's properties.
 * Most things feel like they should be preconfigured properties but are only known on call
 * time as this is used in a hook.
 *
 * @license GPL-2.0-or-later
 */
class OutputPageEditability {

	public function validate( OutputPage $out ): bool {
		return $out->getAuthority()->probablyCan( 'edit', $out->getTitle() )
			&& $this->isEditView( $out );
	}

	/**
	 * This is mostly a duplicate of
	 * @see \Wikibase\Repo\Actions\ViewEntityAction::isEditable()
	 */
	private function isEditView( OutputPage $out ): bool {
		return $this->isLatestRevision( $out )
			&& !$this->isDiff( $out )
			&& !$out->isPrintable();
	}

	private function isDiff( OutputPage $out ): bool {
		return $out->getRequest()->getCheck( 'diff' );
	}

	private function isLatestRevision( OutputPage $out ): bool {
		return !$out->getRevisionId() // the revision id can be null on a ParserCache hit, but only for the latest revision
			|| $out->getRevisionId() === $out->getTitle()->getLatestRevID();
	}

}
