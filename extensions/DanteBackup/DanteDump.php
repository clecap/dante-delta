<?php

use MediaWiki\MediaWikiServices;

require_once ("DanteCommon.php");
require_once ("extensions/DanteCommon/ServiceEndpointHelper.php");


$DUMP_PATH = "/var/www/html/wiki-dir/dump";    // TODO: allow this as setting in the configuration file !! 

global $IP;
$FILE_LIST_BACKUP = "$IP/extensions/DanteBackup/list_of_files_to_backup";

class DanteDump extends DanteSpecialPage {

#region  DATA which may be moved around in the scope of this class
public string $archiveName;
public string $dbName; 
public string $tarName;
public string $srces;
public string $srcFeatures;
public bool $files;
public bool $db;
public string $target;
public bool $zip;       //     if true: we should use compression
public bool $enc;       //     if true: we should use encryption

//public      $srcFiles;  //     true (all files) or name of a file (including namespace) listing every file to be dumped

// FEATURES:

private     $aesPW;       // password to be used by aes, in case $enc is true
private     $bucketName;  // name of aws bucket to be used
#endregion

public function __construct () { parent::__construct( 'DanteDump', 'dante-dump' ); }

// page provides hint to read-only mode engine that it will not write
public function doesWrites():bool {return false;}

protected function showForm (): void {
 // send post data to THIS url and add action=submit to the URL so we can distinguish showing this page from submitting data to it
  $action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );  
  $out    = $this->getOutput();
  $out->addModuleStyles ( ['ext.DanteBackup.specialpage.styles'] );  // this is a styles-only module which mediawiki loads early enough to prevent FOUC
  $out->addModules      ( ['ext.DanteBackup.specialpage' ] );        // this loads JS, later

  $out->addHTML (wfMessage ("dante-page-dump-intro"));             // show some intro text
  $out->addHelpLink( 'index.php?title=Help:DanteDump', true );  // provide a help link   // TODO: must fill with contents

  // describe the form to be displayed
  $form = array_merge ( DanteCommon::HEADER, DanteCommon::SOURCE_FEATURES, DanteCommon::getTARGET_FORM(), DanteCommon::FEATURES );  // generate the form
  self::standardForm ($form, $action, "dump", "Do the dump");
}


protected function getSpecificCommands ( $formId ): mixed {
  global $DUMP_PATH;

  $request = $this->getRequest();

  // pick up all the data required for dispatching
  $this->archiveName = $request->getVal ( 'archiveName' );     danteLog ("DanteBackup", "archiveName $this->archiveName \n");
  $this->dbName      = $request->getVal ( 'dbName' );          danteLog ("DanteBackup", "dbName $this->dbName \n");
  $this->tarName     = $request->getVal ( 'tarName' );         danteLog ("DanteBackup", "tarName $this->tarName \n");

// TODO: must modify the javascript in the form that the dump identification tag does not accept illegal characters

  if ( strpos( $this->tarName, '*') !== false) { $this->tarName = ""; }  // squash it to empty if not proper


  $this->srces       = $request->getVal ( 'srces' );           danteLog ("DanteBackup", "srces $this->srces \n");
  $this->srcFeatures = $request->getVal ( 'srcFeatures' );     danteLog ("DanteBackup", "srcFeatures $this->srcFeatures \n");
  $this->files       = $request->getVal ( 'files' );           danteLog ("DanteBackup", "files $this->files \n");
  $this->db          = $request->getVal ( 'db' );              danteLog ("DanteBackup", "db $this->db \n");
  $this->target      = $request->getVal ( 'target' );          danteLog ("DanteBackup", "target $this->target \n");
  $this->zip         = $request->getVal ( 'compressed' ) ?? false;   danteLog ("DanteBackup", "zip $this->zip \n");
  $this->enc         = $request->getVal ( 'encrypted' )  ?? false;   danteLog ("DanteBackup", "enc $this->enc \n");
      
 // get the values stored in the preferences
  $this->bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );
  $this->aesPW            = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-encpw' );

  danteLog ("DanteBackup", "bucketName $this->bucketName\n");
  danteLog ("DanteBackup", "aesPW $this->aesPW\n");  // TODO: remove from logging, make more secure

  // build production commands, prepend with a pipefail so we do not mask error condistions along the pipe and append compression and encryption
  $generatePages =  self::wrap ( $this->getPageCommand () );
  $generateDb    =  self::wrap ( $this->getDBCommand () );
  if ($this->tarName !== "") {$generateTar   =  self::wrap ( $this->getFilesCommand () );} else {$generateTar = "";}  // check if we really want file tar archive

  $txt = null;
  switch ( $this->target ) {
    case "aws":      
      $generatePages  .= " | /opt/myenv/bin/aws s3 cp -  s3://{$this->bucketName}/".$this->archiveName;
      $generateDb     .= " | /opt/myenv/bin/aws s3 cp -  s3://{$this->bucketName}/".$this->dbName;
      if ( $generateTar !== "" ) {$generateTar    .= " | /opt/myenv/bin/aws s3 cp -  s3://{$this->bucketName}/".$this->tarName; }
      return [$generatePages, $generateDb, $generateTar];  // that's the commands to be executed
      break;
    case "github":   /*  $txt = self::dumpToAWS_FG  ( $this);  */ break;
    case "ssh":     /*  $txt = DanteCommon::dumpToAWS_FG  ( $this); */  break;
    case "client":        $this->getOutput()->disable();   self::dumpToBrowser ( $this );  break;
    case "server":     
      if ( !is_dir ( $DUMP_PATH ) )    { if ( !mkdir($DUMP_PATH, 0777, true) ) {throw new Exception("Failed to create directory: $DUMP_PATH"); } }
      if ( !is_writable ($DUMP_PATH) ) { if ( !chmod($DUMP_PATH, 0777) ) { throw new Exception("Directory is not writable and chmod failed: $DUMP_PATH"); } }
      $generatePages  .= " > ".$DUMP_PATH."/".$this->archiveName;
      $generateDb     .= " > ".$DUMP_PATH."/".$this->dbName;
      if ( $generateTar !== "" ) {$generateTar    .= " >" .$DUMP_PATH. "/".$this->tarName; }
      return [$generatePages, $generateDb, $generateTar];  // that's the commands to be executed
      break;
    default:              throw new Exception ("Illegal value found for target:" . $values["target"] . " This should not happen");
  }

  return [];  // should not happen
}





// TODO: make password more secure !!!
/** wraps a command with
 *    1) a leading pipefail to prevent errors along the pipe to be masked
 *    2) piping into first compression and then encryption
 */
private function wrap ( $cmd ) {
  if ( $cmd === "" ) {return "";}
  return "set -o pipefail; " . $cmd . ($this->zip ? " | gzip " : " ") . ($this->enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC " : " ") ; 
}



// TODO: deprecate this in favor of wrap
// command decorator for a command $cmd which produces a stream while dumping; result then gets piped/redirected into different sinks
private static function cmdZipEncDump ( $cmd, $zip, $enc ) {
  return "set -o pipefail; " . $cmd . ($zip ? " | gzip " : " ") . ($enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC " : " ") ; 
}



/*
private function dumpToWindow ( ) {
  header( "Content-type: text/plain; charset=utf-8" );
  $cmd = $this->getCommand ();
  $cmd = self::cmdZipEncDump ($cmd, $this->zip, $this->enc, $this->aesPW);
  $cmd = $cmd . " 2>&1 ";  // redirecting stderror gives us the chance of seeing error messages in the window 
  $result = 0; 
  passthru ($cmd, $result);
  echo "ERROR: $result, $cmd";
}
*/

/*
private static function dumpToBrowser ($obj, ) {
  $filename = DanteCommon::generateFilename( $obj->getNativeExtension(), $obj->zip, $obj->enc);
  DanteCommon::contentTypeHeader ($obj->zip, $obj->enc);
  header( "Content-disposition: attachment;filename={$filename}" );
  $cmd = $obj->getCommand ();
  $cmd = self::cmdZipEncDump ($cmd, $obj->zip, $obj->enc, $obj->aesPW);
  $result = 0; 
  passthru ($cmd, $result);
}
*/


// generate and return a command for dumping pages
// as side effect: generates a list of files to dump
public function getPageCommand (  ) {
  global $IP;
  global $FILE_LIST_BACKUP;

  // fullOpt controls the options on pages present
  switch ( $this->srcFeatures ) {
    case "current":            $fullOpt = "--current"; break;
    case "allrevisions":       $fullOpt = "--full";    break;
    default: throw new Exception ("Wrong value for parameter srcFeatures: $this->srcFeatures");
  }

  // filesOpt controls which information on wiki uploaded files is present
  switch ( $this->files ) {
    case "nofiles":  $filesOpt = "";          break;
    case "metadata": $filesOpt = "--uploads"; break;
    case "separate": $filesOpt = "";         break;
    case "include":  $filesOpt = "--uploads --include-files"; break;
    default: throw new Exception ("wrong vlaue for parameter files: $this->files");
  }

  danteLog ("DanteBackup", "Source specification is: " . $this->srces . "\n");

  // srces controls which pages are present in the xml page archive 

// TODO: CLEAN - truncate the file !!!!!!!!!

  switch ($this->srces) {
    case "nopages":            touch ($FILE_LIST_BACKUP);  break;  // should be empty; xml dump may still contain namespace names etc.
    case "listed":             InfoExtractor::articleExtract ("Backupfiles", true, $FILE_LIST_BACKUP);  break;  // TODO check 
    case "category":           InfoExtractor::appendCategoryArticlesToFile ("backup", $FILE_LIST_BACKUP); break;
    
    case "categories":         $this->makeLongList ();  break;// TODO check and fix
    case "all":               $srcOpt = " ";  break;  // TODO: WHAT ABOUT THE DRYRUN in this case !!!!!  where do we get the list of all files ???
    // TODO: also want option: all with the exception of the system files (what are they ???)
    default: throw new Exception ("Wrong value for parameter pages: $this->srces");
  }

  $command = " php $IP/maintenance/dumpBackup.php $fullOpt $filesOpt $srcOpt";
  danteLog ("DanteBackup", "\nDanteDump: getCommand: Command for dumping is: " . $command);

  return $command;
}


private function getDBCommand () {
  global $wgDBname, $wgDBserver, $wgDBpassword, $wgDBuser;
  $cmd = "mysqldump --skip-ssl --host=$wgDBserver --user=$wgDBuser --password=$wgDBpassword --single-transaction $wgDBname " . ($this->zip ? " | gzip " : "") . ($this->enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000  -pass env:LOCAL_FILE_ENC " : "" );
  return $cmd;
}

private function getFilesCommand () {
  global $wgUploadDirectory;
  $cmd = "tar -czvf - --exclude='thumb' \"$wgUploadDirectory\" ";  // TODO Test this. Do we really want -v (lists all files ??)  and what about -z ... is this not doubling the compression thingie???
  return $cmd;
}


// TODO: if the command execution fails - we need a more prominent, red error message at the end produced by the javascript for the serviceEndpoint

// TODO: List Backups should also report tar files.
// TODO Restore should also apply to tar files 

// TODO: getConfigFile and makeLongList and makeCatFileList  should be moved into DanteCommon extension since we need that for other stuff timezone_offset_get
// TODO: extension.json of DanteBackup should declare that we need DanteCommon installed as prerequistite.  The others maybe as well !!
// TODO factor some stuff from Parsifal into DanteCommon
// TODO: remove that branch in Parsifal .... github

// generates a list of files to backup in file $IP/extensions/DanteBackup/list_of_files_to_backup
// TODO: maybe already unused - DEPRECATE
private static function listOfFiles ( $srces ) {
  global $IP;
  @unlink ( "$IP/extensions/DanteBackup/list_of_files_to_backup");  // delete current list of files to dump // TODO: trun this into a fixed constant or filename somewhere
  switch ( $srces ) {
    case "nopages":         touch ("$IP/extensions/DanteBackup/list_of_files_to_backup");  break;  // file should be empty, xml dump might still contain namespace names and more
    case "listed":          InfoExtractor::articleExtract ("Backupfiles", true, "$IP/extensions/DanteBackup/list_of_files_to_backup");  break;  // TODO check and fix
    case "category":        self::makeCatFileList  ("backup");  break;// TODO check and fix
    case "categories":      self::makeLongList ();  break;// TODO check and fix
    case "all":             $srcOpt = " ";  break;  // TODO: WHAT ABOUT THE DRYRUN in this case !!!!!  where do we get the list of all files ???
    // TODO: also want option: all with the exception of the system files (what are they ???)
    default: throw new Exception ("Wrong value for parameter pages: $srces");
  }
}


// given the $titleName of a page inside of MediaWiki name, generate a file named list_of_files_to_backup
// which contains the files listed in this MediaWiki file
// TODO: migrate into DanteCommon extension
private static function getConfigFileDEPRECATED ( $titleName ) {
  global $IP;
  danteLog ("DanteBackup", "Will get article by name=" . $titleName);                                                 
  $title      = Title::newFromText( $titleName, NS_MEDIAWIKI );             // build title object for MediaWiki:Corefiles
  $wikipage   = new WikiPage ($title);                                                // get the WikiPage for that title
  $contentObject = $wikipage->getContent();                                                         // and obtain the content object for that
  if ($contentObject ) {                                                                            // IF we have found a content object for this thing
    $code    = ContentHandler::getContentText ( $contentObject );
    $code    = extractPreContents ($code);
    danteLog ("DanteBackup", "Found extracted contents in this file as: $code \n");
    unlink ("$IP/extensions/DanteBackup/list_of_files_to_backup");
    $ret = file_put_contents( "$IP/extensions/DanteBackup/list_of_files_to_backup",  $code, LOCK_EX);   
    danteLog ("DanteBackup", "Wrote list of files to backup\n");
  }   
  else { 
    danteLog ("DanteBackup", "\n\n MediaWiki:"  .$titleName. " could not be found \n\n");
    return "Could not find MediaWiki:" .$titleName;}
}



// TODO: DanteInitialStore.php: makeList is much better !!
// TODO: migrate into DanteCommon extension
// TODO: must lock this file during the entire Dump process in order not to have a second concurrent Dump process !!!!!!!!!!
// given the name of a category, append to list_of_files_to_backup the title of all articles belonging (directly) to that category

/**
 *  Given the name of a MediaWiki category, append to file $fileName the title of all articles belonging to this category 
 *  @param [string] $category  Name of the category
 *  @param [string] $fileName  Name of the file
 */
// TODO: DEPRECATE THIS APPROACH - way tooo complicated !!! already have a replacement 
private static function makeCatFileList ( $category, $fileName ) {
  global $wgScriptPath, $wgServer, $IP;

  // $endPoint =  $wgServer . "/" . $wgScriptPath . "/api.php";      // TODO: does not work inside of a docker container connected to a reverse proxy
  $endPoint =   "http://localhost/" . $wgScriptPath . "/api.php";    // TODO: NOT SUFFICIENTLY CORRECT !!!! should work inside of a docker container

  $params = ["action" => "query", "list" => "categorymembers", "cmtitle" => "Category:".$category, "format" => "json"];
  $url = $endPoint . "?" . http_build_query( $params );
  danteLog ("DanteBackup", "\n\nInitializing curl for URL= ". $url ."\n\n");
  $ch = curl_init( $url );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  $output = curl_exec( $ch );

  // danteLog ("DanteBackup", "\n\nType of output:: ". gettype( $output ) ." and value " . ($output  ? "TRUE" : "FALSE" )."\n\n");
  // danteLog ("DanteBackup", "\n\nRESULT of curl category listing: ". print_r($output, true) ."\n\n FINISHED");
  danteLog ("DanteBackup", "\nCurl Last error was: " . curl_error($ch)."  \n\n");
  $result = json_decode( $output, true );
  //danteLog ("DanteBackup", print_r($result, true) . "\n\n");
  $titleList = array();  // collect in array first before writing to file
  foreach( $result["query"]["categorymembers"] as $page ) {
    danteLog("DanteBackup", $page["title"] . "\n" );
    file_put_contents( "$IP/extensions/DanteBackup/list_of_files_to_backup",  $page["title"]."\n", LOCK_EX | FILE_APPEND);  
  }
  danteLog ("DanteBackup", "\n\n Curl API access FINISHED\n\~");
  curl_close( $ch );  // close sessions and free all ressources
  return;
}


// TODO: migrate into DanteCommon extension
// for all categories in MediaWiki:Backupcategories get the direct files and append them
private static function makeLongList () {
  global $IP;
  danteLog ("DanteBackup", "Will get at MediaWiki:Backupcategories \n");                                                 
  $title      = Title::newFromText( "Backupcategories", NS_MEDIAWIKI );                       // build title object for MediaWiki:Backupcategories
  $wikipage   = new WikiPage ($title);                                                        // get the WikiPage for that title
  $contentObject = $wikipage->getContent();                                                   // and obtain the content object for that
  if ($contentObject ) {                                                                      // IF we have found a content object for this thing
    $code    = ContentHandler::getContentText ( $contentObject );
    $code    = extractPreContents ($code);
    danteLog ("DanteBackup", "Found extracted contents in this file as:\n $code \n");
    danteLog ("DanteBackup", "Type is: ". gettype($code) ." \n");
    $arr = preg_split("/\r\n|\n|\r/", $code);
    danteLog ("DanteBackup", "Got: " . print_r ($arr, true) . " \n");

    foreach ( $arr as $categ ) { self::makeCatFileList ($categ); }
  }   
  else { danteLog ("DanteBackup", "\n\n MediaWiki:Backupcategories could not be found \n\n"); }  // TODO: maybe rather exception
}


// Helper function to get subcategories of given categories in MediaWiki.
private static function getSubcategories(array $categories) {
  $allSubcategories = [];
  foreach ($categories as $category) {
    $subcategories = self::fetchSubcategories($category);                          // Fetch subcategories for the current category
    $allSubcategories = array_merge($allSubcategories, $subcategories);      // Merge subcategories with the result array
  }
  $allSubcategories = array_unique($allSubcategories);                       // Remove duplicates
  return $allSubcategories;
}

// Fetches subcategories of a given category using MediaWiki API.
private function fetchSubcategories($category) {
  $subcategories = [];
  $continue = '';

  $apiEndpoint =  wfScript( 'api' );  // 'https://your-mediawiki-site/api.php';    // MediaWiki API endpoint // TODO
  do {
    $queryParams = [
      'action' => 'query',
      'list' => 'categorymembers',
      'cmtitle' => 'Category:' . $category,
      'cmtype' => 'subcat',
      'cmlimit' => 'max',
      'format' => 'json',
    ];
    if ($continue) {$queryParams['cmcontinue'] = $continue;}

    // Make the API request
    $url = $apiEndpoint . '?' . http_build_query($queryParams);
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    if (isset($data['query']['categorymembers'])) {
      foreach ($data['query']['categorymembers'] as $member) {
        $subcategories[] = str_replace('Category:', '', $member['title']);
      }
    }
    $continue = isset($data['continue']['cmcontinue']) ? $data['continue']['cmcontinue'] : '';
  } while ($continue);

  return $subcategories;
}


// Fetches all page names from a MediaWiki site across all namespaces.
function getAllPageNames(): array {
  $allPageNames = [];
  $continue = '';

  $apiEndpoint = wfScript( 'api' );  // 'https://your-mediawiki-site/api.php';      // MediaWiki API endpoint

  do {
    // Build the query parameters  // TODO: check: does this REALLY ALWAYS provide all files ??? not sure. apilimit !!  must know as relevant for DUMP !!!!
    $queryParams = ['action' => 'query',  'list' => 'allpages',  'aplimit' => 'max',  'format' => 'json' ];

    if ($continue) {$queryParams['apcontinue'] = $continue;}

    // Make the API request
    $url = $apiEndpoint . '?' . http_build_query($queryParams);
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if (isset($data['query']['allpages'])) {
      foreach ($data['query']['allpages'] as $page) {
        $allPageNames[] = $page['title'];
      }
    }

    $continue = isset($data['continue']['apcontinue']) ? $data['continue']['apcontinue'] : '';

  } while ($continue);

  return $allPageNames;
}



}  // end of class