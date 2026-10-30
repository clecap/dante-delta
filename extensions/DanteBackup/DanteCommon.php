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

const DEBUG_FORM = [
  'showls'           => [ 'section' => 'debug',   'class' => 'HTMLCheckField',  'label' => 'Show AWS Bucket ls',             'name' => 'showls', 'type' => 'check'],
];


// TODO: DEPRECATE THIS
  public static function getTARGET_FORM () {
   return  [
    'radio'  => [ 'section' => 'target' , 'type' => 'radio',  'label' => '', 
        'options' => [ 
           '<b>AWS S3</b> (shows error messages; may take minutes to hours)'                                                                                 => "aws",
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
  // danteLog ("DanteBackup", "\n DanteCommon::cmdZipEncRestore generate: $cmdGenerate \n $cmdConsume \n  " . ($zip ? "  compressed": "  UNcompressed")."\n   $enc \n\n");
  $ret = "set -o pipefail; " .  $cmdGenerate . ($enc ? "  openssl  aes-256-cbc -d -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC | " : "" ) . ($zip ? " gunzip -c | " : "") .  $cmdConsume;
  // danteLog ("DanteBackup", "\n DanteCommon::cmdZipEncRestore returns $ret \n\n");
  return $ret;
}


public static function getEnvironmentUser (User $user) {
  global $wgDBpassword;
  $accessKey        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-accesskey' );
  $secretAccessKey  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-secretaccesskey' );
  $awsRegion        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-region' );
  $awsBucketName    = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-bucketname' );
  $awsEncPW         = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-encpw' );
  return array ( "AWS_ACCESS_KEY_ID"     => "$accessKey", 
                 "AWS_SECRET_ACCESS_KEY" => "$secretAccessKey", 
                 "AWS_REGION"            => "$awsRegion",
                 "AWS_BUCKET_NAME"       => "$awsBucketName",
                 "LOCAL_FILE_ENC"        => "$awsEncPW",
                 "MYSQL_PWD"             => "$wgDBpassword"     // read by mysqldump / mysql, keeps the password out of the process list
               );
}


} // end CLASS



// ALL THE BELOW IS DEPRECATED

/** An interface EnvironmentPreparator serves to prepare and clear an environment for the execution of shell commands
 *  It has to be implemented and instantiated by a (possibly stateful) class which knows how to do that.
 */
 /*
interface EnvironmentPreparator {
  public function prepare ();    // prepare the environment
  public function clear   ();    // clear the environment
}
*/

/** An object of class AWSEnvironmentPreparator prepares and clears the environment for the execution of amazon AWS CLI calls
 */
 /*
class AWSEnvironmentPreparator implements EnvironmentPreparator {
  protected string $accessKey, $secretAccessKey, $awsRegion;

  function __construct (string $accessKey, string $secretAccessKey, string $awsRegion) { $this->accessKey = $accessKey; $this->secretAccessKey = $secretAccessKey; $this->awsRegion = $awsRegion; }

  public function prepare () {
    set_time_limit(3000);  // TODO: we might need to adjust this ??????  // wemight reduce that again in clear ??
    putenv ("AWS_ACCESS_KEY_ID=$this->accessKey"); putenv ("AWS_SECRET_ACCESS_KEY=$this->secretAccessKey"); putenv ("AWS_REGION=$this->awsRegion");
  }

  public function clear   () { putenv ("AWS_ACCESS_KEY_ID=NIL"); putenv ("AWS_SECRET_ACCESS_KEY=NIL"); putenv ("AWS_REGION=NIL"); }
}
*/

///// TODO: deprecate the preparator everywhere !!

/** An object of class AWSEnvironmentPreparatorUser obtains the necessary data from the preferences of a user in mediawiki
 */
/*
class AWSEnvironmentPreparatorUser extends AWSEnvironmentPreparator {
  function __construct (User $user) {
    $accessKey        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-accesskey' );
    $secretAccessKey  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-secretaccesskey' );
    $awsRegion        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'aws-region' );
   if ( is_null ($accessKey) || is_null ($secretAccessKey) || is_null ($awsRegion) ) { throw new Exception ("The current user has not yet set preferences for the AWS Keys and should do so in Preferences / Dantewiki");}
    parent::__construct( $accessKey, $secretAccessKey, $awsRegion );
  }
}
*/
