<?php

namespace MediaWiki\Extension\DisplayTitle;

use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;

class DisplayTitleHooks implements ParserFirstCallInitHook {
	private DisplayTitleService $displayTitleService;

	public function __construct( DisplayTitleService $displayTitleService ) {
		$this->displayTitleService = $displayTitleService;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setFunctionHook( 'getdisplaytitle', [ $this, 'onParserFunctionHook' ] );
	}

	public function onParserFunctionHook( Parser $parser, string $pagename ): string {
		$title = Title::newFromText( $pagename );

		if ( $title !== null ) {
			$this->displayTitleService->getDisplayTitle( $title, $pagename );
		}

		return $pagename;
	}
}
