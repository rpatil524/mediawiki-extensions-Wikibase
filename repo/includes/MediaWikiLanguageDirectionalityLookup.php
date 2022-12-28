<?php

namespace Wikibase\Repo;

use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use Wikibase\View\LanguageDirectionalityLookup;

/**
 * Service for looking up language directionalities based on MediaWiki's Language
 * class.
 *
 * @license GPL-2.0-or-later
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
class MediaWikiLanguageDirectionalityLookup implements LanguageDirectionalityLookup {

	private LanguageFactory $languageFactory;

	private LanguageNameUtils $languageNameUtils;

	public function __construct(
		LanguageFactory $languageFactory = null,
		LanguageNameUtils $languageNameUtils = null
	) {
		$this->languageFactory = $languageFactory ?? MediaWikiServices::getInstance()->getLanguageFactory();
		$this->languageNameUtils = $languageNameUtils ?? MediaWikiServices::getInstance()->getLanguageNameUtils();
	}

	/**
	 * @see LanguageDirectionalityLookup::getDirectionality
	 *
	 * @param string $languageCode
	 *
	 * @return string|null 'ltr', 'rtl' or null if unknown
	 */
	public function getDirectionality( $languageCode ) {
		if ( !$this->languageNameUtils->isValidCode( $languageCode ) ) {
			return null;
		}

		$lang = $this->languageFactory->getLanguage( $languageCode );
		return $lang->getDir();
	}

}
