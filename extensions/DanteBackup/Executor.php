<?php



/** The class Executor bundles convenience functions for execution shell commands */ 
class Executor {

  // execute the command $cmd containing AWS CLI in the background
  // assume that the output is dealt with by the command itself (piping or streaming or whatever)
  public static function executeAWS_BG ( EnvironmentPreparator $prep, $cmd ) {
    $prep->prepare ();

    $prep->clear();
  }

  // execute a return command of the AWS CLI in the foreground; 
  // capture the output, the return code and possibly the error code
  // returns the return code; 
  public static function executeAWS_FG_RET ( EnvironmentPreparator $prep, string $cmd, ?string &$output, ?string &$error ) {
    $prep->prepare ();
    $proc = proc_open($cmd,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $closeParam = proc_close($proc);
    $prep->clear();
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
   // $proc = popen("ping -c 5 google.com", 'r');
   // while (!feof($proc)) {echo "[".date("i:s")."] ".fread($proc, 4096).'<br>';flush();ob_flush();}

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
  // terminates by writing $final
  public static function liveExecuteX_OLD ( $arr, $final ) {

// this works, but not when we are using compression on the transport layer. in that case
// the system gathers everything and sends it out only when finished.


    foreach ( $arr as $ele ) {
      echo ""; echo "<h4 style='color:blue;'>COMMAND: "; echo htmlspecialchars($ele); echo "</h4>";  flush();ob_flush();
      $proc = proc_open($ele,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes);
    
      while (!feof ($pipes[1])) {  // drain stdout
        $info = fread ($pipes[1], 4096);
        $info = trim ($info);
        //if (strlen ($info) > 0) {
          $htmlInfo = str_replace ("\n", "<br>", $info);
          echo "[".date("H:i:s")."] ".$htmlInfo.'<br>';flush();ob_flush();
       // } 
      } // end while
      while (!feof ($pipes[2])) {  // drain stderr
        $info = fread ($pipes[2], 4096);
        $info = trim ($info);
        if (strlen ($info) > 0) {      
          $htmlInfo = str_replace ("\n", "<br>", $info);  
          $htmlInfo = "<span style='color:red;'>ERROR:" .$htmlInfo. "</span>";
          echo "[".date("H:i:s")."] ".$htmlInfo.'<br>';flush();ob_flush(); 
        }
        else {
          $htmlInfo = "<span>No error information found</span>";
          echo "[".date("H:i:s")."] ".$htmlInfo.'<br>';flush();ob_flush(); 
        }
      }  // end while draining stderr

//      $closeParam = proc_close($proc);
//      echo "<h5>EXIT CODE of COMMAND is: ".$closeParam."</h5>";

      flush(); ob_flush();

      //fclose($pipes[1]);
      //fclose($pipes[2]);
    }

    echo $final;
    exit();
}





// called with an array of shell functions which produce live, real time output
// pipes the output, as it comes up, to the web page, including stderr
// terminates by writing $final
public static function liveExecuteX ( $arr, $final ) {

  header('Content-Type: text/event-stream');
  header('Cache-Control: no-cache');
  header('Connection: keep-alive');

  echo "Do not reload or close window until we tell you so"; echo "\n\n"; flush(); ob_flush();

  $didHaveError = false;

  foreach ( $arr as $ele ) {
    echo "----- COMMAND:  $ele"; echo "\n"; flush(); ob_flush();
    $proc = proc_open($ele,[ 1 => ['pipe','w'], 2 => ['pipe','w'],], $pipes);
    while (!feof ($pipes[1])) {  // drain stdout
      $info = fgets ($pipes[1]);
      $info = trim ($info);
      if (strlen ($info) > 0) {echo "[".date("H:i:s")."] ".$info."\n";flush();ob_flush();}
    } // end while
    $first = true;
    while (!feof ($pipes[2])) {  // drain stderr
      $info = fgets ($pipes[2]);
      $info = trim ($info);
      if (strlen ($info) > 0) {      
        if ($first) { echo "\n** Information sent to stderr: \n"; flush();ob_flush(); $first = false;}
        echo "** [".date("H:i:s")."] ".$info."\n"; flush();ob_flush(); 
      }
      else {
          //echo "No error information found"; echo "\n"; flush();ob_flush(); 
      }
    }  // end while draining stderr
    $closeParam = proc_close($proc);
    if ($closeParam != 0) {
      $didHaveError = true;
      echo "\n\n";
      echo "************************************* \n";
      echo "*************** ERROR *************** \n";
      echo "************************************* \n";
      echo "*** \n";
      echo "*** The  Exit code was: $closeParam \n";
      echo "*** The erroneous command was: $ele \n";
      echo "*** \n";
  }
  else {
  echo "-- EXIT CODE of COMMAND at [".date("H:i:s")."] is: ".$closeParam; echo "\n\n"; flush(); ob_flush();}
  } // end for loop over all commands

  echo "\n\n";
  if ($didHaveError) { echo "************ ONE or more COMMAND were in ERROR ***"; flush(); ob_flush(); } 
  else {}




  echo $final; flush(); ob_flush();
  exit();
}





}