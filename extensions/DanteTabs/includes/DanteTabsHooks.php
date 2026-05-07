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

		// Only inject tabs when browsing a main-namespace article
		// or a page already in one of our Dante namespaces.
		$relevantNamespaces = array_merge( [ NS_MAIN ], array_keys( $danteNamespaces ) );
		if ( !in_array( $currentNs, $relevantNamespaces, true ) ) {
			return;
		}

		foreach ( $danteNamespaces as $nsId => $nsName ) {
			$targetTitle = Title::makeTitle( $nsId, $baseName );
			$key         = 'dante-' . strtolower( $nsName );

			$isSelected = ( $currentNs === $nsId );
			$exists     = $targetTitle->isKnown();

			$classes = [];
			if ( $isSelected ) {
				$classes[] = 'selected';
			}
			if ( !$exists ) {
				$classes[] = 'new';
			}

			$href = $exists
				? $targetTitle->getLinkURL()
				: $targetTitle->getLinkURL( [ 'action' => 'edit', 'redlink' => '1' ] );

			$links['namespaces'][$key] = [
				'class'   => $classes ? implode( ' ', $classes ) : false,
				'text'    => $sktemplate->msg( 'nstab-dante-' . strtolower( $nsName ) )->text(),
				'href'    => $href,
				'context' => 'dante',
				'primary' => true,
			];
		}
	}
}
