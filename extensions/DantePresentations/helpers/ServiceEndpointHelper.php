<?php

// This file contains the service endpoint helper class
// 1. Goal of this file is to keep the service endpoints itself clean from helper classes
// 2. Goal if this file is to make the helper class available to files which need access without having
//         the structure of an endpoint.

use MediaWiki\Session\SessionManager;

class ServiceEndpointHelper {

///// TODO: session timeout where ??????
public static function sendTemplate ( $cmd, $env ) {   // sends a template, attach a command string to the session
  header('Content-Type: text/html');  
  header('Cache-Control: no-cache');
  header('Connection: keep-alive');

  $raw    = file_get_contents ( 'php://input' );
  $method = $_SERVER['REQUEST_METHOD'];

  $session = SessionManager::getGlobalSession();
  $session->set( 'Dante_Cmd', $cmd );             
  $session->set( 'Dante_Env', $env );

  $template = <<<END
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="./extensions/DantePresentations/endpoints/serviceEndpoint.css" />
    <title>Server side Script</title>
  </head>
  <body>

<h3>General Log</h3>
<ul id="log"></ul>

<h3>Commands</h3>
<div id="commands">
  <!-- Here, Javascript will inject dynamically some information -->
</div>

  <script src="./extensions/DantePresentations/endpoints/serviceEndpoint.js"></script>
  </body>
</html>
END;

  echo $template;

  flush(); ob_flush();
  exit;
}


public static function sendMessagesExample () {
  header('Content-Type: text/event-stream');
  header('Cache-Control: no-cache');
  header('X-Accel-Buffering: no');

  $i = 1;
  while (true) {
    $msg = "Message {$i} at " . date('H:i:s');
    $phpJson = ["command" => "log", "data" => "some stuff"];
    $msg = json_encode ($phpJson);
    echo "data: {$msg}\n\n";
    @ob_flush();
    @flush();
    $i++;
    sleep(1);
  }
  exit;
}


// NOTE: The TWO \n are essential to receive each portion as separate message
public static function sendX ( $x, $num, $chunk ) {
  $phpJson = ["command" => $x, "data" => $chunk, "num"=> $num];
  $msg = json_encode ($phpJson);
  echo "data: {$msg}\n\n";
  @ob_flush();
  @flush();
}


// THIS variant streams text as JOSN
// called with an array of shell functions which produce live, real time output
// pipes the output, as it comes up, to the web page, including stderr
// since we are using compression on the transport layer, this is not going to work as text/html
// thus we use text/event-stream
public static function liveExecuteJsonStream ( $arr, $env = array() ) {
  $didHaveError = false;  // flag to detect if we ever had an error
  $errorCount   = 0;      // counts the number of command which were in error
  $count = 0;             // counts the commands

  $num = count ( $arr );

  foreach ( $arr as $ele ) {
    $count++;
    // ServiceEndpointHelper::sendX ( "log", $count, $ele);    // log the array element as text string for debugging purposes

    ServiceEndpointHelper::sendX ( "setup", $count, $ele);  // setup a logging area for this command
    ServiceEndpointHelper::sendX ( "cmd",   $count, $ele);  // log the command itself

    $proc = proc_open($ele,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes, null, $env); 

    // Set STDOUT and STDERR to non-blocking
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
      // construct $read as array of sockets which we potentially still can read
      $read = [];
      if (!feof($pipes[1])) {$read[] = $pipes[1];}
      if (!feof($pipes[2])) {$read[] = $pipes[2];}

      if ($read) {
        $write = null;
        $except = null;
        $numChanged = stream_select($read, $write, $except, 0, 200000);         // Wait up to 0.2s for data

        if ($numChanged === false) {
          //echo "\n\n++++++++++++++++ STREAM SELECTION ERROR ++++++++++++\n\n";  // TODO: what to do here???
          break;
        }

        if ($numChanged > 0) {
          // echo "Stream selected selected $numChanged sockets \n";
          foreach ($read as $r) {
            $chunk = fread($r, 8192);
            if ($chunk === false || $chunk === '') {continue;}
            if ($r === $pipes[1])     { ServiceEndpointHelper::sendX ( "stdout", $count, $chunk); }
            elseif ($r === $pipes[2]) { ServiceEndpointHelper::sendX ( "stderr", $count, $chunk); }
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
      if ($status["running"]) { ServiceEndpointHelper::sendX ( "running" , $count, "running" ); }  
      else {   // process is no longer running 
       if (feof($pipes[1]) && feof($pipes[2])) {
          ServiceEndpointHelper::sendX (  ($status["exitcode"] === 0 ? "exitOk" : "exitErr" ) , $count,  "exit ".$status["exitcode"] ); 
          break; }
        else   {                                { 
          ServiceEndpointHelper::sendX (  ($status["exitcode"] === 0 ? "drainOk" : "drainErr" ) , $count,  "draining ".$status["exitcode"] ); 
        }
        }
      }

      // Small sleep to avoid busy-wait 
      usleep(50000); // 50ms
    }

    // this command has finished, can close the resource, check the final status and signal this in the UI
    $closeParam = proc_close($proc);

    if ($closeParam != 0) {$didHaveError = true; $errorCount++;  ServiceEndpointHelper::sendX ( "in-error", $count, "" );}
    else                  {                                      ServiceEndpointHelper::sendX ( "was-ok", $count, "" ); }

  } // end for loop over all commands

//  if ($didHaveError) { echo "************ $errorCount COMMANDs were in ERROR ***";  } 
//  else               { echo "***** All commands have completed successfully";      }

  ServiceEndpointHelper::sendX ( "close", $count, $chunk);

}


}  // end class