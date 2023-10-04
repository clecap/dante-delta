<?php

/**
 * This endpoint provides existing content from the Mediawiki in a custom skinned form.
 */

//   NOTE: Debugging this: Apache log has error messages if we get no result by direct call to endpoint

// need the following two lines to obtain reasonable errors from the endpoint instead of only 500 er status from webserver


error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once ("danteEndpoint.php");

class ShowEndpoint extends DanteEndpoint {

// ShowEndpoint gets its input from header query information, which is picked up in base class DanteEndpoint and which is set in ????
// TODO: also allow stuff in query extension of URL !!
public function getInput () {
  $searchKey =  $this->nsName . ":".  $this->dbkey;
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
  // $this->stringContent = "= HIHI = \n  '''qwe''' [[Main]]"; return 1;   // NOTE: For debugging / testing: Uncomment this for testing purposes, then call endpoint directly in browser

  $text = $this->getInput();
  $raw = "<pre> length of text=" .strlen ($text). " dbkey=" . $this->dbkey. "pagename=".$this->pageName. "  titleText=".$this->titleText."  ns=". $this->ns ." nsName=" .$this->nsName. "</pre>";

  EndpointLog ("\n showEndpoint: getContent: section is " . $this->sect. "\n");

  $parsedText = $this->parseText ( $text, $this->hiding, $this->sect);
  //if (strlen ($parsedText) == 0) {$parsedText = "INPUT TEXT WAS: " .$text. "RESULTANT TEXT IS EMPTY - Case 1";} 

//  $parsedTextSection = $this->getSection();


// TODO: here we want to inject some style files !!!!
// we would want to add css as in https://192.168.2.37/wiki-dir/load.php?lang=en&modules=ext.Parsifal%7Cskins.vector.styles.legacy&only=styles&skin=vector

  // TODO: make this selectable via interface
  // $cssPath = "mediawikiEndpoint.css";

  // my own bundle MUST be here as only this includes latex.ccss from PARSIFAL  // TODO: HOWEVER this only is reauired for the tex endpoint - and how we include this for the Parsifal endpoint still is very ???????????????????????????
  $cssPath = "../../../load.php?lang=en&modules=ext.Parsifal%7Cskins.vector.styles.legacy&only=styles&skin=vector";
  //$cssPath = "load.php?lang=en&modules=skins.vector.styles.legacy&only=styles&skin=vector";

  $cssPath2 = "showEndpoint.css";

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




  
  
 // $jsPath = ";

  // build the final page which we will show
// TODO language should be picked up from danteEndpoint information
  $this->stringContent = "<!DOCTYPE html><html lang='en' dir='ltr'>"."<head>"."<meta charset='UTF-8'/><!-- Version 1-->".
    "<link rel='stylesheet' href='${cssPath}'><link rel='stylesheet' href='${cssPath2}'>".
    "<script async src='../../../load.php?lang=en&amp;modules=startup&amp;only=scripts&amp;raw=1&amp;skin=vector'></script>".
    "<script src='$wgExtensionAssetsPath/Parsifal/js/runtime.js'></script>".
    "</head>".  "<body style='transform:scale("  .$this->transformScale. ");transform-origin:top left;'>" . 
   // "<body style='transform:scale(1);'>".
    "<div id='bodyContent' class='vector-body'>"  .
       "<div id='mw-content-text' class='mx-body-content mw-content-ltr'>" .
    $parsedText .  
        "</div>" .
    "</div>" .
  $drawIOSizePatch.
  // $raw.     // only debug 
    "</body></html>";
  return 1;
}

} // class



$point = new ShowEndpoint ();
$point->execute();
