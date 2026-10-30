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
 * @param [boolean]  $stopOnError   In case of error, abort further processing of commands
 * @return void
 *
 * Function $notify can be 
 *  (1) a function sending parts of an event stream to the client - or 
 *  (2) logging things to a file                                  - or 
 *  (3) writing it into an HTML page                              - or
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
public static function executeCommandArray ( $arr, $env = array(), &$stdoutCollect = null, &$stderrCollect = null, ?callable $notify=null, $stopOnError = true ) {
  $didHaveError = false;  // flag to detect if we ever had an error
  $errorCount   = 0;      // counts the number of command which were in error
  $count        = 0;      // counts the commands, counting starts at 1 (since this is for the user)

  $arr = array_values(array_filter($arr, function ($value) { return $value !== ""; })); // remove all empty strings
  $num = count ( $arr );

  foreach ( $arr as $obj ) {  // iterate over all objects in the array
    $count++;
    danteLog ("DanteCommon", "*** entered loop for number $count \n");

    $timeout = null;       // default is not to use a timeout
    $startTime = time();   // memorize time the command was started
    $comment = "NC";       // default is NC for no comment

    if (is_string($obj) || is_callable ($obj)) {$ele = $obj;} // if $obj is a string or a callable, this is the command
    else  {                                                                  // otherwise we expect an array/map with keys "command", optional "timeout" 
                                                                             // and "args" in case of a function. "args" must be a serializable Array of arguments
                                                                             // NOTE: no timeouts for callables.
                                                                             // optional is key comment

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

      if ( is_callable ($ele) ) { if ( isset ($obj["args"] ) ) {$args = $obj["args"];} }
      if ( isset($obj["timeout"]) )                                   {$timeout = $obj["timeout"];}

//      if ( !isset($obj["timeout"] )  && is_string ($ele)   ) {
//        $notify ? $notify ( "close", $count, "Cannot find timeout in array object with string command " . $count . " for ". print_r ($ele, true)) : null;  // must notify client or it continues requesting
//        throw new Exception ("Cannot find timeout in array object ". print_r ($ele, true) );  
//      }

      $timeout = ( isset($obj["timeout"]) ? $obj["timeout"] : null); // do not use a timeout if none is specified

      if ( isset ($obj["comment"])) { $comment = $obj["comment"];}
    }  // end ELSE 

 
    $notify ? $notify( "setup", $count, $comment) : null;  // setup a logging area for this command if necessary
    $notify ? $notify( "cmd",   $count, $ele) : null;  // log the command itself

    danteLog ("DanteCommon", "Now checking ele \n " );
    danteLog ("DanteCommon", "ele reports as  " . print_r ($ele, true). "\n");
    danteLog ("DanteCommon", (is_callable ($ele) ? " IS_CALL ": " IS_NOT_CALL " ) . ( is_string ($ele) ? "IS_STRING" : "IS_NOT_STRING" ) . "\n");

    if (!is_callable ($ele) && !is_string($ele)) {throw new Exception ( "Is neither callable nor string: " . print_r ($ele, true) );}

    if (is_callable ($ele))   {  /* this branch is for the case that we find a callable in the array */
      ['result' =>$fcnResult, 'stdout' => $fcnStdout, 'stderr' => $fcnStderr, 'exception' => $fcnException ] = self::captureOutput ( $ele, ...$args );  // capture the output of the call to the callable, including possible exceptions

      $fcnStdout = trim ($fcnStdout);  $fcnStderr = trim ($fcnStderr);

      if (!empty($fcnStdout)) {$notify ? $notify ( "stdout", $count, $fcnStdout) : null;  }                      // sets the stdout field
      if (!empty ($fcnStderr)) {$notify ? $notify ( "stderr", $count, $fcnStderr ): null; }                    // sets the stderr field

      $duration = time() - $startTime;
      $tim = "duration=".number_format($duration, 2)."[sec]";
      $notify ? $notify ( "tick", $count, chunk: $tim ) : null;

      if ($fcnException === null) {  // there was no exception
        $notify ? $notify (  "retOk" , $count,  "return=". print_r ($fcnResult, true) ) : null; 
        continue;
      }
      else {
        $didHaveError = true; $errorCount++; 
        $notify ? $notify (  "retErr" , $count,  "EXCEPTION raised: ".  get_class($fcnException) ) : null; 

        if ($stopOnError) {break;} else {continue;}
      }
    }
   
    // open the process ressource
    danteLog ("DanteCommon", "Invoking in proc_open:  " . print_r ($ele, true). "\n");

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

      usleep(100000);      // Small sleep of 100ms to avoid busy-wait
      $tick += 0.1;                       // add time
      $tim = "duration=".$tick."[sec]";
      $notify ? $notify ( "tick", $count, chunk: $tim ) : null;
    }

// TODO: NOW that we are drained we could also issue another notification for the -now-fully collected output
// TODO: Maybe only now provide this back to the caller into the stdout and stderr collectors

    // this command has finished, can close the resource, check the final status and signal this in the UI
    $closeParam = proc_close($proc);

    if ($closeParam != 0) {
      $didHaveError = true; $errorCount++;  $notify ? $notify ( "in-error", $count, "" ) : null;
      if ($stopOnError) {  break;} else {continue;}
    }
    else                  {                                      $notify ? $notify ( "was-ok",   $count, "" ) : null; }

  } // end for loop over all commands  

  $notify ? $notify ( "close", $count, ( $didHaveError ?  "Please check: There were $errorCount errors "  . ($stopOnError ? " and we stopped on the error " : " and we continued after the error" )   : "Completed. You may now leave this window."  )) : null;
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
 * NOTE: By design of PHP we can only catch exceptions via a handler. 
 *       We cannot catch arbitrary data written to stderr from a PHP function.
 *       We can, however, patch the error_log function and use this inside of the called functions.
 */
public static function captureOutput (callable $fn, ...$args): array {
  $stdout = '';
  $stderr = '';
  $result = null;
  $exception = null;

  // Redirect error_log() output to a temp file — error_log() bypasses set_error_handler
  $tmpErrFile  = tempnam(sys_get_temp_dir(), 'dante_stderr_');
  $prevErrorLog = ini_get('error_log');
  ini_set('error_log', $tmpErrFile);

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

    // Restore error_log and collect whatever was written to it
    ini_set('error_log', $prevErrorLog ?: '');
    if (file_exists($tmpErrFile)) {
      $logged = file_get_contents($tmpErrFile);
      if ($logged !== false && $logged !== '') {$stderr .= $logged;}
      unlink($tmpErrFile);
    }
  }

  return ['result' => $result, 'stdout' => $stdout, 'stderr' => $stderr, 'exception' => $exception];
}


}





// NOTE FOR ME:  args are still missing



abstract class DanteCommand {   // abstract base for commands executed by ServiceExecutor
  protected ?string $comment;
  protected ?int    $timeout;

  public function __construct ( ?string $comment = "", ?int $timeout = null ) { $this->comment = $comment; $this->timeout = $timeout; }

  public function getComment (): ?string { return $this->comment; }   // human readable comment, shown as heading of the command

  abstract public function execute ( ?Notifier $notifier, array $env = [], &$stdoutCollect = null, &$stderrCollect = null ): bool;  // execute command, notify occasionally, collect stdout/stderr if requested; true on success
  abstract public function getString (): string;   // return a string representation of the command

/**
 * Executes an array of commands and collects stdout and stderr along the way.
 *
 * @param [DanteCommand] $arr
 * @param array $env
 * @param [type] $stdoutCollect If not null, collects the stdout of every command
 * @param [type] $stderrCollect If not null, collects the stderr of every command
 * @param [Notifier] $notifier If not null, notified occasionally during reading out the pipes and iterating the command array
 * @param [boolean]  $stopOnError   In case of error, abort further processing of commands
 * @return void
 *
 * Notifier $notifier can be
 *  (1) a function sending parts of an event stream to the client - or 
 *  (2) logging things to a file                                  - or 
 *  (3) writing it into an HTML page                              - or
 *  (4) null, for doing nothing an djust utilizing the remaining functionalities we have here
 *
 * CALLED in serviceEndpoint.php
 *
 * $arr is an array of DanteCommand objects
 */


public static function executeCommandArray ( $arr, $env = array(), &$stdoutCollect = null, &$stderrCollect = null, ?Notifier $notifier = null, $stopOnError = true ) {
  $didHaveError = false;  // flag to detect if we ever had an error
  $errorCount   = 0;      // counts the number of command which were in error
  $count        = 0;      // counts the commands, counting starts at 1 (since this is for the user)

  foreach ( $arr as $obj ) {  // iterate over all objects in the array     $obj is the specific element we work on
    $count++;
    danteLog ("DanteCommon", "*** entered loop for number $count \n");

    $notifier ? $notifier->notify ( "setup", $obj->getComment() ) : null;      // setup a logging area for this command if necessary
    $notifier ? $notifier->notify ( "cmd",   $obj->getString() )  : null;      // log the command itself

    // execute; collect output per command (index $count) if the caller asked for it; an exception counts as error and is reported as stderr
    $out = ( $stdoutCollect !== null ) ? "" : null;
    $err = ( $stderrCollect !== null ) ? "" : null;
    try { $wasOk = $obj->execute ( $notifier, $env, $out, $err ); }
    catch ( \Throwable $e ) {
      $wasOk = false;
      $msg   = "[" . get_class ( $e ) . "] " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
      $notifier ? $notifier->notify ( "stderr", $msg ) : null;
      if ( $err !== null ) { $err .= $msg; }
    }
    if ( $stdoutCollect !== null ) { $stdoutCollect[$count] = $out; }
    if ( $stderrCollect !== null ) { $stderrCollect[$count] = $err; }

    if ( !$wasOk ) {
      $didHaveError = true; $errorCount++;
      if ($stopOnError) {  break;} else {continue;}
    }
  } // end for loop over all commands

  $notifier ? $notifier->notify ( "close", ( $didHaveError ?  "Please check: There were $errorCount errors "  . ($stopOnError ? " and we stopped on the error " : " and we continued after the error" )   : "Completed. You may now leave this window."  )) : null;
  if ($didHaveError) {
    $notifier ? $notifier->notify ( "in-error", "" ) : null;
  }

} // function

} // class






// DanteCallableCommand is a danteCommand consisting of a PHP callable function
class DanteCallableCommand extends DanteCommand {

  private string|array $callableCmd;   // "Class::method" or [class, method]; callable is not allowed as property type, and closures could not be stored in the session

  public function __construct ( string|array $callableCmd, ?string $comment = "", ?int $timeout = null ) {
    if ( !is_callable ( $callableCmd ) ) { throw new InvalidArgumentException ( "Not a callable: " . print_r ( $callableCmd, true ) ); }   // reject anything PHP cannot call
    parent::__construct ( $comment, $timeout );
    $this->callableCmd = $callableCmd;
  }

  public function execute ( ?Notifier $notifier, array $env = [], &$stdoutCollect = null, &$stderrCollect = null ): bool {
      $startTime = time();   // memorize time the callable was started
      ['result' =>$fcnResult, 'stdout' => $fcnStdout, 'stderr' => $fcnStderr, 'exception' => $fcnException ] = ServiceExecutor::captureOutput ( $this->callableCmd, ...$args );  // capture the output of the call to the callable, including possible exceptions
      $fcnStdout = trim ($fcnStdout);  $fcnStderr = trim ($fcnStderr);

      if (!empty($fcnStdout))  {$notifier ? $notifier->notify ( "stdout", $fcnStdout) : null;  }                   // sets the stdout field
      if (!empty ($fcnStderr)) {$notifier ? $notifier->notify ( "stderr", $fcnStderr ): null;  }                   // sets the stderr field

      $duration = time() - $startTime;
      $tim = "duration=".number_format($duration, 2)."[sec]";
      $notifier ? $notifier->notify ( "tick", $tim ) : null;

      if ($fcnException === null) { $notifier ? $notifier->notify (  "retOk"  , "return=". print_r ($fcnResult, true) )     : null;    return true;  }
      else                        { $notifier ? $notifier->notify (  "retErr" ,  "EXCEPTION raised: ".  get_class($fcnException) ) : null;    return false; }
  }  

public function getString (): string { return is_array ( $this->callableCmd ) ? ( is_object ( $this->callableCmd[0] ) ? get_class ( $this->callableCmd[0] ) : $this->callableCmd[0] ) . "::" . $this->callableCmd[1] : $this->callableCmd; }   // readable form, [class, method] shown as Class::method


}



// DanteStringCommand is a DanteCommand consisting of a shell executable string
class DanteStringCommand extends DanteCommand {
  private string $stringCommand;

  public function getString (): string { return $this->stringCommand; }

  public function __construct ( string $stringCommand, ?string $comment = "", ?int $timeout = null ) { parent::__construct ( $comment, $timeout ); $this->stringCommand = $stringCommand; }

  public function execute ( ?Notifier $notifier, array $env = [], &$stdoutCollect = null, &$stderrCollect = null ): bool {
    $proc = proc_open ( $this->stringCommand, [ 1 => ['pipe','w'], 2 => ['pipe','w'] ], $pipes, null, $env );
    if ( $proc === false ) { throw new Exception ( "Could not start command: $this->stringCommand" ); }   // otherwise $pipes is unset and errors follow

    stream_set_blocking ( $pipes[1], false );  stream_set_blocking ( $pipes[2], false );   // non-blocking reads
    $tick       = 0.0;  
    $startTime  = time();  
    $terminated = false;  
    $exitCode   = -1;

    if ( $stdoutCollect !== null ) { $stdoutCollect = ""; }   // caller asked for collection
    if ( $stderrCollect !== null ) { $stderrCollect = ""; }

    while ( true ) {
      // read whatever is available on stdout and stderr, at most 0.2s wait
      $read = [];
      if ( !feof ( $pipes[1] ) ) { $read[] = $pipes[1]; }
      if ( !feof ( $pipes[2] ) ) { $read[] = $pipes[2]; }
      if ( $read ) {
        $write = null; $except = null;
        $numChanged = stream_select ( $read, $write, $except, 0, 200000 );
        if ( $numChanged === false ) { throw new Exception ( "error while draining pipes of command $this->stringCommand" ); }
        foreach ( $read as $r ) {
          $chunk = fread ( $r, 8192 );
          if ( $chunk === false || $chunk === '' ) { continue; }
          if ( $r === $pipes[1] ) { $notifier ? $notifier->notify ( "stdout", $chunk ) : null;  if ( $stdoutCollect !== null ) { $stdoutCollect .= $chunk; } }
          else                    { $notifier ? $notifier->notify ( "stderr", $chunk ) : null;  if ( $stderrCollect !== null ) { $stderrCollect .= $chunk; } }
        }
      }

      // terminate the process when the timeout is exceeded
      if ( !$terminated && $this->timeout !== null && ( time() - $startTime ) > $this->timeout ) {
        $notifier ? $notifier->notify ( "timeout", ( time() - $startTime ) ) : null;
        proc_terminate ( $proc );  $terminated = true;
      }

      // report status; remember the exit code as soon as the process has ended; leave the loop when both pipes are drained
      $status = proc_get_status ( $proc );
      if ( !$status["running"] && $exitCode === -1 ) { $exitCode = $status["exitcode"]; }
      if ( $status["running"] )                      { $notifier ? $notifier->notify ( "running","running " . $tick ) : null; }
      elseif ( feof ( $pipes[1] ) && feof ( $pipes[2] ) ) { $notifier ? $notifier->notify ( $exitCode === 0 ? "exitOk"  : "exitErr", "exit="     . $exitCode ) : null; break; }
      else                                                { $notifier ? $notifier->notify ( $exitCode === 0 ? "drainOk" : "drainErr", "draining " . $exitCode ) : null; }

      usleep ( 100000 );  $tick += 0.1;   // avoid busy-wait
      $notifier ? $notifier->notify ( "tick", "duration=" . $tick . "[sec]" ) : null;
    }

    fclose ( $pipes[1] );  fclose ( $pipes[2] );   // close pipes before proc_close, as PHP recommends
    proc_close ( $proc );
    $ok = ( $exitCode === 0 );
    $notifier ? $notifier->notify ( $ok ? "was-ok" : "in-error", "" ) : null;
    return $ok; 
  }
}




 


