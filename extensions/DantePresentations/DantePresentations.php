<?php

use MediaWiki\MediaWikiServices;
require_once ("renderers/hideRenderer.php");


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

  }

// TODO: THE BELOW STUFF MUST BE IMPROVED AND REFACTORED

  $namespaceIndex     = $title->getNamespace();                 // get number of namespace
  $dbkey              = $title->getDBKey();                     // convert title to dbkey format

  $query =   "Wiki-wgNamespaceNumber="  .urlencode ($namespaceIndex)  . "&" ."Wiki-dbkey="              .urlencode ($dbkey)           . "&" . "Wiki-hiding=true";

  $query =   "wiki-wgtitle=" . $title->getPrefixedDBKey();


  $showEndpointUrl = $wgScriptPath. '/extensions/DantePresentations/endpoints/showEndpoint.php?' . $query;  // works
  $showExternalUrl = $wgServer . $wgScriptPath . "/extensions/DantePresentations/externalMonitor.html?presentation=" .urlencode ($showEndpointUrl);  // works

  $links['views']['my_view_zwo'] = ['class' => '', 'href' => $showExternalUrl, 'text' => 'Show', 'title' => "Opens a window for selecting content for presentations and tab chrome casting", 'target' => '_blank' ]; 

  $fullView =  $wgScriptPath. '/extensions/DantePresentations/endpoints/showEndpoint.php?' . $query;  // works
  
  $links['views']['audio'] = ['class' => '', 'href' => $fullView, 'text' => 'Full View', 'title' => "Opens a window for selecting content for presentations and tab chrome casting", 'target' => '_blank', 
    'data-fullview-endpoint' => $wgScriptPath. '/extensions/DantePresentations/endpoints/showEndpoint.php'];

   $slideExternalUrl = $wgServer . $wgScriptPath . '/extensions/DantePresentations/endpoints/swipeEndpoint.php?' . $query;  // works
   $links['views']['slides'] = ['class' => '', 'href' => $slideExternalUrl, 'text' => 'Swipe', 'title' => "Show page as slideshow with slider", 'target' => '_blank'];


  $activeCollection = "";
  if ($namespaceIndex == NS_COLLECTION) {
    if (isset($_COOKIE['active_collection'])) {
      $activeCollection = $_COOKIE['active_collection'];
      $activeCollection = urldecode($activeCollection);
      $links['views']['collection'] = ['class' => '', 'href' => 'javascript:window.CLEAR_ACTIVE_COLLECTION()', 'title' => 'Deactivate the active collection '.$activeCollection, 'text' => "Deactivate"];
    } else {
       $links['views']['collection'] = ['class' => '', 'href' => 'javascript:window.SET_ACTIVE_COLLECTION()', 'title' => 'Activate collection '.$activeCollection, 'text' => "Activate"];
    }
  }

 //   $links['views'][''] = ['class' => '', 'href' => $slideExternalUrl, 'text' => 'Swipe', 'title' => "Show page as slideshow with slider", 'target' => '_blank'];

}



/**

The following code makes mediawiki accept more html and custom tags.
This must be done carefully, since it can potentially open up security issues.

  <div onclick="alert('XSS')">Click me</div>           on*attributes may contain unwanted JS which could steal cookies
  <script>alert('XSS');</script>                       script tags may contain unwanted JS which could steal cookies
  <iframe src="http://evil.example.com"></iframe>      iframes may contain phishing attacks by embedding phishing interactions into the wiki
  <img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">    Base64 decodes to <script>alert(1)</script>
  <div style="background-image: url(javascript:alert(1))">Bad CSS</div>

Thus we basically must think of
  Strip dangerous tags (e.g. <script>, <iframe>, <style>)
  Strip dangerous attributes (e.g. on*, style)
  Escape values and tags

 */


  public static function onParserFirstCallInit( Parser $parser ) {

    $RECURSIVE_ESCAPE_TAGS = [ 'section', 'article', 'figure', 'aside' ];
    foreach ( $RECURSIVE_ESCAPE_TAGS as $tag ) {
      $parser->setHook( $tag, function ( $input, $args, $parser, $frame ) use ( $tag ) {
        $attrs = '';
        // foreach ( $args as $key => $value ) {$attrs .= ' ' . htmlspecialchars( $key ) . '="' . htmlspecialchars( $value ) . '"';}  // disallow all attributes
        $content = $parser->recursiveTagParse( $input, $frame );  // recursively parse the contents of the tag
        $content = htmlspecialchars ($content);                   // escape the contents
        return "<$tag$attrs>" . $content . "</$tag>";
      });
    }

/*
    $RECURSIVE_TAGS = [ ];                     // TAGS which are recursively parsed
    foreach ( $RECURSIVE_TAGS as $tag ) {
      $parser->setHook( $tag, function ( $input, $args, $parser, $frame ) use ( $tag ) {
        $attrs = '';
        foreach ( $args as $key => $value ) {$attrs .= ' ' . htmlspecialchars( $key ) . '="' . htmlspecialchars( $value ) . '"';}
        $content = $parser->recursiveTagParse( $input, $frame );  // recursively parse the contents of the tag
        // $content = htmlspecialchars ($content);                // escape the contents
        return "<$tag$attrs>" . $content . "</$tag>";
      });
    }
*/

    $ESCAPE_TAGS = [ "h1", "h2", "h3", "h4", "h5", "h6" ];
    foreach ( $ESCAPE_TAGS as $tag ) {
      $parser->setHook( $tag, function ( $input, $args, $parser, $frame ) use ( $tag ) {
        $attrs = '';
//        foreach ( $args as $key => $value ) {$attrs .= ' ' . htmlspecialchars( $key ) . '="' . htmlspecialchars( $value ) . '"';} // disallow all attributes
        // $content = $parser->recursiveTagParse( $input, $frame );  // recursively parse the contents of the tag
        $content = htmlspecialchars ($content);                   // escape the contents
        return "<$tag$attrs>" . $content . "</$tag>";
      });
    }

/*
    $TAGS = [ ];                     // TAGS which are reproduced as they appear
    foreach ( $TAGS as $tag ) {
      $parser->setHook( $tag, function ( $input, $args, $parser, $frame ) use ( $tag ) {
        $attrs = '';
        foreach ( $args as $key => $value ) {$attrs .= ' ' . htmlspecialchars( $key ) . '="' . htmlspecialchars( $value ) . '"';}
        // $content = $parser->recursiveTagParse( $input, $frame );  // recursively parse the contents of the tag
        // $content = htmlspecialchars ($content);
        return "<$tag$attrs>" . $content . "</$tag>";
      });
    }
*/

    // these tags require a particular renderers
    $parser->setHook ( 'hide',  [ "HideRenderer",   'renderProminent' ]  );
    $parser->setHook ( 'audio', [ "AudioRenderer",  'renderTag']         );      
    $parser->setHook ( 'video', [ "VideoRenderer",  'renderTag']         );      

    $parser->setHook ( 'pasted-html', [ self::class,  'pastedHtml']         );      



  // $parser->setHook ( 'todo',    [ self::class,  'todo']               );      

    
  } // end function onParserFirstCallInit


public static function pastedHtml ( $input, array $args, Parser $parser, PPFrame $frame ) {
        // foreach ( $args as $key => $value ) {$attrs .= ' ' . htmlspecialchars( $key ) . '="' . htmlspecialchars( $value ) . '"';}  // disallow all attributes


  $content = $parser->recursiveTagParse( $input, $frame );  // recursively parse the contents of the tag
        
  return "<blockquote class='pastedHtml'>" . $content . "</blockquote>";
}



// NOTE: This is only called for normal pages and not for the edit page 
// CAVE: Read in dante-wiki/doc:  DEVELOPMENT-FOUC-MEDIAWIKI.md  to get all settings right

public static function onOutputPageBeforeHTML( OutputPage &$out, &$text ) {
  $out->addModuleStyles ( ["ext.DantePresentations.styles"] );                               
}



/*
public static function todo ( $input, array $args, Parser $parser, PPFrame $frame ) {


  $parsed = $parser->recursiveTagParse( $input, $frame );
  // Since this can span different parses, we need to take account of the fact recursiveTagParse only half parses the text. or strip tags
  // (UNIQ's) will be exposed. (Alternative would be to just call $parser->replaceLinkHolders() and $parser->mStripState->unstripBoth() right here right now.
  $serialized = serialize( $parser->serializeHalfParsedText( $parsed ) );
  $parser->getOutput()->setPageProperty( 'tdoProperty', $serialized );

  $currentValue ="";
  $prop = unserialize( $parser->getOutput()->getPageProperty( 'tdoProperty' ) );
  if ( !$parser->isValidHalfParsedText( $prop ) ) { $currentValue = '<span class="error">Error retrieving prop</span>';} 
  else { $currentValue = $parser->unserializeHalfParsedText( $prop );}

  return "";

;}

*/



public static function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerlinks ) {
  global $wgDanteOperatingMode, $wgServer, $wgScriptPath;
  if ( strcmp ($key, 'places') == 0 ) {
    $footerlinks['imprint']       = Html::element( 'a', ['href' => $wgServer.$wgScriptPath."/index.php/"."Project:Imprint", 'rel' => 'noreferrer noopener' ], "Imprint");
    $footerlinks['parsifaldebug'] = Html::element( 'a',  ['href' => $wgServer.$wgScriptPath."/index.php/"."Special:ParsifalDebug", 'rel' => 'noreferrer noopener' ], "Mode: " . $wgDanteOperatingMode);

    $freeSpace = "Free Space: " . floor ( disk_free_space ("/var/www/html") / 1000000000 ) . " GB";
    $footerlinks['space'] = Html::element( 'a',  ['href' =>   $wgServer. $wgScriptPath . "/index.php/" .  "Special:ParsifalReset", 'rel' => 'noreferrer noopener'], $freeSpace);
  } // end if
} // end function





// when we edit a page, intercept the edit process via javascript and insert an edit preview (if appropriate)
public static function onEditPageshowEditForminitial ( EditPage &$editPage, OutputPage $output) {
  $output->addHeadItem ("preview", "<script src='extensions/DantePresentations/preview.js'></script>");  // TODO: go to preview-min.js

  $codeMirror  =  "<script src='extensions/Parsifal/vendor/codemirror/codemirror-5.65.3/lib/codemirror.js'></script>";
  $codeMirror .=  "<link rel='stylesheet' href='extensions/Parsifal/vendor/codemirror/codemirror-5.65.3/lib/codemirror.css'></script>";
  $codeMirror .=  "<link rel='stylesheet' href='extensions/Parsifal/codemirror/codemirror-parsifal.css'></script>";      
  $codeMirror .=  "<script src='extensions/Parsifal/vendor/codemirror/codemirror-5.65.3/mode/stex/stex.js'></script>";
  $codeMirror .=  "<script src='extensions/Parsifal/vendor/codemirror/codemirror-5.65.3/addon/edit/matchbrackets.js'></script>";   

  $codeMirror .=  "<script src='extensions/Parsifal/codemirror/search.js'></script>";   
  $codeMirror .=  "<script src='extensions/Parsifal/vendor/codemirror/codemirror-5.65.3/addon/search/searchcursor.js'></script>"; 
  $codeMirror .=  "<script src='extensions/Parsifal/vendor/codemirror/codemirror-5.65.3/addon/dialog/dialog.js'></script>"; 
  $codeMirror .=  "<link rel='stylesheet' href='extensions/Parsifal/vendor/codemirror/codemirror-5.65.3/addon/dialog/dialog.css'></script>";  

   $editPage->editFormTextBottom  = $codeMirror . "<script data-src='DantePresentations.php'>DPRES.editPreviewPatch();</script>";

 }


// at this moment in the build process we have easy access to the current page name and we add the current page name into the crumbs for the next occasion
public static function onBeforePageDisplay( OutputPage $output, Skin $skin ) {
  $output->addHeadItem( 'dante-meta-generator', '<meta name="generator" content="DanteWiki">' );  // add a meta tag to identify pages from a DanteWiki
  $output->addModules( [ "ext.DantePresentations" ] );  // injects css and js

  // for testing the look and feel
  // $output->setIndicators ( [ "<span class='info'>Info</span>", "<span class='warning'>Warning</span>",  "<span class='error'>Error</span>"] );

  $title              = $output->getTitle();
  $text               = $title->getFullText();
  $namespaceIndex     = $title->getNamespace();                 // get number of namespace

  // collect all indicators for this page and set them
  $indicators = [];
  if ($namespaceIndex == NS_COLLECTION) {
    if (isset($_COOKIE['active_collection'])) {
      $activeCollection = $_COOKIE['active_collection'];
      $activeCollection = urldecode($activeCollection);
      $activeCollection = str_replace('_', ' ', $activeCollection);
     if ($text == $activeCollection) { array_push ($indicators,  "<span class='info'>This <b>$activeCollection</b> active</span>" ); }
     else                            { array_push ($indicators,  "<span class='warning'>Different <b>$activeCollection</b> active</span>" );}
    }
  }

// for testing // TODO: and as marker for stuff till to do
/*
  array_push ($indicators,  "<span class='info'>Info</span>");
  array_push ($indicators,  "<span class='warning'>Warning</span>");
  array_push ($indicators,  "<span class='error'>Error</span>");

  array_push ($indicators, "<span class='INFO'>Audio</span>");  // TODO: might also want to add click handler for this
  array_push ($indicators, "<span class='info'>Video</span>");  // TODO: might also want to add click handler for this
  array_push ($indicators, "<span class='WARNING'>Todos</span>");  // TODO: might also want to add click handler for this
  array_push ($indicators, "<span class='ERROR'>Feedback</span>");  // TODO: might also want to add click handler for this
*/

  $output->setIndicators ($indicators);

/*
 $context = RequestContext::getMain()->getRequest();
    $action = $context->getVal('action');
    
    // Check if the action is 'view'
    if ($action === 'view' || empty($action)) {
        // Action is 'view' (or it's the default action when no action is specified)
        $out->addHTML('<p>You are viewing the page!</p>');
    } else {
        // Not a 'view' action
        $out->addHTML('<p>This is not a view page.</p>');
    }
*/
// TODO: do we want this in an edit action as well? maybe yes, since we might be interested in a preview on the status of the existing page !!!
// TODo whatabout in move ?? in history ??
   

  if ( $namespaceIndex >= 0 ) {  // excludes Media und Special

    $currentText = $output->getHTML ();
    if (strpos($text, '<div id="toc"') == false) {  // we currently would not get a TOC by the system itself but we do not want the order of the side Chicks to change so we add an empty one ourselves
      $output->addHTML ('
        <div id="toc" class="toc sideChick" role="navigation" aria-labelledby="mw-toc-heading" title="Table of contents of this page" style="width: 0px; height: 0px; display: none;">
          <div id="toc-title" class="sideTitle" lang="en" dir="ltr"><h2>Contents</h2></div><ul><li><b>No table of contents provided</b></li></ul></div>');
    }
    else {   // we have a native, wiki-generated toc: add  a marker class to document element to get the proper markup as in toc.css
      $out->addHeadItem('toc-adjust', '<script data-src="DantePresentations.php">document.documentElement.classList.add ("has_tocs");</script>');
    }

  // below we have class toc in element 'bck' in order to benefit from the vector skin styling

      $output->addHTML ( self::generateSideChick ("cat", "Categories") );
      $output->addHTML ( self::generateSideChick ("sub", "Subpages") );
      $output->addHTML ( self::generateSideChick ("col", "Collections") );
      $output->addHTML ( self::generateSideChick ("act", "Active Collection") );
      $output->addHTML ( self::generateSideChick ("bck", "Backlinks") );
      $output->addHTML ( self::generateSideChick ("fwd", "Forward Links") );
      $output->addHTML ( self::generateSideChick ("sib", "Siblings") );
      $output->addHTML ( self::generateSideChick ("tdo", "Todo") );


  }  // if namespace

} // end onBeforePageDisplay


// TODO: move away from class toc
private static function generateSideChick (string $name, string $longName) {
  $label=strtoupper ($name);
  return "
<div id='$name' class='sideChick' style='display: none;' title='$longName'>
  <div id='$name-title' class='sideTitle' lang='en' dir='ltr' title='$longName (toggle)'>
    <input type='checkbox' title='$longName (pin)' id='$name-pin' class='pinUi'></inpu>
    <h2>$longName</h2>
  </div>
  <ul id='$name-inject'></ul>
  <span title='$longName (toggle)' id='$name-handle' class='sideHandle'>$label</span>
</div>";
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
  

// TODO: USED ??? HOW ???
// defines __SLIDES__ as an additional Mediawiki magic word
// NOTE: reference is https://www.mediawiki.org/wiki/Manual:Magic_words
public static function onGetDoubleUnderscoreIDs( &$ids ) { 
  array_push ( $ids, 'MAG_SLIDES');      // a slide page
  array_push ( $ids, 'MAG_HIDEHEAD');
  array_push ( $ids, 'MAG_HIDEHL');
  return true;
   }


// implement __HIDEHEAD__  and __HIDEHL__ magic word
public static function onParserAfterParse( Parser $parser, &$text, StripState $stripState ) {
  if ( $parser->getOutput()->getPageProperty( 'MAG_HIDEHEAD' ) !== null ) { $parser->getOutput()->addHeadItem ( "mag-hidehead", "<script data-src='DantePresentations:mag-hidehead'>document.documentElement.classList.add('mag-hide-head');</script>"); }
  if ( $parser->getOutput()->getPageProperty( 'MAG_HIDEHL' ) !== null )   { $parser->getOutput()->addHeadItem ( "mag-hidehl",   "<script data-src='DantePresentations:mag-hidehl'>document.documentElement.classList.add('mag-hide-hl');</script>"); }
}


public static function onRegistration () { 
  global $wgFileExtensions;    if ( !in_array( 'txt', $wgFileExtensions ) ) {$wgFileExtensions[] = 'txt';}     // allow uploads of relevant file txpes
 }


} // end CLASS