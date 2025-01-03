<?php

/** This endpoint provides live previews for Mediawiki edits */

// NOTE: Debugging this: Apache log has error messages if we get no result by direct call to endpoint


error_reporting(E_ALL); ini_set('display_errors', 'On'); // uncomment to obtain reasonable errors from the endpoint instead of only 500 er status from webserver

require_once ("danteEndpoint.php");

class MediawikiEndpoint extends DanteEndpoint {

public function getCssPaths () { return array('load.php?lang=en&modules=ext.Parsifal%2Cpygments%7Cskins.vector.styles.legacy&only=styles&skin=vector', 'extensions/DantePresentations/endpoints/mediawikiEndpoint.css'); }

// need the Parsifal runtime in the preview endpoint
public function getJsPaths () { return array('extensions/Parsifal/js/runtime.js'); }

// we need some classes on the body to better mimick the original styles of the skin; these here are hand-collected and experimental
public function getBodyClasses () { return array('mw-body', 'mw-body-content',  'vector-body',  'mw-parser-output'); }
public function getHTMLClasses () {return array (); }


} // class



$point = new MediawikiEndpoint ();
$point->execute();
