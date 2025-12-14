<?php

use MediaWiki\MediaWikiServices;

require_once ("DanteCommon.php");
require_once ("extensions/DanteCommon/ServiceEndpointHelper.php");


$DUMP_PATH = "/var/www/html/wiki-dir/dump";    // TODO: allow this as setting in the configuration file !!  // Path to place the server-local dumps to

global $IP;

class DanteDump extends DanteSpecialPage {

public static $SPEC_PREFIX;  // initialized at end of class

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
    case "github":   /*  $txt = self::dumpToAWS_FG  ( $this);  */ 
      //InfoExtractor::exportAllToTextFiles ("/tmp/gitDump");
      return $this->gitPrepare();
      break;

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
 *
 *  @parameter ?bool If present and true then we must omit the gzip stuff, independently of what $this says. Needed for the tar file
 */
private function wrap ( $cmd, $omitZip = false ) {
  if ( $cmd === "" ) {return "";}
  $retval  = "set -o pipefail; " . $cmd;          // return in a pipe sequence the exit code of the first failing command
  if ($omitZip !== false) {$retval .= ($this->zip ? " | gzip " : " ");}
  $retval .= ($this->enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC " : " ") ; 
  return $retval; 
}



// TODO: deprecate this in favor of wrap
// command decorator for a command $cmd which produces a stream while dumping; result then gets piped/redirected into different sinks
private static function cmdZipEncDump ( $cmd, $zip, $enc ) {
  return "set -o pipefail; " . $cmd . ($zip ? " | gzip " : " ") . ($enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC " : " ") ; 
}


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

  // fullOpt     controls the options on pages present
  switch ( $this->srcFeatures ) {
    case "current":            $fullOpt = "--current"; break;
    case "allrevisions":       $fullOpt = "--full";    break;
    default: throw new Exception ("Wrong value for parameter srcFeatures: $this->srcFeatures");
  }

  // filesOpt    controls which information on wiki uploaded files is present
  switch ( $this->files ) {
    case "nofiles":  $filesOpt = "";          break;
    case "metadata": $filesOpt = "--uploads"; break;
    case "separate": $filesOpt = "";         break;
    case "include":  $filesOpt = "--uploads --include-files"; break;
    default: throw new Exception ("wrong vlaue for parameter files: $this->files");
  }

  danteLog ("DanteBackup", "Source specification is: " . $this->srces . "\n");

  // src controls which pages are present in the xml page archive 
  // prepare the file lists for now
  self::generateSpecFile ( null );  // generate all specification files
  switch ($this->srces) {
    case "nopages":             //  touch ($FILE_LIST_BACKUP);  break;    // should be empty; xml dump may still contain namespace names etc.
    case "all":                   $srcOpt = " ";  break;  
    case "listed":                // NOBREAK
    case "category":              // NOBREAK
    case "categories":            // NOBREAK
    case "categories-indirect":   $srcOpt = self::$SPEC_PREFIX . "this->srces";  break;  
    default: throw new Exception ("Wrong value for parameter pages: $this->srces");
  }

  $command = " php $IP/maintenance/dumpBackup.php $fullOpt $filesOpt $srcOpt";
  danteLog ("DanteBackup", "\nDanteDump: getCommand: Command for dumping is: " . $command);

  return $command;
}


private function gitPrepare () {
 
 


  $USER="clecap";

 $user = $this->getUser();
$TOKEN        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'github-dante-wiki-contents' );

//  $TOKEN="";  // TODO: still must find a secure way to load that GIT access token without committing it to githug !!!!!


// TODO: NOT HE FINAL THING AS IT IS THE INCORRECT TOKEN BY NOW.
 // TODO: need two different cases and tokens: inital contents / system contents - and user dumpToBrowser
 // encrypt and aes might not apply or might not be optimal settings (for github, no binary content recommended to be stored there )


  $GITMAIL="dante-himself@dante.wiki";
  $GITUSER="Dante Wiki System";
  $COMMIT_MESSAGE="Commit by dantewiki itself";

  $REPO="https://$USER:$TOKEN@github.com/clecap/dante-wiki-contents.git";
  $REPO_NAME="dante-wiki-contents";

  $myOutputDir = "/tmp/gitDump";




  // NOTE: below we want to have a cd into the correct directory in every line as every line gets executed in a fresh shell with its (wrong) directory and git
  // in a development container, VS code sometimes patches all kinds of git hooks and environment variables to be connect vs code better to gitPrepare
  // in case we are running in such a container (when developing) we do not want this 
  // that is why we add the following junk
  $unsetCode="unset GIT_ASKPASS && unset SSH_ASKPASS && unset GIT_SSH && unset GIT_SSH_COMMAND && unset GIT_TERMINAL_PROMPT ";
  $adjust="-c core.askPass=  -c credential.helper= -c credential.interactive=never";
  $noHook="-c core.hooksPath=/dev/null";
  $cdRepo="cd $myOutputDir/$REPO_NAME";
  $git="/usr/bin/git";  // want to use original git binary and not a (possibly) vs code patched binary or shell script
   
  $myCmd = ["command" => [InfoExtractor::class, 'exportAllToTextFiles'], 
            "args"    => [ "outDir" => "$myOutputDir/$REPO_NAME", "namespaces" => null, "includeRedirects" => true, "batchSize" => 500, "clean" => true ] ];

  $cmds = [
    "sudo rm -Rf /tmp/gitDump/; mkdir -p $myOutputDir ",    ///// Still rm is hardcoded TODO: cave, danger if we have an interpolation error
    "ls -al /tmp",
    "ls -al $myOutputDir",
    "cd $myOutputDir; $git $adjust $noHook clone --depth 1 $REPO",
    "ls -al /tmp/gitDump",    //// TODO: CAVE: name of the employed repository is used - watch out if we change this for a different user
    "$cdRepo && $git config user.name \"$GITUSER\"",
    "$cdRepo && $git config user.email \"$GITMAIL\"",
    $myCmd,  // now add the fresh files from the wiki to the local git   // TODO: must implement proper filters here 
    "$cdRepo && git add .  && git status ",
   // the following FIRST does a cd and if this succeeds THEN does a diff and if the diff exits with a 1 (which it does when there staged changes exist) only then do a commit
   // this is necessary since a git commit without any staged things would fail with exit code 1 
   // note that we need a command terminator ; at the end of the commit
    "$cdRepo && { git diff --cached --quiet || git commit -m \"$COMMIT_MESSAGE\"; } ",
    "$cdRepo && $git status",
    "$cdRepo && $git push --verbose $REPO",
    "sudo rm -Rf /tmp/gitDump/ ",
  ];
  return $cmds;
}


/**
 * Given a srces specification for files to dump, build a file containing the specified file names under a standardized name
 *
 * @param string $spec Permissible are: "listed", "category", "categories", "categories-indirect" or null
 *                     With null, all permissible files are generated
 * @return void
 */
private static function generateSpecFile ( $spec ) {
  $NAME = self::$SPEC_PREFIX . $spec;
  switch ( $spec ) {
    case "listed":             
      InfoExtractor::articleExtract ("MediaWiki:Backupfiles", true, $NAME);  
      break; 
    case "category":          
      $titles = InfoExtractor::getTitlesOfCategory ("Backup");
      file_put_contents ( $NAME, implode ("\n", $titles) );
      break;
    case "categories":     
      $listOfCategories  = InfoExtractor::articleExtract ("MediaWiki:Backupcategories", true, null);
      $arrayOfCategories = explode ("\n", $listOfCategories);
      $arrayOfCategories = array_filter($arrayOfCategories, function ($str) {return trim($str) !== ''; });  // remove empty entries
      $arrayOfTitles     = InfoExtractor::getTitlesOfCategories ( $arrayOfCategories );
      file_put_contents ( $NAME, implode ("\n", $arrayOfTitles) );
      break;// TODO check and fix
    case "categories-indirect":
       $arrayOfCategories = InfoExtractor::getIndirectSubcategories ("MediaWiki:Backupcategories indirect");
       $arrayOfTitles     = InfoExtractor::getTitlesOfCategories ( $arrayOfCategories );
       file_put_contents ( $NAME, implode ("\n", $arrayOfTitles) );
       break;
    case null:
      self::generateSpecFile ("listed");
      self::generateSpecFile ("category");
      self::generateSpecFile ("categories");
      self::generateSpecFile ("categories-indirect");
      break;

    default: throw new Exception ("Wrong value for parameter pages: $spec");
  }
}






private function getDBCommand () {
  global $wgDBname, $wgDBserver, $wgDBpassword, $wgDBuser;
  $cmd = "mysqldump --skip-ssl --host=$wgDBserver --user=$wgDBuser --password=$wgDBpassword --single-transaction $wgDBname " . ($this->zip ? " | gzip " : "") . ($this->enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000  -pass env:LOCAL_FILE_ENC " : "" );
  return $cmd;
}

private function getFilesCommand () {
  global $wgUploadDirectory;
  $tarOptions = ( $this->enc ? "-cvzf" : "-cvf" );    // encryption is done by tar itself, decryption is automagic when doing tar -x
  $cmd = "tar $tarOptions - --exclude='thumb' \"$wgUploadDirectory\" ";  // TODO Test this. Do we really want -v (lists all files ??)  and what about -z ... is this not doubling the compression thingie???
  return $cmd;
}


// TODO: if the command execution fails - we need a more prominent, red error message at the end produced by the javascript for the serviceEndpoint

// TODO: List Backups should also report tar files.
// TODO Restore should also apply to tar files 

// TODO: getConfigFile and makeLongList and makeCatFileList  should be moved into DanteCommon extension since we need that for other stuff timezone_offset_get
// TODO: extension.json of DanteBackup should declare that we need DanteCommon installed as prerequistite.  The others maybe as well !!
// TODO factor some stuff from Parsifal into DanteCommon
// TODO: remove that branch in Parsifal .... github




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




DanteDump::$SPEC_PREFIX = $IP."/extensions/DanteBackup/lists/"; 