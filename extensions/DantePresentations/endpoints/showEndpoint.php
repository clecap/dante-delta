<?php

/**
 * This endpoint provides existing content from the Mediawiki in a custom skinned form.
 */

error_reporting(E_ALL); ini_set('display_errors', 'On');

use MediaWiki\MediaWikiServices;

require_once ("danteEndpoint.php");

class ShowEndpoint extends DanteEndpoint {


function __construct () {
  parent::__construct();
  $this->printData ();
}


// ShowEndpoint gets its input from header query information, which is picked up in base class DanteEndpoint and which is set in ????
// TODO: also allow stuff in query extension of URL !!
public function getInput () {

  $nsName = MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalName ( $this->ns );
  if ($nsName !== false) {echo "The namespace name for number $this->ns is $nsName.";} 
  else {echo "The namespace number $this->ns is invalid.";}

  $searchKey =  $nsName . ":".  $this->dbkey;
  $title      =  Title::newFromDBkey ( $searchKey );  // TODO: lacks optional interwiki prefix   -   see documentaiton of class Title
  if ($title === null) {
    throw new Exception ("ShowEndpoint: could not generate title from dbkey: (" . $this->dbkey . ")\n");
  }

  $wikipage   = new WikiPage ($title);                              // get the WikiPage for that title
  $contob     = $wikipage->getContent();                            // and obtain the content object for that
  $contenttext = ContentHandler::getContentText( $contob );
  return $contenttext;
}



public function getContent ( ) {
  global $wgExtensionAssetsPath;

  $text = $this->getInput();

  $titleText = "JUSTMNYDUMMY"; // TODO ???

  $raw = "<pre> length of text=" .strlen ($text). " dbkey=" . $this->dbkey. "pagename=".$this->pageName. "  titleText=".$titleText."  ns=". $this->ns . "</pre>";

  $sect = 2;
  $hiding = false;

  $parsedText = $this->parseText ( $text, $hiding, $sect);

// TODO: here we want to inject some style files !!!!
// we would want to add css as in https://192.168.2.37/wiki-dir/load.php?lang=en&modules=ext.Parsifal%7Cskins.vector.styles.legacy&only=styles&skin=vector

  // my own bundle MUST be here as only this includes latex.ccss from PARSIFAL  // TODO: HOWEVER this only is reauired for the tex endpoint - and how we include this for the Parsifal endpoint still is very ???????????????????????????
  $cssPath = "../../../load.php?lang=en&modules=ext.Parsifal%7Cskins.vector.styles.legacy&only=styles&skin=vector";
  //$cssPath = "load.php?lang=en&modules=skins.vector.styles.legacy&only=styles&skin=vector";

  $cssPath2 = "showEndpoint.css";

  $transformScale = 3;

  $drawIOSizePatch = <<<EOD
<script>
const drawIOPatch = () => {
   console.info ("drawiopatch");
  let divs=document.querySelectorAll (".drawio + div");
  console.info ("showEndpoint.php patches",divs);
  divs.forEach ( ele => {  
     // console.info ("showEndpoint.php", ele);
    let img = ele.querySelector ("img");
     // console.info ("persphone", img);
    img.classList.add("drawioShowendpoint");
   // img.style.maxWidth="4000px";
  });
};
drawIOPatch ();
</script>
EOD;

  // build the final page which we will show

  $this->stringContent = "<!DOCTYPE html><html lang='en' dir='ltr'>"."<head>"."<meta charset='UTF-8'/><!-- Version 1-->".
    "<link rel='stylesheet' href='${cssPath}'><link rel='stylesheet' href='${cssPath2}'>".
    "<script async src='../../../load.php?lang=en&amp;modules=startup&amp;only=scripts&amp;raw=1&amp;skin=vector'></script>".
    "<script src='$wgExtensionAssetsPath/Parsifal/js/runtime.js'></script>".
    "</head>".  "<body style='transform:scale("  .$transformScale. ");transform-origin:top left;'>" . 
    "<div id='bodyContent' class='vector-body'>"  .
       "<div id='mw-content-text' class='mx-body-content mw-content-ltr'>" .
    $parsedText .  
        "</div>" .
    "</div>" .
  $drawIOSizePatch.
    "</body></html>";
  return 1;
}

} // class



$point = new ShowEndpoint ();
$point->execute();
