<?php

require_once ("DanteCommon.php");
require_once ("extensions/DanteCommon/ServiceEndpointHelper.php");
require_once ("extensions/DanteCommon/PageCollection.php");


global $IP;

class DanteDump extends DanteSpecialPage {

public static $SPEC_PREFIX;  // initialized at end of class

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

  self::maskSystem         ( $out, $action);
  self::maskPages          ( $out, $action );
  self::maskFiles          ( $out, $action );
  self::maskDatabaseTables ( $out, $action );  
}



// generate the command for the PagesFiles option
private function commandPagesFiles () {
  global $IP;
  $request = $this->getRequest();

  $tag         = $request->getVal ( 'tag' );                    danteLog ("DanteBackup", "tag $tag \n");                  // identifying tag
  $srces       = $request->getVal ( 'srces' );                  danteLog ("DanteBackup", "srces $srces \n");
  $srcFeatures = $request->getVal ( 'srcFeatures' );            danteLog ("DanteBackup", "srcFeatures $srcFeatures \n");  // currentversion  allrevisions
  $files       = $request->getVal ( 'files' );                  danteLog ("DanteBackup", "files $files \n");              // nofiles metadata separate include
  $target      = $request->getVal ( 'target' );                 danteLog ("DanteBackup", "target $target \n");            // aws ssh client server
  $zip         = $request->getVal ( 'compressed' ) ?? false;    danteLog ("DanteBackup", "zip $zip \n");                  // true  false
  $enc         = $request->getVal ( 'encrypted' )  ?? false;    danteLog ("DanteBackup", "enc $enc \n");                  // true false
     
  // get the values stored in the preferences
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );
  danteLog ("DanteBackup", "bucketName $bucketName\n");

  // build production commands, TODO: ??? prepend with a pipefail so we do not mask error condistions along the pipe and append compression and encryption

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

  self::generateSpecFile  ( $srces );  // generate a file containing all the names of files to be dumped  // TODO: must cleanup later

  $pageCommand = " php $IP/maintenance/dumpBackup.php $fullOpt $filesOpt";     danteLog ("DanteBackup", "\nDanteDump: pageCommand for dumping is: " . $pageCommand);
  $generatePages =  self::wrap ($pageCommand , $zip, $enc );   danteLog ("DanteBackup", "\nDanteDump: generatePages for dumping is: " . $generatePages);

  // name of the archive: wiki name followed by the identifying tag; whitespace replaced since the name ends up in a shell command
  global $wgSitename;
  $archiveName = preg_replace ( '/\s+/', '_', $wgSitename . "-" . $tag . "-" . date ( 'Y-m-d-\a\t-H-i' ) ) . "-".$srces."-".$srcFeatures."-".$files. ".xml" . ( $zip ? ".gz" : "" ) . ( $enc ? ".aes" : "" );   
  danteLog ("DanteBackup", "archiveName $archiveName \n");

  return $this->routeToTarget ( $generatePages, $archiveName, $target, $zip, $enc, $bucketName );   // send the dump to the selected destination
 
}


private function commandDB () {
  global $IP;
  $request = $this->getRequest();

  $tag         = $request->getVal ( 'tag' );                    danteLog ("DanteBackup", "tag $tag \n");                  // identifying tag
  $target      = $request->getVal ( 'target' );                 danteLog ("DanteBackup", "target $target \n");            // aws ssh client server
  $zip         = $request->getVal ( 'compressed' ) ?? false;    danteLog ("DanteBackup", "zip $zip \n");                  // true  false
  $enc         = $request->getVal ( 'encrypted' )  ?? false;    danteLog ("DanteBackup", "enc $enc \n");                  // true false

  // dump the complete MediaWiki database; --single-transaction gives a consistent snapshot without locking InnoDB tables, --default-character-set=binary keeps text bytes unchanged
  global $wgDBname, $wgDBserver, $wgDBuser;
  $dumpCommand = "mysqldump --skip-ssl --single-transaction --default-character-set=binary --host=" . escapeshellarg ($wgDBserver) . " --user=" . escapeshellarg ($wgDBuser) . " " . escapeshellarg ($wgDBname);   // password comes from MYSQL_PWD in the environment, see DanteCommon::getEnvironmentUser
  $dumpCommand = self::wrap ($dumpCommand, $zip, $enc );   
  danteLog ("DanteBackup", "\nDanteDump: wrapped dumpCommand is: " . $dumpCommand);   // add pipefail, compression and encryption as selected

  // name of the archive: wiki name, tag and date; the .sql suffix lets DanteListBackups and DanteRestore recognize it as database dump
  global $wgSitename;
  $archiveName = preg_replace ( '/\s+/', '_', $wgSitename . "-" . $tag . "-" . date ( 'Y-m-d-\a\t-H-i' ) ) . ".sql" . ( $zip ? ".gz" : "" ) . ( $enc ? ".aes" : "" );
  danteLog ("DanteBackup", "archiveName $archiveName \n");
  $bucketName  = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  // --skip-ssl
  // --single-transaction:  take a consistent snapshot withut locking the tables 
  // --default-character-set=binary: suggesred by MediaWiki manual so that a character-set mismatch poses no problem
  // escapeshellarg, so special characters (eg in password) do not break shell
  return $this->routeToTarget ( $dumpCommand, $archiveName, $target, $zip, $enc, $bucketName );   // send the dump to the selected destination, never into the event stream

}


/**
 * Send the output of a (wrapped) dump command to the selected target.
 * For aws and server it returns the commands to be executed via the service endpoint;
 * for client and window it streams the output directly to the browser and returns null.
 *
 * @param string $cmd         Dump command, already wrapped for compression and encryption
 * @param string $archiveName File name of the archive, including suffix
 * @param string $target      One of aws, client, window, server
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
      return [$cmd];  // Command to be executed, wrapped into an array

//    case "ssh":           /*  $txt = DanteCommon::dumpToAWS_FG  ( $this); */  break; // TODO: missing still
    case "client":    
      $this->getOutput()->disable();   
      DanteCommon::contentTypeHeader ($zip, $enc);
      header( "Content-disposition: attachment;filename={$archiveName}" ); // attachment: do a download
      $result = 0; 
      passthru ($cmd, $result);
      return null;

    case "window":
      $this->getOutput()->disable();
      header( "Content-Type: text/plain; charset=utf-8" );
      header( "Content-Disposition: inline; filename={$archiveName}" );   // inline: display, do not download
      $result = 0;
      passthru ($cmd, $result);                   // same output as for client (wrap already sets pipefail), including compression and encryption if selected
      return null;

    case "server":     
      if ( !is_dir ( DanteCommon::DUMP_PATH ) )    { if ( !mkdir(DanteCommon::DUMP_PATH, 0777, true) ) {throw new Exception("Failed to create directory:".DanteCommon::DUMP_PATH); } }
      if ( !is_writable (DanteCommon::DUMP_PATH) ) { if ( !chmod(DanteCommon::DUMP_PATH, 0777) ) { throw new Exception("Directory is not writable and chmod failed: ".DanteCommon::DUMP_PATH); } }
      $cmd .= " > " . escapeshellarg ( DanteCommon::DUMP_PATH . "/" . $archiveName );
      $doLs = "ls -l " . escapeshellarg ( DanteCommon::DUMP_PATH . "/" . $archiveName );
      return [$cmd, $doLs];   // Commands to be executed

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

  $text = "<details><summary>Total number of Files mentioned: <b>" . $sumNum . "  of which " .$uniqueNum . " unique ones</b></summary> " .$text . "<p><b>Manifest:</b> $manifest</details>";

  $user = $this->getUser();
  $GIT_TOKEN        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'github-dante-wiki-contents' ); 

   $header = [
     'araw_info'       => [ 'section' => 'selected-files', 'class' => 'HTMLInfoField', 'raw' => true, 'default' => $text ],  // only displays the affected files
     'GIT_OWNER'       => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Git Owner',      'name' => 'GIT_OWNER',   'type' => 'text',  'default' => 'clecap' ],
     'GIT_REPO'        => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Repository',     'name' => 'GIT_REPO',    'type' => 'text',  'default' => 'dante-wiki-contents' ],
     'GIT_BRANCH'      => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Branch',          'name' => 'GIT_BRANCH',  'type' => 'text',  'default' => 'master' ],
     'GIT_COMMIT'      => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Commit Message',  'name' => 'GIT_COMMIT',  'type' => 'text',  'default' => 'Commit by DanteDump for initial contents'],
     'GIT_TOKEN'       => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright',   'size' => 80,  'label' => 'Access Token',    'name' => 'GIT_TOKEN',   'type' => 'text',  'default' => $GIT_TOKEN],
     'MANIFEST_FILE'   => [ 'section' => 'git-data', 'class' => 'HTMLTextField', 'cssclass' => 'headright dante-readonly',   'size' => 80,  'label' => 'Manifest File',    'name' => 'MANIFEST_FILE',   'type' => 'text',  'default' => $manifest, "readonly" => true],
   ];  // need to send manifest file name in the request, maybe no need to display it here as well
  
   self::standardForm ($header, $action, "git", "Dump System Files");
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


const ZIP =  [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Compress',   'name' => 'compressed', 'type' => 'check' , 'help-message' => 'help-zip', 'default' => true ];
const ENC =  [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Encrypt',    'name' => 'encrypted',  'type' => 'check' , 'help-message' => 'help-enc', 'default' => true ];


// MASK for the PAGES and FILES dump action
private function maskPages ($out, $action) {

  $aesPW            = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-encpw' );  // the password stored in preferences

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
  'zip'       => self::ZIP,
  'enc'       => self::ENC
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
    'radio'  => self::TARGET_SELECTOR
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
    'tarname'  => [ 'section' => 'header', 'class' => 'HTMLTextField', 'cssclass' => 'headright dante-readonly', 'size' => 80, 'label' => 'File Archive',  'name' => 'tarName',   'type' => 'text', 'readonly' => true ],
    'radio'    =>  self::TARGET_SELECTOR,
    'zip'      =>  self::ZIP,
    'enc'      => self::ENC
  ];

  self::standardForm ($form, $action, "dumpDB", "Dump Database Tables");
  $out->addHTML ("</details>");
}



// get the specific commands depending on the form whose submit button was pressed
protected function getSpecificCommands ( $formId ): mixed {
  global $DUMP_PATH;

  $request = $this->getRequest();
  switch ($formId) {
    case "formId_git":
      $gitDir = InfoExtractor::makeTempDir ();  // make a temporary directory in which all this will take place
      // export articles in manifest file to the temporary git directiry
      $generate = ["command" => [InfoExtractor::class, 'exportManifestToTextFiles'], 
                   "args"    => [ "manifestFile" => $request->getVal ( 'MANIFEST_FILE' ), "outDir" => "$gitDir", "clean" => false],
                   "comment" => "<b>Exporting</b> files in manifest"
                  ];
      $cmd = self::gitPrepare ( $request->getVal ('GIT_OWNER'),  $request->getVal ('GIT_REPO'),  $request->getVal ('GIT_BRANCH'),  $request->getVal ('GIT_COMMIT'),  $request->getVal ('GIT_TOKEN') , $generate);
      return $cmd;

    case "formId_pages":    $cmd = self::commandPagesFiles (); return $cmd;
    case "formId_dumpDB":   $cmd = self::commandDB ();         return $cmd;

    default:
      throw new Exception ("Not yet implemented or illegal form id: $formId");  
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
private function wrap ( $cmd, $zip, $enc, $omitZip = false ) {
  if ( $cmd === "" ) {return "";}
  $retval  = "set -o pipefail; " . $cmd;          // return in a pipe sequence the exit code of the first failing command
  if ( !$omitZip ) {$retval .= ($zip ? " | gzip " : " ");}       // compress unless explicitly told to omit it
  $retval .= ($enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC " : " ") ; 
  return $retval; 
}



// TODO: deprecate this in favor of wrap
// command decorator for a command $cmd which produces a stream while dumping; result then gets piped/redirected into different sinks
private static function cmdZipEncDump ( $cmd, $zip, $enc ) {
  return "set -o pipefail; " . $cmd . ($zip ? " | gzip " : " ") . ($enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC " : " ") ; 
}



// returns commands to prepare a local git instance and place files in there
private static function gitPrepare ( string $GIT_OWNER, string $GIT_REPO, string $GIT_BRANCH, string $GIT_COMMIT, string $GIT_TOKEN, $generate ) {

  $GITMAIL = "dante-himself@dante.wiki"; 
  $GITUSER = "Dante Wiki System";
  $REPO    = "https://$GIT_OWNER:$GIT_TOKEN@github.com/$GIT_OWNER/$GIT_REPO.git";

  $myOutputDir  = $generate["args"]["outDir"];          // pick up from generation command
  $manifestFile = $generate["args"]["manifestFile"];

  $git    = "/usr/bin/git";  // use original binary, not a possibly VS-code-patched wrapper

  $prune = ["command" => [InfoExtractor::class, 'pruneToManifest'], "args"    => [ "manifestFile" => $manifestFile, "outDir" => $myOutputDir ], "comment" => "Pruning files which were deleted in Wiki"];

// check if the specified REPO has a specified BRANCH and if it has not, generate one at the remote; finally clone the branch, existing or freshly generated
// some git providers only allow generation of a BRANCH upon a non-empty commit, so to cater for this situation we provide for a minimal upload as well
$initBranch = <<<BASH
  git ls-remote --exit-code --heads {$REPO} {$GIT_BRANCH} || (
    tmp=\$(mktemp -d) &&
    git -C "\$tmp" init --initial-branch={$GIT_BRANCH} &&
    git -C "\$tmp" config user.name "{$GITUSER}" &&
    git -C "\$tmp" config user.email "{$GITMAIL}" &&
    git -C "\$tmp" commit --allow-empty -m init &&
    git -C "\$tmp" remote add origin {$REPO} &&
    git -C "\$tmp" push -q origin {$GIT_BRANCH} &&
    rm -rf "\$tmp"
  ) 
BASH;

  $showGitStatus = ["command" => "$git -C $myOutputDir status",  "comment" => "<b>Git Status</b>"];


// NOTE: git -C allows to specify a directory and spares us the cd to the directory

  $cmds = [
  //  [ "command" => "git ls-remote --exit-code --heads {$REPO} {$GIT_BRANCH}" ,               "comment" => "<b>Testing existence of branch</b> $GIT_BRANCH at $GIT_OWNER/$GIT_REPO" ],
    [ "command" => $initBranch,                                                              "comment" => "<b>Conditionally generating branch</b> $GIT_BRANCH in case it is misssing"],
    [ "command" =>  "git clone --depth 1 --branch {$GIT_BRANCH} {$REPO} {$myOutputDir}",     "comment" => "<b>Git Cloning</b> from $GIT_BRANCH at $GIT_OWNER/$GIT_REPO"],
    [ "command" =>  "$git -C $myOutputDir config user.name \"$GITUSER\"",                    "comment" => "Set git commit user.name to $GITUSER"],
    [ "command" => "$git -C $myOutputDir config user.email \"$GITMAIL\"",                    "comment" => "Set git commit user.email to $GITMAIL" ],
    $showGitStatus,
    $generate,                                                  // export wiki pages into the local clone 
    $showGitStatus,
    $prune,                                                            // TODO: does it ????? deletes .git/HEAD  
    $showGitStatus,
    [ "command" => "$git -C $myOutputDir add . " ,           "comment" => "Staging git files"],                    
    $showGitStatus,

    [ "command" => "$git -C $myOutputDir diff --cached --name-status ",  "comment" => "List staged files"],

    // commit only when staged changes exist (git diff --cached exits 1 when changes are present) - otherwise we get an error status when there is nothing to commit
    [ "command" => "{ if $git -C $myOutputDir diff --cached --quiet; then echo 'Nothing to commit'; else $git -C $myOutputDir commit -m \"$GIT_COMMIT\"; fi; }", "comment" => "<b>Git Commit</b>"],   
    $showGitStatus,  
    [ "command" => "$git -C $myOutputDir push --verbose $REPO $GIT_BRANCH",                  "comment" => "Pushing to $GIT_BRANCH at $GIT_OWNER/$GIT_REPO" ],
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
 * Given a srces specification for files to dump, build a file containing the specified file names under a standardized name
 *
 * @param string $spec Permissible are: "listed", "category", "categories", "categories-indirect" 
 *                     
 * @return void
 */
private static function generateSpecFile ( $spec ) {
  $NAME = self::$SPEC_PREFIX . $spec;

  // make sure the target directory exists, since file_put_contents does not create directories
  if ( !is_dir ( self::$SPEC_PREFIX ) && !mkdir ( self::$SPEC_PREFIX, 0775, true ) ) { throw new Exception ("Cannot create directory " . self::$SPEC_PREFIX); }

  switch ( $spec ) {
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



}


// TODO: fix or deprecate
private function getFilesCommand () {
  global $wgUploadDirectory;
  $tarOptions = ( $this->enc ? "-cvzf" : "-cvf" );    // encryption is done by tar itself, decryption is automagic when doing tar -x
  $cmd = "tar $tarOptions - --exclude='thumb' \"$wgUploadDirectory\" ";  // TODO Test this. Do we really want -v (lists all files ??)  and what about -z ... is this not doubling the compression thingie???
  return $cmd;
}


// TODO: if the command execution fails - we need a more prominent, red error message at the end produced by the javascript for the serviceEndpoint

// TODO: List Backups should also report tar files.
// TODO: Restore should also apply to tar files 

// TODO: getConfigFile and makeLongList and makeCatFileList  should be moved into DanteCommon extension since we need that for other stuff timezone_offset_get
// TODO: extension.json of DanteBackup should declare that we need DanteCommon installed as prerequistite.  The others maybe as well !!
// TODO factor some stuff from Parsifal into DanteCommon
// TODO: remove that branch in Parsifal .... github



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




DanteDump::$SPEC_PREFIX = $IP."/extensions/DanteBackup/lists/"; 