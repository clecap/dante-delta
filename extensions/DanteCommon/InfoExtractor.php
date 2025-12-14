<?php

// Infoextractor is a collection of functions which extract information from MediaWiki in certain convenient forms

use MediaWiki\MediaWikiServices;


//use ContentHandler;
//use Title;
//use WikiPage;

class InfoExtractor {


/**
 *   Given the name of a MediaWiki article, optionally with namespace prefix, return its contents.
 *  
 *  @param [string]  $titleName  Name of article, may include namespace prefix
 *  @param [?bool]   $filter     If true, only return contents between the first <pre> tag and the first </pre> tag in the article
 *                               where both, <pre> and </pre> must stand at the beginning of a line
 *  @param [?string] $fileName   If not null, generate a file by this name and place the contents into this file.
 *  @return string|null
 */
public static function articleExtract ( string $titleName, ?bool $filter = null, ?string $fileName = null ): mixed {
  $title = Title::newFromText($titleName);
  if (!$title || !$title->exists()) { throw new Exception ("Title $title not given or nor existent"); }

  $page = WikiPage::factory($title);
  $content = $page->getContent();
  if (!$content) { throw new Exception ("Content obtained from $title is null");}
  $text = ContentHandler::getContentText($content);
  if ($filter) {
    if (preg_match('/^<pre>\R?(.*?)^<\/pre>/ms', $text, $m)) {$text = $m[1];} else {$text = '';}
  }
  if ($fileName !== null) {file_put_contents($fileName, $text); return null;} else {return $text;}
}




/**
 *  Given the name(s) of a MediaWiki category,  return a list of all pages in this category (no duplicates reported)
 *  @param string|string[] $categories  Name of a category or array of names of a category
 */
public static function getTitlesOfCategories ( $categories ): array {
  if ( is_array($categories)) {
    $items = [];
    foreach ($categories as $category) { $items = array_merge ($items, self::getTitlesOfCategories ($category) ); }
     return array_values (array_unique ($items));
  }
  else   { return self::getTitlesOfCategory ($categories);}
}


/**
 *  Given the name of a MediaWiki category,  return a list of all pages in this category (no duplicates reported)
 *  @param string $category  Name of the category
 */
public static function getTitlesOfCategory ( string $category ) {
  if (stripos($category, 'Category:') === 0) {$category = substr($category, strlen('Category:'));}    // Allow passing "Category:Foo" or just "Foo"
  $cat = Category::newFromName($category);    // Category::newFromName expects the bare category name (no prefix)
  if (!$cat) { throw new Exception ("Invalid Category name was: $category");}
  $members = $cat->getMembers();    // TitleArray of members
  $items = [];
  foreach ($members as $title) {
    if (!$title instanceof Title) {continue;}
    // if ($title->getNamespace() !== NS_MAIN) {continue;}     // Only “articles” in the main namespace; remove this check if you want all members
    array_push ($items, $title->getPrefixedText () );
  }
  return array_values (array_unique ($items));
}





/**
 * Given the name of a MediaWiki category, return a list of all categories that are
 * direct or indirect subcategories of it. Cycles are handled safely.
 *
 * @param string $category Category name with or without "Category:" prefix
 * @return string|string[] Array of unique category titles (e.g. "Category:Foo")
 */
public static function getIndirectSubcategories( string|array $category): array {
  if (stripos($category, 'Category:') === 0) {$category = substr($category, strlen('Category:'));}   // Normalize input so Category::newFromName() receives the bare name

  $rootCat = Category::newFromName($category);
  if (!$rootCat) {return [];}       // category as such does not exist, result empty

  $result = [];            // DBkey => "Category:Name"
  $visited = [];           // DBkey => true  required for preventing a loop of the algorithm, if true, DBkey has already been visited
  $queue = [$rootCat];     // the working queue at the beginning is the one category with which we started

  while ($queue) {         // 
    $cat = array_shift($queue);

    $page = $cat->getPage(); // PageIdentity
    if (!$page) {continue;}

    $dbKey = $page->getDBkey();
    if (isset($visited[$dbKey])) {continue;}
    $visited[$dbKey] = true;

    // Fetch category members (as Title objects)
    $members = $cat->getMembers();

    foreach ($members as $title) {
      if (!$title instanceof Title) {continue;}                 // skip members which have no title
      if ($title->getNamespace() !== NS_CATEGORY) {continue;}   // skip members which are not categories
      $subKey = $title->getDBkey();
      $result[$subKey] = $title->getPrefixedText();             // Record result (unique)

      // Prevent infinite loops in cyclic category graphs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
      if (!isset($visited[$subKey])) {
        $subCat = Category::newFromTitle($title);
        if ($subCat) {$queue[] = $subCat;}
      }
    }
  }
  return array_values($result);   // Return only the unique category titles
}



// TODO: Add some more filtering possibilities, as we have it in DanteDump selector mechanisms
// TODO: Test the redirects
  /**
   * Export all pages (current content) to text files (wikitext) using specific file names
   * The file names consist of a slug (human readable but not reproducible) and an encoding (allowing full reproducibility)
   *  
   *
   * @param string       $outDir             Path to the output directory.
   * @param int[]|null   $namespaces         Integer array of namespaces to be included or null if all namespaces should be included
   * @param bool         $includeRedirects   If true, redirects are included
   * @param int          $batchSize          Batch size used for DB calls (default 500)
   * @param bool         $clean              If true: Delete contents in output directory before writing to it 
   *                                         If false: Overwrite output directory
   *
   * @return array Summary: ['exported' => int, 'scanned' => int]
   * @throws \RuntimeException on fatal IO errors.
   */

// TODO: *   - 'progressCallback' => callable|null  // function(int $exported, int $scanned): void

public static function exportAllToTextFiles ( string $outDir = "/tmp/gitDump",  $namespaces=null, $includeRedirects=true, $batchSize = 500, $clean = false ): array {

  $services = MediaWikiServices::getInstance();
  $lb       = $services->getDBLoadBalancer();
  $dbr      = $lb->getConnection( DB_REPLICA );
 
  $progress           = $options['progressCallback'] ?? null;  // TODO: what to do with this?????  it is a callable which we cannot serialize ?? most likely

  if ($clean) {self::rrmdir ($outDir);}  // if requested: recursively delete the output directory - could be polluted from last time
  if ( !is_dir( $outDir ) ) {       // ensure that output directory exists and is writeable
    if ( !mkdir( $outDir, 0775, true ) ) { throw new \RuntimeException( "Cannot create output directory: $outDir" ); } }
  if ( !is_writable( $outDir ) ) {throw new \RuntimeException( "Output directory is not writable: $outDir" );}

  $conds = [];
  if ( $namespaces !== null ) { $conds['page_namespace'] = array_map( 'intval', $namespaces );}
  if ( !$includeRedirects )   { $conds['page_is_redirect'] = 0;}
 
  $exported = 0;    // count the pages we really exported
  $scanned  = 0;    // count the pages we scanned
  $lastId   = - 1;  // last id, to continue from in case we really have so many results that we need more than one batch

  // loop through the data base page table with increasing page ids until we no longer get a result
  while ( true ) {
    $batchConds = $conds;
    $batchConds[] = 'page_id > ' . $dbr->addQuotes( (int)$lastId );

    $res = $dbr->select(
      'page',
      [ 'page_id', 'page_namespace', 'page_title' ],
      $batchConds,
      __METHOD__,
      ['ORDER BY' => 'page_id ASC', 'LIMIT' => $batchSize]
    );

    $rows = iterator_to_array( $res );
    if ( !$rows ) {break;}  // no rows to iterate, break out of while loop

    foreach ( $rows as $row ) {
      $scanned++;
      $lastId = (int)$row->page_id;
      $title = Title::makeTitle( (int)$row->page_namespace, $row->page_title );
      if ( !$title || !$title->exists() ) {continue;}

      $page = WikiPage::factory( $title );
      $rev = $page->getRevisionRecord();
      if ( !$rev ) {continue;}

      $content = $rev->getContent( 'main' );
      if ( !$content ) {continue;}

      $text = ContentHandler::getContentText( $content ); // TODO: modernize this to content models !!

      $relPath = self::makeRelPath( $title, 'txt' );
      $fullPath = rtrim( $outDir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $relPath;

      $dir = dirname( $fullPath );
      if ( !is_dir( $dir ) ) {
        if ( !mkdir( $dir, 0775, true ) ) {throw new \RuntimeException( "Cannot create directory: $dir" );} 
      }

      $ok = @file_put_contents( $fullPath, $text );
      if ( $ok === false ) {throw new \RuntimeException( "Failed writing file: $fullPath" );}

      $exported++;

      if ( is_callable( $progress ) && ( $exported % 500 === 0 ) ) {$progress( $exported, $scanned );}
      
      } // end for loop treating the DB result
    }  // end while loop

  if ( is_callable( $progress ) ) {$progress( $exported, $scanned );}  // call progress notifier
  return ['exported' => $exported, 'scanned' => $scanned];
}






  /**
   * Build relative path:
   *   <Namespace>/<slug>__<token>.<ext>
   */
  public static function makeRelPath( Title $title, string $ext = 'txt' ): string {
    $ns = $title->getNsText();
    if ( $ns === '' ) {$ns = 'Main';}
    $nsDir = self::slugify( $ns );
    $fullTitle = $title->getPrefixedText();
    $file = self::safeFileNameHumanLossless( $fullTitle, $ext );
    return $nsDir . DIRECTORY_SEPARATOR . $file;
  }

  /**
   * Filename:
   *   <slug>__<base64url(UTF-8 title)>.ext
   */
  public static function safeFileNameHumanLossless( string $title, string $ext = 'txt' ): string {
    $slug = self::slugify( $title );
    $token = self::b64urlEncode( $title );

    // Keep slug readable but not too long (token is the source of truth)
    if ( strlen( $slug ) > 80 ) {
      $slug = substr( $slug, 0, 80 );
      $slug = rtrim( $slug, '_' );
      if ( $slug === '' ) {$slug = 'page';}
    }

    return $slug . '__' . $token . '.' . $ext;
  }

  /**
   * Restore exact original title from a filename created by safeFileNameHumanLossless().
   * Pass basename(filename) (no directory).
   */
  public static function restoreTitleFromFileName( string $fileName ): string {
    $base = preg_replace( '/\.[^.]+$/', '', $fileName );
    $parts = explode( '__', $base, 2 );
    if ( count( $parts ) !== 2 ) {
      throw new \RuntimeException( 'Filename does not contain lossless token.' );
    }
    return self::b64urlDecode( $parts[1] );
  }

  /**
   * Create a human-readable slug (lossy by design).
   * Keeps Unicode letters/numbers, plus . _ -
   */
  private static function slugify( string $s ): string {
    $s = str_replace( ' ', '_', $s );
    $s = preg_replace( '/[^\pL\pN._-]+/u', '_', $s );
    $s = preg_replace( '/_+/', '_', $s );
    $s = trim( (string)$s, '_' );
    if ( $s === '' ) {$s = 'page';}
    return $s;
  }

  /**
   * Base64URL encode (lossless, filesystem-safe).
   */
  private static function b64urlEncode( string $s ): string {
    $b64 = base64_encode( $s );
    $b64url = strtr( $b64, '+/', '-_' );
    return rtrim( $b64url, '=' );
  }

  /**
   * Base64URL decode.
   */
  private static function b64urlDecode( string $s ): string {
    $b64 = strtr( $s, '-_', '+/' );
    $pad = strlen( $b64 ) % 4;
    if ( $pad > 0 ) {$b64 .= str_repeat( '=', 4 - $pad );}
    $out = base64_decode( $b64, true );
    if ( $out === false ) {throw new \RuntimeException( 'Invalid base64url token.' );}
    return $out;
  }

/**
 * recursive removal of a directory
 *
 * @param [type] $dir
 * @return void
 */
public static function rrmdir($dir) {
  if (!is_dir($dir)) {return;}
  $items = scandir($dir);
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {continue;}
    $path = $dir . '/' . $item;
    if (is_dir($path)) {self::rrmdir($path);} else {unlink($path);}
  }
  rmdir($dir);
}







}