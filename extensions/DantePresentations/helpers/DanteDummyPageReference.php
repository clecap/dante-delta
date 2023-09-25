<?php

// The parser needs a PageReference interface to function correctly. Here we build a dummy PageReference from the information the DanteEndpoint has received via headers
class DanteDummyPageReference implements MediaWiki\Page\PageReference {
  
  private $wikiId;      // the wikiId                                 - needed for getWikiId()
  private $ns;          // number of the namespace of the page        - needed for getNamespace()
  private $dbkey;       // the page title in db key form  -    the title excluding namespace with underscores, according to https://www.mediawiki.org/wiki/Manual:Title.php   - needed for getDBkey()


  private $title;       // TODO: do we really need that ?
  private $pageName;    // TODO: do we really need that ?


  // construction function is built on the principle: null means: dynamically pick a default
  function __construct ( $wikiId, $ns, $dbkey, $title, $pageName ) {
    if ($wikiId   === null) {$this->wikiId = self::LOCAL;}     else {$this->wikiId = $wikiId;}    // default is: local wiki
    if ($ns       === null) {$this->ns     = 0;}               else {$this->ns = $ns;}            // default is: MAIN namespace
    if ($dbkey    === null) {
      $titleObject = Title::newFromText ( $title, $ns );
      if ($titleObject == null) { $this->dbkey  = "Main_Page"; }
      else {
        $this->dbkey = $titleObject->getDBKey();
        if ($this->dbkey  === null) {$this->dbkey  = "Main_Page";} 
      }
    }
    else { $this->dbkey = $dbkey;}


//  $this->pageName = "Main_Page";
//  $this->title    = "Main Page";
//  $this->wikiId = self::LOCAL;

  }

  public function getWikiId()             {return $this->wikiId;}    // required by interface PageReference
  public function getNamespace() : int    {return $this->ns;}        // required by interface PageReference
  public function getDBkey() : string     {return $this->dbkey;}     // required by interface PageReference
  public function isSamePageAs ( MediaWiki\Page\PageReference $other )  : bool {return $this==$other;}   // required by interface PageReference         // TODO: CAVE: here probably somethign different is meant thatn what we currently implement here !
  public function __toString() : string    { return "DUMMYPAGEREFERENCE"; }   // required by interface PageReference ////////////// TODO 
  public function assertWiki( $wikiId )    {return true;}  // not clear - see WikiAwareEntity.php  // TODO
}