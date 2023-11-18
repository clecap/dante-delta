<?php

/** This endpoint provides live previews for Mediawiki edits */


/**
 *   NOTE: Debugging this: Apache log has error messages if we get no result by direct call to endpoint
 */

// need the following two lines to obtain reasonable errors from the endpoint instead of only 500 er status from webserver
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once ("danteEndpoint.php");

class MediawikiEndpoint extends DanteEndpoint {

// MediawikiEndpoint gets its contents from the post body
public function getInput () {
  //EndpointLog ("MediawikiEndpoint: getInput entered\n");
  $body = file_get_contents("php://input");         // get the input; here: the raw body from the request
  $text = base64_decode ($body);                    // in an earlier version we used, unsuccessfully, some conversion, as in:   $body = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $body); 
  // EndpointLog ("MediawikiEndpoint: getInput sees text: ".$text . "\n");
  return $text;
}


public function getContent ( ) {
  // EndpointLog ("MediawikiEndpoint: getContent entered\n");
  // $this->stringContent = "= HIHI = \n  '''qwe''' [[Main]]"; return 1;   // NOTE: For debugging / testing: Uncomment this for testing purposes, then call the endpoint directly in browser

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

} // class


$point = new MediawikiEndpoint ();
$point->execute();



















