<?php

/**
 * With require WebStart.php (MW_INSTALL_PATH may need to be set beforehand, see Manual:$IP), 
 * a script gets access to MediaWiki components and consequently it can call the API internally or
 */

// need the following two lines to obtain reasonable errors from the endpoint instead of only 500 er status from webserver
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once ("danteEndpoint.php");

$this->parse( $wikitext )->getText( [ 'wrapperDivClass' => '' ] );

class RevealEndpoint extends DanteEndpoint {

public function ensureEnvironment () {
  $this->parser = MediaWikiServices::getInstance()->getParserFactory()->create();
}

public function getContent ( $text ) {
  PageReference $pageReference = new DanteDummyPageReference();
  ParserOptions $options = new ParserOptions ();
  $options->setRemoveComments (false);
  $options->setSuppressTOC (true);
  $lineStart = true;
  $clearState = true;
  $revid = null;

  $ret = Parser::parse ( $text, $pageReference, $options, $linestart, $clearState, $revid);
  return $ret;
}

}


class DanteDummyPageReference implements MediaWiki\Page\PageReference {
   public function getWikiId() {return self::local} ////////////////////////////// ARE THEY THINKING OF REMOTE WIKI INTERCONNECTION - that would be challenging and cool! TODO: rethink
   public function getNamespace() {return 0;}  // Main:
   public function getDBkey() {return "DUMMY_DB_KEY";}
   public function isSamePageAs( PageReference $other ) {return $this==$other;}  // NOT CLEAR FOR WHAT THIS IS NEEDED ??
   public function __toString() { return "DUMMYPAGEREFERENCE"; }
}



























