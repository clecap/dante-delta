<?php

use MediaWiki\MediaWikiServices;
require_once ("renderers/hideRenderer.php");
require_once ("helpers/Common.php");

class DantePresentations {

public static function onSkinTemplateNavigationUniversal ( SkinTemplate $sktemplate, array &$links ) {
  global $wgServer, $wgScriptPath;

  // add a new view/UI element to open a presentation view via a javascript function defined in ext.DantePresentations.js
  // do this only in 1) main namespace AND 2) if the page carries a __SLIDES__ magic word SLIDES

  $title       = $sktemplate->getTitle();                 
  $user        = $sktemplate->getUser();                 // defined in class ContextSource
   danteLog ("DantePresentations", "onSkinTemplateNavigationUniversal \n");

  if (true) {                                                // add only on pages in the Main: namespace  // TODO currently everywhere since we have Help pages with parsifal we want to see as slides
    if (!$sktemplate->canUseWikiPage ()) {return;}
    $parserOutput = $sktemplate->getWikiPage ()->getParserOutput();
    if (!$parserOutput) {return;}
    $action = $sktemplate->getContext()->getActionName();         // get the current action
    if ($parserOutput->getPageProperty ( 'MAG_SLIDES' ) !== null && strcmp ($action, "view") == 0 )  {  // add only when we are viewing the page
      danteLog ("DantePresentations", "injecting stuff \n");
      $links['views']['my_view'] = ['class' => '', 'href' => 'javascript:window.present("' .$wgScriptPath. '")', 'text' => 'Present'];   // siehe ext.DantePresentations.js
  }

  $query = DPCommon\makeQuery ($user, $title, true);

  $showEndpointUrl = $wgScriptPath. '/extensions/DantePresentations/endpoints/showEndpoint.php?' . $query;  // works
  $showExternalUrl = $wgServer . $wgScriptPath . "/extensions/DantePresentations/externalMonitor.html?presentation=" .urlencode ($showEndpointUrl);  // works

  $links['views']['my_view_zwo'] = ['class' => '', 'href' => $showExternalUrl, 'text' => 'Show', 'title' => "Opens a window for selecting content for presentations and tab chrome casting", 'target' => '_blank' ]; 

  $fullView =  $wgScriptPath. '/extensions/DantePresentations/endpoints/showEndpoint.php?' . $query;  // works
  
  $links['views']['audio'] = ['class' => '', 'href' => $fullView, 'text' => 'Full View', 'title' => "Opens a window for selecting content for presentations and tab chrome casting", 'target' => '_blank', 'data-fullview-query' => $query, 'data-fullview-endpoint' => $wgScriptPath. '/extensions/DantePresentations/endpoints/showEndpoint.php?'];

   $slideExternalUrl = $wgServer . $wgScriptPath . '/extensions/DantePresentations/endpoints/swipeEndpoint.php?' . $query;  // works
   $links['views']['slides'] = ['class' => '', 'href' => $slideExternalUrl, 'text' => 'Swipe View', 'title' => "Show page as slideshow with slider", 'target' => '_blank'];

  }  // siehe ext.DantePresentations.js
}

  public static function onParserFirstCallInit( Parser $parser ) {
    $parser->setHook( 'aside', [ self::class, 'renderTag' ] );        
    $parser->setHook( 'hide', [ "HideRenderer", 'renderProminent' ] );
  }


public static function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerlinks ) {
  global $wgDanteOperatingMode, $wgServer, $wgScriptPath;
  if ( strcmp ($key, 'places') == 0 ) {
    $footerlinks['imprint']       = Html::element( 'a', ['href' => $wgServer.$wgScriptPath."/index.php/"."Project:Imprint", 'rel' => 'noreferrer noopener' ], "Imprint");
    $footerlinks['parsifaldebug'] = Html::element( 'a',  ['href' => $wgServer.$wgScriptPath."/index.php/"."Special:ParsifalDebug", 'rel' => 'noreferrer noopener' ], "Mode: " . $wgDanteOperatingMode);

    $freeSpace = "Free Space: " . floor ( disk_free_space ("/var/www/html") / 1000000000 ) . " GB";
    $footerlinks['space'] = Html::element( 'a',  ['href' =>   $wgServer. $wgScriptPath . "/index.php/" .  "Special:ParsifalReset", 'rel' => 'noreferrer noopener'], $freeSpace);
  } // end if
} // end function


public static function renderTag ( $input, array $args, Parser $parser, PPFrame $frame ) {  return "<aside>".$input."</aside>" ;}


/*
  public static function renderHidden ( $input, array $args, Parser $parser, PPFrame $frame ) {
    $output = $parser->recursiveTagParse( $input, $frame );                                   // the tag works recursively, 
      // see https://stackoverflow.com/questions/7639863/mediawiki-tag-extension-chained-tags-do-not-get-processed 
      // see https://www.mediawiki.org/wiki/Manual%3aTag_extensions#How_do_I_render_wikitext_in_my_extension.3F
    $none   = "";
    $hidden = "<div class='seHidden' style='border:2px solid red; border-radius:10px; padding:20px;background-color:yellow;'>" . $output . "</div>";
   // $hint   = "<div style='color:red;background-color:yellow; border-radius:10px; border:2px solid red;'>&nbsp;</div>";
$hint = "";
    $script = "<script> var ele = document.currentScript; ele.previousSibling.style.display='block';</script>";

    $scriptTwo = "<script>if (RLCONF.wgUserGroups.includes('docent')) {document.currentScript.previousSibling.style.display='block';}";

    return $hint.$hidden;
  }
*/

  // when we edit a page, intercept the edit process via javascript and insert an edit preview (if appropriate)
  public static function onEditPageshowEditForminitial ( EditPage &$editPage, OutputPage $output) {
   $output->addHeadItem ("preview", "<script src='extensions/DantePresentations/preview.js'></script>");  // TODO: go to preview-min.js
   $editPage->editFormTextBottom     = "<script>window.editPreviewPatch();</script>";
 }


/** Inject style directly into the header for immediate loading
    We use this here for all cases where immediate reaction is necessary because we otherwise get a FOUC flash of unstyled content
    where the load.php resource loader just comes too late
    1) Intercept the display of the regular TOC
    2) aside markings
 */
public static function onOutputPageAfterGetHeadLinksArray ( $tags, OutputPage $out ) { 
  global $wgServer, $wgScriptPath;
  $out->addHeadItem("tocstyle", "<style data-src='DantePresentations-style.php'>
#toc {display:none;}
aside {
    color: red;
    position:relative; right:-50px; top:0px;
    float:right;
}
</style>");

}


// at this moment in the build process we have easy access to the current page name and we add the current page name into the crumbs for the next occasion
public static function onBeforePageDisplay( OutputPage $output, Skin $skin ) {
  $output->addModules( [ "ext.DantePresentations" ] );  // injects css and js
  
//  $output->setIndicators (["a" => "<span class='audioPresent'>Audio</span>"]);

}


// Get displaytitle page property text.
// $title the Title object for the page
// &$displaytitle to return the display title, if set
// return bool true if the page has a displaytitle page property that is different from the prefixed page name, false otherwise
  private static function getDisplayTitle( Title $title, &$displaytitle ) {
    $pagetitle = $title->getPrefixedText();
    $title     = $title->createFragmentTarget( '' );
    
    if ( $title instanceof Title && $title->canExist() ) {
      $values = PageProps::getInstance()->getProperties( $title, 'displaytitle' );
      $id = $title->getArticleID();
      if ( array_key_exists( $id, $values ) ) {
        $value = $values[$id];
        if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== '' && $value !== $pagetitle ) {
          $displaytitle = $value;
          return true;
        }
      }
    }
    return false;
  }
  
// defines __SLIDES__ as an additional Mediawiki magic word
// NOTE: reference is https://www.mediawiki.org/wiki/Manual:Magic_words
public static function onGetDoubleUnderscoreIDs( &$ids ) { 
  array_push ( $ids, 'MAG_SLIDES');      // a slide page
  array_push ( $ids, 'MAG_HIDEHEAD');
  array_push ( $ids, 'MAG_HIDEHL');
  return true;
   }


  public static function onSkinEditSectionLinks( $skin, $title, $section, $tooltip, &$links, $lang ) {
    global $wgServer, $wgScriptPath;
    $user        = $skin->getUser();               
   $url =        $wgServer."/".$wgScriptPath . "/extensions/DantePresentations/endpoints/showEndpoint.php?" .

  $query = DPCommon\makeQuery ($user, $title, true);
  $query .=   "&" . "sect="                    .urlencode ($section);

// https://localhost:4443/wiki-dir/extensions/DantePresentations/endpoints/showEndpoint.php?

/*
    $links['presentPart'] = [
      'targetTitle' => $title,
      'text' => "present",
      'attribs' => ["href" => "javascript:alert(1);",   // href does not work here
       "class" => "section-show-link internal", "data-section" => $section, "data-href" => $url, "title" => "Sect ".$section ],
      'query' => array( ), 'options' => array() ];
*/

// LINK is activated via jQuery in ex.DantePresentations.js focusing on the class name of the link

/*
  $links['positionPart'] = [
      'targetTitle' => $title,
      'text' => "position",
      'attribs' => [  
       "class" => "section-present-link internal", "data-section" => $section, "data-section-marker" => $section, "title" => "Sect ".$section ],
      'query' => array( ), 'options' => array() ];

  // annotation link
  // activated in ex.DantePresentations.js
  $links['annotationPart'] = [
        'targetTitle' => $title,
        'text' => "anno",
        'attribs' => [  
         "class" => "section-annotation-link internal", "data-section" => $section, "data-section-marker" => $section, "title" => "Sect ".$section ],
        'query' => array( ), 'options' => array() ];
*/

  }  // end onSkinEditSectionLinks


// implement __HIDEHEAD__  and __HIDEHL__ magic word
public static function onParserAfterParse( Parser $parser, &$text, StripState $stripState ) {
  if ( $parser->getOutput()->getPageProperty( 'MAG_HIDEHEAD' ) !== null ) {
    $parser->getOutput()->addHeadItem (
      "<script>document.documentElement.classList.add('mag-hide-head');</script>" .
      "<style>html.mag-hide-head h2 .mw-headline, html.mag-hide-head h3 .mw-headline, html.mag-hide-head h4 .mw-headline, html.mag-hide-head h5 .mw-headline, html.mag-hide-head h6 .mw-headline {display:none;}</style>"
      , "hidehead");
  }
  if ( $parser->getOutput()->getPageProperty( 'MAG_HIDEHL' ) !== null ) {
    $parser->getOutput()->addHeadItem (
      "<script>document.documentElement.classList.add('mag-hide-hl');</script>" .
      "<style>html.mag-hide-hl h2 .mw-headline, html.mag-hide-hl h3 .mw-headline, html.mag-hide-hl h4 .mw-headline, html.mag-hide-hl h5 .mw-headline, html.mag-hide-hl h6 .mw-headline {display:none;}".
      "html.mag-hide-hl h2, html.mag-hide-hl h3, html.mag-hide-hl h4, html.mag-hide-hl h5, html.mag-hide-hl h6 {border-bottom:0px;}   </style>"
      , "hidehead");
  }
 

}


}










