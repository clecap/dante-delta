<?php

require_once ("DanteCommon.php");

class DanteDump extends SpecialPage {

  public bool $all;       //     if true: take all revisions 
  public bool $meta;      //     if true: include upload actions
  public bool $files;     //     if true: include file contents
  public      $srcFiles;  //     true (all files) or name of a file (including namespace) listing every file to be dumped

public function __construct () { parent::__construct( 'DanteDump', 'dante-dump' ); }

public function getGroupName() {return 'dante';}

public function execute( $par ) {
  if (! $this->getUser()->isAllowed ("dante-dump") ) { $this->getOutput()->addHTML ("You do not have the permission to dump."); return;}  

  $this->setHeaders();
  $this->getOutput()->addStyle ("../extensions/DanteBackup/danteBackup.css");  // htmlform-tip  // TODO: really needed ?? // TODO: IMPROVE PATH

  // pick up the request data in general
  $request            = $this->getRequest();
  $names              = $request->getValueNames();                                                         danteLog ("DanteBackup", "names:  " . print_r ( $names,  true )."\n"  );
  $values             = $request->getValues (...$names);                                                   danteLog ("DanteBackup", "values: " . print_r ( $values, true )."\n"  );

  // pick up
  $zip                = (isset ($values["compressed"])           ? $values["compressed"]        : null );  danteLog ("DanteBackup", "zip:  " . $zip. "\n");
  $enc                = (isset ($values["encrypted"])            ? $values["encrypted"]         : null );  danteLog ("DanteBackup", "enc:  " . $enc). "\n";

  if      ( isset ($values["srcFeatures"]) && strcmp ($values["srcFeatures"], "current") == 0) { $this->all = false; }
  else if ( isset ($values["srcFeatures"]) && strcmp ($values["srcFeatures"], "all")     == 0) { $this->all = true;  }
  else                                                                                         { $this->all = false; }
  danteLog ("DanteBackup", "all=" . $this->all . "\n"); 

  if ( isset ($values["srces"]) ) { $this->srcFiles = $values["srces"];} else {  $this->srcFiles = "all";}

  danteLog ("DanteBackup", "srcFiles=" . $this->srcFiles. "\n"); 

  $this->meta    = true;   // always include file upload action metadata with File: pages  //   = (isset ($values["meta"])    ? $values["meta"]    : false  ); danteLog ("DanteBackup",  "meta:  " . $this->meta . "\n"); 
  $this->files   = true;  // always include file contents with File: pages   // = (isset ($values["files"])    ? $values["files"]  : false );  danteLog ("DanteBackup", "files:  " . $this->files. "\n");
      
 // get the values stored in the preferences
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );
  $aesPW            = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-encpw' );

  // dispatch function
  if ( isset ($values["target"] ) ) {  // if we have this set, we are called from the form. We execute the tasks and return.
    $txt = null;
    switch ($values["target"]) {
      case "window":        $this->getOutput()->disable();   DanteCommon::dumpToWindow  ( $this, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW );  break;
      case "browser":       $this->getOutput()->disable();   DanteCommon::dumpToBrowser ( $this, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW );  break;
      case "awsFore":       $txt = DanteCommon::dumpToAWS_FG ( $this,  $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW);   break;
      case "awsBack":       $txt = DanteCommon::dumpToAWS_BG ( $this, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW);  break;
      case "serverFore":    $txt = DanteCommon::dumpToServer ( $this, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW, false);   break;
      case "serverBack":    $txt = DanteCommon::dumpToServer ( $this, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW, true);      break;
      default:              throw new Exception ("Illegal value found for target:" . $values["target"] . " This should not happen");
    }
  if ( $txt !== null ) { $this->getOutput()->addHTML ($txt); }

  return;
  }


  $this->getOutput()->addHTML (wfMessage ("dante-page-dump-intro"));  // show some intro text

  // describe the form to be displayed
  $formDescriptor2 = array_merge ( DanteCommon::SOURCE_FEATURES, DanteCommon::getTARGET_FORM(), DanteCommon::FEATURES );  // generate the form

  $htmlForm2 = new HTMLForm( $formDescriptor2, $this->getContext(), 'dumpform' );
  $htmlForm2->setSubmitText( 'Dump Pages' );
  $htmlForm2->setSubmitCallback( [ $this, 'processInput' ] );
  $htmlForm2->show();
}

  // generate and returns a command for dumping pages
  public function getCommand (  ) {
    global $IP;
    $fullOpt           = ( $this->all      ? "--full "         : "--current");
    $includeFilesOpt   = ( $this->meta     ?  "--uploads"      : " ");
    $filesOpt          = ( $this->files    ? "--include-files" : " ");

  danteLog ("DanteBackup",  "NOW!\n");

  @unlink ( "$IP/extensions/DanteBackup/list_of_files_to_backup");  // delete list of files to backup

  if ( strcmp ($this->srcFiles, "corefiles")        == 0 )   { danteLog ("DanteBackup", "case: corefiles \n");        $srcOpt = "--pagelist=$IP/extensions/DanteBackup/list_of_files_to_backup";    $this->getConfigFile ("Corefiles");    }
  if ( strcmp ($this->srcFiles, "backupfiles")      == 0 )   { danteLog ("DanteBackup", "case backupfiles \n");       $srcOpt = "--pagelist=$IP/extensions/DanteBackup/list_of_files_to_backup";    $this->getConfigFile ("Backupfiles");  }
  if ( strcmp ($this->srcFiles, "backupcategory")   == 0 )   { danteLog ("DanteBackup", "case backupcategory \n");    $srcOpt = "--pagelist=$IP/extensions/DanteBackup/list_of_files_to_backup";    $this->makeCatFileList  ("backup");  }
  if ( strcmp ($this->srcFiles, "backupcategories") == 0 )   { danteLog ("DanteBackup", "case backupcategories \n");  $srcOpt = "--pagelist=$IP/extensions/DanteBackup/list_of_files_to_backup";    $this->makeLongList ();  }
  if ( strcmp ($this->srcFiles, "all")              == 0 )   { danteLog ("DanteBackup", "case all \n");               $srcOpt = " "; }

  $command = " php $IP/maintenance/dumpBackup.php $fullOpt $includeFilesOpt $filesOpt $srcOpt";
  danteLog ("DanteBackup", "\nCommand for dumping is: " . $command);

    return $command;
  }


// backup script needs a file which lists every file to be backed up
private function getConfigFile ($filename) {
  global $IP;
  danteLog ("DanteBackup", "Will get config file at MediaWiki namespace; filename= " . $filename);                                                 
  $title      = Title::newFromText( $filename, NS_MEDIAWIKI );                                // build title object for MediaWiki:Corefiles
  $wikipage   = new WikiPage ($title);                                                        // get the WikiPage for that title
  $contentObject = $wikipage->getContent();                                                   // and obtain the content object for that
  if ($contentObject ) {                                                                      // IF we have found a content object for this thing
    $code    = ContentHandler::getContentText ( $contentObject );
    $code    = extractPreContents ($code);
    danteLog ("DanteBackup", "Found extracted contents in this file as: $code \n");
    unlink ("$IP/extensions/DanteBackup/list_of_files_to_backup");
    $ret = file_put_contents( "$IP/extensions/DanteBackup/list_of_files_to_backup",  $code, LOCK_EX);   
    danteLog ("DanteBackup", "Wrote list of files to backup\n");
  }   
  else { 
    self::debugLog ("\n\n MediaWiki:"  .$filename. " could not be found \n\n");
    return "Could not find MediaWiki:" .$filename;}
}



// given the name of a category, append to list_of_files_to_backup the title of all articles belonging (directly) to that category
private function makeCatFileList ($category) {
  global $wgScriptPath, $wgServer, $IP;

  // $endPoint =  $wgServer . "/" . $wgScriptPath . "/api.php";      // does not work inside of a docker container connected to a reverse proxy
  $endPoint =   "http://localhost/" . $wgScriptPath . "/api.php";    // should work inside of a docker container

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


// for all categories in MediaWiki:Backupcategories get the direct files and append them
private function makeLongList () {
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
  else { self::debugLog ("\n\n MediaWiki:Backupcategories could not be found \n\n"); }
}













  // return the native file extension this dumper would return
  public function getNativeExtension () { return "xml";}


// return:   true: form will not display again
//           false: from WILL be displayed again
//           string: show the string as error message together with the form
public static function processInput( $formData ) {
  return true;
  // return print_r ( $formData,  true ) ;
}

}