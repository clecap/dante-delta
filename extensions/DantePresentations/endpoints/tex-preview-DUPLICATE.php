<?php

/* a cludge function which connects the tex-preview.php endpoint back to the TexProcessor */

// need the following two lines to obtain reasonable errors from the endpoint instead of only 500 er status from webserver
error_reporting(E_ALL);
ini_set('display_errors', 'On');

//require_once ("../config.php");
//require_once ("../php/TexProcessor.php");

TexProcessor::texPreviewEndpoint();

public static function texPreviewEndpoint () {
  $VERBOSE    = false; 
  $CACHE_PATH = CACHE_PATH;
  
  TeXProcessor::ensureCacheDirectory ();                        // TODO: all the time ??? 
  TeXProcessor::ensureEnvironment ();  
  umask (0077);                                                 // preview files should be generated at 600 permission
  
  $body = file_get_contents("php://input");                     // get the input; here: the raw body from the request
  $body = base64_decode ($body);                                // in an earlier version we used, unsuccessfully, some conversion, as in:   $body = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $body); 
  
  // obtain and sanitize further parameter values from the http header
  $tag                  = $_SERVER['HTTP_X_PARSIFAL_TAG'];
  $paraText             = $_SERVER['HTTP_X_PARSIFAL_PARA'];   
  $availablePixelWidth  = $_SERVER['HTTP_X_PARSIFAL_AVAILABLE_PIXEL_WIDTH'];     if (! isset ($availablePixelWidth)) { $availablePixelWidth = 600; }
  $widthLatexCm         =  TAG2WIDTHinCM[$tag];                                  if (! isset ($widthLatexCm))        { $widthLatexCm = 15; }  
     
  if ($VERBOSE) {self::debugLog ("texPreviewEndpoint: sees tag: " . $tag . " widthLatexCm: ". $widthLatexCm . "  availablePixelWidth: ". $availablePixelWidth . " \n");}
  
  try {
    $hash = "NOT YET SET, CRASHED TOO EARLY";                                      // we want a sane value in case self::generateTex crashes for some reason
    $hash = self::generateTex ($body, $tag, "pc_pdflatex");                        // prepare for a precompiled pdflatex run
    $retval = self::Tex2Pdf ($hash, "_pc_pdflatex", "endpoint");                   // do a precompiled pdflatex run
    
    $para = json_decode ( $paraText, true);                  // decode parameter object (which we might get from the endpoint)
    $dpi =  self::DPI ($availablePixelWidth, $widthLatexCm); if ( array_key_exists ("dpi", $para) )   { $dpi = $para["dpi"]; if ($VERBOSE) {self::debugLog ("texPreviewEndpoint: finds dpi override = ".$dpi. "\n");} }  // DPI parameter
    $gamma = 1.5; if ( array_key_exists ("gamma", $para) ) { $gamma = $para["gamma"];  if ($VERBOSE) {self::debugLog ("texPreviewEndpoint: finds gamma override = ".$gamma. "\n");} }                               // GAMMA parameter
        
    $name =  $CACHE_PATH . $hash."_pc_pdflatex.png";
    $scale = self::SCALE($availablePixelWidth, $widthLatexCm); 
    self::Pdf2PngHtmlMT ($hash, $scale, "_pc_pdflatex", "_pc_pdflatex" );          // png must be redone since scale depends on width of preview area
     
//// TODO: currently it looks like we do not send the annotations and html portion for the preview
//     this might be ok but why do we then compute them??
//     and: when load is low we could also kick off generation of the final version

    // send the result to the client
    if ($VERBOSE) {self::debugLog ("texPreviewEndpoint: will now send $name to client, dpi=$dpi, gamma=$gamma \n");}
    if (filesize($name) == 0) { throw new Exception ("texPreviewEndpoint sent a PNG file of size zero. filename: " . $name); }
    $fp = fopen($name, 'rb');
    if ($fp == FALSE)         { throw new Exception ("texPreviewEndpoint could not open PNG file " . $name ); }

   if ($VERBOSE) {self::debugLog ("texPreviewEndpoint: in try block before HEADER \n");}

    // add some headers to the response to support debugging and further handling
    header ("X-Latex-Hash:".$hash);  
    header ("X-Parsifal-Width-Latex-Cm-Was:".$widthLatexCm);
    header ("X-Parsifal-Available-Pixel_Width-Was:".$availablePixelWidth);
    header ("X-Parsifal-Gamma-Used:".$gamma);
    header ("X-Parsifal-Scale-Used:".$gamma);  
    
    if ($retval == 0) { header ("X-Parsifal-Error:None"); }
    else              { header ("X-Parsifal-Error:Soft"); 
    
      
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


















?>
