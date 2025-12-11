<?php

require_once ("DanteCommon.php");
require_once ("Executor.php");


// a memo is a single record for controlling the functionality of uploading DanteWiki system contents files
class Memo {

public string $name;        // name of the collection of contents
public string $filename;    // temporary file name used as pagelist for this collection
public string $contents;    // contents
public string $num;         // number of elements of the collection
public string $label;       // label for the collection
public string $url;         // url for looking uo by user

public static function getConfig () {
 $config = [
    new Memo ( "Cat_DanteInitialContents",         DanteUtil::catList      ("DanteInitialContents"),              "Category:DanteInitialContents" ),
    new Memo ( "Cat_DanteInitialCustomize",        DanteUtil::catList      ("DanteInitialCustomize"),             "Category:DanteInitialCustomize"),
    new Memo ( "MediaWiki_DanteInitialContents",   DanteUtil::listOfListed ("MediaWiki:DanteInitialContents"),    "MediaWiki:DanteInitialContents" ),
    new Memo ( "MediaWiki_DanteInitialCustomize",  DanteUtil::listOfListed ("MediaWiki:DanteInitialCustomize"),   "MediaWiki:DanteInitialCustomize" ),
    new Memo ( "Test",                             DanteUtil::listOfNamespace (NS_TEST),                          "Special:AllPages&from=&to=&namespace=3000" ),
    new Memo ( "MainPage",                         DanteUtil::singleList ("Main Page"),                            "Main_Page" ), 
    new Memo ( "MediaWiki_Sidebar",                DanteUtil::singleList ("MediaWiki:Sidebar"),                   "MediaWiki:Sidebar" )
  ];
  return $config;
}

public function __construct ( string $name, string $filename, string $url ) {
  global $wgServer, $wgScriptPath;
  $this->name      = $name;
  $this->filename  = $filename;
  $this->url       = $url;
  $this->contents  = file_get_contents ($this->filename);
  $this->num       = substr_count($this->contents, "\n");

  $this->label = "<li>All $this->num pages belonging to <a href='$wgServer/$wgScriptPath/index.php?title=$this->url'>$this->url</a>
    <div style='max-height:200px; overflow-y:scroll; overflow-x:hidden; margin:20px;'><pre style='margin:0px; padding:0px;'>$this->contents</pre></div></li>";

}

public function execute ( $owner, $repository, $token, $path, $out=false ) {
  global $IP;

  // generate a manifest file and upload that manifest file
  $response = DanteUtil::storeToGithub ($owner, $repository, "$path/$this->filename-manifest.txt", $token, $this->contents);
  if ($out) $out->addHTML( '<h3>got filepath:</h3><p><pre>' . $this->filename . '</pre></p>' );

  // dump the contents as requested into into variable $output
  $cmd = "php  $IP/maintenance/dumpBackup.php --current --uploads  --include-files  --pagelist=$this->filename";
  $ret = Executor::execute ( $cmd, $output, $error, $duration );

  if ($out) $out->addHTML ( "<h3>dumpBackup wrote to stderr:</h3><p><pre>" .$error. "</pre></p>");
  $response = DanteUtil::storeToGithub ($owner, $repository, "$path/$this->name.xml.gz",  $token,   gzencode ($output) );   // upload a .xml.gz variant
  $response = DanteUtil::storeToGithub ($owner, $repository, "$path/$this->name.xml",    $token,   $output );               // upload a .xml variant

  $json = json_decode ($response);
  // if ($json->status != 200) { throw new Exception ("PROBLEM");}  // TODO: fix ?!?
  $json_indented_by_4 = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
  $json_indented_by_2 = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $json_indented_by_4);
  if ($out) $out->addHTML( '<h3>Github server replied to API call:</h3><p><pre>' . $json_indented_by_2 . '</pre></p>' );
  @unlink ($filepath);
}

} // end class


class DanteInitialStore extends SpecialPage {

public function __construct () { parent::__construct( 'DanteInitialStore' ); }

public function getGroupName() {return 'dante';}
  
public function execute ( $subPage ) {
  $this->setHeaders();
  $this->outputHeader();
  $out = $this->getOutput();
    
  // Check if form is submitted
  $request       = $this->getRequest();
  $owner         = $request->getText ('owner');
  $repository    = $request->getText ('repository');
  $path          = $request->getText ('path');
  $token         = $request->getText ('token');
  $check         = $request->getText ('check');           // used to check if this invocation is from a submission or not

  $config        = Memo::getConfig();   // get the configuration data from the one place where we configure it

  $user   = $this->getUser();
  $token        = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'github-dante-wiki-contents' );

  if ( $check === '12345' ) {  // Display submitted input - only in case we really submitted a token 
    $out->addHTML ("<h3>Details of Script Execution </h3>");
    foreach ( $config as $val ) { $val->execute( $owner, $repository, $token, $path, $out);}
  }
  else {

  // Add explanatory text and form
  $text = <<<EOT
  <p>This special page uploads the current content of Dante System Pages to the dante-wiki-contents github repository.</p>
  <p>It is meant as service function for use (only) by the maintainers of the dante-wiki system.</p>
  <p>Its purpose is to allow the maintainer direct amendment of a Dante System Page from whatever installation.</p>
  <p style='max-width:800px;'>Update processes in existing DanteWikis may overwrite some of those pages.
  Owners of a DanteWiki wanting to prevent this for selected files,
    can remove the category links in the respective pages or remove the page from MediaWiki:InitialContents.</p>
  <h3>Data used in the process</h3>
  <form method="post" action="">
    <table>
      <tr><td><label>Owner</label></td>            <td><input type="text" name="owner"       size="80"  readonly value="clecap"/></td></tr>
      <tr><td><label>Repository</label></td>       <td><input type="text" name="repository"  size="80"  readonly value="dante-wiki-contents"/></td></tr>
      <tr><td><label>Path</label></td>             <td><input type="text" name="path"        size="80"  readonly value="assets/initial-contents"/></td></tr>
      <tr><td><label>Access Token</label></td>     <td><input type="text" name="token"       size="80"  readonly value="$token"/></td></tr>
    </table>

  EOT;

    $text .= "<h3>List of Pages we will upload</h3> <ol>";
    foreach ( $config as $val ) { $text .= $val->label; }
    $text .= "</ol>";
    $text .= '<input type="submit" value="Submit and wait for result"/>';
    $text .= '<input type="hidden" name="check" value="12345" />';
    $text .= '</form>';
    $out->addHTML( $text );

  }


  // NOTE: for the github upload api we need the contents in a shell variable (max 2MB)
  // Should this prove insufficient, we must iterate over chunks or individual files (or activate compression for the storage on github
 
} // end function execute


} // end class

// TODO; MUST clear /tmp files afterwrds - we still do not do so !!




class DanteInitialLoad extends SpecialPage {

public function __construct () { parent::__construct( 'DanteInitialLoad' ); }

public function getGroupName() {return 'dante';}
  

// TODO: CAVE: THIS LACKS CSRF check !!!!!!!!!


public function execute ( $subPage ) {
  //  if (! $this->getUser()->isAllowed ("dante-restore") ) { $this->getOutput()->addHTML ("You do not have the permission to restore."); return;}  

  danteLog ("DanteBackup", "Dante InitialLoad:execute called\n");

  $user   = $this->getUser();
  $out    = $this->getOutput();

  $text = <<<EOT
  <h1>Special:DanteInitialLoad: Loading or Updating Initial or Default DanteWiki Contents</h1>
  <form method="post" action="">
    <table>
      <tr><td><label>URL Path to Directory</label></td>  <td><input type="text" name="urlpath"       size="80"  value="https://github.com/clecap/dante-wiki-contents/raw/master/assets/initial-contents/"/></td></tr>
    </table>
    <input type="hidden" name="was-sent" value="4" />
    <input type="submit" value="Submit"/>
  </form>
  EOT;

  $out->addHTML ( $text );
  $request    = $this->getRequest();
  $urlpath    = $request->getText ('urlpath');
  $wasSent    = $request->getText ('was-sent');

  danteLog ("DanteBackup", "Will request produced: $wasSent\n");
  if ( $wasSent == "4" ) {
    danteLog ("DanteBackup", "Dante InitialLoad:execute Was submitted\n");
    $this->useTransactionalTimeLimit();  // raise time limit for this operation
    $config = Memo::getConfig();   // get the configuration data from the one place where we configure it
    danteLog ("DanteBackup", "Dante InitialLoad:execute will now loop\n");
    foreach ($config as $val) { 
      // danteLog ("DanteBackup", "Dante InitialLoad: will now do ".$val->url."\n");
      DanteRestore::doImportURL ( $urlpath . "/". $val->name.".xml.gz", $this->getUser());
    }

   
  }



}



} // end class

?>