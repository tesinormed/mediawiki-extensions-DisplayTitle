<?php

namespace MediaWiki\Extension\DisplayTitle;

use HtmlArmor;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Disambiguator\Lookup;
use MediaWiki\Page\PageProps;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;

class DisplayTitleService {
	public const CONSTRUCTOR_OPTIONS = [
		'DisplayTitleFollowRedirects',
	];

	private bool $followRedirects;
	private RedirectLookup $redirectLookup;
	private PageProps $pageProps;
	private WikiPageFactory $wikiPageFactory;
	private Lookup $disambiguatorLookup;

	public function __construct(
		ServiceOptions $options,
		RedirectLookup $redirectLookup,
		PageProps $pageProps,
		WikiPageFactory $wikiPageFactory,
		Lookup $disambiguatorLookup
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->followRedirects = $options->get( 'DisplayTitleFollowRedirects' );
		$this->redirectLookup = $redirectLookup;
		$this->pageProps = $pageProps;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->disambiguatorLookup = $disambiguatorLookup;
	}

	/**
	 * Determines link text for self-links and standard links.
	 * If a link is customized by a user (e.g. [[Target|Text]])
	 * it should remain intact. Let us assume a link is not customized if its
	 * html is the prefixed or (to support Semantic MediaWiki queries)
	 * non-prefixed title of the target page.
	 *
	 * @param Title $currentTitle the Title object of the current page
	 * @param Title $target the Title object that the link is pointing to
	 * @param HtmlArmor|string &$html the HTML of the link text
	 * @param bool $wrap whether to wrap result in HtmlArmor
	 * @since 1.3
	 */
	public function handleLink( Title $currentTitle, Title $target, HtmlArmor|string &$html, bool $wrap ): void {
		// Do not use DisplayTitle if the current page is a disambiguation page
		if ( $this->disambiguatorLookup->isDisambiguationPage( $currentTitle ) ) {
			return;
		}

		// Do not use DisplayTitle if the current page is not a content page
		if ( !$currentTitle->isContentPage() ) {
			return;
		}

		// Do not use DisplayTitle if the current page is a redirect to the page being linked
		$wikiPage = $this->wikiPageFactory->newFromTitle( $currentTitle );
		$redirectTarget = $this->redirectLookup->getRedirectTarget( $wikiPage );
		if ( $redirectTarget !== null && $currentTitle === $target->getPrefixedText() ) {
			return;
		}

		$text = $html;
		if ( $text instanceof HtmlArmor ) {
			$text = HtmlArmor::getHtml( $text );
			// Remove html tags used for highlighting matched words in the title, see T355481
			$text = strip_tags( $text );
		}
		$text = str_replace( '_', ' ', $text );

		if ( $text === $target->getPrefixedText() || $text === $target->getSubpageText() ) {
			$this->getDisplayTitle( $target, $html, $wrap );
		}
	}

	/**
	 * Get displaytitle page property text.
	 *
	 * @param Title $title the Title object for the page
	 * @param HtmlArmor|string &$displaytitle to return the display title, if set
	 * @param bool $wrap whether to wrap result in HtmlArmor
	 * @return bool true if the page has a different display title
	 * @since 1.0
	 */
	public function getDisplayTitle( Title $title, HtmlArmor|string &$displaytitle, bool $wrap = false ): bool {
		$title = $title->createFragmentTarget( '' );
		if ( !$title->canExist() ) {
			// If the Title isn't a valid content page (e.g. Special:UserLogin), just return.
			return false;
		}

		$originalPageName = $title->getPrefixedText();
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );

		$redirect = false;
		if ( $this->followRedirects ) {
			$redirectTarget = $this->redirectLookup->getRedirectTarget( $wikiPage );
			if ( $redirectTarget !== null ) {
				$redirect = true;
				$title = Title::newFromLinkTarget( $redirectTarget );
			}
		}

		$id = $title->getArticleID();
		$values = $this->pageProps->getProperties( $title, 'displaytitle' );
		if ( array_key_exists( $id, $values ) ) {
			$value = $values[$id];
			if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== ''
				&& $value !== $originalPageName ) {
				$displaytitle = $value;
				if ( $wrap ) {
					// @phan-suppress-next-line SecurityCheck-XSS
					$displaytitle = new HtmlArmor( $displaytitle );
				}
				return true;
			}
		} elseif ( $redirect ) {
			$displaytitle = $title->getPrefixedText();
			if ( $wrap ) {
				$displaytitle = new HtmlArmor( $displaytitle );
			}
			return true;
		}

		return false;
	}
}
