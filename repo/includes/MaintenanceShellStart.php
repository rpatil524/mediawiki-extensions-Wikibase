<?php

use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;

// phpcs:disable Squiz.Functions.GlobalFunction.Found
// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingReturn
// phpcs:disable MediaWiki.NamingConventions.PrefixedGlobalFunctions.allowedPrefix

if ( !function_exists( 'mws' ) ) {
	/** @phan-suppress-next-line PhanRedefineFunction guarded by function_exists */
	function mws() {
		return MediaWikiServices::getInstance();
	}
}

class_alias( WikibaseRepo::class, 'wbr' );
