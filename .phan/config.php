<?php
// Phan configuration for the dante-delta code: security (taint) analysis with the MediaWiki taint-check plugin, see .github/workflows/phan.yml
// Expects MediaWiki core in the main directory; in CI it is checked out next to our files, as this repository holds only the Dante delta.
// The environment variable MW_PHAN_CONFIG points to config.php of mediawiki/mediawiki-phan-config, in the version MediaWiki 1.39 uses (0.11.1).

// start from the configuration MediaWiki uses for its own extensions; core is in the main directory
putenv ( 'MW_INSTALL_PATH=.' );
$cfg = require getenv ( 'MW_PHAN_CONFIG' );

// our own code: extensions, skins, patched core files and settings in the main directory
$ownDirs  = [ 'extensions/DanteBackup', 'extensions/DanteBread', 'extensions/DanteCommon', 'extensions/DanteHideSection', 'extensions/DanteLinks', 'extensions/DantePresentations', 'extensions/DanteSnippets', 'extensions/DanteSynch', 'extensions/DanteSyntax', 'extensions/DanteTabs', 'extensions/DanteTree', 'skins/skinny', 'skins/DantePresentationSkin' ];
$ownFiles = [ 'includes/import/WikiImporter.php', 'includes/media/SVGReader.php', 'DantePolyfill.php', 'DanteSettings.php', 'DanteSettings-production.php', 'DanteSettings-development.php' ];

/**
 * Directories and files below $dir which do not lead to any of the files in $keep; Phan can only exclude whole directories,
 * so this excludes everything of core except the paths to our patched core files.
 *
 * @param string   $dir  Directory to split up, without trailing slash
 * @param string[] $keep Files which must stay analyzed
 * @return string[] Paths to exclude from analysis
 */
function danteExcludeAllBut ( string $dir, array $keep ): array {
  $result = [];
  // every entry of $dir is excluded as a whole, unless it is one of the kept files or a directory on the way to one
  foreach ( glob ( "$dir/*" ) as $entry ) {
    $onPath = array_filter ( $keep, fn ( $k ) => $k === $entry || str_starts_with ( $k, "$entry/" ) );
    if ( !$onPath ) { $result[] = $entry; continue; }
    if ( is_dir ( $entry ) ) { $result = array_merge ( $result, danteExcludeAllBut ( $entry, $keep ) ); }
  }
  return $result;
}

// parse core, its libraries, its stubs and our code; analyze only our code
$coreDirs = array_values ( array_filter ( [ 'includes', 'languages', 'vendor', '.phan/stubs' ], 'file_exists' ) );
$cfg['directory_list']                  = array_merge ( $ownDirs, $coreDirs );
$cfg['file_list']                       = array_values ( array_filter ( $ownFiles, fn ( $f ) => !str_starts_with ( $f, 'includes/' ) ) );   // the patched core files are found through includes
$cfg['exclude_analysis_directory_list'] = array_merge ( danteExcludeAllBut ( 'includes', $ownFiles ), array_diff ( $coreDirs, [ 'includes' ] ) );

// third party code bundled in our extensions, and files kept only for reference
$cfg['exclude_file_regex'] = '@^extensions/[^/]+/(vendor|node_modules)/|-deprecate\.php$|^DanteSettings-development-deprec\.php$@';

// security issues only; the regular type checks of Phan would report a large number of non-security issues
$cfg['whitelist_issue_types'] = [ 'SecurityCheck-XSS', 'SecurityCheck-SQLInjection', 'SecurityCheck-ShellInjection', 'SecurityCheck-PathTraversal', 'SecurityCheck-RCE', 'SecurityCheck-ReDoS', 'SecurityCheck-DoubleEscaped', 'SecurityCheck-OTHER', 'SecurityCheck-CUSTOM1', 'SecurityCheck-CUSTOM2' ];

return $cfg;
