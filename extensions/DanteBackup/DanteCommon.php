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

const DUMP_PATH = "/var/www/html/wiki-dir/dump";   

const HEADER = [
   'tag'     => [ 'section' => 'header', 'class' => 'HTMLTextField', 'size' => 20, 'label' => 'Identifying Tag', 'name' => 'tag', 'type' => 'text', 'default' => 'dump', 
      'pattern' => '[A-Za-z0-9_-]+', 'title' => 'Enter a tag which shows up as part of the name of the dump' ],
   'archive' => [ 'section' => 'header', 'class' => 'HTMLTextField', 'cssclass' => 'headright', 'size' => 80, 'label' => 'Page Dump',  'name' => 'archiveName', 'type' => 'text',     'readonly' => true  ],
   'dbname'  => [ 'section' => 'header', 'class' => 'HTMLTextField', 'cssclass' => 'headright', 'size' => 80, 'label' => 'Database', 'name' => 'dbName',      'type' => 'text',       'readonly' => true  ],
   'tarname' => [ 'section' => 'header', 'class' => 'HTMLTextField', 'cssclass' => 'headright', 'size' => 80, 'label' => 'File Archive',  'name' => 'tarName',     'type' => 'text',  'readonly' => true  ],
];

const FEATURES = [
  'zip'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Compress',   'name' => 'compressed', 'type' => 'check' , 'default' => true ],
  'enc'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Encrypt',    'name' => 'encrypted',  'type' => 'check' , 'help-message' => 'help-enc', 'default' => true ],
];

const SOURCE_FEATURES = [
  'radio88'  => [ 'section' => 'srcfeatures/rb' , 'type' => 'radio',  'label' => '', 
    'options' => [ '<i>No pages</i> included in the pages archive'                                                                                                   => "nopages",
                   '<b>Listed</b>: Only pages listed in page <a href="./index.php?title=MediaWiki:Backupfiles">MediaWiki:Backupfiles</a>'                            => "listed",
                   '<b>Category</b>: Only pages belonging to <a href="./indx.php?title=Category:Backup">Category:Backup</a> (<a href="./cache/">dryrun</a>)'                                => "category",
                   '<b>Categories</b>: Only pages belonging to a category listed in <a href="./index.php?title=MediaWiki:Backupcategories">MediaWiki:Backupcategories</a>'     => "categories",
                   '<b>Categories Indirect</b>: Only pages belonging to a category or an arbitrarily deep subcategory of a category listed in <a href="./index.php?title=MediaWiki:Backupcategories">MediaWiki:Backupcategories Indirect</a>'     => "categories-indirect",
                   '<b>All</b> pages'                                                                                                                                => "all", 
                     ],   
     'name' => 'srces',  'default' => 'all', 
  ],
  'radio33'  => [ 'section' => 'srcfeatures/ra' , 'type' => 'radio',  'label' => '', 
        'options' => [ '<b>Current</b> versions only'                                   => "current",
                       '<b>All revisions</b> included'                                  => "allrevisions", 
                     ],   
        'name' => 'srcFeatures',  'default' => 'allrevisions', 
 ],
  'radio32'  => [ 'section' => 'srcfeatures/rf' , 'type' => 'radio',  'label' => '', 
        'options' => [ '<i>Nofiles</i>: Do not dump uploaded file contents and do not dump metadata'                      => "nofiles",
                       '<i>Metadata</i>: Dump metadata only, no file contents'                                            => "metadata",
                       '<b>Separate</b>: Dump metadata (into page archive) and file contents into a separate file archive'               => "separate", 
                       '<b>Include</b>: Dump metadata and file contens (both into one large page archive)'                  => "include", 
                     ],   
        'name' => 'files',  'default' => 'include', 
 ],
  'radio31'  => [ 'section' => 'srcfeatures/rc' , 'type' => 'radio',  'label' => '', 
        'options' => [ 'No database dump only do a page dump'                                => "nodb",
                       '<b>Full</b> database dump          '                                 => "db", 
                     ],   
        'name' => 'db',  'default' => 'db', 
 ],
];






const SOURCE_FEATURES_OLD = [
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
        'options' => [ 
           '<b>AWS S3</b> (shows error messages; may take minutes to hours)'                                                                                 => "aws",
           '<b>Github</b> (shows error messages; may take minutes to hours)'                                                                                 => "github",
           '<b>SSH / SCP</b> (shows error messages; may take minutes to hours)'                                                                              => "ssh",
           "<b>Client</b> (save as file on the client using the browser)"                                                                                    => "client",
           '<b>Server</b> (shows error messages; may take minutes to hours; only testing or when server accessible)'                                         => "server",
        ], 
        'name' => 'target',  'default' => 'aws', 
 ]  ,
  ];

  }


  public static function contentTypeHeader ($zip, $enc) {
    if ($enc) { header( "Content-type: application/octet-stream" );} 
    else      { if ($zip) { header( "Content-type: application/x-gzip" );  } else { header( "Content-type: application/xml; charset=utf-8" );}  }
  }




  // command decorator. result then gets piped/redirected into different sinks
public static function cmdZipEncRestore ( $cmdGenerate, $cmdConsume, $zip, $enc ) {
  danteLog ("DanteBackup", "\n DanteCommon::cmdZipEncRestore generate: $cmdGenerate \n $cmdConsume \n  " . ($zip ? "  compressed": "  UNcompressed")."\n   $enc \n\n");
  $ret = "set -o pipefail; " .  $cmdGenerate . ($enc ? "   aes-256-cbc -d -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC | " : "" ) . ($zip ? " gunzip -c | " : "") .  $cmdConsume;
  danteLog ("DanteBackup", "\n DanteCommon::cmdZipEncRestore returns $ret \n\n");
  return $ret;
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
