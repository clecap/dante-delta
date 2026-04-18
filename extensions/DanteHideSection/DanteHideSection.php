<?php

class DanteHideSection {

public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
  $out->addModules( 'ext.dantehideSection' );                                    // injects complete module, css and js


  // need this as inline to prevent FOUC
  // need the .mw-parser-output to prevent any other parts of the skin to be counted (this must be a feature present initially, not added later by js)
  // need the #mw-toc-heading rule to prevent the TOC to show a counter as well




  return true;
}

}