<?php

// The class Executor bundles convenience functions for execution shell commands
class Executor {

  // execute the command $cmd containing AWS CLI in the background
  // assume that the output is dealt with by the command itself (piping or streaming or whatever)
  public static function executeAWS_BG ( EnvironmentPreparator $prep, $cmd ) {
    $prep->prepare ();
// TODO: MISSING ??????????????????
    $prep->clear();
  }


  // execute a return command of the AWS CLI in the foreground; 
  // capture the output, the return code and possibly the error code
  // returns the return code; 
  public static function executeAWS_FG_RET ( string $cmd, $env, ?string &$output, ?string &$error ) {
    $proc = proc_open($cmd,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes, null, $env);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $closeParam = proc_close($proc);
    return $closeParam;
  }



// $cmd:       command to be executed
// $output:    captures stdout
// $error:     captures stderr
// $duration:  captures execution time in microseconds
// return:     return value of the command
 public static function execute ( $cmd, &$output, &$error, &$duration ) {
  $startTime = microtime(true); 
  $proc = proc_open($cmd,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes);
  $output = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $error = stream_get_contents($pipes[2]);
  fclose($pipes[2]);
  $closeParam = proc_close($proc);
  $duration = microtime (true) - $startTime;
  return $closeParam;
  }

  // $output is textual output from a OS command with lines seperated by newlines and cols seperated by one or more blanks
  // $keys is an array of key names
  // returns an array (one element per line) of associative arrays (cols mapped)
  public static function parseColumns ( $output, $keys ) {
    $lines = explode ("\n", $output);
    $allObjs = [];
    foreach ($lines as $line) {
      $line = preg_replace("/\s+/", " ", $line);  // remove multiple white spaces in the line
      if ( strlen ($line) != 0) { 
        $cols = explode (" ", $line);
        $num = 0;
        $obj = [];
        foreach ($cols as $elem) {
          $obj[$keys[$num]] = $elem;
          $num++;
        }
        array_push ($allObjs, $obj);
      }
    }
    return $allObjs;
  }


// called with an array of shell functions which produce output
// pipes the output, as it comes up, to the web page
public static function liveExecute ( $arr ) {
  global $wgServer, $wgScript;

  foreach ( $arr as $ele ) {
    echo "";
    echo "<h4>COMMAND: "; echo $ele; echo "</h4>"; flush();ob_flush();
    $proc = popen ($ele, 'r');

    while (!feof($proc)) {
      $info = fread($proc, 4096);
      $info = trim ($info);
      if (strlen ($info) > 0) {
        $htmlInfo = str_replace ("\n", "<br>", $info);
        echo "[".date("H:i:s")."] ".$htmlInfo.'<br>';flush();ob_flush();} 
      }
  }
  echo "<br><br><a href='".$wgServer.$wgScript."/index.php?Main_Page'>Main Page</a>"; flush(); ob_flush();
    exit ();
}


// called with an array of shell functions which produce live, real time output
// pipes the output, as it comes up, to the web page, including stderr
// since we are using compression on the transport layer, this is not going to work as text/html
// thus we use text/event-stream
public static function liveExecuteX ( $arr, $env = array() ) {
  header('Content-Type: text/event-stream');  
  header('Cache-Control: no-cache');
  header('Connection: keep-alive');

  echo "\n\n"; echo "IMPORTANT: Do not reload or close window until we tell you so"; echo "\n\n"; flush(); ob_flush();

  $didHaveError = false;  // flag to detect if we ever had an error
  $errorCount   = 0;      // counts the number of command which were in error
  $count = 0;             // counts the commands

  $num = count ( $arr );

  foreach ( $arr as $ele ) {
    $count++;
    echo "---- COMMAND: ". sprintf ("%3d", $count) . " of $num is: $ele"; echo "\n"; flush(); ob_flush();
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
          echo "\n\n++++++++++++++++ STREAM SELECTION ERROR ++++++++++++\n\n";
          break;
        }

        if ($numChanged > 0) {
          echo "Stream selected selected $numChanged sockets \n";
          foreach ($read as $r) {
            $chunk = fread($r, 8192);
            if ($chunk === false || $chunk === '') {continue;}
            if ($r === $pipes[1])     {echo "CHUNK FROM STDOUT: ".$chunk;} 
            elseif ($r === $pipes[2]) {echo "CHUNK FROM STDERR: ".$chunk;}
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
      echo "** Command $count ";
      $status = proc_get_status($proc);
      // echo print_r ($status, true);
      if (!$status['running']) {
        if (feof($pipes[1]) && feof($pipes[2])) { echo "has stopped and both stdout and stderr have been emptied\n"; break;}
        else {echo "has stopped but still draining stdout or stderr\n";}
      }
        else {echo "is still running\n";}

    // Small sleep to avoid busy-wait
    usleep(50000); // 50ms
  }

    $closeParam = proc_close($proc);

    if ($closeParam != 0) {
      $didHaveError = true; $errorCount++;
      echo "\n\n";
      echo "************************************* \n";
      echo "*************** ERROR *************** \n";
      echo "************************************* \n";
      echo "*** \n";
      echo "*** The  Exit code was: $closeParam \n";
      echo "*** The erroneous command was: $ele \n";
      echo "*** \n\n";
    }
    else {
      echo "--- EXIT CODE of COMMAND $count of $num at [".date("H:i:s")."] is: ".$closeParam . "                 " . ($closeParam == 0 ? 'OKAY': '****** ERROR ******' ); echo "\n\n\n"; flush(); ob_flush();
    }
  } // end for loop over all commands

  echo "\n\n";
  if ($didHaveError) { echo "************ $errorCount COMMANDs were in ERROR ***";  } 
  else               { echo "***** All commands have completed successfully";      }

  flush(); ob_flush();
  exit();
}


}