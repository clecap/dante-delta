<?php

/** This file contains some common functions used in all of the Dante extensions.
 *
 *  The reason we are doing this is to make stuff available on a more systematic basis
 *  without having to bother wit composer and similar stuff.
 */


// Given some string with mediawiki source text, return the stuff between the first <pre> and the first </pre>, removing these tags and any white space
// The idea is to have 
//   1) Configuration files, usually in the MediaWiki namespace, show their content in preview
//   2) Allow some comments on the purpose and the format of these files as part of these files (before and after the pre tags)

function extractPreContents ($code) {
  $start = strpos ($code, "<pre>") + 5;
  $end   = strpos ($code, "</pre>");
  $code  = substr ($code, $start, $end - $start);
  $code  = trim   ($code);
  return $code;
}

function danteLog ($extension, $text) {
  $fileName = dirname(__FILE__) . "/extensions/".$extension."/LOGFILE";
  if($tmpFile = fopen( $fileName , 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  
  else {throw new Exception ("DanteSettings.php: debugLog could not log to $fileName for extension $extension"); }

  $fileSize = filesize ($fileName);
  if ($fileSize == false) { return; }
  if ($fileSize > 100000) {  $handle = fopen($fileName, 'w'); }  // truncate too long files

}






