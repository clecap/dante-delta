<?php

/**
 * With require WebStart.php (MW_INSTALL_PATH may need to be set beforehand, see Manual:$IP), 
 * a script gets access to MediaWiki components and consequently it can call the API internally or
 */




// need the following two lines to obtain reasonable errors from the endpoint instead of only 500 er status from webserver
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require ("../../../includes/WebStart.php");

$content = "bababüäö";

$length = strlen($content);
header('Content-type: text/html');
header('Content-Length: '.$length);
echo $content;

$this->parser = MediaWikiServices::getInstance()->getParserFactory()->create();

$this->parse( $wikitext )->getText( [ 'wrapperDivClass' => '' ] );

class RevealEndpoint {




public sendResponseHeaders () {
 // add some headers to the response to support debugging and further handling
    // header ("X-Latex-Hash:".$hash);  
    // header ("X-Parsifal-Width-Latex-Cm-Was:".$widthLatexCm);
    // header ("X-Parsifal-Available-Pixel_Width-Was:".$availablePixelWidth);
    // header ("X-Parsifal-Gamma-Used:".$gamma);
    // header ("X-Parsifal-Scale-Used:".$gamma);  
    
    if ($retval == 0) { header ("X-Parsifal-Error:None"); }
    else              { header ("X-Parsifal-Error:Soft"); 
  return;
}

public sendToClient () {}



public static function texPreviewEndpoint () {
  $VERBOSE    = false; 
  $CACHE_PATH = CACHE_PATH;
  



  $body = file_get_contents("php://input");         // get the input; here: the raw body from the request
  $body = base64_decode ($body);                    // in an earlier version we used, unsuccessfully, some conversion, as in:   $body = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $body); 
  
  // send result to client - could be file or could be local data or could be local data which we alos store to a file for cache / debug purposes. 

  try {
    // send the result to the client
    if ($VERBOSE) {self::debugLog ("texPreviewEndpoint: will now send $name to client, dpi=$dpi, gamma=$gamma \n");}
    if (filesize($name) == 0) { throw new Exception ("texPreviewEndpoint sent a PNG file of size zero. filename: " . $name); }
    $fp = fopen($name, 'rb');
    if ($fp == FALSE)         { throw new Exception ("texPreviewEndpoint could not open PNG file " . $name ); }

   if ($VERBOSE) {self::debugLog ("texPreviewEndpoint: in try block before HEADER \n");}

    $this->sendResponseHeaders ();
    
      
                        header ("X-Parsifal-ErrorDetails:".$errorDetails);  // only: if we want to send more detailed errors to client alread in case of soft errors   
                      }  

    // header("Content-type:image/png");  header("Content-Length: " . filesize($name));  // set MANDATORY http reply headers
    fpassthru($fp); 
    fclose ($fp);
  } catch (Exception $ex) { 
    self::renderError ("endpoint Error: ", $ex, $hash); 
  }

  if ($VERBOSE) {self::debugLog ("texPreviewEndpoint: returns from call for " . $tag . " widthLatexCm: ". $widthLatexCm . "  availablePixelWidth: ". $availablePixelWidth . " \n");}

}
























