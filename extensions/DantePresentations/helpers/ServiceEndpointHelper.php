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
    <link rel="stylesheet" href="./extensions/DantePresentations/endpoints/serviceEndpoint.css" />
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
<script src="./extensions/DantePresentations/endpoints/serviceEndpoint.js"></script>
END;
}


/*
public static function sendTemplate (  ) {
  header('Content-Type: text/html');  
  header('Cache-Control: no-cache');
  header('Connection: keep-alive');

  echo self::getHeadHTML ();  flush(); ob_flush();
}
*/


// NOTE: The TWO \n are essential to receive each portion as separate message
/**
 * Send an event to the client (browser) expecting an event stream
 *
 * @param string $x Command parameter for the JS receiving the event
 * @param int $num Number of the command to which the event belongs
 * @param string $chunk Data portion belonging to the event
 * @return void
 */
public static function sendEvent ( $x, $num, $chunk ) {
  $phpJson = ["command" => $x, "data" => $chunk, "num"=> $num];
  $msg = json_encode ($phpJson);
  echo "data: {$msg}\n\n";
  @ob_flush();
  @flush();
}


// called with an array of shell functions which produce live, real time output
// pipes the output, as it comes up, to the web page, including stderr
// thus we use text/event-stream


/**
 * Undocumented function
 *
 * @param [type] $arr
 * @param array $env
 * @param [type] $stdoutCollect If not null, collects the stdout of every command
 * @param [type] $stderrCollect If not null, collects the stderr of every command
 * @return void
 */
public static function liveExecuteJsonStream ( $arr, $env = array(), &$stdoutCollect = null, &$stderrCollect = null ) {
  $didHaveError = false;  // flag to detect if we ever had an error
  $errorCount   = 0;      // counts the number of command which were in error
  $count = 0;             // counts the commands, counting starts at 1 (since this is for the user)

  $num = count ( $arr );

  foreach ( $arr as $ele ) {  // iterate over all commands
    $count++;
    // ServiceEndpointHelper::sendX ( "log", $count, $ele);    // log the array element as text string for debugging purposes

    ServiceEndpointHelper::sendEvent ( "setup", $count, $ele);  // setup a logging area for this command
    ServiceEndpointHelper::sendEvent ( "cmd",   $count, $ele);  // log the command itself

    // open the process ressource
    $proc = proc_open($ele,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes, null, $env); 

    // Set STDOUT and STDERR to non-blocking
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $tick=0.0;

    // if requested, initialize the reporting variables for stdou and stderr for this command
    if ($stdoutCollect) {$stdoutCollect[$count] = "";}
    if ($stderrCollect) {$stderrCollect[$count] = "";}

    // now drain the pipes from the command execution and do so in a manner preventing deadlocks
    while (true) {
      // construct $read as array of sockets which we potentially still can read
      $read = [];
      if (!feof($pipes[1])) {$read[] = $pipes[1];}
      if (!feof($pipes[2])) {$read[] = $pipes[2];}

      if ($read) {
        $write = null; $except = null;  // do not wait for writeable or out of band pipes
        $numChanged = stream_select($read, $write, $except, 0, 200000);  // Wait up to 0.2s for data

        if ($numChanged === false) { throw new Exception ("error while draining pipes during command execution of command $ele");}
        if ($numChanged > 0) {
          // ServiceEndpointHelper::sendEvent ( "log" , $count, "select found $numChanged ready sockets" );
          foreach ($read as $r) {
            $chunk = fread($r, 8192);                    // read a chunk
            if ($chunk === false || $chunk === '') {continue;}            // disregard empty chunks
            if ($r === $pipes[1])     { 
              ServiceEndpointHelper::sendEvent ( "stdout", $count, $chunk); 
              if ($stdoutCollect) {$stdoutCollect[$count] .= $chunk;}
            }
            elseif ($r === $pipes[2]) {
              ServiceEndpointHelper::sendEvent ( "stderr", $count, $chunk); 
              if ($stderrCollect) {$stderrCollect[$count] .= $chunk;}
            }
          }
        }
      }

      // Check timeout
//      if ($timeout !== null && (time() - $startTime) > $timeout) {
//        $timedOut = true;
//        // Try to terminate the process
//        proc_terminate($process);
//      }

      // If process has exited and pipes are at EOF, we are done
      // echo "** Command $count ";

      // check process status
      $status = proc_get_status($proc);
      if ($status["running"]) { ServiceEndpointHelper::sendEvent ( "running" , $count, "running ".$tick ); }  
      else {   // process is no longer running 
       if (feof($pipes[1]) && feof($pipes[2])) {
          ServiceEndpointHelper::sendEvent (  ($status["exitcode"] === 0 ? "exitOk" : "exitErr" ) , $count,  "exit ".$status["exitcode"] ); 
          break; }
        else   {                                { 
          ServiceEndpointHelper::sendEvent (  ($status["exitcode"] === 0 ? "drainOk" : "drainErr" ) , $count,  "draining ".$status["exitcode"] ); 
        }
        }
      }

      // Small sleep to avoid busy-wait 
      usleep(100000); // 100ms
      $tick += 0.1;  // add time
      $tim = $tick."[sec]";
      ServiceEndpointHelper::sendEvent ( "tick", $count, chunk: $tim );
    }

    // this command has finished, can close the resource, check the final status and signal this in the UI
    $closeParam = proc_close($proc);

    if ($closeParam != 0) {$didHaveError = true; $errorCount++;  ServiceEndpointHelper::sendEvent ( "in-error", $count, "" );}
    else                  {                                      ServiceEndpointHelper::sendEvent ( "was-ok",   $count, "" ); }

  } // end for loop over all commands  
  ServiceEndpointHelper::sendEvent ( "close", $count, ( $didHaveError ?  "Please check: There were $errorCount errors." : "Completed. You may now leave this window."  ));
}


}  // end class