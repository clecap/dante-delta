<?php

class BreadCrumbsHooks {


public static function onOutputPageBeforeHTML( OutputPage &$out, &$text ) {
  $out->addModuleStyles ( ["ext.DanteBread.styles"] );  // must be marked position:top in extension.json
}

// NOTE: this injects directly into the header and (2) leads to an immediate synchronous loading
//       this is necessary since we inject window.doreadNow() a bit lower in order to force synchronouse, non-flickering display
public static function onOutputPageAfterGetHeadLinksArray ( $tags, OutputPage $out ) { 
  global $wgScriptPath;
  $out->addHeadItem("breadScript", "<script src='$wgScriptPath/extensions/DanteBread/breadCrumbs.js'></script>");
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

}
