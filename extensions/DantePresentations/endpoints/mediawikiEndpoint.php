<?php

/** This endpoint provides live previews for Mediawiki edits */

// NOTE: Debugging this: Apache log has error messages if we get no result by direct call to endpoint


error_reporting(E_ALL); ini_set('display_errors', 'On'); // uncomment to obtain reasonable errors from the endpoint instead of only 500 er status from webserver

require_once ("danteEndpoint.php");

class MediawikiEndpoint extends DanteEndpoint {

public function getContent ( ) {
  $text = $this->getInput();
  $parsedText = $this->parseText ( $text, false );

  if (strlen ($parsedText) == 0) {$parsedText = "INPUT TEXT WAS: " .$text. "RESULTANT TEXT IS EMPTY - Case 1";}   // in case we did not receive anything from the endpoint, we nevertheless have to display some stuff or we get an error

  // We need some styling from the original mediawiki styling so that the preview looks similar to the final result.
  // here we must include:
  //   1) The styles we are using normally
  //   2) The Parsifal styles
  //   3) Any other style which we might need from other extensions - which here is
  //      3.1 pygments for the syntax highlighting
  // We can derive the URL from a normal view of the wiki, looking for the load.php link there
  //
  // CAVE: Here we assume that Parsifal is installed
  //
  $cssPath="load.php?lang=en&modules=ext.Parsifal%2Cpygments%7Cskins.vector.styles.legacy&only=styles&skin=vector";

  // include mediawikiEndpoint.css for some of our own styling additions (here we can also correct for artifacts of the preview situation)
  $cssPath2 = "extensions/DantePresentations/endpoints/mediawikiEndpoint.css";  

  $script =  "<script src='extensions/Parsifal/js/runtime.js'></script>";   // need the Parsifal runtime in the preview endpoint

  // we need some classes on the body to better mimick the original styles of the skin; these here are hand-collected and experimental
  $bodyClasses = "mw-body mw-body-content vector-body mw-parser-output";

  $this->stringContent = "<!DOCTYPE html><html lang='en' dir='ltr'><head><meta charset='UTF-8'/><link rel='stylesheet' href='${cssPath}'><link rel='stylesheet' href='${cssPath2}'>".
    $script.
"</head><body class='${bodyClasses}'>" . $parsedText .  "</body></html>";


  EndpointLog ("MediawikiEndpoint: getContent sees: " . $this->stringContent);
  return 1;
}


public function getCssPaths () { return array('load.php?lang=en&modules=ext.Parsifal%2Cpygments%7Cskins.vector.styles.legacy&only=styles&skin=vector', 'extensions/DantePresentations/endpoints/mediawikiEndpoint.css'); }
public function getJsPaths () { return array('extensions/Parsifal/js/runtime.js'); }
public function getBodyClasses () { return array('mw-body', 'mw-body-content',  'vector-body',  'mw-parser-output'); }
public function getHTMLClasses () {return array (); }


} // class






$point = new MediawikiEndpoint ();
$point->execute();
