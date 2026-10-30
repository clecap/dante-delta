<?php

// serviceEndpoint implements a more general endpoint for executing long running scripts with error control possibilities for the user
// ServiceEndpointHelper attaches a script to the session and displays an HTML frame in which events can be displayed

// need to use MediaWiki session manager 
require __DIR__ . '/../../includes/WebStart.php';
use MediaWiki\Session\SessionManager;             
$session = SessionManager::getGlobalSession();

// generate headers for an event-stream
header('Content-Type: text/event-stream');  
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');




ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);


 danteLog ("DanteCommon", "serviceendpoint here !\n");

  $user = \RequestContext::getMain()->getUser();
  // danteLog ("DanteCommon", "serviceendpoint here with user: " . print_r ($user, true) . "\n");
  $userName = $user->getName();
  danteLog ("DanteCommon", "\n user is: $userName");

  if ( $user->isRegistered() ) { danteLog ("DanteCommon", "\n registered user is: $userName"); }

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
  echo 'data: ' . json_encode($payload) . "\n\n";  // double \n required for proper separation of events from each other
  @ob_flush();
  flush();
});

// echo "event: exception\n"; echo "data: exception test is Stream started\n\n"; @ob_flush(); flush(); // development debug

// Read/write MediaWiki session data
$cmdString = $session->get ( 'Dante_Cmd' );  // a string which encodes in PHP serialize format the array of DanteCommand objects to be executed
$envString = $session->get ( 'Dante_Env' );  // a string which encodes in JSON format the environment in which these commands shall be executed

// keep this one-shot: remove the queued commands so a revisited result page (back/forward, reload) does not execute them again
$session->remove ( 'Dante_Cmd' );
$session->remove ( 'Dante_Env' );
$session->save ();                                          // persist the removal NOW, since the script below may run for a long time

require_once ( __DIR__ . "/ServiceEndpointHelper.php");     // need sendEvent below

// nothing queued (already executed): tell the client and close the event stream
if ( $cmdString === null ) { ServiceEndpointHelper::sendEvent ( 'close', 0, 'Nothing to execute (commands were already run).' ); exit; }

require_once ( __DIR__ . "/ServiceExecutor.php");   // command classes must be known before unserialize

// restore the array of command objects; only our command classes may be instantiated, anything else becomes __PHP_Incomplete_Class
$cmdArray = unserialize ( $cmdString, [ 'allowed_classes' => [ 'DanteStringCommand', 'DanteCallableCommand' ] ] );
$envArray = json_decode ( $envString, true);



require_once ( __DIR__ . "/ServiceEndpointHelper.php");  // CAVE: MUST require this here, since the autoloader mechanism does not properly detect the callable below

$stdoutCollect = null; $stderrCollect = null;


$notifier = new WebNotifier ();   // sends notifications as server-sent events to the browser


require_once ( __DIR__ . "/ServiceExecutor.php");   // for danteCommand

DanteCommand::executeCommandArray ($cmdArray, $envArray, $stdoutCollect, $stderrCollect, $notifier);   // session now holds DanteCommand objects, which only the new loop understands

// ServiceExecutor::executeCommandArray ( $cmdArray, $envArray, $stdoutCollect, $stderrCollect, [ ServiceEndpointHelper::class, 'sendEvent' ] );

?>
