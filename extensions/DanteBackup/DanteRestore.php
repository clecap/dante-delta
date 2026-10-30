<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;

require_once ("Executor.php");       // TODO: should move away from this
require_once ("DanteCommon.php");    // TODO: really? check ?!



require_once ("extensions/DanteCommon/ServiceEndpointHelper.php");
require_once ("extensions/DanteCommon/ServiceExecutor.php");    

class DanteRestore extends DanteSpecialPage {

public function __construct() {parent::__construct( 'DanteRestore', 'dante-restore' ); }

// page provides hint to read-only mode engine that it might do a write
public function doesWrites() {return true;}

// SCP credentials go into the environment, not into the command, so they are neither shown in the progress display nor visible in the process list
protected function adjustEnvironment ( array $env ): array {
  $request = $this->getRequest();
  if ( $request->getVal ( 'scpUser' ) === null ) { return $env; }   // only for the SCP form
  $env['DANTE_SCP_USER'] = $request->getVal ( 'scpUser' );
  $env['DANTE_SCP_PW']   = $request->getVal ( 'scpPassword', '' );   // always entered manually, no fallback to a stored password
  $env['HOME']           = DanteCommon::homeDir();                   // curl finds ~/.ssh/known_hosts through HOME, which PHP-FPM does not pass on
  return $env;
}

/**
 * Additionally to the standard behaviour, answer the AJAX request ?action=scplist of the Load button with a JSON listing of the dump files in the SCP directory.
 *
 * @param string|null $par
 * @return void
 */
public function execute ( $par ): void {
  $request = $this->getRequest();
  if ( $request->getVal ( 'action' ) !== 'scplist' ) { parent::execute ( $par ); return; }

  $this->checkPermissions();   // same right as for the page itself
  $this->getOutput()->disable();
  header ( "Content-Type: application/json; charset=utf-8" );

  // POST with a valid CSRF token only, since the request carries a password and makes the server connect to a host
  if ( !$request->wasPosted() || !$this->getContext()->getCsrfTokenSet()->matchToken ( $request->getVal ( 'token', '' ) ) ) { echo json_encode ( [ 'ok' => false, 'error' => 'Invalid or expired token, please reload the page' ] ); return; }

  try   { echo json_encode ( [ 'ok' => true, 'files' => self::listScpDirectory ( $request->getVal ( 'scpUser', '' ), $request->getVal ( 'scpHost', '' ), $request->getVal ( 'scpPath', '' ), $request->getVal ( 'scpPassword', '' ) ) ] ); }
  catch ( Exception $e ) { echo json_encode ( [ 'ok' => false, 'error' => $e->getMessage() ] ); }   // the password never appears in a message or in the log
}

/**
 * List the dump files in a directory at an SCP host via SFTP, newest first.
 *
 * @param string $dir Absolute directory, or relative to the home directory of $user
 * @return array[] Each with name, type (Pages, Database or Files), time (Y-m-d H:i) and size in bytes
 * @throws Exception on an illegal host or when the listing fails
 */
private static function listScpDirectory ( string $user, string $host, string $dir, string $pw ): array {
  DanteCommon::checkScpHost ( $host );
  $url = DanteCommon::scpUrl ( "sftp", $host, $dir );
  if ( !str_ends_with ( $url, '/' ) ) { $url .= '/'; }   // trailing slash: curl lists the directory

  // credentials through the environment, as for the restore; short timeouts, since the page waits for the answer
  $env  = [ 'DANTE_SCP_USER' => $user, 'DANTE_SCP_PW' => $pw, 'HOME' => DanteCommon::homeDir() ];
  $proc = proc_open ( "curl -sS --fail --connect-timeout 5 --max-time 10 --user \"\$DANTE_SCP_USER:\$DANTE_SCP_PW\" " . escapeshellarg ( $url ), [ 1 => ['pipe', 'w'], 2 => ['pipe', 'w'] ], $pipes, null, $env );
  if ( $proc === false ) { throw new Exception ( "Could not start curl" ); }
  $out = stream_get_contents ( $pipes[1] );  $err = stream_get_contents ( $pipes[2] );   // both are small, reading one after the other cannot block
  fclose ( $pipes[1] );  fclose ( $pipes[2] );
  if ( proc_close ( $proc ) !== 0 ) { throw new Exception ( "Listing $host:$dir failed: " . trim ( $err ) ); }
  return self::parseSftpListing ( $out );
}

/**
 * Turn the ls -l style listing curl returns for an sftp:// directory into the dump files it contains, newest first.
 *
 * @param string $listing Lines like "-rw-r--r--  1 user group  1234 Oct 30 17:39 name"
 * @return array[] Each with name, type, time and size
 */
private static function parseSftpListing ( string $listing ): array {
  $files = [];
  foreach ( explode ( "\n", $listing ) as $line ) {
    $p = preg_split ( '/\s+/', trim ( $line ), 9 );                                // permissions links owner group size month day time-or-year name
    if ( count ( $p ) < 9 || $p[0][0] !== '-' ) { continue; }                     // regular files only
    [ , , , , $size, $mon, $day, $timeOrYear, $name ] = $p;
    if ( ( $type = self::dumpType ( $name ) ) === null ) { continue; }            // dump files only, as in the AWS list
    $recent = str_contains ( $timeOrYear, ':' );                                  // ls shows the time for recent files, the year for older ones
    $ts     = strtotime ( "$mon $day " . ( $recent ? date ( 'Y' ) . " $timeOrYear" : "$timeOrYear 00:00" ) );
    if ( $recent && $ts > time() + 86400 ) { $ts = strtotime ( "-1 year", $ts ); }   // e.g. a December file listed in January belongs to the previous year
    $files[] = [ 'name' => $name, 'type' => $type, 'time' => date ( 'Y-m-d H:i', $ts ), 'size' => (int) $size, 'ts' => $ts ];
  }
  usort ( $files, fn ( $a, $b ) => $b['ts'] <=> $a['ts'] );                        // newest first
  return array_map ( function ( $f ) { unset ( $f['ts'] ); return $f; }, $files );
}

// Pages, Database or Files for a dump file name, null for any other file
private static function dumpType ( string $name ): ?string {
  try { [$db, $enc, $zip, $tar] = self::checkName ( $name ); } catch ( Exception $e ) { return null; }
  return $tar ? "Files" : ( $db ? "Database" : "Pages" );
}



protected function showForm  (): void  {
  // send post data to THIS url and add action=submit to the URL so we can distinguish showing this page from submitting data to it
  $action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );  

  $out    = $this->getOutput();
  $out->addModuleStyles ( ['ext.DanteBackup.specialpage.styles'] );  // this is a styles-only module which mediawiki loads early enough to prevent FOUC
  $out->addHTML (wfMessage ("dante-page-restore-intro"));
  $out->addHelpLink( 'index.php?title=Help:DanteRestore', true );  // provide a help link   // TODO: must fill with contents

  $out->addHTML ("<h2>From a local file on your computer</h2>");                  // FORM PART: LOCAL FILE
  $form = [ 'xmlimport' => ['type' => 'file','name' => 'xmlimport', 'accept' => [ 'application/xml', 'text/xml', 'application/x-gzip-compressed', 'application/gzip', 'application/x-tar', 'application/octet-stream' ], 'section' => 'select-local-file', 'required' => true, ] ];
  self::standardForm ($form, $action, "LOCAL", "Restore from file");

  $out->addHTML ("<h2>From a file from the AWS S3 storage area</h2>");          // FORM PART: AWS S3
  $out->addHTML ("The type (Pages, Database, Files) determines the restored elements.");
  $form = [ 'awsRadio'  => [ 'type' => 'radio', 'section' => 'restore-from-aws', 'options' => $this->getFileArray (),   'name' => 'awsRadio', 'required' => true  ] ];
  self::standardForm ($form, $action, "AWS", "Restore from AWS");

  $out->addHTML ("<h2>From a file at an Internet URL (not operative?)</h2>");   // FORM PART: Internet URL
  $form = [ 'url' => [ 'type' => 'text', 'name' => 'url', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];
  self::standardForm ($form, $action, "URL", "Restore from URL");

  $out->addHTML ("<h2>From a file at an SSH/SCP location</h2>");   // FORM PART SSH/SCP
  $sshUser     = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'ssh-restore-user' );   // defaults from the preferences, tab DanteWiki
  $sshHost     = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'ssh-host' );
  $form =    [ 'scpUser'     => [ 'type' => 'text',     'name' => 'scpUser',     'label' => 'SCP User',     'section' => 'restore-from-scp', 'required' => true, 'default' => $sshUser ] ]
           + [ 'scpHost'     => [ 'type' => 'text',     'name' => 'scpHost',     'label' => 'SCP Host',     'section' => 'restore-from-scp', 'required' => true, 'default' => $sshHost ] ]
           + [ 'scpPath'     => [ 'type' => 'text',     'name' => 'scpPath',     'label' => 'SCP Path',     'section' => 'restore-from-scp', 'required' => true ] ]   // directory of the dumps
           + [ 'scpPassword' => [ 'type' => 'password', 'name' => 'scpPassword', 'label' => 'SCP Password', 'section' => 'restore-from-scp', 'required' => true, 'autocomplete' => 'new-password' ] ]   // entered every time, never stored or prefilled
           + [ 'scpLoad'     => [ 'type' => 'info',     'raw' => true, 'section' => 'restore-from-scp',
                                  'default' => '<button type="button" id="dante-scp-load">Load</button><div id="dante-scp-list"></div>' ] ];   // ext.DanteBackup.restore fills the list with radio buttons named scpFile
  self::standardForm ($form, $action, "SCP", "Restore via SCP");
  $out->addModules ( [ 'ext.DanteBackup.restore' ] );   // JS for the Load button

  $out->addHTML ("<h2>Restore from GITHUB (not yet fully operative)</h2>");             // FORM PART: GITHUB
  $owner       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'git-owner' );
  $repository  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'git-repository' );
  $branch      = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'git-branch' );
  $token       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'github-dante-wiki-contents' );   // as in DanteDump
  $form =    [ 'gitOwner'      =>  [ 'type' => 'text',  'name' => 'gitOwner',      'label' => 'Git Owner',      'section' => 'restore-from-github', 'required' => true, 'default' =>  $owner    ] ]
           + [ 'gitRepository' =>  [ 'type' => 'text',  'name' => 'gitRepository', 'label' => 'Repository',     'section' => 'restore-from-github', 'required' => true, 'default' =>  $repository   ] ]
           + [ 'gitBranch'     =>  [ 'type' => 'text',  'name' => 'gitBranch',     'label' => 'Branch',         'section' => 'restore-from-github', 'required' => true, 'default' =>  $branch   ] ]
           + [ 'gitToken'      =>  [ 'type' => 'text',  'name' => 'gitToken',      'label' => 'Access Token',   'section' => 'restore-from-github', 'required' => true, 'default' =>  $token   ] ];
  self::standardForm ($form, $action, "GITHUB", "Restore via GITHUB");
}


// returns the specific command sequence required for executing the necessary functions
protected function getSpecificCommands ( $formId ): mixed {
  global $IP;
  $request = $this->getRequest();
  switch ($formId) {
    case 'formId_LOCAL':
      // danteLog ("DanteBackup", "ALL: " . print_r ($_FILES, true) . "\n");
      danteLog ("DanteBackup", "name " . $_FILES['xmlimport']['name'] . "\n");
      danteLog ("DanteBackup", "mime: " . $_FILES['xmlimport']['type'] . "\n");
      danteLog ("DanteBackup", "size " . $_FILES['xmlimport']['size'] . "\n"); 
      danteLog ("DanteBackup", "tmp name " . $_FILES['xmlimport']['tmp_name'] . "\n");      
      danteLog ("DanteBackup", "error " . $_FILES['xmlimport']['error'] . "\n");
      $info = "Name: ". $_FILES['xmlimport']['name']. " Size " . number_format (floatval ( $_FILES['xmlimport']['size'] ) / (1024 * 1024), 2) . "[MB]";  // TODO: MAYBE OFR OUTPUT - but where and when ???
      // keep the upload: PHP deletes tmp_name at the end of this request, but the commands run later in serviceEndpoint.php
      $fileName = tempnam ( sys_get_temp_dir(), "dante_restore_" );
      if ( $fileName === false || !move_uploaded_file ( $_FILES['xmlimport']['tmp_name'], $fileName ) ) { throw new Exception ( "Could not keep the uploaded file" ); }
      $arr   = $this->getCommandsFILE ( $fileName, $info, $_FILES['xmlimport']['name'] );   // type from the original name, since the temporary name has no suffix
      $arr[] = new DanteStringCommand ( "rm -f " . escapeshellarg ( $fileName ), "Removing uploaded file" );   // NOTE: skipped if a command before fails; then the file stays in the temp directory
      break;

    case 'formId_AWS':
      $fileName = $request->getVal ("awsRadio");                                           // pick up the selected file name
      // danteLog ("DanteBackup", "radio selected file is: ".$fileName. "\n");     // log the relevant data if needed
      $arr = $this->getCommandsAWS ($fileName);                                          // get an array of commands
      break;

    case 'formId_URL':
      $url = $request->getVal ("url");
      $this->doImportURL ( $url, $this->getUser() ); // TODO: missing function ???

      break;

    case 'formId_GITHUB':
      $owner  = $request->getVal ( 'gitOwner' );  
      $repo   = $request->getVal ( 'gitRepository' );  
      $branch = $request->getVal ( 'gitBranch' );
      $token  = $request->getVal ( 'gitToken' );
      danteLog ("DanteBackup", "formId_GITHUB: " . $owner);
      $arr = $this->gitClone ( $owner, $repo, $branch, $token );
      break; 

    case 'formId_SCP':
      $dir  = $request->getVal ( 'scpPath', '' );
      $file = $request->getVal ( 'scpFile', '' );
      if ( $file === '' || str_contains ( $file, '/' ) ) { throw new Exception ( "Please press Load and select a file from the list" ); }   // a plain name from the listing, so it cannot leave the directory
      $arr = $this->getCommandsSCP ( $request->getVal ( 'scpHost', '' ), ( $dir === '' ? $file : rtrim ( $dir, '/' ) . '/' . $file ) );   // user and password come through the environment, see adjustEnvironment
      break;

    default:
       throw new Exception ("Not yet implemented case: ".$formId);
       break;
    }
    return $arr;
}


/**
 *
 * Analyze the suffix structure of a filename and return a boolean array [db, enc, zip, tar]
 * This is needed to understand requirements how to handle a file selected for restore
 *
 * @param mixed $fileName
 * @return array [db, enc, zip, tar]; tar is true for a file archive of the uploaded files
 * @throws Exception
 */
private static function checkName ($fileName) {
  $db = null;
  $enc = null;
  $zip = null;
  $tar = false;

  if     ( str_ends_with ($fileName, ".tar.gz.aes") ) { $db=false;  $enc=true;   $zip=true;   $tar=true; }
  elseif ( str_ends_with ($fileName, ".tar.aes") )    { $db=false;  $enc=true;   $zip=false;  $tar=true; }
  elseif ( str_ends_with ($fileName, ".tar.gz") )     { $db=false;  $enc=false;  $zip=true;   $tar=true; }
  elseif ( str_ends_with ($fileName, ".tar") )        { $db=false;  $enc=false;  $zip=false;  $tar=true; }
  elseif ( str_ends_with ($fileName, ".xml.gz.aes") ) { $db=false;  $enc=true;   $zip=true;  }
  elseif ( str_ends_with ($fileName, ".xml.aes") )    { $db=false;  $enc=true;   $zip=false; }
  elseif ( str_ends_with ($fileName, ".xml.gz") )     { $db=false;  $enc=false;  $zip=true;  }
  elseif ( str_ends_with ($fileName, ".xml") )        { $db=false;  $enc=false;  $zip=false; }
  elseif ( str_ends_with ($fileName, ".sql.gz.aes") ) { $db=true;   $enc=true;   $zip=true;  }
  elseif ( str_ends_with ($fileName, ".sql.aes") )    { $db=true;   $enc=true;   $zip=false; }
  elseif ( str_ends_with ($fileName, ".sql.gz") )     { $db=true;   $enc=false;  $zip=true;  }
  elseif ( str_ends_with ($fileName, ".sql") )        { $db=true;   $enc=false;  $zip=false; }
  else { throw new Exception ("incompatible file extension found in file $fileName");}
  return [$db, $enc, $zip, $tar];
}


/**
 * Commands for restoring a page archive streamed from $source: each step reads the archive anew, MediaWiki: and Template: pages first, since the other pages need them.
 *
 * @param string $source Shell command writing the archive to stdout, ending in a pipe, e.g. "curl -L 'https://...' | "
 * @return DanteCommand[]
 */
private static function getPagesRestoreCommands ( string $source, $zip, $enc ): array {
  global $IP;
  $cmd = [];
  $cmd[] = new DanteStringCommand ( DanteCommon::cmdZipEncRestore ( $source, " php $IP/extensions/DanteBackup/countFilter.php ",                   $zip, $enc ) );
  $cmd[] = new DanteStringCommand ( DanteCommon::cmdZipEncRestore ( $source, " php $IP/maintenance/importDump.php --report=10 --namespaces '8' ",  $zip, $enc ) );
  $cmd[] = new DanteStringCommand ( DanteCommon::cmdZipEncRestore ( $source, " php $IP/maintenance/importDump.php --report=10 --namespaces '10' ", $zip, $enc ) );
  $cmd[] = new DanteStringCommand ( DanteCommon::cmdZipEncRestore ( $source, " php $IP/maintenance/importDump.php --report=10 --uploads ",         $zip, $enc ) );
  return self::addPostImportCommands ( $cmd );   // do maintenance stuff we need to do after every import
}


/**
 * Commands for restoring a file archive (as made by DanteDump "Dump Files Only") into the upload directory.
 *
 * @param string $source Shell command writing the archive to stdout, ending in a pipe, e.g. "cat 'x.tar.gz' | "
 * @return DanteCommand[]
 */
private static function getFilesRestoreCommands ( string $source, $zip, $enc ): array {
  global $IP, $wgUploadDirectory;
  $parent = dirname ( rtrim ( $wgUploadDirectory, '/' ) );   // archive paths start with the name of the upload directory, e.g. images/a/ab/File.png
  $cmd = [];
  // decrypt and decompress as for the other archives; -v lists every restored file; existing files are overwritten
  // never restore .htaccess (at any depth): it keeps Apache from executing uploads, and a crafted one from a foreign archive could enable e.g. PHP in the upload directory
  $cmd[] = new DanteStringCommand ( DanteCommon::cmdZipEncRestore ( $source, " tar -xvf - -C " . escapeshellarg ( $parent ) . " --exclude='.htaccess'", $zip, $enc ), "<b>Restoring</b> uploaded files" );
  // files without a database record (e.g. when no pages dump with uploads was restored) get one, so the wiki knows them
  $cmd[] = new DanteStringCommand ( "php $IP/maintenance/rebuildImages.php --missing", "Registering files without database record" );
  return $cmd;
}




// TODO: also need sql variant of this
// generate the commands for restoring from local file
public function getCommandsFILE ($fileName, $info, $originalName = null) {
  global $IP;
  [$db, $enc, $zip, $tar] = self::checkName ( $originalName ?? $fileName );

   danteLog ("DanteBackup", "\n getCommandsFILE ");
  $cmd = [];
  $cmd[] = new DanteStringCommand ( "echo " . escapeshellarg ( $info ) );   // quoted, since the uploaded file name is user input
  if ( $tar ) { return array_merge ( $cmd, self::getFilesRestoreCommands ( "cat " . escapeshellarg ( $fileName ) . " | ", $zip, $enc ) ); }   // file archive
  $cmd[] = new DanteStringCommand ( "cat $fileName | php $IP/extensions/DanteBackup/countFilter.php " );  // TODO LACKS unzp and decrypt
  $cmd[] = new DanteStringCommand ( DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --namespaces '8'  ", $zip, $enc ) );    // get MediaWiki: namespace (need Parsifal templates on board first)
  $cmd[] = new DanteStringCommand ( DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --namespaces '10' ", $zip, $enc ) );    // get Template: namespace
  $cmd[] = new DanteStringCommand ( DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --uploads         ", $zip, $enc ) );    // TODO: can we really merge this into "all the rest" ?????
  $cmd = self::addPostImportCommands ( $cmd );  // do maintenance stuff we need to do after every import
  return $cmd;
}










// TODO: ALSO NEED a variant for Database files ........
// generate the commands for restoring from $file from AWS S3 instance
private function getCommandsAWS ($fileName) {
  global $IP;
  [$db, $enc, $zip, $tar] = self::checkName ($fileName);

  $env = DanteCommon::getEnvironmentUser ($this->getUser());
  $bucketName = $env["AWS_BUCKET_NAME"];

  danteLog ("DanteBackup", "getCommandsAWS: doImportAWS: $fileName, enc:" .print_r ($enc, true). " zip: ". print_r($zip,true)."\n");
  danteLog ("DanteBackup", "we have " . ($enc ? "ENC " : " NO-ENC ") . "  "  . ($zip ? "ZIP" : " NO-ZIp" )  . "\n");

  $cmd = [];

  // TODO aesPassword !!!!!!
  //  cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName - | ", " php $IP/maintenance/importDump.php --namespaces '8' --debug 2>&1 ", $zip, $enc);

  danteLog ("DanteBackup", "\n Selected file is $fileName \n");

  if ($fileName == "ERROR") {
    $cmd[] = new DanteStringCommand ( "echo 'The system signalled an error and so this option is not available'" );
    return $cmd;
  }

  if ($tar) {   // file archive; bucket and file name quoted, since the selected name comes from the request
    $cmd = self::getFilesRestoreCommands ( "/opt/myenv/bin/aws s3 cp " . escapeshellarg ( "s3://$bucketName/$fileName" ) . " - | ", $zip, $enc );
  }
  elseif ($db) {
 //   gunzip -c mediawiki_dump.sql.gz | mysql -u wikiuser -p wikidb

  }
  else {

//    danteLog ("DanteBackup", "environment in getCommandAWS is ".print_r ($env, true));

   //array_push ($arr, "/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName - | openssl aes-256-cbc -d -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC > /tmp/DONE-ZWEI ");  // works
    $source = "/opt/myenv/bin/aws s3 cp " . escapeshellarg ( "s3://$bucketName/$fileName" ) . " - | ";   // quoted, since the selected file name comes from the request
    $cmd    = self::getPagesRestoreCommands ( $source, $zip, $enc );
  }
  return $cmd;
}


// TODO: ALSO NEED a variant for Database files ........
public static function getCommandsURL ($url, $user) {
  global $IP;
  [$db, $enc, $zip, $tar] = self::checkName ($url);

  $env = DanteCommon::getEnvironmentUser ( $user ); // TODO: needed ?? password ???  // TODO: hopefully not. CAVE: DanteInitialStore.php uses this as well

  $cmd = [];
  $cmd[] = new DanteStringCommand ( "echo " . escapeshellarg ( $url ) );
  if ( $tar ) { return array_merge ( $cmd, self::getFilesRestoreCommands ( "curl -L " . escapeshellarg ( $url ) . " | ", $zip, $enc ) ); }   // file archive
  $source = "curl -L " . escapeshellarg ( $url ) . " | ";   // quoted, since the URL comes from the request
  $cmd    = array_merge ( $cmd, self::getPagesRestoreCommands ( $source, $zip, $enc ) );
  return $cmd;
}


/**
 * Commands for restoring from a file at an SCP location, streamed without local storage, as for AWS S3.
 * A file archive is read once; a page archive is fetched anew for each import step.
 *
 * @param string $host Host name, optionally with :port
 * @param string $path Absolute path, or relative to the home directory of the SCP user
 * @return DanteCommand[]
 * @throws Exception on an illegal host or an unsupported file type
 */
private function getCommandsSCP ( string $host, string $path ): array {
  DanteCommon::checkScpHost ( $host );
  [$db, $enc, $zip, $tar] = self::checkName ( $path );                                                                                     // fail early on an unknown file type
  if ( $db ) { throw new Exception ( "Restoring database dumps is not implemented yet" ); }    // TODO: fix
  $url = DanteCommon::scpUrl ( "scp", $host, $path );

  // curl speaks scp and takes the password; without -o it writes to stdout, so it is the start of the pipe
  // credentials from the environment, so the displayed command does not contain them; the host key must be known in ~/.ssh/known_hosts   // TODO: host key issue still to be resolved / initialized
  $source = "curl -sS --fail --user \"\$DANTE_SCP_USER:\$DANTE_SCP_PW\" " . escapeshellarg ( $url ) . " | ";
  $cmd    = [ new DanteStringCommand ( "echo " . escapeshellarg ( "Restoring from SCP: $host:$path" ) ) ];
  return array_merge ( $cmd, $tar ? self::getFilesRestoreCommands ( $source, $zip, $enc ) : self::getPagesRestoreCommands ( $source, $zip, $enc ) );
}





// generates a prefix for the choice form with Database: Pages: or Files: as prefix
private static function getPrefix ($name) {
  if (str_ends_with ($name, ".sql.gz.aes") || str_ends_with ($name, ".sql.aes") || str_ends_with ($name, ".sql.gz") || str_ends_with ($name, ".sql") ) return "<b  class='prefix-database'>Database: </b>";
  if (str_ends_with ($name, ".xml.gz.aes") || str_ends_with ($name, ".xml.aes") || str_ends_with ($name, ".xml.gz") || str_ends_with ($name, ".xml") ) return "<b  class='prefix-pages'>Pages: </b>";
  if (str_ends_with ($name, ".tar.gz.aes") || str_ends_with ($name, ".tar.aes") || str_ends_with ($name, ".tar.gz") || str_ends_with ($name, ".tar") ) return "<b class='prefix-files'>Files:    </b>";
}



// does an ls of the  AWS S3 bucket and returns the result apropriately formatted for a radio button selection form for the file
private function getFileArray () {
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  $env = DanteCommon::getEnvironmentUser ($this->getUser());
  $cmd = "/opt/myenv/bin/aws s3api list-objects-v2 --bucket {$bucketName} --query 'Contents[].[Key,LastModified,Size]' --output json";
  $retCode = Executor::executeAWS_FG_RET ( $cmd, $env, $output, $error );  // TODO: still written in blocking mode - might want to fix eventually
  $retArray = array ();

  // parse the result
  if ($retCode == 0) {
    $objects = json_decode($output, true);  // Decode the JSON output into a PHP array // TODO: could be in error
    if (is_array($objects)) {
      $filtered = array_filter($objects, function ($object) { 
        $name = $object[0];
        return 
          str_ends_with ($name, ".sql.gz.aes") || str_ends_with ($name, ".sql.aes") || str_ends_with ($name, ".sql.gz") || str_ends_with ($name, ".sql") ||
          str_ends_with ($name, ".xml.gz.aes") || str_ends_with ($name, ".xml.aes") || str_ends_with ($name, ".xml.gz") || str_ends_with ($name, ".xml") ||
          str_ends_with ($name, ".tar.gz.aes") || str_ends_with ($name, ".tar.aes") || str_ends_with ($name, ".tar.gz") || str_ends_with ($name, ".tar");
      });
      $filtered = array_values ( $filtered );

      usort($filtered, function ($a, $b) {return strtotime($b[1]) - strtotime($a[1]);});    // Sort the objects by LastModified in descending order

      foreach ($filtered as $object) {
        $retArray[  "<span class='table-name'  >". self::getPrefix($object[0]). trim($object[0])."</span> ".     // blanks between the spans, since no CSS rule separates them
                    "<span class='table-stamp' >". $object[1] . "</span> ".
                    "<span class='table-size'  >". number_format ($object[2]/ (1024*1024), 2)  . "[MB] </span>"]   = $object[0];
       }  // foreach
    }
    else { $retArray["<div style='color:red;'><p>ERROR: Could not parse the following JSON return from aws:</p><p><code>$output</code></p></div>"]= "ERROR";}
  }
  else {
    $retArray["<div style='color:red;'><p>ERROR: An error occured while processing the following aws listing command:</p/><code>$cmd</code><p>The stderr contained this information: $error.</p><p>The command exit code was $retCode</p></div>"] = "ERROR";
  }
  return $retArray;
}


// service function: appends to the command array $cmd all those commands required after an import of an xml archive; return the new command array
// this is the command version
private static function addPostImportCommands ( $cmd ) {
  global $IP;
  // see https://www.mediawiki.org/wiki/Manual:ImportDump.php about how we must run this after an import // TODO: really all of this ????
   
  $cmd[]=  new DanteStringCommand ("php $IP/maintenance/rebuildrecentchanges.php") ; 
  $cmd[]=  new DanteStringCommand ("php $IP/maintenance/initSiteStats.php --update ") ;
  $cmd[]=  new DanteStringCommand ("php $IP/maintenance/rebuildImages.php") ; 
  $cmd[]=  new DanteStringCommand ("php $IP/maintenance/rebuildall.php") ; 
  $cmd[]=  new DanteStringCommand ("php $IP/maintenance/checkImages.php") ; 
  $cmd[]=  new DanteStringCommand ("php $IP/maintenance/refreshFileHeaders.php --verbose") ;
  return $cmd;
}




// returns commands to prepare a local git instance and place files in there from git
private function gitClone ( $owner, $repo, $branch, $token ) {
  global $IP;
  $targetDir = InfoExtractor::makeTempDir ();

  // all form inputs end up in shell commands: URL-encode the URL parts and shell-quote every argument, so quotes or $(...) in them cannot inject commands
  $repoUrl   = escapeshellarg ( sprintf( 'https://%s:%s@github.com/%s/%s.git', rawurlencode($owner), rawurlencode($token), rawurlencode($owner), rawurlencode($repo) ) );
  $branchArg = escapeshellarg ( $branch );
  $dirArg    = escapeshellarg ( $targetDir );

  $cmds = [
    new DanteStringCommand ( "git clone --depth=1 --single-branch --branch $branchArg $repoUrl $dirArg",                  "<b>Cloning</b> $owner/$repo branch $branch" ),   // clone remote repository
    new DanteStringCommand ( "php $IP/extensions/DanteCommon/importDirectory.php --ddir $dirArg --slug --overwrite",      "<b>Importing</b> into the wiki" ),               // import contents into wiki
    new DanteStringCommand ( "rm -Rf $dirArg",                                                                            "Removing temporary directory" )                  // clean temporary directory
  ];
  return $cmds;
}


} // end class




