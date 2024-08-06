<?php

/** DanteCommon contains some common code used in several parts of the DanteBackup extension */

require_once ("Executor.php");

use MediaWiki\MediaWikiServices;

// returns the list of all subpages of a page
// $titleText for example: "DumpCollections"
// $namespace: for example:  NS_MEDIAWIKI
function getSubPagesFor( $titleText, $namespace ) {
  $title      = Title::newFromText( $titleText, NS_MEDIAWIKI );                              // build title object 
  $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
  $dbr = $lb->getConnection( DB_REPLICA );
  $conditions = ['page_namespace' => $title->getNamespace(),'page_title'  . $dbr->buildLike( $title->getDBkey() . '/', $dbr->anyString() ) ];  // inspired by https://raw.githubusercontent.com/ProfessionalWiki/SubPageList/master/src/Lister/SimpleSubPageFinder.php
  $conditions['page_is_redirect'] = 0;
  $options = [];
  $result = $dbr->select( 'page', [ 'page_id', 'page_namespace', 'page_title', 'page_is_redirect' ], $conditions, __METHOD__, $options);
  
  $titleIterator = TitleArray::newFromResult ( $result );
  $titleArray    = iterator_to_array( $titleIterator );
  $func = function($title) {    return $title->getDBKey(); };
  $mapped = array_map ( $func, $titleArray );

  return $mapped;
}




class DanteCommon {

const DUMP_PATH = "DUMP";

// describe the form to be displayed (and insert the default values picked up from preferences)
const INFO = [
  'ainform' => ['type' => 'info', 'section' => '',
      'label' => 'info',
      'default' => '<a href="https://wikipedia.org/">Wikipediaqqqq</a>',
      'raw' => true,   // If true, the above string won't be HTML escaped
  ] 
];

const FEATURES = [
  'zip'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Compress',   'name' => 'compressed', 'type' => 'check'  ],
  'enc'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Encrypt',    'name' => 'encrypted',  'type' => 'check'  ]
];


const SOURCE_FEATURES = [
  'radio88'  => [ 'section' => 'srcfeatures/rb' , 'type' => 'radio',  'label' => '', 
    'options' => [ 'Only pages listed in <a href=\'./MediaWiki:Corefiles\'>MediaWiki:Corefiles</a>'                                  => "corefiles",
                   'Only pages listed in <a href=\'./MediaWiki:Backupfiles\'>MediaWiki:Backupfiles</a>'                              => "backupfiles",
                   'Only pages in <b>category backup</b>'                                                                            => "backupcategory",
                   'Only pages in a category listed in <a href=\'./MediaWiki:Backupcategories\'>MediaWiki:Backupcategories</a>'      => "backupcategories",
                    '<b>All</b> pages'                                                                                               => "all", 
                     ],   
     'name' => 'srces',  'default' => 'all', 
  ],
  'radio33'  => [ 'section' => 'srcfeatures/ra' , 'type' => 'radio',  'label' => '', 
        'options' => [ '<b>Current</b> revision only'                         => "current",
                       '<b>All</b> revisions'                                 => "all", 
                     ],   
        'name' => 'srcFeatures',  'default' => 'all', 
 ],
];


const DEBUG_FORM = [
  'showls'           => [ 'section' => 'debug',   'class' => 'HTMLCheckField',  'label' => 'Show AWS Bucket ls',             'name' => 'showls', 'type' => 'check'],
];


  public static function getTARGET_FORM () {
   return  [
    'radio'  => [ 'section' => 'target' , 'type' => 'radio',  'label' => '', 
        'options' => [ // "bibi".wfMessage ('somestuff')->plain() =>  "checkme",  // TODO: that's how to localize this stuff; that's why we have this as return of a function and not as a const array
           '<b>AWS S3 foreground</b> (shows error messages; may take minutes to hours)'                                                                       => "awsFore",
           '<b>AWS S3 background</b> (no error messages; need to check for completion by <a href=\'./Special:DanteListBackups\'>listing backups</a>)'         => "awsBack", 
           '<b>Github foreground</b> (shows error messages; may take minutes to hours)'                                                                       => "githubFore",
           '<b>Github background</b> (no error messages; need to check for completion by <a href=\'./Special:DanteListBackups\'>listing backups</a>)'         => "githubBack", 
           '<b>SSH foreground</b> (shows error messages; may take minutes to hours)'                                                                          => "sshFore",
           '<b>SSH background</b> (no error messages; need to check for completion by <a href=\'./Special:DanteListBackups\'>listing backups</a>)'            => "sshBack", 
           "<b>Client</b> (save as file on the client using the browser)"                                                                                     => "browser",
           '<em>Window</em> (show it in the browser window; may include error messages; useful for debugging)'                                                => "window",
           '<b>Server foreground</b> (shows error messages; may take minutes to hours; only testing or when server accessible)'                               => "serverFore",
           '<b>Server background</b> (no error messages; need to check for completion on server; only testing or when server accessible)'                     => "serverBack",
                     ], 
        'name' => 'target',  'default' => 'awsFore', 
 ]  ,
  ];

  }


  public static function contentTypeHeader ($zip, $enc) {
    if ($enc) { header( "Content-type: application/octet-stream" );} 
    else      { if ($zip) { header( "Content-type: application/x-gzip" );  } else { header( "Content-type: application/xml; charset=utf-8" );}  }
  }

  public static function generateFilename ($typ, $zip, $enc) {
    global $wgSitename;
    $filename = urlencode( $wgSitename ) . wfTimestampNow();
   if ($enc) { if ($zip) { return  $filename . ".$typ.gz.aes" ;} else { return $filename . ".$typ.aes" ;}} 
    else      { if ($zip) { return  $filename . ".$typ.gz"     ;} else { return $filename . ".$typ"     ;}}
  }

  // $obj: the object providing the getNativeExtension and generateCommand functions

// TODO: where would we inject the set pipefail property ??

  // command decorator. result then gets piped/redirected into different sinks
  public static function cmdZipEnc ( $cmd, $zip, $enc, $aesPW ) {
    global $IP;
    return $cmd . ($zip ? " | gzip " : " ") . ($enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -pass pass:$aesPW " : " ") ; }

  // command decorator. arguments are generated from different sources and get piped into this
  public static function decUnzipCmd ( $dec, $aesPW, $unzip, $cmd ) { return  ($dec ? " openssl aes-256-cbc -d -salt -pbkdf2 -pass $aesPW | " : " ") .  ($zip ? " gunzip | " : "") . $cmd ; }


// execute a command obtained from $obj->getCommand
// stream the stdout of the command execution to the browser, assuming (text/plain) to show it best
public static function dumpToWindow ($obj, $zip, $enc, $aesPW) {
    header( "Content-type: text/plain; charset=utf-8" );
    $cmd = $obj->getCommand ();
    $cmd = DanteCommon::cmdZipEnc ($cmd, $zip, $enc, $aesPW);
    $cmd = $cmd . " 2>&1 ";  // redirecting stderror gives us the chance of seeing error messages in the window 
    $result = 0; 
    $ptResult = passthru ($cmd, $result);
    echo "ERROR: $ptResult, $result, $cmd";
}

  public static function dumpToBrowser ($obj, $zip, $enc, $aesPW) {
    $filename = DanteCommon::generateFilename( $obj->getNativeExtension(), $zip, $enc);
    DanteCommon::contentTypeHeader ($zip, $enc);
    header( "Content-disposition: attachment;filename={$filename}" );
    $cmd = $obj->getCommand ();
    $cmd = DanteCommon::cmdZipEnc ($cmd, $zip, $enc, $aesPW);
    $result = 0; 
    passthru ($cmd, $result);
  }


// background // TODO redo completelly
public static function dumpToAWS_BG ($obj, $bucketName, $zip, $enc, $aesPW) {
  $cmd = $obj->getCommand ();  // TODO: pipefail ß?????
  $cmd = DanteCommon::cmdZipEnc ($cmd, $zip, $enc, $aesPW);

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
  $cmd     = DanteCommon::cmdZipEnc ($cmd, $zip, $enc, $aesPW);
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


public static function dumpToServer ( $obj, $name, $zip, $enc, $aesPW, $background ) {
  global $IP;

  $dirPath = $IP. "/".DanteCommon::DUMP_PATH;
  echo "---------------------------$IP -----------".$dirPath;
  if ( !file_exists ( $dirPath ) ) { mkdir ( $dirPath, 0755); }
  $filename = DanteCommon::generateFilename( $obj->getNativeExtension(), $zip, $enc);
  $errorFileName = DanteCommon::DUMP_PATH."/DANTEDBDump_ERROR_FILE$filename";

  $cmd = $obj->getCommand ();
  $cmd = DanteCommon::cmdZipEnc ($cmd, $zip, $enc, $aesPW);
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



public static function getEnvironmentUser (User $user) {
  $accessKey        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-accesskey' );
  $secretAccessKey  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-secretaccesskey' );
  $awsRegion        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-region' );
  return array ("AWS_ACCESS_KEY_ID" => "$accessKey", "AWS_SECRET_ACCESS_KEY" => "$secretAccessKey", "AWS_REGION" => "$awsRegion");
}



} // end CLASS




/** An interface EnvironmentPreparator serves to prepare and clear an environment for the execution of shell commands
 *  It has to be implemented and instantiated by a (possibly stateful) class which knows how to do that.
 */
interface EnvironmentPreparator {
  public function prepare ();    // prepare the environment
  public function clear   ();    // clear the environment
}


/** An object of class AWSEnvironmentPreparator prepares and clears the environment for the execution of amazon AWS CLI calls
 */
class AWSEnvironmentPreparator implements EnvironmentPreparator {
  protected string $accessKey, $secretAccessKey, $awsRegion;

  function __construct (string $accessKey, string $secretAccessKey, string $awsRegion) { $this->accessKey = $accessKey; $this->secretAccessKey = $secretAccessKey; $this->awsRegion = $awsRegion; }

  public function prepare () {
    set_time_limit(3000);  // TODO: we might need to adjust this ??????  // wemight reduce that again in clear ??
    putenv ("AWS_ACCESS_KEY_ID=$this->accessKey"); putenv ("AWS_SECRET_ACCESS_KEY=$this->secretAccessKey"); putenv ("AWS_REGION=$this->awsRegion");
  }

  public function clear   () { putenv ("AWS_ACCESS_KEY_ID=NIL"); putenv ("AWS_SECRET_ACCESS_KEY=NIL"); putenv ("AWS_REGION=NIL"); }
}


/** An object of class AWSEnvironmentPreparatorUser obtains the necessary data from the preferences of a user in mediawiki
 */

class AWSEnvironmentPreparatorUser extends AWSEnvironmentPreparator {
  function __construct (User $user) {
    $accessKey        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-accesskey' );
    $secretAccessKey  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-secretaccesskey' );
    $awsRegion        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-region' );
   if ( is_null ($accessKey) || is_null ($secretAccessKey) || is_null ($awsRegion) ) { throw new Exception ("The current user has not yet set preferences for the AWS Keys and should do so in Preferences / Dantewiki");}
    parent::__construct( $accessKey, $secretAccessKey, $awsRegion );
  }
}
