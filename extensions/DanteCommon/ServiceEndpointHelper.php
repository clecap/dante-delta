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
return <<<END
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="./extensions/DanteCommon/serviceEndpoint.css" />
    <title>Server side Script</title>
  </head>
  <body>
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
  const frame = document.getElementById("myFrame");

  frame.addEventListener("load", () => {
    const doc = frame.contentDocument || frame.contentWindow.document;
    if (!doc) return;
    const body = doc.body;
    const getDocHeight = () => {
      const html = doc.documentElement;
      return Math.max ( body.scrollHeight, body.offsetHeight, body.clientHeight, html.scrollHeight, html.offsetHeight, html.clientHeight );
    };

    const resizeAndScroll = () => {
      const height = getDocHeight();
      frame.style.height = height + "px";
      frame.style.overflow = "hidden";
      body.style.overflow  = "hidden";

      // Always keep the outer page scrolled to the bottom
      const root = document.documentElement;
      window.scrollTo({ top: Math.max( root.scrollHeight, document.body.scrollHeight), behavior: "auto" });
    };

    new ResizeObserver(resizeAndScroll).observe(body);
    window.addEventListener("resize", resizeAndScroll);

    resizeAndScroll();
  });
</script>
END;
}



public static function getIframe ( ) {
  $contents =   htmlspecialchars (self::getHeadHTML () . self::getGeneral ()); // need htmlspecialchars for inclusiong into a srcdoc
  return "<iframe id='myFrame' srcdoc=\"$contents\" style='width:100%; height:100%; border:1px solid lightgrey;border-radius:5px;'></iframe>" . self::getResizeJS();
}



public static function getGeneral ( $topInfo = "" ) {
return <<<END
<h2>Script Execution</h2>
The system is executing a script. This process may take some time. Do not close this window before we inform you about completion of all activities.<br><br>
<h3>General Log</h3>
<ul id="log"></ul>
<h3>Commands</h3>
<div id="commands">
</div>
<script src="./extensions/DanteCommon/serviceEndpoint.js"></script>
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
  $msg = json_encode ($phpJson);
  echo "data: {$msg}\n\n";
  @ob_flush();
  @flush();
}




}  // end class