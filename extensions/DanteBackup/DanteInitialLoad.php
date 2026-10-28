<?php

require_once ("DanteCommon.php");
require_once (__DIR__ . "/../DanteCommon/PageCollection.php");



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
