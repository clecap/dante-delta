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



// css code required for the small swiper, see https://intizarahmad.github.io/swipe/
const SWIPER_CSS = "<style data-src='showEndpoint.php'>
.swipe             { overflow: hidden; visibility: hidden; position: relative;}
.swipe-wrap        { overflow: hidden;  position: relative;}
.swipe-wrap > div  { float: left; width: 100%;  position: relative; }
</style>";

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
   EndpointLog ("***** SwipeEndpoint constructed \n" );  
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

//  $this->parse ($input, false);
//  $sections = $this->parserOutput->getSections();
//  $num = count ( $sections);

  $num = 2;

  $ret = "<div id='mySwipe' class='swipe'><div class='swipe-wrap'>"; // open the swiper

  for ($secnum = 0; $secNum < $num; $secNum++) {
    $parsedText    = $this->parseText ( $input, false, $secNum );
   // $ret .= "<div>" . $parsedText . "</div>";
    $ret .= "<div><div id='bodyContent' class='vector-body'><div id='mw-content-text' class='mx-body-content mw-content-ltr'><span>This is number ${secNum}</span></div></div></div>";
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
  //return $this->processOne (1);
}


public function getCssPaths () {
  return [
    "../../../load.php?lang=en&modules=ext.Parsifal%7Cskins.vector.styles.legacy&only=styles&skin=vector",
    // my own bundle MUST be here as only this includes latex.ccss from PARSIFAL  // TODO: HOWEVER this only is reauired for the tex endpoint - and how we include this for the Parsifal endpoint still is very ???????????????????????????
    // "load.php?lang=en&modules=skins.vector.styles.legacy&only=styles&skin=vector"
    "showEndpoint.css"
  ];
}


public function getAsyncJsPaths() { return [ '../../../load.php?lang=en&amp;modules=startup&amp;only=scripts&amp;raw=1&amp;skin=vector']; }

public function getJsPaths () { global $wgExtensionAssetsPath;  return ["$wgExtensionAssetsPath/Parsifal/js/runtime.js",
"$wgExtensionAssetsPath/DantePresentations/js/swipe.min.js"]; }

public function getHeadText () : string { return "<style> body {transform:scale(".$this->transformScale."); transform-origin:top left;</style>"; }

public function decorateBody ( string $text ) : string {
  return  SWIPER_CSS .
     "<div id='bodyContent' class='vector-body'>"  .
       "<div id='mw-content-text' class='mx-body-content mw-content-ltr'>" .
       $text .  
        "</div>" .
    "</div>" . 
    DRAWIO_SIZE_PATCH .
    SWIPER_JS;
;
}


} // class



$point = new SwipeEndpoint (  );
$point->execute();  // display text obtained from process



