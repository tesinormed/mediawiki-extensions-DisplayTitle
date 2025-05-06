<?php

namespace MediaWiki\Extension\DisplayTitle;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;

return [
	'DisplayTitleService' =>
		static function ( MediaWikiServices $services ): DisplayTitleService {
			return new DisplayTitleService(
				new ServiceOptions(
					DisplayTitleService::CONSTRUCTOR_OPTIONS,
					$services->getConfigFactory()->makeConfig( 'displaytitle' )
				),
				$services->getRedirectLookup(),
				$services->getPageProps(),
				$services->getWikiPageFactory(),
				$services->getService( 'DisambiguatorLookup' )
			);
		},
];
