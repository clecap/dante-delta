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
  'fil'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Filter',     'name' => 'filter',     'type' => 'check',  'help-message' => 'help-filter'  ],
  'zip'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Compress',   'name' => 'compressed', 'type' => 'check' , 'help-message' => 'help-zip' ],
  'enc'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Encrypt',    'name' => 'encrypted',  'type' => 'check' , 'help-message' => 'help-enc' ],
];


const SOURCE_FEATURES = [
  'radio88'  => [ 'section' => 'srcfeatures/rb' , 'type' => 'radio',  'label' => '', 
    'options' => [ 'Only pages listed in page <a href="./index.php?title=MediaWiki:Backupfiles">MediaWiki:Backupfiles</a>'                              => "backupfiles",
                   'Only pages in <a href="./indx.php?title=Category:Backup">Category:Backup</a>'                                                       => "backupcategory",
                   'Only pages in a category listed in <a href="./index.php?title=MediaWiki:Backupcategories">MediaWiki:Backupcategories</a>'           => "backupcategories",
                    '<b>All</b> pages'                                                                                                                  => "all", 
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
           '<b>AWS S3 background</b> (no error messages; need to check for completion by <a href=\'./index.php?title=Special:DanteListBackups\'>listing backups</a>)'         => "awsBack", 
           '<b>Github foreground</b> (shows error messages; may take minutes to hours)'                                                                       => "githubFore",
           '<b>Github background</b> (no error messages; need to check for completion by <a href=\'./Special:DanteListBackups\'>listing backups</a>)'         => "githubBack", 
           '<b>SSH foreground</b> (shows error messages; may take minutes to hours)'                                                                          => "sshFore",
           '<b>SSH background</b> (no error messages; need to check for completion by <a href=\'./Special:DanteListBackups\'>listing backups</a>)'            => "sshBack", 
           "<b>Client</b> (save as file on the client using the browser)"                                                                                     => "browser",
           'Window (show it in the browser window; may include error messages; useful for debugging)'                                                => "window",
           'List (only show list of files as dry-run; does <em>not</em> dump; useful for debugging)'                                                => "list",
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



public static function checkSuffix ( $string, $suffix) {
  if (substr($string, -strlen($suffix)) === $suffix) { return true; } 
  else { return false; }
}


  public static function generateFilename ($typ, $zip, $enc) {
    global $wgSitename;
    $filename = urlencode( $wgSitename ) . wfTimestampNow();
   if ($enc) { if ($zip) { return  $filename . ".$typ.gz.aes" ;} else { return $filename . ".$typ.aes" ;}} 
    else      { if ($zip) { return  $filename . ".$typ.gz"     ;} else { return $filename . ".$typ"     ;}}
  }

  // $obj: the object providing the getNativeExtension and generateCommand functions




  // command decorator. result then gets piped/redirected into different sinks
//   TODO: MAYBE: openssl aes-256-cbc -e -salt -pbkdf2 -pass pass:$aesPW " : " ") ; 
// openssl aes-256-cbc -d -salt -pbkdf2 -pass ENV:LOCAL_FILE_ENC |
public static function cmdZipEncRestore ( $cmdGenerate, $cmdConsume, $zip, $enc ) {
  return "set -o pipefail; " .  $cmdGenerate . ($enc ? " openssl aes-256-cbc -d -salt -pass env:LOCAL_FILE_ENC | " : "" ) . ($zip ? " gunzip -c | " : "") .  $cmdConsume;
}


public static function getEnvironmentUser (User $user) {
  $accessKey        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-accesskey' );
  $secretAccessKey  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-secretaccesskey' );
  $awsRegion        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-region' );
  $awsBucketName    = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-bucketname' );
  $awsEncPW         = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-encpw' );
  return array ( "AWS_ACCESS_KEY_ID"     => "$accessKey", 
                 "AWS_SECRET_ACCESS_KEY" => "$secretAccessKey", 
                 "AWS_REGION"            => "$awsRegion",
                 "AWS_BUCKET_NAME"       => "$awsBucketName",
                 "LOCAL_FILE_ENC"        => "$awsEncPW"
               );
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


///// TODO: deprecate the preparator everywhere !!

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
