<?php

// MediaWiki defines these via extension.json at load time.
// The guards here satisfy static analysis and make the values explicit.
defined( 'NS_TEST' )   || define( 'NS_TEST',   3000 );
defined( 'NS_LIT' )    || define( 'NS_LIT',    3002 );
defined( 'NS_TODO' )   || define( 'NS_TODO',   3004 );
defined( 'NS_FAQ' )    || define( 'NS_FAQ',    3006 );
defined( 'NS_EX' )     || define( 'NS_EX',     3008 );
defined( 'NS_DRAFT' )  || define( 'NS_DRAFT',  3010 );

class DanteTabsHooks {

	/**  
	 * Returns the Dante namespace ID → display name map.
	 * Keyed by PHP constant value so callers don't need to repeat it.
	 * NOTE: This function determines the visibility of the tabs in the UI - we do not want Test to show up all the time
	 */
	private static function getDanteNamespaces(): array {
		return [
			NS_DRAFT  => 'Draft',
			NS_TODO   => 'Todo',
			NS_LIT    => 'Lit',
			NS_FAQ    => 'Faq',
			NS_EX     => 'Ex',
		];
	}

	/**
	 * Adds one tab per Dante namespace to every article and Dante-namespace page.
	 *
	 * The tab for the namespace the viewer is currently in is marked selected.
	 * Tabs link to the page of the same (DB-key) name in the respective namespace.
	 */
	public static function onSkinTemplateNavigationUniversal(
		SkinTemplate $sktemplate,
		array &$links
	): void {
		$title     = $sktemplate->getTitle();
		$currentNs = $title->getNamespace();
		$baseName  = $title->getDBkey();

		$danteNamespaces = self::getDanteNamespaces();

		// Activate for NS_MAIN, all Dante content namespaces, and their talk counterparts.
		$talkNamespaces     = array_merge( [ NS_TALK ], array_map( fn( $id ) => $id + 1, array_keys( $danteNamespaces ) ) );
		$relevantNamespaces = array_merge( [ NS_MAIN ], array_keys( $danteNamespaces ), $talkNamespaces );
		if ( !in_array( $currentNs, $relevantNamespaces, true ) ) { return; }

		// Keep only talk tabs from MediaWiki's output — subject tabs are rebuilt by us.
		// Filtering by context is key-independent and works regardless of MW version.
		// Rebuild with fixed order: Page | Discussion | Dante tabs
		$newTabs = [];

		// 1. Page (NS_MAIN) — always first
		$mainTitle  = Title::makeTitle( NS_MAIN, $baseName );
		$mainExists = $mainTitle->isKnown();
		$mainHref   = $mainExists ? $mainTitle->getLinkURL() : $mainTitle->getLinkURL( [ 'action' => 'edit', 'redlink' => '1' ] );
		$mainClass  = ( $currentNs === NS_MAIN ) ? 'selected' : ( $mainExists ? false : 'new' );
		$newTabs['nstab-main'] = [ 'class' => $mainClass, 'text' => $sktemplate->msg( 'nstab-main' )->text(), 'href' => $mainHref, 'context' => 'subject', 'primary' => true ];

		// 2. Discussion — always Talk:$baseName regardless of current namespace
		$talkTitle  = Title::makeTitle( NS_TALK, $baseName );
		$talkExists = $talkTitle->isKnown();
		$talkHref   = $talkExists ? $talkTitle->getLinkURL() : $talkTitle->getLinkURL( [ 'action' => 'edit', 'redlink' => '1' ] );
		$talkClass  = ( $currentNs === NS_TALK ) ? 'selected' : ( $talkExists ? false : 'new' );
		$newTabs['talk'] = [ 'class' => $talkClass, 'text' => $sktemplate->msg( 'talk' )->text(), 'href' => $talkHref, 'context' => 'talk' ];

		// 3. Dante namespace tabs
		foreach ( $danteNamespaces as $nsId => $nsName ) {
			$targetTitle = Title::makeTitle( $nsId, $baseName );
			$key         = 'dante-' . strtolower( $nsName );
			$isSelected  = ( $currentNs === $nsId );
			$exists      = $targetTitle->isKnown();
			$classes     = array_filter( [ $isSelected ? 'selected' : '', $exists ? '' : 'new' ] );
			$href        = $exists ? $targetTitle->getLinkURL() : $targetTitle->getLinkURL( [ 'action' => 'edit', 'redlink' => '1' ] );
			$newTabs[$key] = [ 'class' => $classes ? implode( ' ', $classes ) : false, 'text' => $sktemplate->msg( 'nstab-dante-' . strtolower( $nsName ) )->text(), 'title' => $sktemplate->msg( 'nstab-dante-' . strtolower( $nsName ) . '-hint' )->text(), 'href' => $href, 'context' => 'dante', 'primary' => true ];
		}

		$links['namespaces'] = $newTabs;
	}
}
