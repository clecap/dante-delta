<?php


// The class Executor bundles convenience functions for execution shell commands
class Executor {

  
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


}