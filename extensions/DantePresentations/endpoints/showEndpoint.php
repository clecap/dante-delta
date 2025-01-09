<?php

/**
 * This endpoint provides existing content from the Mediawiki in a custom skinned form.
 */

error_reporting(E_ALL); ini_set('display_errors', 'On');

require_once ("danteEndpoint.php");

// js code for proper resizing drawio figures
const DRAWIO_SIZE_PATCH = "
<script>
const drawIOPatch = () => {
   console.info ('drawiopatch');
  let divs=document.querySelectorAll ('.drawio + div');
  console.info ('showEndpoint.php patches',divs);
  divs.forEach ( ele => {  
     // console.info ('showEndpoint.php', ele);
    let img = ele.querySelector ('img');
     // console.info ('persphone', img);
    img.classList.add('drawioShowendpoint');
   // img.style.maxWidth='4000px';
  });
};
drawIOPatch ();
</script>
";



class ShowEndpoint extends DanteEndpoint {

function __construct () {
  parent::__construct(); 
  wfLoadExtension ("DanteHideSection");
}

private $transformScale = 1;


// ShowEndpoint gets its input from header query information, which is picked up in base class DanteEndpoint and which is set in ????
// TODO: also allow stuff in query extension of URL !!
public function getInput () {
  $searchKey =  $this->nsName . ":".  $this->dbkey;
  $title      =  Title::newFromDBkey ( $searchKey );  // TODO: lacks optional interwiki prefix   -   see documentaiton of class Title
  if ($title === null) {
    throw new Exception ("ShowEndpoint: could not generate title from dbkey: (" . $this->dbkey . ")\n");
  }

  $wikipage    = new WikiPage ($title);                              // get the WikiPage for that title
  $contob      = $wikipage->getContent();                            // and obtain the content object for that
  $contenttext = ContentHandler::getContentText( $contob );
  return $contenttext;
}


public function getCssPaths () {

  return [
   // "../../../load.php?lang=en&modules=ext.Parsifal%7Cskins.vector.styles.legacy&only=styles&skin=vector",
    // my own bundle MUST be here as only this includes latex.ccss from PARSIFAL  // TODO: HOWEVER this only is reauired for the tex endpoint - and how we include this for the Parsifal endpoint still is very ???????????????????????????
    // "load.php?lang=en&modules=skins.vector.styles.legacy&only=styles&skin=vector"
    "showEndpoint.css"
  ];
}

public function getAsyncJsPaths() {
 // return [];
  return [ '../../../load.php?lang=en&amp;modules=startup%7Cmediawiki.util%7Cjquery%7Cext.dantehideSection&amp;only=scripts&amp;skin=vector']; 

  return [ '../../../load.php?lang=en&amp;modules=startup%7Cmediawiki.util%7Cjquery%7Cext.dantehideSection&amp;only=scripts&amp;raw=1&amp;skin=vector&amp;debug=2']; 
  return [''];
}    // load startup and jquery in minified form // TODO: remove debug !



public function getJsPaths () { global $wgExtensionAssetsPath;  
  return [
 //"../../../load.php?lang=en&amp;modules=startup&amp;only=scripts&amp;skin=vector",
//  "../../../load.php?lang=en&amp;modules=startup%7Cmediawiki.util%7Cjquery&amp;only=scripts&amp;skin=vector",    // TODO: remove raw=1
//"../../../load.php?lang=en&amp;modules=ext.dantehideSection&amp;only=scripts&amp;skin=vector",
// '../../../load.php?lang=en&amp;modules=startup%7Cmediawiki.util%7Cjquery%7Cext.dantehideSection&amp;only=scripts&amp;skin=vector',
  "$wgExtensionAssetsPath/DantePresentations/endpoints/showEndpoint.js", 
 // "$wgExtensionAssetsPath/Parsifal/js/runtime.js"
 ]; }  // TODO: go to min.js

public function getHeadText () : string { return "<style> body {transform:scale(".$this->transformScale."); transform-origin:top left;</style><script>console.time('start');</script>"; }

public function decorateBody ( string $text ) : string {
  global $wgExtensionAssetsPath;
  return
     "<div id='bodyContent' class='vector-body'>"  .
       "<div id='mw-content-text' class='mx-body-content mw-content-ltr'>" .
       $text .  
        "</div>" .
    "</div>" . 
    DRAWIO_SIZE_PATCH .
"<script>
//console.log ('final', Object.keys (mw));
//console.log ('final', Object.keys (mw.loader));

//var loaderResult = mw.loader.load ('" .$wgExtensionAssetsPath. "/Parsifal/js/runtime.js');
//console.log ('final 3', Object.keys (mw.loader));
//console.log ('loaderResult ', loaderResult)


</script>
"
;
}


} // class



$point = new ShowEndpoint (  );
$point->execute();  // display text obtained from process
