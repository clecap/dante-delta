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
 *
 * $arr is an array of one of 
 *     string         in which case it is a simple string command which must be executed
 *     callable       in which case it is a callable to be called 
 *     map with keys "command", "timeout" and "args" where "args" must be a serializable array of arguments 
 *       the command may be a string or a callable
 *       the timeout may provide an additional timeout value
 *       the args may provide an arry of arguments to be called
 */
public static function executeCommandArray ( $arr, $env = array(), &$stdoutCollect = null, &$stderrCollect = null, ?callable $notify=null ) {
  $didHaveError = false;  // flag to detect if we ever had an error
  $errorCount   = 0;      // counts the number of command which were in error
  $count = 0;             // counts the commands, counting starts at 1 (since this is for the user)

  $arr = array_values(array_filter($arr, function ($value) { return $value !== ""; })); // remove all empty strings

  $num = count ( $arr );

  foreach ( $arr as $obj ) {  // iterate over all objects in the array
    $count++;

    $timeout = null;       // default is not to use a timeout
    $startTime = time();   // memorize time the command was started

    if (is_string($obj) || is_callable ($obj)) {$ele = $obj;} // if $obj is a string or a callable, this is the command
    else  {                                                                  // otherwise we expect an array/map with keys "command", "timeout" 
                                                                             // and "args" in case of a function. "args" must be a serializable Array of arguments
                                                                             // NOTE: no timeouts for callables.
      if ( !isset($obj["command"] )) {
        $notify ? $notify ( "close", $count, "Error: Cannot find command at slot " . $count. " but just " . print_r ($obj, true)) : null;  // must notify client or it continues requesting
        throw new Exception ("Cannot find command in array object ". print_r ($obj, true) );
      }
      $ele     = $obj["command"];  // now we can set the command variable
      $args    = null;

      // check if command has a correct value !
      if ( !is_callable ($ele) && ! is_string ($ele) ) {
        $notify ? $notify ( "close", $count, "Error: At slot " . $count. " there is no callable and no string but just " . print_r ($ele, true)) : null;  // must notify client or it continues requesting
        throw new Exception ("Error: At slot ". $count . " there is no callable and no string but just " . print_r ($ele, true) );
      }

      if ( is_callable ($ele) ) {
        if ( isset ($obj["args"] ) ) {$args = $obj["args"];}
      }

      if ( !isset($obj["timeout"] )  && is_string ($ele)   ) {
        $notify ? $notify ( "close", $count, "Cannot find timeout in array object with string command " . $count . " for ". print_r ($ele, true)) : null;  // must notify client or it continues requesting
        throw new Exception ("Cannot find timeout in array object ". print_r ($ele, true) );  
      }

      $timeout = ( isset($obj["timeout"]) ? $obj["timeout"] : null); // do not use a timeout if none is specified
    } 

    $notify ? $notify( "setup", $count, $ele) : null;  // setup a logging area for this command if necessary
    $notify ? $notify( "cmd",   $count, $ele) : null;  // log the command itself


    if (is_callable ($ele))   {  /* this branch is for the case that we find a callable in the array */

      ['result' =>$fcnResult, 'stdout' => $fcnStdout, 'stderr' => $fcnStderr, 'exception' => $fcnException ] = self::captureOutput ( $ele, ...$args );  // capture the output of the call to the callable, including possible exceptions

      $notify ? $notify ( "stdout", $count, $fcnStdout) : null;
      $notify ? $notify ( "stderr", $count, $fcnStderr ): null;

      $duration = time() - $startTime;
      $tim = "duration=".$duration."[sec]";
      $notify ? $notify ( "tick", $count, chunk: $tim ) : null;

      if ($fcnException === null) {$notify ? $notify (  "ret" , $count,  "retu=". print_r ($fcnResult, true) ) : null; }
      else {
        $didHaveError = true; $errorCount++; 
        $notify ? $notify (  "exitErr" , $count,  "EXCEPTION raised: ".  get_class($fcnException) ) : null; 
      }

      continue;
    }
   
    // open the process ressource
    $proc = proc_open($ele,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes, null, $env); 

    // Set STDOUT and STDERR to non-blocking
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $tick=0.0;

    // if requested, initialize the reporting variables for stdout and stderr for this command
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

      // Check timeout, but only if we are using timeouts, i.e. $timeout !== null
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
  if ($didHaveError) {
    $notify ? $notify ( "in-error", 0, "" ) : null;
  }
}



/**
 * Call a callable function and capture stdout, stderr and result
 *
 * @param callable $fn
 * @param [type] ...$args
 * @return array
 *
 * NOTE: By design of PHP we can only catch exceptions via a handler. We cannot catch arbitrary data written to stderr 
 *       from a PHP function.
 */
public static function captureOutput (callable $fn, ...$args): array {
  $stdout = '';
  $stderr = '';
  $result = null;
  $exception = null;

  $prevHandler = set_error_handler(
    function (int $severity, string $message, string $file, int $line) use (&$stderr, &$prevHandler) {
      if (!(error_reporting() & $severity)) {return false;}
      $stderr .= sprintf ("[%s] %s in %s on line %d\n", self::error_type_to_string($severity), $message, $file, $line);
      if ($prevHandler) { return $prevHandler($severity, $message, $file, $line); }
      return true;
    },
    E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED
  );

  ob_start();

  try {$result = $fn(...$args);} 
  catch (\Throwable $e) {
    $exception = $e;
    $stderr .= sprintf( "[%s] %s in %s on line %d\n%s\n",  get_class($e),  $e->getMessage(),  $e->getFile(),  $e->getLine(),  $e->getTraceAsString() );
  } 
  finally {
    $stdout = ob_get_contents();
    ob_end_clean();
    restore_error_handler();
  }

  return ['result' => $result, 'stdout' => $stdout, 'stderr' => $stderr, 'exception' => $exception];
}




}