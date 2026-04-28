<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

require_once __DIR__ . '../../../maintenance/Maintenance.php';


/*
 * 
 * 
 */

class ImportDirectory extends Maintenance {

  public function __construct() {
    parent::__construct();

    $this->addDescription( 'Reads in text files and imports their content to pages of the wiki' );

    $this->addOption( 'user', 'Username to which edits should be attributed. ' .
      'Default: "Maintenance script"', false, true, 'u' );
    $this->addOption( 'summary', 'Specify edit summary for the edits', false, true, 's' );
    $this->addOption( 'use-timestamp', 'Use the modification date of the text file as the timestamp for the edit' );
    $this->addOption( 'overwrite', 'Overwrite existing pages. If --use-timestamp is passed, this will only overwrite pages if the file has been modified since the page was last modified.' );
    $this->addOption( 'prefix', 'A string to place in front of the file name', false, true, 'p' );
    $this->addOption( 'bot', 'Mark edits as bot edits in the recent changes list.' );
    $this->addOption( 'rc', 'Place revisions in RecentChanges.' );
    $this->addOption( 'file', 'File to import' , false, true);
    $this->addOption( 'dir', 'Directory to traverse' , false, true );
    $this->addOption( 'ddir', 'Double layered irectory to traverse' , false, true);
    $this->addOption( 'slug', 'Filename is sluggified' );
  }

  public function execute() {
    $userName = $this->getOption( 'user', false );
    $summary  = $this->getOption( 'summary', 'Imported from text file' );
    $useTimestamp = $this->hasOption( 'use-timestamp' );
    $rc = $this->hasOption( 'rc' );
    $bot = $this->hasOption( 'bot' );
    $overwrite = $this->hasOption( 'overwrite' );
    $prefix = $this->getOption( 'prefix', '' );
    $slug = $this->hasOption ("slug");

    $files = [];  // maps file name to file contents

    if ($this->hasOption ('file')) {
      $fileName = $this->getOption ("file");
      echo "FILE is: $fileName";
      if ($slug) { $fileName = InfoExtractor::restoreTitleFromFileName($fileName); }
      $files[$fileName] =  file_get_contents( $fileName );
      if ( $files[$fileName] === false) {$this->fatalError( "Could not read file $fileName.\n", 11 );} 
      return;
    }

    if ($this->hasOption( 'dir')) {
    
    }

    if ($this->hasOption( 'ddir')) {
      $ddir = $this->getOption ('ddir');
      echo "DDIR IS: $ddir";
      $it = new DirectoryIterator($ddir);
      foreach ($it as $dirInfo) {
        if ($dirInfo->isDot()) {continue;}     // skip dot files
        if (!$dirInfo->isDir()) {continue;}    // skip non-directories
        $dirName = $dirInfo->getPathname();
        $subIt = new DirectoryIterator($dirName);
        foreach ($subIt as $fileinfo) {
          if ($fileinfo->isDot()) {continue;}
          if (strtolower($fileinfo->getExtension()) !== 'txt') {continue;}
          if ($fileinfo->isFile()) {
            $fileName = $fileinfo->getPathname();
            if ($slug) { $pageTitle =  InfoExtractor::restoreTitleFromFileName($fileName); }
//            $pageTitle = self::restoreTitleFromFileName($fileName);
            $files[$pageTitle] =  file_get_contents( $fileName );
//            echo "\n Got: $fileName";
          }
        }
      }
    }


  // files is the array of file names to work on

    $count = count( $files );
    $this->output( "Importing $count pages...\n" );

    if ( $userName === false ) {
      $user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
    } else {
      $user = User::newFromName( $userName );
    }

    if ( !$user ) {$this->fatalError( "Invalid username\n" );} // TODO: rather exception ??
    if ( $user->isAnon() ) {$user->addToDatabase();}

    $exit = 0;

    $successCount = 0;
    $failCount = 0;
    $skipCount = 0;

    $revLookup = MediaWikiServices::getInstance()->getRevisionLookup();

    foreach ( $files as $file => $text ) {
      $pageName = $prefix . $file;
      $timestamp = $useTimestamp ? wfTimestamp( TS_UNIX, filemtime( $file ) ) : wfTimestampNow();

      $title = Title::newFromText( $pageName );
      // Have to check for # manually, since it gets interpreted as a fragment
      if ( !$title || $title->hasFragment() ) {
        $this->error( "Invalid title $pageName. Skipping.\n" );
        $skipCount++;
        continue;
      }

      $exists = $title->exists();
      $oldRevID = $title->getLatestRevID();
      $oldRevRecord = $oldRevID ? $revLookup->getRevisionById( $oldRevID ) : null;
      $actualTitle = $title->getPrefixedText();

      if ( $exists ) {
        $touched = wfTimestamp( TS_UNIX, $title->getTouched() );
        if ( !$overwrite ) {
          $this->output( "Title $actualTitle already exists. Skipping.\n" );
          $skipCount++;
          continue;
        } elseif ( $useTimestamp && intval( $touched ) >= intval( $timestamp ) ) {
          $this->output( "File for title $actualTitle has not been modified since the " .
            "destination page was touched. Skipping.\n" );
          $skipCount++;
          continue;
        }
      }

      $content = ContentHandler::makeContent( rtrim( $text ), $title );
      $rev = new WikiRevision( MediaWikiServices::getInstance()->getMainConfig() );
      $rev->setContent( SlotRecord::MAIN, $content );
      $rev->setTitle( $title );
      $rev->setUserObj( $user );
      $rev->setComment( $summary );
      $rev->setTimestamp( $timestamp );

      if ( $exists &&
        $overwrite &&
        $rev->getContent()->equals( $oldRevRecord->getContent( SlotRecord::MAIN ) )
      ) {
        $this->output( "File for title $actualTitle contains no changes from the current " .
          "revision. Skipping.\n" );
        $skipCount++;
        continue;
      }

      $status = $rev->importOldRevision();
      $newId = $title->getLatestRevID();

      if ( $status ) {
        $action = $exists ? 'updated' : 'created';
        $this->output( "Successfully $action $actualTitle\n" );
        $successCount++;
      } else {
        $action = $exists ? 'update' : 'create';
        $this->output( "Failed to $action $actualTitle\n" );
        $failCount++;
        $exit = 1;
      }

      // Create the RecentChanges entry if necessary
      if ( $rc && $status ) {
        if ( $exists ) {
          if ( is_object( $oldRevRecord ) ) {
            RecentChange::notifyEdit(
              $timestamp,
              $title,
              $rev->getMinor(),
              $user,
              $summary,
              $oldRevID,
              $oldRevRecord->getTimestamp(),
              $bot,
              '',
              $oldRevRecord->getSize(),
              $rev->getSize(),
              $newId,
              // the pages don't need to be patrolled
              1
            );
          }
        } else {
          RecentChange::notifyNew(
            $timestamp,
            $title,
            $rev->getMinor(),
            $user,
            $summary,
            $bot,
            '',
            $rev->getSize(),
            $newId,
            1
          );
        }
      }
    }  // end for loop

    $this->output( "Done! $successCount succeeded, $skipCount skipped.\n" );
    if ( $exit ) {
      $this->fatalError( "Import failed with $failCount failed pages.\n", $exit );
    }
  }
}

$maintClass = ImportDirectory::class;
require_once RUN_MAINTENANCE_IF_MAIN;
