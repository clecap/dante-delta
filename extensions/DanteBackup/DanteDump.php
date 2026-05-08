<?php

require_once ("DanteCommon.php");
require_once ("extensions/DanteCommon/ServiceEndpointHelper.php");
require_once ("extensions/DanteCommon/PageCollection.php");


$DUMP_PATH = "/var/www/html/wiki-dir/dump";    // TODO: allow this as setting in the configuration file !!  // Path to place the server-local dumps to

global $IP;

class DanteDump extends DanteSpecialPage {

public static $SPEC_PREFIX;  // initialized at end of class

#region  DATA which may be moved around in the scope of this class

// TODO: clean this up since we now use this as local variables in the calls
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

 
  // TODO: must clean up by deleting files later !! (manifest, intermediary git directories and MUCH more !!!)


  // Detremines which files have to be dumped
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


  self::maskPages          ( $out, $action );
  self::maskFiles          ( $out, $action );
  self::maskDatabaseTables ( $out, $action );  
}

// the mask for the pages dump option
private function maskPages ($out, $action) {
  $out->addHTML ("<h2>Dump Pages / Files as Archive</h2>");
  $out->addHTML ("<details><summary>Dumps Pages and Possibly Files as Single Archive</summary>");
  $form = [
    'tag'     => [ 'section' => 'header', 'class' => 'HTMLTextField', 'size' => 20, 'label' => 'Identifying Tag', 'name' => 'tag', 'type' => 'text', 'default' => 'dump',
       'pattern' => '[A-Za-z0-9_-]+', 'title' => 'Enter a tag which shows up as part of the name of the dump' ],
    'archive' => [ 'section' => 'header', 'class' => 'HTMLTextField', 'cssclass' => 'headright dante-readonly', 'size' => 80, 'label' => 'Page Archive Name',     'name' => 'archiveName', 'type' => 'text', 'readonly' => true ],
    'tarname' => [ 'section' => 'header', 'class' => 'HTMLTextField', 'cssclass' => 'headright dante-readonly', 'size' => 80, 'label' => 'File Archive Name',  'name' => 'tarName',     'type' => 'text', 'readonly' => true ],
  
    'radio88'  => [ 'section' => 'srcfeatures/rb' , 'type' => 'radio',  'label' => '', 
      'options' => [ '<i>No pages</i> included in the pages archive'                                                                                                   => "nopages",
                     '<b>Listed</b>: Only pages listed in page <a href="./index.php?title=MediaWiki:Backupfiles">MediaWiki:Backupfiles</a>'                            => "listed",
                     '<b>Category</b>: Only pages belonging to <a href="./index.php?title=Category:Backup">Category:Backup</a>'                                => "category",
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
          'options' => [ '<i>Nofiles</i>: Do not dump uploaded file contents and do not dump metadata'                      => "nofiles",
                       '<i>Metadata</i>: Dump metadata only, no file contents'                                              => "metadata",
                       '<b>Separate</b>: Dump metadata (into page archive) and file contents into a separate file archive'  => "separate", 
                       '<b>Include</b>: Dump metadata and file contens (both into one large page archive)'                  => "include", 
                     ],   
        'name' => 'files',  'default' => 'include', 
   ],
    'radio'  => [ 'section' => 'target' , 'type' => 'radio',  'label' => '', 
        'options' => [ 
           '<b>AWS S3</b> (shows error messages; may take minutes to hours)'                                                                                 => "aws",
           '<b>SSH / SCP</b> (shows error messages; may take minutes to hours)'                                                                              => "ssh",
           "<b>Client</b> (save as file on the client using the browser)"                                                                                    => "client",
           '<b>Server</b> (shows error messages; may take minutes to hours; only testing or when server accessible)'                                         => "server",
        ], 
        'name' => 'target',  'default' => 'aws', 
     ]  ,  
  'zip'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Compress',   'name' => 'compressed', 'type' => 'check' , 'default' => true ],
  'enc'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Encrypt',    'name' => 'encrypted',  'type' => 'check' , 'help-message' => 'help-enc', 'default' => true ],
  ];

  self::standardForm ($form, $action, "pages", "Do the dump");
  $out->addHTML ("</details>");
}


// command for the pages dump option
private function commandPages () {
  $request = $this->getRequest();

  $archiveName = $request->getVal ( 'archiveName' );     danteLog ("DanteBackup", "archiveName $archiveName \n");

//  if ( strpos( $this->tarName, '*') !== false) { $this->tarName = ""; }  // squash it to empty if not proper

  $srces       = $request->getVal ( 'srces' );           danteLog ("DanteBackup", "srces $srces \n");
  $srcFeatures = $request->getVal ( 'srcFeatures' );     danteLog ("DanteBackup", "srcFeatures $srcFeatures \n");
  $files       = $request->getVal ( 'files' );           danteLog ("DanteBackup", "files $files \n");
  $target      = $request->getVal ( 'target' );          danteLog ("DanteBackup", "target $target \n");
  $zip         = $request->getVal ( 'compressed' ) ?? false;   danteLog ("DanteBackup", "zip $zip \n");
  $enc         = $request->getVal ( 'encrypted' )  ?? false;   danteLog ("DanteBackup", "enc $enc \n");
     
  // get the values stored in the preferences
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );
  $aesPW            = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-encpw' );

  danteLog ("DanteBackup", "bucketName $bucketName\n");
  // danteLog ("DanteBackup", "aesPW $aesPW\n");  // TODO: remove from logging, make more secure

  // build production commands, prepend with a pipefail so we do not mask error condistions along the pipe and append compression and encryption

  $pageCommand = $this->getPageCommand ( $srcFeatures, $files, $srces);
  danteLog ("DanteBackup", "pageCommand $pageCommand\n");
  $generatePages =  self::wrap ($pageCommand , $zip, $enc );
  danteLog ("DanteBackup", "wrapped pageCommand $generatePages\n");

//  if ($this->tarName !== "") {$generateTar   =  self::wrap ( $this->getFilesCommand (), $zip, $enc );} else {$generateTar = "";}  // check if we really want file tar archive

  $txt = null;
  switch ( $target ) {
    case "aws":      
      $generatePages  .= " | /opt/myenv/bin/aws s3 cp -  s3://{$bucketName}/".$archiveName;
      danteLog ("DanteBackup", "aws generatePages: $generatePages\n");
      // if ( $generateTar !== "" ) {$generateTar    .= " | /opt/myenv/bin/aws s3 cp -  s3://{$this->bucketName}/".$this->tarName; }
      //return [$generatePages, $generateTar];  // that's the commands to be executed
      return [$generatePages];  // that's the commands to be executed
      break;

    case "ssh":     /*  $txt = DanteCommon::dumpToAWS_FG  ( $this); */  break;
    case "client":        $this->getOutput()->disable();   self::dumpToBrowser ( $this );  break;
    case "server":     
      if ( !is_dir ( $DUMP_PATH ) )    { if ( !mkdir($DUMP_PATH, 0777, true) ) {throw new Exception("Failed to create directory: $DUMP_PATH"); } }
      if ( !is_writable ($DUMP_PATH) ) { if ( !chmod($DUMP_PATH, 0777) ) { throw new Exception("Directory is not writable and chmod failed: $DUMP_PATH"); } }
      $generatePages  .= " > ".$DUMP_PATH."/".$this->archiveName;
     // if ( $generateTar !== "" ) {$generateTar    .= " >" .$DUMP_PATH. "/".$this->tarName; }
      return [$generatePages, $generateTar];  // that's the commands to be executed
      break;
    default:              throw new Exception ("Illegal value found for target:" . $values["target"] . " This should not happen");
  }
 

}


private function maskFiles ( $out, $action ) {
  $out->addHTML ("<h2>Dump Files</h2>");
  $out->addHTML ("<details><summary>Dumps files which were uploaded to this DanteWiki</summary>");
  $form = [
    'tag'     => [ 'section' => 'header', 'class' => 'HTMLTextField', 'size' => 20, 'label' => 'Identifying Tag', 'name' => 'tag', 'type' => 'text', 'default' => 'dump',
       'pattern' => '[A-Za-z0-9_-]+', 'title' => 'Enter a tag which shows up as part of the name of the dump' ],
    'archive' => [ 'section' => 'header', 'class' => 'HTMLTextField', 'cssclass' => 'headright dante-readonly', 'size' => 80, 'label' => 'Page Dump',     'name' => 'archiveName_files', 'type' => 'text', 'readonly' => true ],
     'zip'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Compress',   'name' => 'compressed_files', 'type' => 'check' , 'default' => true ],
     'enc'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Encrypt',    'name' => 'encrypted_files',  'type' => 'check' , 'help-message' => 'help-enc', 'default' => true ],
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
  
  
  self::standardForm ($form, $action, "dump", "Dump Files");
  $out->addHTML ("</details>");
}



private function maskDatabaseTables ( $out, $action ) {
  $out->addHTML ("<h2>Dump Database Tables</h2>");
  $out->addHTML ("<details><summary>Dumps Database Tables in SQL Text Form.</summary>");

  $header = [
    'tag'      => [ 'section' => 'header', 'class' => 'HTMLTextField', 'size' => 20, 'label' => 'Identifying Tag', 'name' => 'tag', 'type' => 'text', 'default' => 'dump',
                    'pattern' => '[A-Za-z0-9_-]+', 'title' => 'Enter a tag which shows up as part of the name of the dump' ],
    'tarname'  => [ 'section' => 'header', 'class' => 'HTMLTextField', 'cssclass' => 'headright dante-readonly', 'size' => 80, 'label' => 'File Archive',  'name' => 'tarName',   'type' => 'text', 'readonly' => true ],
    'radio'    => [ 'section' => 'target' , 'type' => 'radio',  'label' => '', 
        'options' => [ 
           '<b>AWS S3</b> (shows error messages; may take minutes to hours)'                                                                                 => "aws",
           '<b>SSH / SCP</b> (shows error messages; may take minutes to hours)'                                                                              => "ssh",
           "<b>Client</b> (save as file on the client using the browser)"                                                                                    => "client",
           '<b>Server</b> (shows error messages; may take minutes to hours; only testing or when server accessible)'                                         => "server",
        ], 
        'name' => 'target',  'default' => 'aws', 
    ]  ,




    'zip'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Compress',   'name' => 'compressed_db', 'type' => 'check' , 'default' => true ],
    'enc'    => [ 'section' => 'features',  'class' => 'HTMLCheckField',  'label' => 'Encrypt',    'name' => 'encrypted_db',  'type' => 'check' , 'help-message' => 'help-enc-db', 'default' => true ],
  ];

  $form = array_merge ( $header, DanteCommon::getTARGET_FORM() );  // generate the form
  self::standardForm ($form, $action, "dumpDB", "Dump Database Tables");
  $out->addHTML ("</details>");
}




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

    case "formId_pages":
      $cmd = self::commandPages ();
      return $cmd;

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
  if ($omitZip !== false) {$retval .= ($zip ? " | gzip " : " ");}
  $retval .= ($enc ? " | openssl aes-256-cbc -e -salt -pbkdf2 -iter 100000 -pass env:LOCAL_FILE_ENC " : " ") ; 
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



// generate and return a shell command for dumping pages
// as side effect: generates a list of files to dump
public static function getPageCommand ( $srcFeatures, $files, $srces ) {
  global $IP;

  // fullOpt     controls the options on pages present
  switch ( $srcFeatures ) {
    case "currentversion":             $fullOpt = "--current"; break;
    case "allrevisions":               $fullOpt = "--full";    break;
    default: throw new Exception ("Illegal value for parameter srcFeatures: $srcFeatures");
  }

  // filesOpt    controls which information on wiki uploaded files is present
  switch ( $files ) {
    case "nofiles":   $filesOpt = "";                          break;
    case "metadata":  $filesOpt = "--uploads";                 break;
    case "separate":  $filesOpt = "";                          break;
    case "include":   $filesOpt = "--uploads --include-files"; break;
    default:          throw new Exception ("Illegal value for parameter files: $files");
  }

  danteLog ("DanteBackup", "Source specification is: " . $srces . "\n");

  // src controls which pages are present in the xml page archive 
  // prepare the file lists for now
 

///////  self::generateSpecFile ( null );  // generate all specification files // TODO: not yet implemented

  switch ($srces) {
    case "nopages":             //  touch ($FILE_LIST_BACKUP);  break;    // should be empty; xml dump may still contain namespace names etc.
    case "all":                   $srcOpt = " ";  break;  
    case "listed":                // NOBREAK
    case "category":              // NOBREAK
    case "categories":            // NOBREAK
    case "categories-indirect":   // $srcOpt = self::$SPEC_PREFIX . "$srces";  break;  
    default: throw new Exception ("Wrong value for parameter pages or not yet implemented: $srces");
  }

  $command = " php $IP/maintenance/dumpBackup.php $fullOpt $filesOpt $srcOpt";
  danteLog ("DanteBackup", "\nDanteDump: getCommand: Command for dumping is: " . $command);

  return $command;
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
    $prune,                                  // TODO: does it ????? deletes .git/HEAD  
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



private static function countUniqueNonEmptyLines( string $filePath ): int {
  $lines = file( $filePath, FILE_IGNORE_NEW_LINES );
  if ( $lines === false ) { return 0; }
  $unique = array_unique( array_filter( $lines, fn( $l ) => trim( $l ) !== '' ) );
  return count( $unique );
}

}  // end of class




DanteDump::$SPEC_PREFIX = $IP."/extensions/DanteBackup/lists/"; 