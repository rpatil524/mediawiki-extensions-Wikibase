<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Infrastructure\DataAccess;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\Repo\RestApi\Domain\ReadModel\SiteLink;
use Wikibase\Repo\RestApi\Domain\ReadModel\SiteLinks;
use Wikibase\Repo\RestApi\Domain\Services\SiteLinkRetriever;
use Wikibase\Repo\RestApi\Domain\Services\SiteLinksRetriever;
use Wikibase\Repo\RestApi\Infrastructure\SiteLinksReadModelConverter;

/**
 * @license GPL-2.0-or-later
 */
class SiteLinkLookupSiteLinksRetriever implements SiteLinksRetriever, SiteLinkRetriever {

	private SiteLinkLookup $siteLinkLookup;
	private SiteLinksReadModelConverter $siteLinksReadModelConverter;

	public function __construct(
		SiteLinkLookup $siteLinkLookup,
		SiteLinksReadModelConverter $siteLinksReadModelConverter
	) {
		$this->siteLinkLookup = $siteLinkLookup;
		$this->siteLinksReadModelConverter = $siteLinksReadModelConverter;
	}

	public function getSiteLinks( ItemId $itemId ): SiteLinks {
		$siteLinksArray = $this->siteLinkLookup->getSiteLinksForItem( $itemId );

		return $this->siteLinksReadModelConverter->convert( new SiteLinkList( $siteLinksArray ) );
	}

	public function getSiteLink( ItemId $itemId, string $site ): ?SiteLink {
		foreach ( $this->getSiteLinks( $itemId ) as $siteLink ) {
			if ( $siteLink->getSite() === $site ) {
				return $siteLink;
			}
		}

		return null;
	}
}