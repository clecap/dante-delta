<?php

require_once ("DanteCommon.php");
require_once ("Executor.php");
require_once (__DIR__ . "/../DanteCommon/PageCollection.php");



class DanteInitialStore extends SpecialPage {

public function __construct () { parent::__construct( 'DanteInitialStore' ); }

public function getGroupName() {return 'dante';}
  
public function execute ( $subPage ) {
  $this->setHeaders();
  $this->outputHeader();
  $out = $this->getOutput();
    
  // Check if form was submitted
  $request       = $this->getRequest();
  $owner         = $request->getText ('owner');
  $repository    = $request->getText ('repository');
  $path          = $request->getText ('path');
  $token         = $request->getText ('token');
  $check         = $request->getText ('check');           // used to check if this invocation is from a submission or not

  $config        = PageCollection::getConfig();                           // get the configuration data from the one place where we configure it

  $user          = $this->getUser();
  $token         = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $user, 'github-dante-wiki-contents' );

  if ( $check === '12345' ) {  // Display submitted input - only in case we really submitted a token  // TODO: make it variable SECURITY ISSUE
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
  // Should this prove insufficient, we must iterate over chunks or individual files
 
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
    $config = PageCollection::getConfig();   // get the configuration data from the one place where we configure it
    danteLog ("DanteBackup", "Dante InitialLoad:execute will now loop\n");
    foreach ($config as $val) { 
      // danteLog ("DanteBackup", "Dante InitialLoad: will now do ".$val->url."\n");
      DanteRestore::doImportURL ( $urlpath . "/". $val->name.".xml.gz", $this->getUser());
    }

   
  }

}

} // end class

?>