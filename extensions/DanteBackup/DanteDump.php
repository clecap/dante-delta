<?php

require_once ("DanteCommon.php");

class DanteDump extends SpecialPage {

public bool $all;       //     if true: take all revisions, otherwise only take current revision
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
  $filter    = (isset ($values["filter"])            ? $values["filter"]        : null );  danteLog ("DanteBackup", "filter:  " . $filter. "\n");
  $zip       = (isset ($values["compressed"])        ? $values["compressed"]    : null );  danteLog ("DanteBackup", "zip:  " . $zip. "\n");
  $enc       = (isset ($values["encrypted"])         ? $values["encrypted"]     : null );  danteLog ("DanteBackup", "enc:  " . $enc). "\n";

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
      case "window":        $this->getOutput()->disable();   self::dumpToWindow  ( $this, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW );  break;
      case "browser":       $this->getOutput()->disable();   self::dumpToBrowser ( $this, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW );  break;
      case "list":          $this->getOutput()->disable();   self::dumpToList (); break;

      case "awsFore":       $txt = self::dumpToAWS_FG ( $this,  $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW);   break;
      case "awsBack":       $txt = self::dumpToAWS_BG ( $this, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW);  break;

/*
      case "githubFore":       $txt = DanteCommon::dumpToAWS_FG ( $this,  $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW);   break;
      case "githubBack":       $txt = DanteCommon::dumpToAWS_BG ( $this, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW);  break;
      case "sshFore":       $txt = DanteCommon::dumpToAWS_FG ( $this,  $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW);   break;
      case "sshBack":       $txt = DanteCommon::dumpToAWS_BG ( $this, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW);  break;
*/

      case "serverFore":    $txt = self::dumpToServer ( $this, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW, false);   break;
      case "serverBack":    $txt = self::dumpToServer ( $this, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW, true);      break;
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



// command decorator for a command $cmd which produces a stream while dumping; result then gets piped/redirected into different sinks
private static function cmdZipEncDump ( $cmd, $zip, $enc ) {
  return "set -o pipefail; " . $cmd . ($zip ? " | gzip " : " ") . ($enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -pass env:LOCAL_FILE_ENC " : " ") ; 
}



private static function dumpToList ( $src ) {



}


private static function dumpToWindow () {
  header( "Content-type: text/plain; charset=utf-8" );
  $cmd = $obj->getCommand ();
    $cmd = self::cmdZipEncDump ($cmd, $zip, $enc, $aesPW);
    $cmd = $cmd . " 2>&1 ";  // redirecting stderror gives us the chance of seeing error messages in the window 
    $result = 0; 
    $ptResult = passthru ($cmd, $result);
    echo "ERROR: $ptResult, $result, $cmd";
}


  private static function dumpToBrowser ($obj, $zip, $enc, $aesPW) {
    $filename = DanteCommon::generateFilename( $obj->getNativeExtension(), $zip, $enc);
    DanteCommon::contentTypeHeader ($zip, $enc);
    header( "Content-disposition: attachment;filename={$filename}" );
    $cmd = $obj->getCommand ();
    $cmd = self::cmdZipEncDump ($cmd, $zip, $enc, $aesPW);
    $result = 0; 
    passthru ($cmd, $result);
  }


// obj go away 
// background // TODO redo completelly
public static function dumpToAWS_BG ($obj, $bucketName, $zip, $enc, $aesPW) {
  $cmd = $obj->getCommand ();  // TODO: pipefail ß?????
  $cmd = self::cmdZipEncDump ($cmd, $zip, $enc, $aesPW);

  $name    = "s3://$bucketName/" . DanteCommon::generateFilename(  $obj->getNativeExtension(), $zip, $enc);
  $cmd = $cmd . " | /opt/myenv/bin/aws s3 cp - $name ";
  $cmd = "( $cmd ) &>DANTEDBDump_LOCAL_ERROR_FILE & ";  // TODO: correct redirect ?  test
  $retCode = Executor::executeAWS_FG_RET ( new AWSEnvironmentPreparatorUser ($obj->getUser()), $cmd, $output, $error );
  if ($retCode == 0) { return "<div>The background execution has been started. For success check listing of backups or <a href='../DANTEDBDump_LOCAL_ERROR_FILE'>Error File</a></div>"; }
  else {return "<div>The execution failed with return value $retCode. We got the following error message: <br><div style='color:red;'>" . implode ("<br>", explode ("\n", $error)) . "</div>";   }
}



// foreground
public static function dumpToAWS_FG ( $obj, $bucketName, $zip, $enc, $aesPW) {
  $cmd     = "set -o pipefail; " . $obj->getCommand ( );  // pipefail prevents masking of error conditions along the pipe
  $cmd     = self::cmdZipEncDump ($cmd, $zip, $enc, $aesPW);
  $name    = "s3://$bucketName/" . DanteCommon::generateFilename ($obj->getNativeExtension(), $zip, $enc);
  $cmd    .= " | /opt/myenv/bin/aws s3 cp - $name ";

  $retText = "";  // accumulates this and the subsequent listing command

  $env = self::getEnvironmentUser ($obj->getUser());
  $retCode = Executor::executeAWS_FG_RET ( $cmd, $env, $output, $error );
  
  $retText .= "<h3>Command</h3><code>$cmd</code>"; 
  $retText .= "<h3>Information sent to <code>stdout</code></h3>" . nl2br (htmlspecialchars ($output, ENT_QUOTES, 'UTF-8')) . "";
  $retText .= "<h3>Information sent to <code>stderr</code></h3>" . nl2br (htmlspecialchars ( $error,  ENT_QUOTES, 'UTF-8')) . "";
  if ($retCode == 0) { $retText .= "<h3>Execution successful</h3>";}
  else               { $retText .= "<h3 style='color:red;'>Execution failed, return code $retCode </h3>";}

  $retText .= "<h3>Directory listening of $bucketName</h3>";

/*
  $retCode = Executor::executeAWS_FG_RET ( " /opt/myenv/bin/aws s3 ls $bucketName --human-readable ", $env ,  $output, $error );
  if ($retCode != 0) { $retText .= "<hr>ERROR ".   preg_replace ("/\n/", "<br>", $error) . "<hr>";} 
  else               { $retText .= "<hr>".   preg_replace ("/\n/", "<br>", $output) . "<hr>";}
*/

  $cmd = "/opt/myenv/bin/aws s3api list-objects-v2 --bucket {$bucketName} --query 'Contents[].[Key,LastModified,Size]' --output json";
  $retCode = Executor::executeAWS_FG_RET ( $cmd, $env, $output, $error );
  $objects = json_decode($output, true);  // Decode the JSON output into a PHP array
  if (is_array($objects)) {
    usort($objects, function ($a, $b) {return strtotime($b[1]) - strtotime($a[1]);});    // Sort the objects by LastModified in descending order
    $retText .= "<ul>";
    foreach ($objects as $object) { $retText .= '<li><span style="display:inline-block;width:400px;min-width:400px;">' . $object[0] . '</span><span style="display:inline-block;width:300px;">' . $object[1] . "</span>";
      $retText .= "<span style='display:inline-block;width:400px;'>". number_format ($object[2]/ (1024*1024), 2)  . "[MB] </span></li>\n"; }
    $retText .= "</ul>";
  }
  else { $retText .= "Did not get reply from aws";}
  return $retText;
}




// TODO obj go away
public static function dumpToServer ( $obj, $name, $zip, $enc, $aesPW, $background ) {
  global $IP;

  $dirPath = $IP. "/".DanteCommon::DUMP_PATH;
  echo "---------------------------$IP -----------".$dirPath;
  if ( !file_exists ( $dirPath ) ) { mkdir ( $dirPath, 0755); }
  $filename = DanteCommon::generateFilename( $obj->getNativeExtension(), $zip, $enc);
  $errorFileName = DanteCommon::DUMP_PATH."/DANTEDBDump_ERROR_FILE$filename";

  $cmd = $obj->getCommand ();
  $cmd = self::cmdZipEncDump ($cmd, $zip, $enc, $aesPW);
  $cmd .= " > ".DanteCommon::DUMP_PATH."/".$filename;

  if ($background) {$cmd = "( $cmd ) &> $errorFileName & ";}
  $ret = Executor::execute ( $cmd, $output, $error, $duration);

  if ($background) {
    if ($ret == 0) { return "<div>The execution was started successful. Command was: $cmd </div>"; }
    else {return "<div>The execution failed with return value $retCode. We got the following error message: <br><div style='color:red;'>" . implode ("<br>", explode ("\n", $error)) . "</div>"; }
  } 
  else {  // when running in foreground 
    return "<div>Execution of $cmd return value $ret and output $output and error $error</div>";
   }
}



// generate and return a command for dumping pages
// as side effect: generates a list of files to dump
public function getCommand (  ) {
  global $IP;
  $fullOpt           = ( $this->all      ? "--full "         : "--current");
  $includeFilesOpt   = ( $this->meta     ?  "--uploads"      : " ");
  $filesOpt          = ( $this->files    ? "--include-files" : " ");

  danteLog ("DanteBackup",  "NOW!\n");

  danteLog ("DanteBackup", "Source specification is: " . $this->srcFiles . "\n");

  // generate a file which contains a list of files to dump
  @unlink ( "$IP/extensions/DanteBackup/list_of_files_to_backup");  // delete list of files to dump
  switch ( $this->srcFiles ) {
    case "corefiles":          $srcOpt = "--pagelist=$IP/extensions/DanteBackup/list_of_files_to_backup";    $this->getConfigFile ("Corefiles");  break;
    case "backupfiles":       $srcOpt = "--pagelist=$IP/extensions/DanteBackup/list_of_files_to_backup";    $this->getConfigFile ("Backupfiles");  break;
    case "backupcategory":    $srcOpt = "--pagelist=$IP/extensions/DanteBackup/list_of_files_to_backup";    $this->makeCatFileList  ("backup");  break;
    case "backupcategories":  $srcOpt = "--pagelist=$IP/extensions/DanteBackup/list_of_files_to_backup";    $this->makeLongList ();  break;
    case "all":               $srcOpt = " ";  break;
  }


  $command = " php $IP/maintenance/dumpBackup.php $fullOpt $includeFilesOpt $filesOpt $srcOpt";
  danteLog ("DanteBackup", "\nCommand for dumping is: " . $command);

  return $command;
}


// given the name of a file inside of MediaWiki namespace, generate a file named list_of_files_to_backup
// which contains the files listed in this MediaWiki file
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
// TODO: DanteInitialStore.php: makeList is much better !!
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


// Helper function to get subcategories of given categories in MediaWiki.
private static function getSubcategories(array $categories) {
  $allSubcategories = [];
  foreach ($categories as $category) {
    $subcategories = fetchSubcategories($category);                          // Fetch subcategories for the current category
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
function getAllPageNames() {
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