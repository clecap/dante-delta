<?php

// This file contains the service endpoint helper class
// 1. Goal of this file is to keep the service endpoints itself clean from helper classes
// 2. Goal if this file is to make the helper class available to files which need access without having
//         the structure of an endpoint.

use MediaWiki\Session\SessionManager;

class ServiceEndpointHelper {


  // attach a command(list) and an environment to the current session
public static function attachToSession ($cmd, $env) {
  $session = SessionManager::getGlobalSession();
  $session->set( 'Dante_Cmd', $cmd );             
  $session->set( 'Dante_Env', $env );
}




public static function getHeadHTML () {
  global $wgExtensionAssetsPath;   // works with and without short URLs, unlike ./extensions
return <<<END
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="{$wgExtensionAssetsPath}/DanteCommon/serviceEndpoint.css" />
    <title>Server side Script</title>
  </head>
  <body class=''>
END;
}


/**
 * Returns a JS snippet for inclusion after the iframe
 * The JS enlarges the iframe as it grows so that it never shows a scrollbar but the scrollbar shows up 
 * in its parent. Scrolls down to the bottom.
 *
 */
private static function getResizeJS () {
return <<<END
<script>
{ // block scope, so the constants do not clash if this snippet appears twice on a page
  const frame = document.getElementById("myFrame");

  frame.addEventListener("load", () => {
    const doc = frame.contentDocument || frame.contentWindow.document;
    if (!doc) return;
    const body = doc.body;
    const getDocHeight = () => {
      const html = doc.documentElement;
      return Math.max ( body.scrollHeight, body.offsetHeight, body.clientHeight, html.scrollHeight, html.offsetHeight, html.clientHeight );
    };

    const resizeAndScroll = (force) => {
      const root     = document.documentElement;
      const atBottom = window.innerHeight + window.scrollY >= Math.max( root.scrollHeight, document.body.scrollHeight) - 40;   // was the user following the output? checked before resizing, 40px tolerance
      frame.style.height   = getDocHeight() + "px";
      frame.style.overflow = "hidden";
      body.style.overflow  = "hidden";

      // follow new output only when the user was at the bottom, so scrolling up to read earlier output is not interrupted
      if ( force === true || atBottom ) { window.scrollTo({ top: Math.max( root.scrollHeight, document.body.scrollHeight), behavior: "auto" }); }
    };

    new ResizeObserver( () => resizeAndScroll() ).observe(body);
    window.addEventListener("resize", () => resizeAndScroll() );

    resizeAndScroll(true);   // initially jump to the output, from then on follow only while at the bottom
  });
}
</script>
END;
}



public static function getIframe ( ) {
  $contents =   htmlspecialchars (self::getHeadHTML () . self::getGeneral ()); // need htmlspecialchars for inclusiong into a srcdoc
  return "<iframe id='myFrame' srcdoc=\"$contents\" style='width:100%; height:100%; border:1px solid lightgrey;border-radius:5px;'></iframe>" . self::getResizeJS();
}



public static function getGeneral ( $topInfo = "" ) {
  global $wgExtensionAssetsPath;   // works with and without short URLs, unlike ./extensions
return <<<END
<h2>Script Execution</h2>
The system is executing a script. This process may take some time. Do not close this window before we inform you about completion of all activities.<br><br>
<h3>General Log</h3>
<ul id="log"></ul>
<h3>Commands</h3>
<div id="commands">
</div>
<script src="{$wgExtensionAssetsPath}/DanteCommon/serviceEndpoint.js"></script>
END;
}


// NOTE: The TWO \n are essential to receive each portion as separate message
/**
 * Send an event to the client (browser) expecting an event stream
 *
 * @param string $x Command parameter for the JS receiving the event
 * @param int $num Number of the command to which the event belongs
 * @param string $chunk Data portion belonging to the event
 * @return void
 */
public static function sendEvent ( $x, $num, $chunk ): void {
  $phpJson = ["command" => $x, "data" => $chunk, "num"=> $num];
  $msg = json_encode ($phpJson, JSON_INVALID_UTF8_SUBSTITUTE);   // substitute invalid UTF-8 (binary output, characters split at chunk borders), otherwise json_encode returns false and the client cannot parse the event
  if ( $msg === false ) { $msg = json_encode ( ["command" => "log", "data" => "Program error: could not encode event '$x': " . json_last_error_msg(), "num" => $num] ); }   // other encoding failures (e.g. NAN): send a log message instead of an empty event
  echo "data: {$msg}\n\n";
  @ob_flush();
  @flush();
}


}  // end class





// a notifier is an object which can send a notification command $cmd together with further $data as information
// this can be implemented as a logger to a file but also to a client page in a browser receiveing web events from the server
abstract class Notifier {
  protected int $num = 0;   // identification to which command the notification will belong

  public function setNum ( int $number ): void { $this->num = $number; }   // set before notifying about a command
  abstract public function notify ( $command, $data );
}



class WebNotifier extends Notifier {

public function notify ( $command, $data ): void {
  $phpJson = ["command" => $command, "data" => $data, "num"=> $this->num];
  $msg = json_encode ($phpJson, JSON_INVALID_UTF8_SUBSTITUTE);   // substitute invalid UTF-8 (binary output, characters split at chunk borders), otherwise json_encode returns false and the client cannot parse the event
  if ( $msg === false ) { $msg = json_encode ( ["command" => "log", "data" => "Program error: could not encode event '$command': " . json_last_error_msg(), "num" => $this->num] ); }   // other encoding failures (e.g. NAN): send a log message instead of an empty event
  echo "data: {$msg}\n\n";
  @ob_flush();
  @flush();
}

}
