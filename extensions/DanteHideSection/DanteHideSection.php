<?php

class DanteHideSection {

public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
  $out->addModules( 'ext.dantehideSection' );                                    // injects complete module, css and js
  return true;
}

}


