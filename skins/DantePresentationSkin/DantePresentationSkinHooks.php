<?php


use MediaWiki\MediaWikiServices;

// logging function for development
function logger2 ($text) {
  $fileName = dirname(__FILE__) ."/LOGFILE";
  if($tmpFile = fopen( $fileName , 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  
  else {throw new Exception ("local log could not log to $fileName"); }
}

class DantePresentationSkinHooks {

// NOTE: do not use addModules as for the Reveal boot sequence this comes too late
public static function onBeforePageDisplay ( OutputPage $out, Skin $skin ) {
  //global $wgDefaultUserOptions; 

    logger2 ("DantePresentationSkinHooks: found skin name: " . $skin->getSkinName() . "\n");

  if (strcmp ( $skin->getSkinName (), "dantepresentationskin" ) == 0) {  // inject this module only in case we are using the dantepresentationskin skin

    logger2 ("DantePresentationSkinHooks: injecting stuff \n");
    $out->addStyle("DantePresentationSkin/vendor/reveal/dist/reset.css");
    $out->addStyle("DantePresentationSkin/vendor/reveal/dist/reveal.css");
    $out->addStyle("DantePresentationSkin/resources/danteReveal.css");    

    // determine the specific preference of the user regarding the theme
    $user = RequestContext::getMain()->getUser();
    $theme = MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $user, 'presentation-theme' );

    $out->addStyle("DantePresentationSkin/vendor/reveal/dist/theme/$theme.css");  // dependent on user preferences
  }
}



// NOTE: this injects directly into the header and leads to an immediate loading
public static function onOutputPageAfterGetHeadLinksArray ( $tags, OutputPage $out ) { 
  global $wgScriptPath;
 // $out->addHeadItem("immediate", "<script src='$wgScriptPath/skins/DantePresentationSkin/resources/immediate.js'></script>");
 // TODO: currently not used.  ok ?

}


public static function onUserSaveSettings( User $user ) { 
  $theme = MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $user, 'presentation-theme' );

}

/** onGetPreferences: Build a UI for asking the user about theme preferences for the presentation skin
 * 
 * 
 */
public static function onGetPreferences ( $user, &$preferences ) { 
  $preferences['presentation-theme'] = [
    'type' => 'radio',
    'section' => 'dante/presentation',
    'options' => [
      'Black'        => 'black',
      'White'        => 'white',
      'League'      => 'league',
      'Beige'      => 'beige',
      'Sky'      => 'sky',
    'Night'      => 'night',
    'Serif'      => 'serif',
    'Simple'      => 'simple',
    'Solarized'      => 'solarized',
    'Blood'      => 'blood',
    'Moon'      => 'moon',
    ],  // we should NOT have a default value here or else we cannot properly store this (at least it looks like)
    'help-message' => 'prefs', // a system message (optional)
  ];
}

}