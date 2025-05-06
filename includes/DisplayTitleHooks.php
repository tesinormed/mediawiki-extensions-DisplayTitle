<?php

namespace MediaWiki\Extension\DisplayTitle;

use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SelfLinkBeginHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

class DisplayTitleHooks implements
	ParserFirstCallInitHook,
	HtmlPageLinkRendererEndHook,
	OutputPageParserOutputHook,
	SelfLinkBeginHook
{
	private DisplayTitleService $displayTitleService;
	private NamespaceInfo $namespaceInfo;

	public function __construct(
		DisplayTitleService $displayTitleService,
		NamespaceInfo $namespaceInfo
	) {
		$this->displayTitleService = $displayTitleService;
		$this->namespaceInfo = $namespaceInfo;
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

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/HtmlPageLinkRendererEnd
	 * @inheritDoc
	 */
	public function onHtmlPageLinkRendererEnd( $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ): void {
		$title = RequestContext::getMain()->getTitle();
		if ( $title === null ) {
			return;
		}

		$this->displayTitleService->handleLink(
			$title,
			Title::newFromLinkTarget( $target ),
			$text,
			wrap: true
		);
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SelfLinkBegin
	 * @inheritDoc
	 */
	public function onSelfLinkBegin( $nt, &$html, &$trail, &$prefix, &$ret ): void {
		$this->displayTitleService->handleLink(
			$nt,
			$nt,
			$html,
			wrap: false
		);
	}

	/**
	 * Implements OutputPageParserOutput hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 * Handle talk page title.
	 *
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 * @since 1.0
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( $title === null || !$title->isTalkPage() ) {
			return;
		}

		$subjectPage = Title::castFromLinkTarget( $this->namespaceInfo->getSubjectPage( $title ) );
		if ( $subjectPage === null || !$subjectPage->exists() ) {
			return;
		}

		if ( !$this->displayTitleService->getDisplayTitle( $subjectPage, $displaytitle ) ) {
			return;
		}

		$parserOutput->setTitleText( wfMessage( 'displaytitle-talkpagetitle', $displaytitle )->plain() );
	}
}
