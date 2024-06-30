<?php

class BreadCrumbsHooks {

  // we only need a very short style snippet - and this we include into a static string variable
  public static $cssSpec = <<<EOD
    #breadcrumbinsert   {position:relative; top:-11px; min-height:17px; text-align:left;}
    #breadcrumbinsert   {background-color: #f6f6f6; border-color: #dcdcdc;border-radius: 3px;border-style: solid;border-width: 1px;padding-left:0.2em;}
  EOD;

//    #breadcrumbinsert   {position:relative; top:-11px; height:17px; max-height:17px; min-height:17px; text-align:left;overflow:hidden;}


  // NOTE: this injects directly into the header and leads to an immediate loading
  public static function onOutputPageAfterGetHeadLinksArray ( $tags, OutputPage $out ) { 
    global $wgScriptPath;
    $out->addHeadItem("breadStyle", "<script src='$wgScriptPath/extensions/DanteBread/breadCrumbs.js'></script>");
    $out->addHeadItem("bread", "<style data-src='DanteBread'>".BreadCrumbsHooks::$cssSpec."</style>");
  }


  // inject the element where we will show the breadcrum and inject a script tag for calling for its initialization
  public static function onSiteNoticeAfter ( &$siteNotice, $skin) {
    $siteNotice .= '<div id="breadcrumbinsert"></div><script>window.doBreadNow()</script>';  
    return false;
  }


  // add the current page name into the crumbs for the next occasion displaying themn
  public static function onBeforePageDisplay( $output, $skin ) {
    global $wgScriptPath;                     // provide the script with path information which, at this part of the process, is not yet available on the javascript side
    $title = $output->getTitle();
    $pageName = $title->getPrefixedText();    // title incl prefix without underscores
    $pageKey  = $title->getPrefixedDBkey();   // title incl prefix with underscores
    $output->addInlineScript ("if (window.addFreshCrumb) {window.addFreshCrumb('".$pageName."','".$pageKey."','".$wgScriptPath."');}");
  }


// TODO: what do we need this for ???

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
  
}
