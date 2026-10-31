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


  // $ext: archive type before compression and encryption, one of xml, sql, tar
  public static function contentTypeHeader ($zip, $enc, $ext = "xml") {
    $plain = [ "xml" => "application/xml; charset=utf-8", "sql" => "application/sql; charset=utf-8", "tar" => "application/x-tar" ];
    if ($enc) { header( "Content-type: application/octet-stream" );}
    else      { if ($zip) { header( "Content-type: application/x-gzip" );  } else { header( "Content-type: " . ( $plain[$ext] ?? "application/octet-stream" ) );}  }
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


/**
 * curl option pinning the SSH host key to the SHA256 fingerprint in the preference ssh-host-pubsha256, so no known_hosts file is needed.
 * libssh2 negotiates the ECDSA host key if the server offers one, so this must be the fingerprint of /etc/ssh/ssh_host_ecdsa_key.pub on the server.
 *
 * @param \MediaWiki\User\UserIdentity $user User whose preference holds the fingerprint
 * @param bool $skip True if the user checked Skip Host Key Check: curl then accepts any host key
 * @return string --hostpubsha256 with the quoted fingerprint, or -k when skipping, for insertion into a curl command
 * @throws Exception when not skipping and the preference is empty or not a SHA256 fingerprint
 */
public static function hostKeyOption ( \MediaWiki\User\UserIdentity $user, bool $skip ): string {
  if ( $skip ) { return "-k"; }   // insecure: no host key check at all, neither fingerprint nor known_hosts
  $fp = trim ( (string) MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'ssh-host-pubsha256' ) );
  $fp = preg_replace ( '/^SHA256:/', '', $fp );   // as printed by ssh-keygen -l; curl does not accept the prefix
  // base64 of 32 bytes: 43 characters plus optional padding
  if ( $fp === '' )                                         { throw new Exception ( "No SSH host key fingerprint configured: set SSH Host Key Fingerprint in the preferences" ); }
  if ( !preg_match ( '/^[A-Za-z0-9+\/]{43}=?$/', $fp ) )   { throw new Exception ( "SSH host key fingerprint in the preferences is not a SHA256 fingerprint" ); }
  return "--hostpubsha256 " . escapeshellarg ( $fp );
}

// only a host name, optionally with :port; anything else could change the URL, e.g. user@other-host
public static function checkScpHost ( string $host ): void {
  if ( !preg_match ( '/^[A-Za-z0-9.-]+(:[0-9]+)?$/', $host ) ) { throw new Exception ( "Illegal SCP host '$host'" ); }
}

// URL for curl (scp:// or sftp://): an absolute path stays absolute, a relative one is taken relative to the home directory (/~/); every path segment URL-encoded
public static function scpUrl ( string $scheme, string $host, string $path ): string {
  $urlPath = implode ( '/', array_map ( 'rawurlencode', explode ( '/', ltrim ( $path, '/' ) ) ) );
  return "$scheme://$host" . ( str_starts_with ( $path, '/' ) ? "/" : "/~/" ) . $urlPath;
}


} // end CLASS

