<?php


 // must have this. Autoloader does not work across some extension boundaries...
require_once ( "InfoExtractor.php" );

class ServiceExecutor {


/**
 * Executes an array of commands and collects stdout and stderr along the way.
 *
 * @param [type] $arr
 * @param array $env
 * @param [type] $stdoutCollect If not null, collects the stdout of every command
 * @param [type] $stderrCollect If not null, collects the stderr of every command
 * @param [callable] $notify If not null, called occasionally during reading out the pipes and iterating the command array
 * @return void
 *
 * Function $notify can be 
 *  (1) a function sending parts of an event stream to the client - or 
 *  (2) logging things to a file - or 
 *  (3) writing it into an HTML page - or
 *  (4) null, for doing nothing an djust utilizing the remaining functionalities we have here
 *
 * CALLED in serviceEndpoint.php
 */
public static function executeCommandArray ( $arr, $env = array(), &$stdoutCollect = null, &$stderrCollect = null, ?callable $notify=null ) {
  $didHaveError = false;  // flag to detect if we ever had an error
  $errorCount   = 0;      // counts the number of command which were in error
  $count = 0;             // counts the commands, counting starts at 1 (since this is for the user)

  $arr = array_values(array_filter($arr, function ($value) { return $value !== ""; })); // remove all empty strings

  $num = count ( $arr );

  foreach ( $arr as $obj ) {  // iterate over all objects in the array
    $count++;

    $timeout = null;       // default is no timeout
    $startTime = time();   // memorize time the command was started
    if (is_string($obj) || is_callable ($obj)) {$ele = $obj;} // if $obj is a string or a callable, this is the command
    else  {                                                                  // otherwise we expect an array/map with keys "command", "timeout" and. NOTE: no timeouts for callables.
                // and "args" in case of a function. "args" must be a serializable Array of arguments
      if ( !isset($obj["command"] )) {
        $notify ? $notify ( "close", $count, "Error: Cannot find command at slot " . $count. " but just " . print_r ($obj, true)) : null;  // must notify client or it continues requesting
        throw new Exception ("Cannot find command in array object ". print_r ($obj, true) );}
      if ( !isset($obj["timeout"] )) {
        $notify ? $notify ( "close", $count, "Got a timeout on server at slot " . $count) : null;  // must notify client or it continues requesting
        throw new Exception ("Cannot find timeout in array object ". print_r ($obj, true) );}
      $ele     = $obj["command"];
      $timeout = $obj["timeout"];
    } 
    
    // ServiceEndpointHelper::sendX ( "log", $count, $ele);    // log the array element as text string for debugging purposes

    $notify ? $notify( "setup", $count, $ele) : null;  // setup a logging area for this command if necessary
    $notify ? $notify( "cmd",   $count, $ele) : null;  // log the command itself

    if     (is_string ($ele))     { /* continues below */}
    elseif (is_callable ($ele))   {  /* this branch is for the case that we find a callable in the array */
      ['result' =>$fcnResult, 'stdout' => $fcnStdout, 'stderr' => $fcnStderr ] = self::captureOutput( $ele );  // capture the output of the call to the callable
      $notify ? $notify ( "stdout", $count, $fcnStdout) : null;
      $notify ? $notify ( "stderr", $count, $fcnStderr ): null;
      $notify ? $notify (  "ret" , $count,  "returned=". print_r ($fcnResult, true) ) : null; 
      continue;
    }
    else {throw new Exception ("Element is neither string nor callable");}

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
          // $notify ? $notify ( "log" , $count, "select found $numChanged ready sockets" ) : null;  // TODO maybe use another command as info or warning or so
          foreach ($read as $r) {
            $chunk = fread($r, 8192);                    // read a chunk
            if ($chunk === false || $chunk === '') {continue;}            // disregard empty chunks
            if ($r === $pipes[1])     { 
              $notify ? $notify ( "stdout", $count, $chunk) : null;
              if ($stdoutCollect) {$stdoutCollect[$count] .= $chunk;}
            }
            elseif ($r === $pipes[2]) {
              $notify ? $notify ( "stderr", $count, $chunk) : null;
              if ($stderrCollect) {$stderrCollect[$count] .= $chunk;}
            }
          }
        }
      }

      // Check timeout
      if ($timeout !== null && (time() - $startTime) > $timeout) {
        $notify ? $notify ("timeout", $count, (time() - $startTime)) : null;
        proc_terminate($proc);  //        // Try to terminate the process
      }

      // If process has exited and pipes are at EOF, we are done
      // echo "** Command $count ";

      // check process status
      $status = proc_get_status($proc);
      if ($status["running"]) { $notify ? $notify( "running" , $count, "running ".$tick ) : null; }  
      else {   // process is no longer running 
       if (feof($pipes[1]) && feof($pipes[2])) {
          $notify ? $notify (  ($status["exitcode"] === 0 ? "exitOk" : "exitErr" ) , $count,  "exit=".$status["exitcode"] ) : null; 
          break; }
        else   {                                { 
          $notify ? $notify (  ($status["exitcode"] === 0 ? "drainOk" : "drainErr" ) , $count,  "draining ".$status["exitcode"] ) : null; 
        }
        }
      }

      // Small sleep to avoid busy-wait 
      usleep(100000); // 100ms
      $tick += 0.1;  // add time
      $tim = "duration=".$tick."[sec]";
      $notify ? $notify ( "tick", $count, chunk: $tim ) : null;
    }


// TODO: NOW that we are drained we could also issue another notification for the -now-fully collected output
// TODO: Maybe only now provide this back to the caller into the stdout and stderr collectors

    // this command has finished, can close the resource, check the final status and signal this in the UI
    $closeParam = proc_close($proc);

    if ($closeParam != 0) {$didHaveError = true; $errorCount++;  $notify ? $notify ( "in-error", $count, "" ) : null;}
    else                  {                                      $notify ? $notify ( "was-ok",   $count, "" ) : null; }

  } // end for loop over all commands  
  $notify ? $notify ( "close", $count, ( $didHaveError ?  "Please check: There were $errorCount errors." : "Completed. You may now leave this window."  )) : null;
}



/**
 * Call a callable function and capture stdou, stderr and result
 *
 * @param callable $fn
 * @param [type] ...$args
 * @return array
 *
 * NOTE: By design of PHP we can only catch exceptions via a handler. We cannot catch arbitrary data written to stderr 
 *       from a PHP function.
 */
public static function captureOutput(callable $fn, ...$args): array {
  $stdout = '';
  $stderr = '';

  $prevHandler = set_error_handler (function ( int $severity, string $message, string $file, int $line ) use (&$stderr, &$prevHandler) {
    if (!(error_reporting() & $severity)) {return false;}
    $stderr .= sprintf( "[%s] %s in %s on line %d\n",   self::error_type_to_string($severity), $message, $file, $line );
    if ($prevHandler) { return $prevHandler($severity, $message, $file, $line);}
    return true; // prevent default error handling
  }, E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

  ob_start();                                                      // clear output buffer
  try     { $result = $fn(...$args); $stdout = ob_get_clean(); }  // call result and pick up output stuff 
  finally { restore_error_handler(); }                            // restore the previous error handler

  return [ 'result' => $result ?? null, 'stdout' => $stdout, 'stderr' => $stderr ];
}

private static function error_type_to_string(int $severity): string {
  return match ($severity) {
    E_ERROR             => 'ERROR',               E_WARNING        => 'WARNING',       E_PARSE => 'PARSE',                            E_CORE_ERROR => 'CORE_ERROR',
    E_CORE_WARNING      => 'CORE_WARNING',        E_COMPILE_ERROR  => 'COMPILE_ERROR', E_COMPILE_WARNING  => 'COMPILE_WARNING',       E_NOTICE => 'NOTICE',
    E_USER_ERROR        => 'USER_ERROR',          E_USER_WARNING   => 'USER_WARNING',  E_USER_NOTICE      => 'USER_NOTICE',           
    E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',   E_DEPRECATED      => 'DEPRECATED',   E_USER_DEPRECATED  => 'USER_DEPRECATED',       default => 'UNKNOWN',
  };
}






}