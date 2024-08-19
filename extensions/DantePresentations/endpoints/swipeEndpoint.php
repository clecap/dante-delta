<?php


/**
 * This endpoint provides swipeable content
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


// js code required for the small swiper, see https://intizarahmad.github.io/swipe/
const SWIPER_JS = "<script>
var element = document.getElementById('mySwipe');
window.mySwipe = new Swipe(element, {
  startSlide: 0,
  draggable: true,
  autoRestart: false,
  continuous: false,
  disableScroll: true,
  stopPropagation: true,
  callback: function(index, element) {},
  transitionEnd: function(index, element) {}
});
</script>";


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








class SwipeEndpoint extends DanteEndpoint {


function __construct () {
  parent::__construct(); 
  $this->caching = false;     // must turn off caching for this endpoint - at least as it is implemented in the base class - we will TODO need a separate caching here maybe ?!?!?!?
}

private $transformScale = 1;



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




public function processSlide () : string {
  $input         = $this->getInput();
  $this->parseText ($input, false);

  $sections = $this->parserOutput->getSections();                   // that is not the number of sections but the number of section entry points in below structure !!
  EndpointLog ("***** Sections: ".print_r ($sections, true)."\n" );  

  $num = count ( $sections) + 1;  // +1 since number 0 shows the portion before the first section

/*
The section number 0 pulls the text before the first heading; other numbers will pull the given section along with its lower-level subsections. 
If the section is not found, $mode=get will return $newtext, and $mode=replace will return $text.

Section 0 is always considered to exist, even if it only contains the empty string. If $text is the empty string and section 0 is replaced, $newText is returned.
*/

  $ret = "<div id='mySwipe' class='swipe'><div class='swipe-wrap'>"; // open the swiper

  for ($secNum = 1; $secNum < $num; $secNum++) {  // start at 1 to jump over the page prefix
    $parsedText    = $this->parseText ( $input, false, $secNum );
   // $ret .= "<div>" . $parsedText . "</div>";
   // $ret .= "<div><div id='bodyContent' class='vector-body'><div id='mw-content-text' class='mx-body-content mw-content-ltr'><span>This is number ${secNum}</span></div></div></div>"; // works

     $index  = $sections[$secNum]["index"];
     $number = $sections[$secNum]["number"];
     $line   = $sections[$secNum]["line"];

     $position = "<div class='swipe-positioner'>Index: ".$index." number ".$secNum." of " . $num . " line: ".$line."</div>";

  // below we have what in vector usually is id=bodyContent and id=mw-content-text , but here we need to use it as class since we have this element for EVERY slide portion of the page

  //   $ret .= "<div class='bodyContent vector-body'><div class='mw-content-text mx-body-content mw-content-ltr'>".$parsedText."</div></div>";

  $ret .= "<div class='bodyContent'>".$parsedText."</div>" . $position;

  }
  $ret .= "</div></div>";  // close the swiper
  $ret = $this->decorate ( $ret );

  return $ret;
}
 

// shows only the number seciont (without heading of the section)
public function processOne ( $secNum ) : string {
  $input         = $this->getInput();
  $parsedText    = $this->parseText ( $input, false, $secNum );
  $decoratedText = $this->decorate ($parsedText);
  return $decoratedText;
}


// This is the core function for obtaining the output of the (generic) endpoint
// here we use it to switch between different process variants
public function process () : string {
  return $this->processSlide ();
  //return $this->processOne (2);
}

public function getCssPaths () {
  return [
    "../../../load.php?lang=en&modules=ext.Parsifal%7Cskins.vector.styles.legacy&only=styles&skin=vector",
    // my own bundle MUST be here as only this includes latex.ccss from PARSIFAL  // TODO: HOWEVER this only is reauired for the tex endpoint - and how we include this for the Parsifal endpoint still is very ???????????????????????????
    // "load.php?lang=en&modules=skins.vector.styles.legacy&only=styles&skin=vector"
    "swipeEndpoint.css"
  ];
}

public function getAsyncJsPaths() { return [ '../../../load.php?lang=en&amp;modules=startup&amp;only=scripts&amp;raw=1&amp;skin=vector']; }

public function getJsPaths () { global $wgExtensionAssetsPath;  return ["$wgExtensionAssetsPath/Parsifal/js/runtime.js",
"$wgExtensionAssetsPath/DantePresentations/js/swipe.min.js"]; }

public function getHeadText () : string { return "<style> body {transform:scale(".$this->transformScale."); transform-origin:top left;</style>"; }

public function decorateBody ( string $text ) : string {
  return  $text .  DRAWIO_SIZE_PATCH . SWIPER_JS;
}


} // class



$point = new SwipeEndpoint (  );
$point->execute();  // display text obtained from process



