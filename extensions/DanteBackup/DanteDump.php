<?php

require_once ("DanteCommon.php");
require_once ("extensions/DanteCommon/ServiceEndpointHelper.php");
require_once ("extensions/DanteCommon/PageCollection.php");


class DanteDump extends DanteSpecialPage {

public function __construct () { parent::__construct( 'DanteDump', 'dante-dump' ); }

// page provides hint to read-only mode engine that it will not write
public function doesWrites():bool {return false;}

// a password entered in the form replaces the encryption password from the preferences for this dump
protected function adjustEnvironment ( array $env ): array {
  $pw = $this->getRequest()->getVal ( 'encpw', '' );
  if ( $pw !== '' ) { $env['LOCAL_FILE_ENC'] = $pw; }

  // target SSH / SCP: credentials go into the environment, not into the command, so they are neither shown in the progress display nor visible in the process list
  if ( $this->getRequest()->getVal ( 'target' ) === 'ssh' ) {
    $env['DANTE_SCP_USER'] = $this->getRequest()->getVal ( 'scpUser', '' );
    $env['DANTE_SCP_PW']   = $this->getRequest()->getVal ( 'scpPassword', '' );   // always entered manually, no stored password
  }
  return $env;
}

// target SSH / SCP: user, host and password must be given; checked before any work, since the fields are not required in the form (they are hidden for the other targets)
private function requireScpInput (): void {
  $request = $this->getRequest();
  if ( $request->getVal ( 'scpUser', '' ) === '' || $request->getVal ( 'scpPassword', '' ) === '' ) { throw new Exception ( "Target SSH / SCP needs SCP User and SCP Password" ); }
  DanteCommon::checkScpHost ( $request->getVal ( 'scpHost', '' ) );
  DanteCommon::hostKeyOption ( $this->getUser(), $request->getCheck ( 'scpSkipHostKey' ) );   // throws if the host key fingerprint is missing in the preferences and the check is not skipped
}

// stop before any work if encryption is selected but no password is available: openssl would silently encrypt with the empty password
private function requireEncryptionPassword ( $enc ): void {
  if ( !$enc ) { return; }
  $env = $this->adjustEnvironment ( DanteCommon::getEnvironmentUser ( $this->getUser() ) );
  if ( ( $env['LOCAL_FILE_ENC'] ?? '' ) === '' ) { throw new Exception ( "Encryption is selected, but there is no password: enter one in the form or set it in Special:Preferences, tab DanteWiki" ); }
}

// true only for our own manifests: a pc_ file directly in the temp directory, as written by PageCollection::makeManifest; form input naming other files is rejected
private static function isOwnManifest ( string $path ): bool {
  return dirname ( $path ) === sys_get_temp_dir() && preg_match ( '/^pc_[A-Za-z0-9]+$/', basename ( $path ) ) && is_file ( $path );
}

/**
 * Additionally to the standard behaviour, serve a manifest file as plain text for ?action=manifest&name=pc_...
 *
 * @param string|null $par
 * @return void
 */
public function execute ( $par ): void {
  $request = $this->getRequest();
  if ( $request->getVal ( 'action' ) !== 'manifest' ) { parent::execute ( $par ); return; }

  $this->checkPermissions();   // same right as for the page itself
  $name = $request->getVal ( 'name', '' );

  $path = sys_get_temp_dir() . "/" . basename ( $name );
  if ( !self::isOwnManifest ( $path ) ) { throw new HttpError ( 404, "No such manifest file" ); }   // prevents reading arbitrary files via the URL

  $this->getOutput()->disable();
  header ( "Content-Type: text/plain; charset=utf-8" );
  readfile ( $path );
}

protected function showForm (): void {
  // send post data to THIS url and add action=submit to the URL so we can distinguish showing this page from submitting data to it
  $action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );  
  $out    = $this->getOutput();
  $out->addModuleStyles ( ['ext.DanteBackup.specialpage.styles'] );          // this is a styles-only module which mediawiki loads early enough to prevent FOUC
  $out->addModules      ( ['ext.DanteBackup.specialpage' ] );                // this loads JS, later

  $out->addHTML (wfMessage ("dante-page-dump-intro"));                    // show some intro text
  $out->addHelpLink( 'index.php?title=Help:DanteDump', true );  // provide a help link   // TODO: must fill with contents

  self::maskSystem         ( $out, $action);
  self::maskPages          ( $out, $action );
  self::maskFiles          ( $out, $action );
  self::maskDatabaseTables ( $out, $action );  // shown to all; the right dante-dbdump is checked on execution, see getSpecificCommands
}



// generate the command for the PagesFiles option
private function commandPagesFiles () {
  global $IP;
  $request = $this->getRequest();

  $tag         = $request->getVal ( 'tag' );                    danteLog ("DanteBackup", "tag $tag \n");                  // identifying tag
  $srces       = $request->getVal ( 'srces' );                  danteLog ("DanteBackup", "srces $srces \n");
  $srcFeatures = $request->getVal ( 'srcFeatures' );            danteLog ("DanteBackup", "srcFeatures $srcFeatures \n");  // currentversion  allrevisions
  $files       = $request->getVal ( 'files' );                  danteLog ("DanteBackup", "files $files \n");              // nofiles metadata separate include
  $target      = $request->getVal ( 'target' );                 danteLog ("DanteBackup", "target $target \n");            // aws ssh client window server
  $zip         = $request->getVal ( 'compressed' ) ?? false;    danteLog ("DanteBackup", "zip $zip \n");                  // "1" if checked, null otherwise
  $enc         = $request->getVal ( 'encrypted' )  ?? false;    danteLog ("DanteBackup", "enc $enc \n");                  // "1" if checked, null otherwise
  $this->requireEncryptionPassword ( $enc );

  // check the target and its input before the spec file is created, so a wrong target or incomplete SCP data leaves no temporary file behind
  if ( !in_array ( $target, [ "aws", "ssh", "client", "window", "server" ], true ) ) { throw new Exception ( "Target '$target' is not supported" ); }
  if ( $target === "ssh" ) { $this->requireScpInput (); }

  // get the values stored in the preferences
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );
  danteLog ("DanteBackup", "bucketName $bucketName\n");

  switch ( $srcFeatures ) {
    case     "currentversion":    $fullOpt = "--current"; break;
    case     "allrevisions":      $fullOpt = "--full";    break;
    default:                      throw new Exception ("Illegal value for parameter srcFeatures: $srcFeatures");
  }

  // filesOpt    controls which information on wiki uploaded files is present
  switch ( $files ) {
    case     "nofiles":        $filesOpt = "";                          break;
    case     "metadata":       $filesOpt = "--uploads";                 break;
    case     "separate":       $filesOpt = "";                          break;
    case     "include":        $filesOpt = "--uploads --include-files"; break;
    default:                   throw new Exception ("Illegal value for parameter files: $files");
  }

  $archiveName = $this->archiveName ( $tag, "-$srces-$srcFeatures-$files", "xml", $zip, $enc );   // checks the tag, so do this before any work is done
  $specFile    = self::generateSpecFile ( $srces );                                                    // temporary file with the titles of the selected pages

  $pageCommand = " php $IP/maintenance/dumpBackup.php $fullOpt $filesOpt --pagelist=" . escapeshellarg ( $specFile );     danteLog ("DanteBackup", "\nDanteDump: pageCommand for dumping is: " . $pageCommand);   // --pagelist restricts the dump to the selected pages
  $generatePages =  self::wrap ($pageCommand , $zip, $enc );   danteLog ("DanteBackup", "\nDanteDump: generatePages for dumping is: " . $generatePages);

  $cmds = $this->routeToTarget ( $generatePages, $archiveName, $target, $zip, $enc, $bucketName );   // send the dump to the selected destination

  // remove the temporary spec file: client and window have already dumped (null), aws and server dump later from the session, so they get a final cleanup command
  // NOTE: if the dump fails and stopOnError skips the cleanup, the file stays in the system temp directory
  if ( $cmds === null ) { @unlink ( $specFile ); }
  else                  { $cmds[] = new DanteStringCommand ( "rm -f " . escapeshellarg ( $specFile ), "Removing temporary page list" ); }
  return $cmds;
}


private function commandDB () {
  global $IP;
  $request = $this->getRequest();

  $tag         = $request->getVal ( 'tag' );                    danteLog ("DanteBackup", "tag $tag \n");                  // identifying tag
  $target      = $request->getVal ( 'target' );                 danteLog ("DanteBackup", "target $target \n");            // aws ssh client window server
  $zip         = $request->getVal ( 'compressed' ) ?? false;    danteLog ("DanteBackup", "zip $zip \n");                  // "1" if checked, null otherwise
  $enc         = $request->getVal ( 'encrypted' )  ?? false;    danteLog ("DanteBackup", "enc $enc \n");                  // "1" if checked, null otherwise
  $this->requireEncryptionPassword ( $enc );

  // dump the complete MediaWiki database; --single-transaction gives a consistent snapshot without locking InnoDB tables, --default-character-set=binary keeps text bytes unchanged
  global $wgDBname, $wgDBserver, $wgDBuser;
  $dumpCommand = "mysqldump --skip-ssl --single-transaction --default-character-set=binary --host=" . escapeshellarg ($wgDBserver) . " --user=" . escapeshellarg ($wgDBuser) . " " . escapeshellarg ($wgDBname);   // password comes from MYSQL_PWD in the environment, see DanteCommon::getEnvironmentUser
  $dumpCommand = self::wrap ($dumpCommand, $zip, $enc );   
  danteLog ("DanteBackup", "\nDanteDump: wrapped dumpCommand is: " . $dumpCommand);   // add pipefail, compression and encryption as selected

  $archiveName = $this->archiveName ( $tag, "", "sql", $zip, $enc );   // the .sql suffix lets DanteRestore recognize it as database dump
  $bucketName  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  // --skip-ssl
  // --single-transaction:  take a consistent snapshot withut locking the tables 
  // --default-character-set=binary: suggesred by MediaWiki manual so that a character-set mismatch poses no problem
  // escapeshellarg, so special characters (eg in password) do not break shell
  return $this->routeToTarget ( $dumpCommand, $archiveName, $target, $zip, $enc, $bucketName );   // send the dump to the selected destination, never into the event stream

}


// generate the commands for dumping the uploaded files as tar archive; target, compression and encryption as for the other dumps
private function commandFiles () {
  $request = $this->getRequest();
  $tag     = $request->getVal ( 'tag' );                    danteLog ("DanteBackup", "tag $tag \n");                  // identifying tag
  $target  = $request->getVal ( 'target' );                 danteLog ("DanteBackup", "target $target \n");            // aws ssh client window server
  $zip     = $request->getVal ( 'compressed' ) ?? false;    danteLog ("DanteBackup", "zip $zip \n");                  // "1" if checked, null otherwise
  $enc     = $request->getVal ( 'encrypted' )  ?? false;    danteLog ("DanteBackup", "enc $enc \n");                  // "1" if checked, null otherwise
  $this->requireEncryptionPassword ( $enc );

  $archiveName = $this->archiveName ( $tag, "", "tar", $zip, $enc );   // checks the tag; .tar suffix marks a file archive
  $bucketName  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );
  $cmd         = self::wrap ( $this->getFilesCommand (), $zip, $enc );   // compression and encryption by the same pipe as for pages and database
  danteLog ("DanteBackup", "\nDanteDump: wrapped files command is: " . $cmd);

  return $this->routeToTarget ( $cmd, $archiveName, $target, $zip, $enc, $bucketName );
}


/**
 * Build the archive name: wiki name, tag, date, an optional middle part, extension and the suffixes for compression and encryption.
 *
 * @return string e.g. Dantewiki-dump-2026-05-07-at-22-44-07-all-allrevisions-include.xml.gz.aes
 * @throws Exception if the tag contains other characters than letters, digits, _ and -
 */
private function archiveName ( ?string $tag, string $middle, string $ext, $zip, $enc ): string {
  global $wgSitename;
  // server-side check: the form pattern is enforced only by the browser; a posted tag like ../../x would let target "server" write outside the dump directory
  if ( !preg_match ( '/^[A-Za-z0-9_-]+$/', $tag ?? '' ) ) { throw new Exception ( "Illegal tag '$tag': only letters, digits, _ and - are allowed" ); }
  $name = preg_replace ( '/[^A-Za-z0-9_-]+/', '_', $wgSitename ) . "-" . $tag . "-" . date ( 'Y-m-d-\a\t-H-i-s' ) . $middle . "." . $ext . ( $zip ? ".gz" : "" ) . ( $enc ? ".aes" : "" );   // wiki name reduced to safe characters as well, since it ends up in paths and shell commands
  danteLog ("DanteBackup", "archiveName $name \n");
  return $name;
}


/**
 * Run $cmd with the same environment as targets aws and server get (preferences plus form input) and copy its stdout to the browser.
 * passthru cannot be given an environment, and PHP-FPM clears its own, so openssl and mysqldump would miss LOCAL_FILE_ENC and MYSQL_PWD.
 *
 * @param string $cmd Dump command, already wrapped for compression and encryption
 * @return int Exit code of the command
 */
private function streamToBrowser ( string $cmd ): int {
  $env    = $this->adjustEnvironment ( DanteCommon::getEnvironmentUser ( $this->getUser() ) );   // same environment as aws and server get through the session
  $errLog = tempnam ( sys_get_temp_dir(), "dante_err_" );                                        // stderr into a file, so a full stderr pipe cannot block the dump
  $proc   = proc_open ( $cmd, [ 1 => ['pipe', 'w'], 2 => ['file', $errLog, 'w'] ], $pipes, null, $env );
  if ( $proc === false ) { @unlink ( $errLog ); throw new Exception ( "Could not start dump command" ); }

  while ( ob_get_level() > 0 ) { ob_end_clean(); }                                 // stream directly instead of collecting a possibly huge dump in output buffers
  while ( !feof ( $pipes[1] ) ) { echo fread ( $pipes[1], 65536 ); flush(); }      // copy stdout to the browser as it comes
  fclose ( $pipes[1] );
  $exitCode = proc_close ( $proc );

  // headers and data are already sent, so errors can only be logged
  $err = file_get_contents ( $errLog );  @unlink ( $errLog );
  if ( $exitCode !== 0 || $err !== '' ) { danteLog ( "DanteBackup", "streamToBrowser: exit=$exitCode stderr: $err\n" ); }
  return $exitCode;
}


/**
 * Send the output of a (wrapped) dump command to the selected target.
 * For aws, ssh and server it returns the commands to be executed via the service endpoint;
 * for client and window it streams the output directly to the browser and returns null.
 *
 * @param string $cmd         Dump command, already wrapped for compression and encryption
 * @param string $archiveName File name of the archive, including suffix
 * @param string $target      One of aws, ssh, client, window, server
 * @param mixed  $zip         Whether compression was selected (sets content type for client)
 * @param mixed  $enc         Whether encryption was selected (sets content type for client)
 * @param string $bucketName  AWS bucket, used for target aws
 * @return array|null Commands to be executed, or null if output was already sent
 * @throws Exception on an unknown target or an unusable dump directory
 */
private function routeToTarget ( $cmd, $archiveName, $target, $zip, $enc, $bucketName ) {
  switch ( $target ) {
    case "aws":      
      $cmd .= " | /opt/myenv/bin/aws s3 cp -  " . escapeshellarg ( "s3://{$bucketName}/{$archiveName}" );  // quoted, since the tag is user input
      danteLog ("DanteBackup", "aws command: $cmd\n");
      return [ new DanteStringCommand ( $cmd, "<b>Dumping</b> to AWS S3 as $archiveName" ) ];  // Command to be executed, wrapped into an array

    case "ssh":
      $this->requireScpInput ();
      $scpHost = $this->getRequest()->getVal ( 'scpHost' );
      $scpDir  = $this->getRequest()->getVal ( 'scpPath', '' );
      $scpFile = ( $scpDir === '' ? $archiveName : rtrim ( $scpDir, '/' ) . '/' . $archiveName );   // empty path: home directory of the SCP user; as for the restore, absolute or relative to that home directory
      // upload streamed: curl reads the dump from stdin (-T -); sftp, since scp needs the file size in advance; credentials from the environment; host key pinned by fingerprint; --ftp-create-dirs creates missing directories of the path, also for sftp
      $cmd .= " | curl -sS --fail " . DanteCommon::hostKeyOption ( $this->getUser(), $this->getRequest()->getCheck ( 'scpSkipHostKey' ) ) . " --user \"\$DANTE_SCP_USER:\$DANTE_SCP_PW\" --ftp-create-dirs -T - " . escapeshellarg ( DanteCommon::scpUrl ( "sftp", $scpHost, $scpFile ) );
      danteLog ("DanteBackup", "ssh command: $cmd\n");
      return [ new DanteStringCommand ( $cmd, "<b>Dumping</b> to $scpHost as $scpFile" ) ];

    case "client":
      $this->getOutput()->disable();   
      DanteCommon::contentTypeHeader ( $zip, $enc, preg_match ( '/\.(tar|sql)(\.|$)/', $archiveName, $m ) ? $m[1] : "xml" );   // type of the archive before compression and encryption
      header( "Content-disposition: attachment;filename={$archiveName}" ); // attachment: do a download
      $this->streamToBrowser ( $cmd );
      return null;

    case "window":
      $this->getOutput()->disable();
      header( "Content-Type: text/plain; charset=utf-8" );
      header( "Content-Disposition: inline; filename={$archiveName}" );   // inline: display, do not download
      $this->streamToBrowser ( $cmd );            // same output as for client, including compression and encryption if selected
      return null;

    case "server":     
      if ( !is_dir ( DanteCommon::DUMP_PATH ) )    { if ( !mkdir(DanteCommon::DUMP_PATH, 0777, true) ) {throw new Exception("Failed to create directory:".DanteCommon::DUMP_PATH); } }
      if ( !is_writable (DanteCommon::DUMP_PATH) ) { if ( !chmod(DanteCommon::DUMP_PATH, 0777) ) { throw new Exception("Directory is not writable and chmod failed: ".DanteCommon::DUMP_PATH); } }
      $cmd .= " > " . escapeshellarg ( DanteCommon::DUMP_PATH . "/" . $archiveName );
      $doLs = "ls -l " . escapeshellarg ( DanteCommon::DUMP_PATH . "/" . $archiveName );
      return [ new DanteStringCommand ( $cmd, "<b>Dumping</b> to server as $archiveName" ), new DanteStringCommand ( $doLs, "Listing the dump file" ) ];   // Commands to be executed

    default:              throw new Exception ("Illegal value found for target: $target This should not happen");
  }
}


private function maskSystem ($out, $action) : void {
  // TODO: must clean up by deleting files later !! (manifest, intermediary git directories and MUCH more !!!)

  // Determines which files have to be dumped
  $config = PageCollection::getConfig();                                 // get an array of PageCollections (and there, in PageCollection, it is defined which files we dump)
  $manifest = PageCollection::makeManifest ( $config );     // build a manifest file from this Pagecollection. It contains all the files we should dump

  $sumNum = 0;
  $text = ""; foreach ( $config as $value) {$text .= $value->label; $sumNum += $value->num; unlink ($value->filename); }  // format proper output text AND unlink intermediary individual collection files

  $uniqueNum = self::countUniqueNonEmptyLines ($manifest);
 
  $out->addHTML ("<h2>Dump System Pages</h2>");
  $out->addHTML ("<details><summary>Dumps Pages of the DanteWiki System but no User Pages to a Git Repository for System Development</summary>");

  $manifestUrl = htmlspecialchars ( $this->getPageTitle()->getLocalURL ( [ 'action' => 'manifest', 'name' => basename ( $manifest ) ] ) );   // served by execute(), since /tmp is not reachable from the web
  $text = "<details><summary>Total number of Files mentioned: <b>" . $sumNum . "  of which " .$uniqueNum . " unique ones</b></summary> " .$text . "<p><b>Manifest:</b> <a href=\"$manifestUrl\" target=\"_blank\" rel=\"noopener\">" . htmlspecialchars ( $manifest ) . "</a></details>";

  $user = $this->getUser();
  $GIT_TOKEN        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'github-dante-wiki-contents' ); 

   $header = [
     'araw_info'       => [ 'section' => 'selected-files', 'class' => 'HTMLInfoField', 'raw' => true, 'default' => $text ],  // only displays the affected files
     'GIT_OWNER'       => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Git Owner',      'name' => 'GIT_OWNER',   'type' => 'text',  'default' => 'clecap' ],
     'GIT_REPO'        => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Repository',     'name' => 'GIT_REPO',    'type' => 'text',  'default' => 'dante-wiki-contents' ],
     'GIT_BRANCH'      => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Branch',          'name' => 'GIT_BRANCH',  'type' => 'text',  'default' => 'master' ],
     'GIT_COMMIT'      => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Commit Message',  'name' => 'GIT_COMMIT',  'type' => 'text',  'default' => 'Commit by DanteDump for initial contents'],
     'GIT_TOKEN'       => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Access Token',    'name' => 'GIT_TOKEN',   'type' => 'text',  'default' => $GIT_TOKEN],
     'MANIFEST_FILE'   => [ 'type' => 'hidden', 'name' => 'MANIFEST_FILE', 'default' => $manifest ],   // not displayed, but still sent with the request for the git dump
   ];
  
   self::standardForm ($header, $action, "sys", "Dump System Pages");
   $out->addHTML ("</details>");
}



const TARGET_SELECTOR = [ 'section' => 'target' , 'type' => 'radio',  'label' => '', 
        'options' => [ 
           '<b>AWS S3</b> (shows error messages; may take minutes to hours)'                                                                                 => "aws",
           '<b>SSH / SCP</b> (shows error messages; may take minutes to hours)'                                                                              => "ssh",
           "<b>Client</b> (save as file on the client using the browser)"                                                                                    => "client",
           "Window (show it in the browser window - for debug)"                                                                                              => "window",
           '<b>Server</b> (shows error messages; may take minutes to hours; only testing or when server accessible)'                                         => "server",
        ], 
        'name' => 'target',  'default' => 'aws',
];

// fields for target SSH / SCP; cssclass dante-scp-field lets ext.DanteBackup.specialpage.js show them only while this target is selected
// not required in the form, since hidden fields would block the submit for the other targets; requireScpInput checks them on the server
private function scpFields (): array {
  $lookup = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup();
  return [
    'scpUser'     => [ 'section' => 'target', 'type' => 'text',     'name' => 'scpUser',     'label' => 'SCP User',     'cssclass' => 'dante-scp-field', 'default' => $lookup->getOption ( $this->getUser(), 'ssh-dump-user' ) ],   // defaults from the preferences
    'scpHost'     => [ 'section' => 'target', 'type' => 'text',     'name' => 'scpHost',     'label' => 'SCP Host',     'cssclass' => 'dante-scp-field', 'default' => $lookup->getOption ( $this->getUser(), 'ssh-host' ) ],
    'scpPath'     => [ 'section' => 'target', 'type' => 'text',     'name' => 'scpPath',     'label' => 'SCP Path',     'cssclass' => 'dante-scp-field' ],   // target directory; empty: home directory of the SCP user
    'scpPassword' => [ 'section' => 'target', 'type' => 'password', 'name' => 'scpPassword', 'label' => 'SCP Password', 'cssclass' => 'dante-scp-field', 'autocomplete' => 'new-password' ],   // entered every time, never stored or prefilled
    'scpSkipHostKey' => [ 'section' => 'target', 'type' => 'check', 'name' => 'scpSkipHostKey', 'label' => 'Skip Host Key Check', 'cssclass' => 'dante-scp-field' ],   // never checked by default
  ];
}


const ZIP =  [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Compress',   'name' => 'compressed', 'type' => 'check' , 'default' => true ];
const ENC =  [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Encrypt',    'name' => 'encrypted',  'type' => 'check' , 'help-message' => 'help-enc', 'default' => true ];
const ENCPW = [ 'section' => 'features', 'type' => 'password', 'label' => 'Password', 'name' => 'encpw', 'size' => 30, 'help-message' => 'help-encpw', 'autocomplete' => 'new-password' ];   // empty: use the password from the preferences; never prefilled, so the stored password does not appear in the page source


// MASK for the PAGES and FILES dump action
private function maskPages ($out, $action) {

  $out->addHTML ("<h2>Dump Pages &amp; Files</h2>");
  $out->addHTML ("<details><summary>Dumps Pages and Possibly Files as Single Archive</summary>");
  $form = [
    'tag'     => [ 'section' => 'header', 'class' => 'HTMLTextField', 'size' => 20, 'label' => 'Identifying Tag', 'name' => 'tag', 'type' => 'text', 'default' => 'dump',
       'pattern' => '[A-Za-z0-9_-]+', 'title' => 'Enter a tag which shows up as part of the name of the dump' ],
  
    'radio88'  => [ 'section' => 'srcfeatures/rb' , 'type' => 'radio',  'label' => '', 
      'options' => [ '<i>No pages</i> included in the pages archive'                                                                                                   => "nopages",
                     '<b>Listed</b>: Only pages listed in page <a href="./index.php?title=MediaWiki:Backupfiles">MediaWiki:Backupfiles</a>'                            => "listed",
                     '<b>Category</b>: Only pages belonging to <a href="./index.php?title=Category:Backup">Category:Backup</a>'                                        => "category",
                     '<b>Categories</b>: Only pages belonging to a category listed in <a href="./index.php?title=MediaWiki:Backupcategories">MediaWiki:Backupcategories</a>'     => "categories",
                     '<b>Categories Indirect</b>: Only pages belonging to a category or an arbitrarily deep subcategory of a category listed in <a href="./index.php?title=MediaWiki:Backupcategories">MediaWiki:Backupcategories Indirect</a>'     => "categories-indirect",
                     '<b>All</b> pages'                                                                                                                                => "all", 
                       ],   
       'name' => 'srces',  'default' => 'all', 
    ],

    'radio33'  => [ 'section' => 'srcfeatures/ra' , 'type' => 'radio',  'label' => '', 
        'options' => [ '<b>Current versions</b> only'                                   => "currentversion",
                       '<b>All revisions</b> included'                                  => "allrevisions", 
                     ],   
        'name' => 'srcFeatures',  'default' => 'allrevisions', 
     ],
    'radio32'  => [ 'section' => 'srcfeatures/rf' , 'type' => 'radio',  'label' => '', 
          'options' => [ '<i>Nofiles</i>: Do not dump uploaded file contents and do not dump metadata'                        => "nofiles",
                         '<i>Metadata</i>: Dump metadata only, no file contents'                                              => "metadata",
                         '<b>Separate</b>: Dump metadata (into page archive) and file contents into a separate file archive'  => "separate", 
                         '<b>Include</b>: Dump metadata and file contens (both into one large page archive)'                  => "include", 
                     ],   
        'name' => 'files',  'default' => 'include', 
   ], 'radio' => self::TARGET_SELECTOR,
  ...$this->scpFields (),
  'zip'       => self::ZIP,
  'enc'       => self::ENC,
  'encpw'     => self::ENCPW
  ];

  self::standardForm ($form, $action, "pages", "Do the dump");
  $out->addHTML ("</details>");
}



// MASK for the FILES dump action
private function maskFiles ( $out, $action ) {
  $out->addHTML ("<h2>Dump Files Only</h2>");
  $out->addHTML ("<details><summary>Dumps files which were uploaded to this DanteWiki</summary>");
  $form = [
    'tag'     => [ 'section' => 'header', 'class' => 'HTMLTextField', 'size' => 20, 'label' => 'Identifying Tag', 'name' => 'tag', 'type' => 'text', 'default' => 'dump',
       'pattern' => '[A-Za-z0-9_-]+', 'title' => 'Enter a tag which shows up as part of the name of the dump' ],
     'zip'    => self::ZIP,
     'enc'    => self::ENC,
     'encpw'  => self::ENCPW,
    'radio'   => self::TARGET_SELECTOR,
    ...$this->scpFields ()
  ];
  
  self::standardForm ($form, $action, "dump", "Dump Files");
  $out->addHTML ("</details>");
}


// MASK for the DATABASE dump action
private function maskDatabaseTables ( $out, $action ) {
  $out->addHTML ("<h2>Dump Database Tables</h2>");
  $out->addHTML ("<details><summary>Dumps Database Tables in SQL Text Form.</summary>");

  $form = [
    'tag'      => [ 'section' => 'header', 'class' => 'HTMLTextField', 'size' => 20, 'label' => 'Identifying Tag', 'name' => 'tag', 'type' => 'text', 'default' => 'dump',
                    'pattern' => '[A-Za-z0-9_-]+', 'title' => 'Enter a tag which shows up as part of the name of the dump' ],
    'radio'    =>  self::TARGET_SELECTOR,
    ...$this->scpFields (),
    'zip'      =>  self::ZIP,
    'enc'      =>  self::ENC,
    'encpw'    =>  self::ENCPW
  ];

  self::standardForm ($form, $action, "dumpDB", "Dump Database Tables");
  $out->addHTML ("</details>");
}



// get the specific commands depending on the form whose submit button was pressed
protected function getSpecificCommands ( $formId ): mixed {
  $request = $this->getRequest();
  switch ($formId) {
    case "formId_sys":
      $manifestFile = $request->getVal ( 'MANIFEST_FILE', '' );
      if ( !self::isOwnManifest ( $manifestFile ) ) { throw new Exception ( "Invalid manifest file" ); }   // the hidden field can be changed in the request; accept only our own manifests
      $gitDir = InfoExtractor::makeTempDir ();  // make a temporary directory in which all this will take place
      // export articles in manifest file to the temporary git directiry
      $generate = new DanteCallableCommand ( [InfoExtractor::class, 'exportManifestToTextFiles'], [ "manifestFile" => $manifestFile, "outDir" => "$gitDir", "clean" => false], "<b>Exporting</b> files in manifest" );
      $cmd = self::gitPrepare ( $request->getVal ('GIT_OWNER'),  $request->getVal ('GIT_REPO'),  $request->getVal ('GIT_BRANCH'),  $request->getVal ('GIT_COMMIT'),  $request->getVal ('GIT_TOKEN') , $generate, $gitDir, $manifestFile );
      return $cmd;

    case "formId_pages":    $cmd = $this->commandPagesFiles (); return $cmd;
    case "formId_dump":     $cmd = $this->commandFiles ();     return $cmd;
    case "formId_dumpDB":
      if ( !$this->getUser()->isAllowed ( 'dante-dbdump' ) ) { throw new PermissionsError ( 'dante-dbdump' ); }   // the form is shown to all; only the execution needs the right
      $cmd = $this->commandDB ();         return $cmd;

    default:
      throw new Exception ("Not yet implemented or illegal form id: $formId");
  }
}





// TODO: make password more secure !!!
/** wraps a command with
 *    1) a leading pipefail to prevent errors along the pipe to be masked
 *    2) piping into first compression and then encryption
 */
private function wrap ( $cmd, $zip, $enc ) {
  if ( $cmd === "" ) {return "";}
  $retval  = "set -o pipefail; " . $cmd;          // return in a pipe sequence the exit code of the first failing command
  $retval .= ($zip ? " | gzip " : " ");
  $retval .= ($enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC " : " ") ;
  return $retval;
}



// returns commands to prepare a local git instance and place files in there
private static function gitPrepare ( string $GIT_OWNER, string $GIT_REPO, string $GIT_BRANCH, string $GIT_COMMIT, string $GIT_TOKEN, DanteCommand $generate, string $myOutputDir, string $manifestFile ) {

  $GITMAIL = "dante-himself@dante.wiki";
  $GITUSER = "Dante Wiki System";

  // all form inputs end up in shell commands: URL-encode the URL parts and shell-quote every argument, so quotes or $(...) in them cannot inject commands
  $REPO    = escapeshellarg ( "https://" . rawurlencode ($GIT_OWNER) . ":" . rawurlencode ($GIT_TOKEN) . "@github.com/" . rawurlencode ($GIT_OWNER) . "/" . rawurlencode ($GIT_REPO) . ".git" );
  $BRANCH  = escapeshellarg ( $GIT_BRANCH );
  $COMMIT  = escapeshellarg ( $GIT_COMMIT );

  $git    = "/usr/bin/git";  // use original binary, not a possibly VS-code-patched wrapper

  $prune = new DanteCallableCommand ( [InfoExtractor::class, 'pruneToManifest'], [ "manifestFile" => $manifestFile, "outDir" => $myOutputDir ], "Pruning files which were deleted in Wiki" );

// check if the specified REPO has a specified BRANCH and if it has not, generate one at the remote; finally clone the branch, existing or freshly generated
// some git providers only allow generation of a BRANCH upon a non-empty commit, so to cater for this situation we provide for a minimal upload as well
$initBranch = <<<BASH
  git ls-remote --exit-code --heads {$REPO} {$BRANCH} || (
    tmp=\$(mktemp -d) &&
    git -C "\$tmp" init --initial-branch={$BRANCH} &&
    git -C "\$tmp" config user.name "{$GITUSER}" &&
    git -C "\$tmp" config user.email "{$GITMAIL}" &&
    git -C "\$tmp" commit --allow-empty -m init &&
    git -C "\$tmp" remote add origin {$REPO} &&
    git -C "\$tmp" push -q origin {$BRANCH} &&
    rm -rf "\$tmp"
  ) 
BASH;

  $showGitStatus = new DanteStringCommand ( "$git -C $myOutputDir status", "<b>Git Status</b>" );


// NOTE: git -C allows to specify a directory and spares us the cd to the directory

  $cmds = [
  //  [ "command" => "git ls-remote --exit-code --heads {$REPO} {$GIT_BRANCH}" ,               "comment" => "<b>Testing existence of branch</b> $GIT_BRANCH at $GIT_OWNER/$GIT_REPO" ],
    new DanteStringCommand ( $initBranch,                                                              "<b>Conditionally generating branch</b> $GIT_BRANCH in case it is misssing" ),
    new DanteStringCommand ( "git clone --depth 1 --branch {$BRANCH} {$REPO} {$myOutputDir}",          "<b>Git Cloning</b> from $GIT_BRANCH at $GIT_OWNER/$GIT_REPO" ),
    new DanteStringCommand ( "$git -C $myOutputDir config user.name \"$GITUSER\"",                     "Set git commit user.name to $GITUSER" ),
    new DanteStringCommand ( "$git -C $myOutputDir config user.email \"$GITMAIL\"",                    "Set git commit user.email to $GITMAIL" ),
    $showGitStatus,
    $generate,                                                  // export wiki pages into the local clone 
    $showGitStatus,
    $prune,                                                     // TODO: does it ????? deletes .git/HEAD  
    $showGitStatus,
    new DanteStringCommand ( "$git -C $myOutputDir add . ",           "Staging git files" ),
    $showGitStatus,

    new DanteStringCommand ( "$git -C $myOutputDir diff --cached --name-status ",  "List staged files" ),

    // commit only when staged changes exist (git diff --cached exits 1 when changes are present) - otherwise we get an error status when there is nothing to commit
    new DanteStringCommand ( "{ if $git -C $myOutputDir diff --cached --quiet; then echo 'Nothing to commit'; else $git -C $myOutputDir commit -m $COMMIT; fi; }", "<b>Git Commit</b>" ),
    $showGitStatus,  
    new DanteStringCommand ( "$git -C $myOutputDir push --verbose $REPO $BRANCH",                      "Pushing to $GIT_BRANCH at $GIT_OWNER/$GIT_REPO" ),
    $showGitStatus,
  //  [ "command" => "rm -Rf $myOutputDir",                                                    "comment" => "Remove temp directory"]
  ];


  

  return $cmds;
}


/**
 * Write the given titles, one per line, into file $name; throw if writing fails.
 *
 * @param string   $name   Full path of the spec file
 * @param string[] $titles Page titles to write
 * @return void
 * @throws Exception
 */
private static function writeSpecFile ( string $name, array $titles ): void {
  if ( file_put_contents ( $name, implode ("\n", $titles) ) === false ) { throw new Exception ("Could not write spec file $name"); }  // file_put_contents only warns, so turn failure into an exception
}


/**
 * Given a srces specification for pages to dump, build a temporary file with their titles, one per line, for dumpBackup.php --pagelist
 *
 * @param string $spec Permissible are: "nopages", "listed", "category", "categories", "categories-indirect", "all"
 * @return string Path of the temporary spec file; the caller must remove it after the dump
 */
private static function generateSpecFile ( $spec ): string {
  // a fresh temporary file per dump, so parallel dumps do not overwrite each other's list and nothing is written into the extension directory
  $NAME = tempnam ( sys_get_temp_dir(), "dante_spec_" );
  if ( $NAME === false ) { throw new Exception ("Cannot create temporary spec file"); }

  switch ( $spec ) {
    case "nopages":
      self::writeSpecFile ( $NAME, [] );   // empty list: dumpBackup.php then dumps no pages at all (and therefore also no uploaded files)
      break;
    case "listed":
      InfoExtractor::articleExtract ("MediaWiki:Backupfiles", true, $NAME);  
      break; 
    case "category":          
      $titles = InfoExtractor::getTitlesOfCategory ("Backup");
      self::writeSpecFile ( $NAME, $titles );
      break;
    case "categories":     
      $listOfCategories  = InfoExtractor::articleExtract ("MediaWiki:Backupcategories", true, null);
      $arrayOfCategories = explode ("\n", $listOfCategories);
      $arrayOfCategories = array_filter($arrayOfCategories, function ($str) {return trim($str) !== ''; });  // remove empty entries
      $arrayOfTitles     = InfoExtractor::getTitlesOfCategories ( $arrayOfCategories );
      self::writeSpecFile ( $NAME, $arrayOfTitles );
      break;// TODO check and fix
    case "categories-indirect":
      $arrayOfCategories = InfoExtractor::getIndirectSubcategories ("MediaWiki:Backupcategories indirect");
      $arrayOfTitles     = InfoExtractor::getTitlesOfCategories ( $arrayOfCategories );
      self::writeSpecFile ( $NAME, $arrayOfTitles );
      break;

    case "all":
      $arrayOfTitles = InfoExtractor::getAllTitles ();          // all pages in all namespaces
      self::writeSpecFile ( $NAME, $arrayOfTitles );
      break;

    default: throw new Exception ("Wrong value for parameter pages: $spec");
  }

  danteLog ("DanteBackup", "\nDanteDump: generated spec file by name of " . $NAME);
  return $NAME;
}


// tar command writing all uploaded files to stdout; compression and encryption are added by wrap, so plain tar without -z here
private function getFilesCommand (): string {
  global $wgUploadDirectory;
  $dir  = rtrim ( $wgUploadDirectory, '/' );
  $base = basename ( $dir );
  // -C: paths in the archive relative to the parent, e.g. images/a/ab/File.png; thumbnails, temporary files and locks are regenerated by MediaWiki and need no backup
  // -v: lists every archived file; since the archive itself goes to stdout, tar writes this list to stderr, which the progress display shows
  // .htaccess (at any depth) is left out: it is part of the installation, not of the uploads, and the restore must never take it from an archive
  return "tar -cvf - -C " . escapeshellarg ( dirname ( $dir ) ) . " --exclude=" . escapeshellarg ( "$base/thumb" ) . " --exclude=" . escapeshellarg ( "$base/temp" ) . " --exclude=" . escapeshellarg ( "$base/lockdir" ) . " --exclude='.htaccess' " . escapeshellarg ( $base );
}




// TODO: getConfigFile and makeLongList and makeCatFileList  should be moved into DanteCommon extension since we need that for other stuff timezone_offset_get
// TODO: extension.json of DanteBackup should declare that we need DanteCommon installed as prerequistite.  The others maybe as well !!



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
      'action'  => 'query',
      'list'    => 'categorymembers',
      'cmtitle' => 'Category:' . $category,
      'cmtype'  => 'subcat',
      'cmlimit' => 'max',
      'format'  => 'json',
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
      foreach ($data['query']['allpages'] as $page) {$allPageNames[] = $page['title'];}
    }

    $continue = isset($data['continue']['apcontinue']) ? $data['continue']['apcontinue'] : '';
  } while ($continue);

  return $allPageNames;
}



private static function countUniqueNonEmptyLines( string $filePath ): int {
  $lines = file( $filePath, FILE_IGNORE_NEW_LINES );
  if ( $lines === false ) { return 0; }
  $unique = array_unique( array_filter( $lines, fn( $l ) => trim( $l ) !== '' ) );
  return count( $unique );
}

}  // end of class
