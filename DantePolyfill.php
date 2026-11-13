<?php

/** This file contains some common functions used in all of the Dante extensions. 
 *
 *  The reason we are doing this is to make stuff available on a more systematic basis
 *  without having to bother wit composer and similar stuff.
 */


// Given some string with mediawiki source text, return the stuff between the first <pre> and the first </pre>, removing these tags and any white space
// The idea is to have 
//   1) Configuration files, usually in the MediaWiki namespace, show their content in preview
//   2) Allow some comments on the purpose and the format of these files as part of these files (before and after the pre tags)
function extractPreContents ($code) {
  $start = strpos ($code, "<pre>") + 5;
  $end   = strpos ($code, "</pre>");
  $code  = substr ($code, $start, $end - $start);
  $code  = trim   ($code);                                                               // removes white space at the beginning and at the end
  $lines = explode("\n", $code);                                                         // Split the string into an array of lines
  $filteredLines = array_filter($lines, function($line) {return trim($line) !== '';});   // Use array_filter to remove empty lines
  $result = implode("\n", $filteredLines);                                               // Join the filtered lines back into a single string

  $result .= "\n";    // append one \n so that counting the newlines also counts the lines we have
  return $result;
}


function danteLog ($extension, $text) {
  $fileName = dirname(__FILE__) . "/extensions/".$extension."/LOGFILE";
  
  if($tmpFile = fopen( $fileName , 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  
  else {throw new Exception ("DanteSettings.php: debugLog could not log to $fileName for extension $extension"); }

  $fileSize = filesize ($fileName);
  if ($fileSize == false) { return; }
  if ($fileSize > 1000000) {  $handle = fopen($fileName, 'w'); }  // truncate too long files

}



/**
 * Thrown by DanteUtil::executor when the executed command was terminated because it exceeded its CPU time limit.
 * Carries the details of the aborted run so callers can log or display them.
 */
class DanteCpuLimitException extends RuntimeException {

  public string $cmd;       // the command as passed to executor (without the ulimit wrapper)
  public int    $limit;     // the CPU limit in seconds
  public float  $cpuTime;   // CPU seconds consumed by the command and its waited-for children
  public float  $duration;  // wall clock seconds of the run
  public string $output;    // stdout captured up to the abort
  public string $error;     // stderr captured up to the abort

  /**
   * @param string $cmd       command as passed to executor
   * @param int    $limit     CPU limit in seconds
   * @param float  $cpuTime   CPU seconds consumed
   * @param float  $duration  wall clock seconds
   * @param string $output    captured stdout
   * @param string $error     captured stderr
   */
  public function __construct (string $cmd, int $limit, float $cpuTime, float $duration, string $output, string $error) {
    $this->cmd = $cmd; $this->limit = $limit; $this->cpuTime = $cpuTime; $this->duration = $duration; $this->output = $output; $this->error = $error;
    parent::__construct (sprintf ("CPU time limit of %d s exceeded (used %.1f s CPU, %.1f s wall clock) by command: %s", $limit, $cpuTime, $duration, $cmd));
  }
}


// bundles some utility functions
class DanteUtil {


/**
 * Given the name $cat of a category, return the name of a temporary file listing all files belonging to this category (this includes category files)
 */
static public function catList ( $cat ) {
  // Get the list of pages which are in the given category $cat
  $dbr           = \MediaWiki\MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();  // replaces deprecated wfGetDB( DB_REPLICA )
  $categoryTitle = Title::newFromText( $cat, NS_CATEGORY );
  if ( !$categoryTitle ) { throw new Exception ("Cannot find category $cat"); }

  $res = $dbr->select(
    array( 'categorylinks',   'page' ),
    array( 'page_namespace',  'page_title' ),
    array( 'cl_from = page_id', 'cl_to' => $categoryTitle->getDBkey() ),
      __METHOD__
    );

  $filepath = tempnam ("/tmp", "DantePageList");   // getting a fresh file name helps us to avoid race conditions of parallel invocations; so we do not need locks
  $file = fopen( $filepath, 'w' );
  foreach ( $res as $row ) {
    $title = Title::makeTitle( $row->page_namespace, $row->page_title );
    fwrite( $file, $title->getFullText() . PHP_EOL );
  }

  fclose( $file );
  return $filepath;
}



/**
 * Given the name of a title (which may include a namespace prefix) an returns the contents of this file in the first <pre> element to be found there
 * Where every line is terminated by a newline.
 *
 *
 */
static public function listOfListed ($name) {
  $title      = Title::newFromText( $name  );                             
  if ($title == null) { throw new Exception ("Title $name not found");}       
  $wikipage   = \MediaWiki\MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );  // WikiPage via factory instead of new WikiPage()
  if ($wikipage == null) { throw new Exception ("Could not generate Wikipage for title $name");}                                   // signal the caller that we did not get a WikiPage
  $contentObject = $wikipage->getContent();                                                   // and obtain the content object for that
  if ($contentObject ) {                                                                      // IF we have found a content object for this thing
    $contentText = ($contentObject instanceof \MediaWiki\Content\TextContent) ? $contentObject->getText() : '';  // replaces deprecated ContentHandler::getContentText
    $contentText = extractPreContents ($contentText);
    $filepath = tempnam ("/tmp", "DantePageList");
    file_put_contents ($filepath, $contentText); 
    return $filepath;
  }
  throw new Exception ("Could not get content object for $name");
}


// given a namespace index such as NS_TEST, return an arrayy of pages in the namespace
static public function getPagesInNamespace( $namespaceIndex ) {
  // Query to get all pages in the specified namespace
  $dbr = \MediaWiki\MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();  // replaces deprecated wfGetDB( DB_REPLICA )
  $res = $dbr->select(
    'page',  // The table to select from
    'page_title',  // The column to select
    [ 'page_namespace' => $namespaceIndex ],  // The condition (where clause)
    __METHOD__  // The name of the calling function, for logging purposes
  );

  $pages = [];
  foreach ( $res as $row ) { $pages[] = Title::makeTitle( $namespaceIndex, $row->page_title )->getPrefixedText(); }
  return $pages;
}

// returns name of a temporary file containing all parges contained in the given namespace
static public function listOfNamespace ( $nsIndex ) {
  $filepath = tempnam ("/tmp", "DantePageList");
  $arr = DanteUtil::getPagesInNamespace ( $nsIndex );
  file_put_contents ($filepath, implode ("\n",$arr)."\n");
  return $filepath;
}

// returns name of a temporary file
static public function singleList ( $name ) {
  $filepath = tempnam ("/tmp", "DantePageList");
  file_put_contents ( $filepath, $name . "\n");
  return $filepath;
}





/**
 *
 *  $cmd:       command to be executed
 *  $output:    variable which captures stdout
 *  $error:     variable which captures stderr
 *  $logger:    callable logger function, if provided logs invocations
 *  $duration:  variable which captures execution time in microseconds, if provided by the callser
 *  $timeout:   CPU time limit for the command in seconds (NOT wall clock time), default 10 seconds; 0 means no limit
 *  $env:       environment to run command in
 *
 *  return:     exit code of the command, or the signal number if it was killed by a signal
 *  throws:     DanteCpuLimitException if the command was terminated for exceeding the CPU time limit
 *  CAVE 0: The limit applies per process: children of the command each get their own budget. A child killed for exceeding it only
 *          leads to an exception if this makes the whole command terminate, i.e. if the child was the last command or the command exits with 152.
 *  CAVE 1: We MUST store the value of proc_open somewhere and we must release ressource using proc_close, otherwise things may go wrong
 *  CAVE 2: Similar with the pipes, which MUST be prepared, read and properly closed.
 * CAVE: Only works for short output and may block   TODO: fix
 */

public static function executor ( string $cmd, &$output, &$error, ?callable $logger, &$duration = null, $timeout = 10, $env = null ) {
  if ($logger) {$cfn = debug_backtrace()[1]['function']; $logger ( "$cfn calling shell executor\n"); }  // get name of the calling function

  $origCmd    = $cmd;                                                               // keep the unwrapped command for the exception message
  $startTime  = microtime(true);
  $cpuBefore  = self::childCpuTime();                                               // CPU time of already reaped children, to compute the delta later

  // add a CPU limit: hard limit 1s above soft limit, so the kernel first sends SIGXCPU (only ever sent for this reason) and SIGKILL only if SIGXCPU is ignored
  // CAVE: first set both limits, then lower the soft one; "ulimit -H" alone fails (EINVAL) while the soft limit is still above the new hard limit
  // CAVE: escapeshellarg is required, since $cmd may itself contain double quotes, backslashes or & (eg the "&latex file.tex\dump" of a format precompile)
  if ($timeout > 0) {$cmd = '/bin/bash -c ' . escapeshellarg ('ulimit -t ' . ($timeout + 1) . '; ulimit -S -t ' . $timeout . '; ' . $cmd);}
  $proc       = proc_open ( $cmd, [ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes, null, $env );

  $output     = stream_get_contents($pipes[1]); fclose($pipes[1]);
  $error      = stream_get_contents($pipes[2]); fclose($pipes[2]);                  // MUST close pipes before doing a proc_close

  // wait for termination via proc_get_status, since only it tells us whether the process was killed by a signal (closed pipes do not imply termination)
  while ( ($status = proc_get_status ($proc))['running'] ) { usleep (10000); }
  proc_close($proc);                                                                // release resources; exit information was taken from $status
  $closeParam = $status['signaled'] ? $status['termsig'] : $status['exitcode'];    // same convention as proc_close: exit code, or signal number when killed

  $duration   = microtime (true) - $startTime;
  $cpuTime    = self::childCpuTime() - $cpuBefore;                                  // CPU time consumed by the command and its waited-for children

  // detect termination by the CPU limit
  if ($timeout > 0) {
    $SIGXCPU = defined ('SIGXCPU') ? SIGXCPU : 24;                                  // pcntl may not be loaded; 24 on Linux
    $SIGKILL = 9;
    $byXcpu  = ( $status['signaled'] && $status['termsig'] == $SIGXCPU ) || ( !$status['signaled'] && $status['exitcode'] == 128 + $SIGXCPU );   // killed directly, or bash reports a child killed by SIGXCPU
    $byKill  = ( ( $status['signaled'] && $status['termsig'] == $SIGKILL ) || ( !$status['signaled'] && $status['exitcode'] == 128 + $SIGKILL ) ) && $cpuTime >= $timeout;   // SIGXCPU ignored, hard limit hit
    if ($byXcpu || $byKill) {
      if ($logger) { $logger ( "$cfn executor: CPU limit of $timeout s exceeded.\n    Command: $cmd\n    CPU: $cpuTime\n    DURATION: $duration\n    OUTPUT:--------\n$output\n--------\n    ERROR: $error\n" ); }
      throw new DanteCpuLimitException ($origCmd, (int) $timeout, $cpuTime, $duration, (string) $output, (string) $error);
    }
  }

  // if ($closeParam != 0) { $logger ("Executor of $cmd closed with a non-zero return from proc_close: $closeParam\n" );}  ////// TODO: rather w warning ?!?
  if ($logger)         { $logger ( "$cfn executor call completed.\n    Command: $cmd\n    DURATION: $duration\n    OUTPUT:--------\n$output\n--------\n    ERROR: $error\n" ); }
  return $closeParam;
}


/**
 * Return the user plus system CPU time of all terminated and waited-for child processes of this PHP process.
 *
 * @return float  CPU seconds
 */
private static function childCpuTime () : float {
  $ru = getrusage (1);                                                              // 1 = RUSAGE_CHILDREN
  return $ru['ru_utime.tv_sec'] + $ru['ru_utime.tv_usec'] / 1e6 + $ru['ru_stime.tv_sec'] + $ru['ru_stime.tv_usec'] / 1e6;
}









}
