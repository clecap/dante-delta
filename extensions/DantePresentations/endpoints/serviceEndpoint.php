<?php

// serviceEndpoint implements a more general endpoint for executing long running scripts with error control possibilities for the user
// ServiceendpointHelper attaches script to the session and displays an HTML frame in which events can be displayed


// need to use MediaWiki session manager
require __DIR__ . '/../../../includes/WebStart.php';
use MediaWiki\Session\SessionManager;             
$session = SessionManager::getGlobalSession();

// generate headers for an event-stream
header('Content-Type: text/event-stream');  
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// We need to change the error handlers, since the receiver of this endpoint expects an event stream format and
// PHP sends errors and exceptions usually as HTML, to which the receiver cannot react properly

// set custom PHP error handler to send every PHP error in SSE format and not in HTML format as usual
set_error_handler(function ($severity, $message, $file, $line) {
  $payload = ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line ];

  // now send in event format
  echo "event: php-error\n";
  echo 'data: ' . json_encode($payload) . "\n\n";
  @ob_flush();
  flush();
  return true;  // Returning true prevents the normal PHP error handler (and HTML output)
});

// Same for uncaught exceptions
set_exception_handler(function (Throwable $e) {
  $payload = ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine() ];

  // now send in event format
  echo "event: php-exception\n";
  echo 'data: ' . json_encode($payload) . "\n\n";
  @ob_flush();
  flush();
});

// echo "event: exception\n"; echo "data: exception test is Stream started\n\n"; @ob_flush(); flush(); // development debug

// Read/write MediaWiki session data
$cmdString = $session->get( 'Dante_Cmd' );  // a string which encodes in JSON format the array of commands to be executed
$envString = $session->get ( 'Dante_Env');  // a string which encodes in JSON format the environment in which these commands shall be executed

// convert json text into php array
$cmdArray = json_decode ( $cmdString, true );
$envArray = json_decode ( $envString, true);

require_once ( __DIR__ . "/../helpers/ServiceEndpointHelper.php");

ServiceEndpointHelper::liveExecuteJsonStream ( $cmdArray, $envArray );

?>
