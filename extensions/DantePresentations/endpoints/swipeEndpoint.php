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
  callback: function(index, element) { console.log ('Going to:', index);  document.getElementById('selector').selectedIndex = index;  },
  transitionEnd: function(index, element) { }
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



const SCALER = "
<script>
document.

</script>
";




class SwipeEndpoint extends DanteEndpoint {



function __construct () {
  parent::__construct(); 
  $this->caching = false;     // must turn off caching for this endpoint - at least as it is implemented in the base class - we will TODO need a separate caching here maybe ?!?!?!?
}

private $transformScale = 1;
private $posSelect ="";        // for the selector 

public function getInput () { return self::getInputTitle(); }


public function processSlide () : string {
  global $wgServer, $wgScriptPath;

  $input         = $this->getInput();
  $this->parseText ($input, false);
  $sections      = $this->parserOutput->getSections();                   // that is not the number of sections but the number of section entry points in below structure !!
  self::Log ("***** Sections: ".print_r ($sections, true)."\n" );  

  $arrNumMax = count ( $sections ); 

/* 
The section number 0 pulls the text before the first heading; other numbers will pull the given section along with its lower-level subsections. 
If the section is not found, $mode=get will return $newtext, and $mode=replace will return $text.

Section 0 is always considered to exist, even if it only contains the empty string. If $text is the empty string and section 0 is replaced, $newText is returned.
*/

  $ret = "<div id='mySwipe' class='swipe'><div id='swipe-wrap' class='swipe-wrap'>"; // open the swiper

  $linear = 0;  // linear counter of all slide elements

  // generate the slide selector
  $this->posSelect   = "<select name='ba' class='selectPage' id='selector'>";
  for ($arrNum = 0; $arrNum < $arrNumMax; $arrNum++) { 
    $txt = $sections[$arrNum]['line'];
    $arrNumPlus = $arrNum+1;
    $this->posSelect .= "<option value='$arrNumPlus' class='optionClass'>$arrNumPlus: &nbsp;&nbsp; $txt</option>";}
  $this->posSelect .= "</select>";

  // section index 0 is skipped as it is the portion before the first true section
  for ($arrNum = 0; $arrNum < $arrNumMax; $arrNum++) {                       // iterate all elements of the sections array generated by the parser
    $secNum = $sections[$arrNum]["index"];                                   // the "number" of a section in that array will be the the number we extract from the parser
    // however we must check if that node might be an intermediate node and not a leaf node

    if ( $arrNum < $arrNumMax-1 ) {  // if we are not looking at the last element of the sections array
      self::Log ("Looking at section array element with index $arrNum out of $arrNumMax many elements \n");
      self::Log ("Current level is ".$sections[$arrNum]['level']." and next level is ".$sections[$arrNum+1]['level']."\n");
      if ($sections[$arrNum+1]["level"] > $sections[$arrNum]["level"]) {
        self::Log ("Skipping ". $sections[$arrNum]['line']."\n");
        continue;} // then we can check if the next level is higher, which means that this level is not to be shown
    }
    $linear++;
    self::Log ("Using  slide with line ". $sections[$arrNum]['line']." as slide sequence $linear, picking for parser secNum=$secNum\n");

    $parsedText  = $this->parseText ( $input, false, $secNum );

    // build the element displaying where we are
    $posText     = "<div class='swipe-positioner'><span class='swipe-num' title='Current number'>".$linear."</span> of <span class='total-num-slides' title='Total number'></span></div>";

    // build a zoom UI
    $zoomUI = "<div class='swipe-zoom'><button class='zoomMinus'>-</button><button class='zoomShow'></button><button class='zoomPlus'>+</button></div>";

    // build a navigation element
    $mainLink = "<a class='swipe-link' href='".$wgServer.$wgScriptPath."/index.php?title=".$this->dbkey."' title='Go back to main article'>".$this->title."</a>";
    $nextLink = "<button onclick='window.mySwipe.next();'>&#8594;</button>";
    $prevLink = "<button  onclick='window.mySwipe.prev();'>&#8592; </button>";
    $navigator   = "<div class='swipe-navigator'>".$prevLink.$mainLink.$nextLink."</div>";

//    $myLine      = "<div class='bodyContent'>".$posText.$navigator.$parsedText."</div>";    // works


    $myLine      = "<div class='bodyContent'><div class='swipe-line'>".$posSelect.$posText. $zoomUI.$navigator."</div><div class='swipe-stuff'><div class='content'><div class='innerContent'>".$parsedText."</div></div></div></div>";    

    $ret .= $myLine;
  }

  $ret .= "</div></div>";  // close the swiper

  $scrip = "<script> document.querySelectorAll ('.total-num-slides').forEach( x=> x.innerHTML='".$linear."');   </script>";
  $ret .= $scrip;
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
  $ret = "";
  try { $ret .= $this->processSlide ();
    // $ret = $this->processOne (2);
  }
  catch (Throwable $t) { $ret .= $t->__toString(); }
  return $ret;
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
public function getJsPaths () { global $wgExtensionAssetsPath;  return ["$wgExtensionAssetsPath/Parsifal/js/runtime.js", "$wgExtensionAssetsPath/DantePresentations/js/swipe.min.js",
  "$wgExtensionAssetsPath/DantePresentations/endpoints/swipeEndpoint.js"
]; }
public function getHeadText () : string { return "<style> body {transform:scale(".$this->transformScale."); transform-origin:top left;</style>"; }

public function decorateBody ( string $text ) : string {
  return  $text .  DRAWIO_SIZE_PATCH . SWIPER_JS . $this->posSelect . "<script>window.INIT();</script>" ;
}

} // class



$point = new SwipeEndpoint (  );
$point->execute();  // display text obtained from process



