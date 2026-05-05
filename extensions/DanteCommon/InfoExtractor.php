<?php

// Infoextractor is a collection of functions which extract information from MediaWiki in certain convenient forms

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;

/** @package  */
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

public static function exportAllToTextFiles ( string $outDir = "/tmp/gitDump",  $namespaces=null, $includeRedirects=true, $batchSize = 500, $clean = false ): array {
  print ("exportAlltoTextFiles is HERE *****");

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
      if ( !$title ) { throw new Exception ("Failed to make title for " . $row->page_title ); }
      if ( !$title->exists() ) {throw new Exception ("Title does not exist for " . $row->page_title); }

      $page = WikiPage::factory( $title );
      $rev = $page->getRevisionRecord();
      if ( !$rev ) { throw new Exception ("Could not get revision record for " . $row->page_title);}

      $content = $rev->getContent( 'main' );
      if ( !$content ) { throw new Exception ("Could not get content for " . $row->page_title);}

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






// exports pages listed in a manifest file (one title per line) to text files in $outDir
public static function exportManifestToTextFiles ( string $manifestFile, string $outDir, bool $clean = false ): array {
  if ( !is_readable( $manifestFile ) ) { throw new \RuntimeException( "Cannot read manifest file: $manifestFile" ); }

  if ( $clean ) { self::rrmdir( $outDir ); }
  if ( !is_dir( $outDir ) ) {
    if ( !mkdir( $outDir, 0775, true ) ) { throw new \RuntimeException( "Cannot create output directory: $outDir" ); }
  }
  if ( !is_writable( $outDir ) ) { throw new \RuntimeException( "Output directory is not writable: $outDir" ); }

  $lines    = file( $manifestFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
  $exported = 0;
  $skipped  = 0;

  foreach ( $lines as $titleText ) {
    $titleText = trim( $titleText );
    $title = Title::newFromText( $titleText );
    if ( !$title )           { $skipped++;  throw new Exception ("No title object obtained for: ".$titleText); }
    if ( !$title->exists() ) { $skipped++;  throw new Exception ("Title does not exist for: ". $titleText); }

    $page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
    $rev  = $page->getRevisionRecord();
    if ( !$rev ) { $skipped++;  throw new Exception ("No revision record obtained for: " . $titleText); }

    $content = $rev->getContent( 'main' );
    if ( !$content ) { $skipped++;  throw new Exception ("No content obtained for: ". $titleText); }

    $text     = ( $content instanceof \TextContent ) ? $content->getText() : $content->serialize();
    $relPath  = self::makeRelPath( $title, 'txt' );
    $fullPath = rtrim( $outDir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $relPath;

    $dir = dirname( $fullPath );
    if ( !is_dir( $dir ) ) {
      if ( !mkdir( $dir, 0775, true ) ) { throw new \RuntimeException( "Cannot create directory: $dir" ); }
    }

    if ( @file_put_contents( $fullPath, $text ) === false ) { throw new \RuntimeException( "Failed writing file: $fullPath" ); }
    $exported++;
  }

  echo "Exported: $exported \n";
  echo "Skipped: $skipped\n";

  return [ 'exported' => $exported, 'skipped' => $skipped ];
}


// deletes every file in $outDir whose relative path is not the expected path of a title listed in $manifestFile
public static function pruneToManifestOLD ( string $manifestFile, string $outDir ): array {
  if ( !is_readable( $manifestFile ) ) { throw new \RuntimeException( "Cannot read manifest file: $manifestFile" ); }
  if ( !is_dir( $outDir ) )            { return [ 'deleted' => 0 ]; }

  $base = rtrim( $outDir, DIRECTORY_SEPARATOR );

  $lines    = file( $manifestFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
  $expected = [];
  foreach ( $lines as $titleText ) {
    $title = Title::newFromText( trim( $titleText ) );
    if ( !$title ) { continue; }
    $expected[ str_replace( '\\', '/', self::makeRelPath( $title, 'txt' ) ) ] = true;
  }

  $deleted = 0;
  $it = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator( $base, \RecursiveDirectoryIterator::SKIP_DOTS ),
    \RecursiveIteratorIterator::LEAVES_ONLY
  );
  foreach ( $it as $fileInfo ) {
    if ( !$fileInfo->isFile() ) { continue; }
    $rel = str_replace( '\\', '/', substr( $fileInfo->getPathname(), strlen( $base ) + 1 ) );
    if ( !isset( $expected[$rel] ) ) {
      unlink( $fileInfo->getPathname() );
      $deleted++;
    }
  }

  return [ 'deleted' => $deleted ];
}




// deletes every file in $outDir whose relative path is not the expected path of a title listed in $manifestFile;
// never touches .git or anything below .git/
public static function pruneToManifest ( string $manifestFile, string $outDir ): array {
  if ( !is_readable( $manifestFile ) ) { throw new \RuntimeException( "Cannot read manifest file: $manifestFile" ); }
  if ( !is_dir( $outDir ) )            { return [ 'deleted' => 0 ]; }

  $base = rtrim( $outDir, DIRECTORY_SEPARATOR );

  $lines    = file( $manifestFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
  $expected = [];
  foreach ( $lines as $titleText ) {
    $title = Title::newFromText( trim( $titleText ) );
    if ( !$title ) { continue; }
    $expected[ str_replace( '\\', '/', self::makeRelPath( $title, 'txt' ) ) ] = true;
  }

  $deleted = 0;

  $dirIt = new \RecursiveDirectoryIterator(
    $base,
    \RecursiveDirectoryIterator::SKIP_DOTS
  );

  $filterIt = new \RecursiveCallbackFilterIterator(
    $dirIt,
    static function ( \SplFileInfo $current, string $key, \RecursiveDirectoryIterator $iterator ): bool {
      if ( $current->isDir() && $current->getFilename() === '.git' ) {
        return false;
      }

      return true;
    }
  );

  $it = new \RecursiveIteratorIterator(
    $filterIt,
    \RecursiveIteratorIterator::LEAVES_ONLY
  );

  foreach ( $it as $fileInfo ) {
    if ( !$fileInfo->isFile() ) { continue; }

    $rel = str_replace( '\\', '/', substr( $fileInfo->getPathname(), strlen( $base ) + 1 ) );

    if ( $rel === '.git' || str_starts_with( $rel, '.git/' ) ) {
      continue;
    }

    if ( !isset( $expected[$rel] ) ) {
      unlink( $fileInfo->getPathname() );
      $deleted++;
    }
  }

  return [ 'deleted' => $deleted ];
}





// iterates a directory containing slug coded files and imports all of them
public static function importAllTextFilesSlugged ( string $inDir, string $userName ) {

  $user = MediaWikiServices::getInstance()->getUserFactory()->newFromName($userName); // convert to UserIdentity

  $it = new DirectoryIterator($inDir);
  foreach ($it as $dirInfo) {
    if ($dirInfo->isDot()) {continue;}     // skip dot files
    if (!$dirInfo->isDir()) {continue;}    // skip non-directories
    $subIt = new DirectoryIterator($dirInfo->getPathname());
    foreach ($subIt as $fileinfo) {
      if ($fileinfo->isDot()) {continue;}
      if (strtolower($fileinfo->getExtension()) !== 'txt') {continue;}
      if ($fileinfo->isFile()) {
        $fileName = $fileinfo->getPathname();
        $pageTitle = self::restoreTitleFromFileName($fileName);

        // danteLog ("DanteBackup", "\n importing slugged file $fileName as $pageTitle" );

      //  self::importTextFileToWikiPage($fileName, $pageTitle, $user, false);
      
// $pageTitle = "Clemen" . random_int(1, 100);

//  self::createPage ( $pageTitle, "I am " . $pageTitle.random_int (1,2000), $user);

//danteLog ("DanteBackup", "\n PAGE WAS CREATED" );

//        self::addPageFromFile ( $pageTitle, $fileName, $user, "SECOND");
      
      }
    }
  }
}






public static function createPage(string $titleText, string $pageText, \MediaWiki\User\UserIdentity $user) {
  $title = \Title::newFromText( $titleText );
  if ( !$title ) {throw new \Exception( 'Illegal title' );}

  $services = \MediaWiki\MediaWikiServices::getInstance();
  $wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );
  $content  = \ContentHandler::makeContent( $pageText, $title );

  $pageUpdater = $wikiPage->newPageUpdater( $user );
  $pageUpdater->setContent( SlotRecord::MAIN, $content );
  $pageUpdater->saveRevision ( \CommentStoreComment::newUnsavedComment( 'Page created programmatically' ) );

  if ( !$pageUpdater->wasSuccessful() || !$pageUpdater->wasRevisionCreated() ) {
    $status = $pageUpdater->getStatus();
    throw new \Exception(
      'Save failed: ' . print_r( $status->getErrorsArray(), true )
    );
  }
}





private static function createPage2 (string $titleText, string $pageText, \MediaWiki\User\UserIdentity $user) {
  
  $title = \Title::newFromText( $titleText );
  if ( !$title ) {throw new Exception ("illegal title");}

  $services = \MediaWiki\MediaWikiServices::getInstance();
  $wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );
  $content = \ContentHandler::makeContent( $pageText, $title );

  $pageUpdater = $wikiPage->newPageUpdater( $user );
  $pageUpdater->setContent( 'main', $content );

  $pageUpdater->saveRevision(\CommentStoreComment::newUnsavedComment( 'Page created programmatically' ));

    $status = $pageUpdater->getStatus();
   
  //    'Page creation failed: ' . implode( '; ', $status->getErrorsArray() )
  
  danteLog ("DanteBackup", print_r ($status, true));

  

}






// BROKEN ???
// imports a single text file
public static function importTextFileToWikiPage ( string $filePath, string $pageTitleText, UserIdentity $performer, bool $createOnly = false ): void   {

  $text = file_get_contents($filePath);
  if ($text === false) {throw new RuntimeException("Could not read file: $filePath");}

  $title = Title::newFromText($pageTitleText);
  if (!$title) {throw new RuntimeException("Invalid page title: $pageTitleText");}

  $services = MediaWikiServices::getInstance();
  $wikiPage = $services->getWikiPageFactory()->newFromTitle($title);

  if ($createOnly && $wikiPage->exists()) {throw new RuntimeException("Page already exists: $pageTitleText");}

  $content = ContentHandler::makeContent($text, $title);

  danteLog("DanteBackup", "\n performer: '" . $performer->getName() . "' id=" . $performer->getId());
  if ($performer->getId() === 0) { throw new RuntimeException("Performer is anonymous (id=0) — pass a registered user"); }

  $pageUpdater = $wikiPage->newPageUpdater($performer);
  $pageUpdater->setContent(SlotRecord::MAIN, $content);

  $summary = CommentStoreComment::newUnsavedComment ( 'Imported from text file' );

  $rev = $pageUpdater->saveRevision($summary);
  $status = $pageUpdater->getStatus();

  danteLog ("DanteBackup", "\n importing '$pageTitleText' (resolved: '" . $title->getPrefixedText() . "' ns=" . $title->getNamespace() . ") rev=" . ($rev ? $rev->getId() : 'null') . " status=$status" );
  // danteLog ("DanteBackup", "\n text is: " . $text);


  if ($status === null) { throw new RuntimeException("Failed to save page: no status returned"); }
  if (!$status->isOK()) {
    $errors = [];
    foreach ($status->getErrors() as $error) { if (isset($error['message'])) { $errors[] = (string)$error['message']; } }
    $message = $errors ? implode('; ', $errors) : 'Unknown save failure';
    throw new RuntimeException("Failed to save page: $message");
  }
  if ($rev === null) { danteLog("DanteBackup", "\n no-change (page already has this content): $pageTitleText"); }

  $wikiPage->clear();
  danteLog("DanteBackup", "\n page exists in DB after save: " . ($wikiPage->exists() ? 'YES' : 'NO'));


}


/**
 * Create a wiki page from a local file.
 */
public static function addPageFromFile( string $pageTitle, string $filePath, UserIdentity $user, string $summary = 'Created from file' ) {
  
  if ( !is_file( $filePath ) || !is_readable( $filePath ) ) {throw new Exception ("file does not exist or not readable");}

  $text = file_get_contents( $filePath );
  if ( $text === false ) { throw new Exception ("text is false"); }

  $title = Title::newFromText( $pageTitle );
  if ( !$title || !$title->canExist() ) { throw new Exception ("invalid page title");}

  $services = MediaWikiServices::getInstance();
  $wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );

  if ( $wikiPage->exists() ) { 
    danteLog ("DanteBackup", "page already exists: " . $pageTitle);
    return;
  }

  $content = ContentHandler::makeContent( $text, $title, CONTENT_MODEL_WIKITEXT);

  $updater = $wikiPage->newPageUpdater( $user );
  $updater->setContent( SlotRecord::MAIN, $content );

  $updater->saveRevision(
    CommentStoreComment::newUnsavedComment( $summary ),
    EDIT_NEW
  );

  if ( !$updater->wasSuccessful() || !$updater->isNew() ) {
    $status = $updater->getStatus();
    throw new Exception ("save error " . print_r ($status, true));
  }

  $gen = $title->getPrefixedText();

  danteLog ( "DanteBackup", "\n saved: " . $gen . " created:  " . ($updater->wasRevisionCreated() ? "TRUE" : "FALSE") ) ;
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






// generate a unique temporary directory and returns its name 
// racecondition safe 
public static function makeTempDir(string $prefix = 'mw_'): string {
  $base = rtrim(sys_get_temp_dir(), '/');
  for ($i = 0; $i < 20; $i++) {  // do 20 attempts (preventing collisions)
    $dir = $base . '/' . $prefix . bin2hex(random_bytes(16));
    if (mkdir($dir, 0777)) {return $dir;}  // break out of loop
    if (!file_exists($dir)) {
      throw new RuntimeException('Failed to create temporary directory');
    }
  }
  throw new RuntimeException('Could not create a unique temporary directory');
}














}