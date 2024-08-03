<?php

/*! 
 *  \author   Clemens H. Cap
 *  \brief    Bundle the static and job specific functionalities. Represent a Job for storing a Snippet in NS_SNIP
 */

require_once ("../../../includes/WebStart.php");
require_once ("../../../DanteSettings-used.php");  // also needed to pick up production or development conventions in the endpoint

error_reporting(E_ALL); error_reporting (0); ini_set('display_errors', 'On');  // NOTE: uncomment this for debugging in case server delivers a 500 error

require_once ("../helpers/DanteDummyUserIdentity.php");
require_once ("../helpers/DanteDummyPageReference.php");
require_once ("../renderers/hideRenderer.php");

function EndpointLog ($text) {
  global $wgAllowVerbose;
  if (!$wgAllowVerbose) {return;}
  $fileName = "ENDPOINT_LOG";
  if($tmpFile = fopen( $fileName, 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  // NOTE: close immediatley after writing to ensure proper flush
  else {throw new Exception ("debugLog in danteEndpoint.php could not log"); }

  $fileSize = filesize ($fileName);
  if ($fileSize == false) { return; }
  if ($fileSize > 100000) {  $handle = fopen($fileName, 'w'); }  // truncate too long files
  }

function ParsifalLog ($text) {
  global $wgAllowVerbose;
  //if (!$wgAllowVerbose) {return;}
  $fileName = "../../Parsifal/LOGFILE";
  if($tmpFile = fopen( $fileName, 'a')) {fwrite($tmpFile, "DanteEndpoint: ".$text);  fclose($tmpFile);}  // NOTE: close immediatley after writing to ensure proper flush
  else {throw new Exception ("parsifalLog in danteEndpoint.php could not log"); }

  $fileSize = filesize ($fileName);
  if ($fileSize == false) { return; }
  if ($fileSize > 100000) {  $handle = fopen($fileName, 'w'); }  // truncate too long files
  }




class DanteEndpoint {


  const USE_STRING      = 1;
  const USE_FILE_NAME   = 2;
  const USE_FILE_HANDLE = 3;

  // instance variables which define the interface to the endpoint
  protected $stringContent;
  protected $fileName;
  protected $filePointer;

  // operational parameters
  protected $startTime;  

  // mandatory parameters (??)
  protected ?string   $userName;    // needed for constructing the user identity
  protected ?string   $pageName;    // needed for constructing the page reference  // TODO: not clear why we need this AND the title ??
  protected ?int      $ns;          // number of the namespace; needed for constructing the page reference
  protected ?string   $title;       // TODO: title name???ß of the page    // needed for constructing the page reference
  protected ?string   $dbkey;  // TODO: not clear if needed for paghe reference since currently we use NULL for it

  // optional parameters of the endpoint api
  protected bool      $hiding;



function __construct () {
  $this->startTime = microtime (true);
  
  // get API data from http headers (set in preview.js)
   $headers  = getallheaders();                   
  // EndpointLog ("\n Found headers: \n" . print_r ( $headers, true));
  $this->pickupDataFromArray ( $headers );

  // get API data from query portion of URL - would overwrite those from the headers
  // EndpointLog ("\nFound SERVER=" . print_r ($_SERVER, true));   
    // EndpointLog ("\n Query String is: ". print_r ($_SERVER['QUERY_STRING'], true));
    parse_str ($_SERVER['QUERY_STRING'], $parsed);
    // EndpointLog ("\nParsed Query String is " . print_r ($parsed, true));  
    $this->pickupDataFromArray ( $parsed );


}



// TODO: check what we inject as headers in previre.js !!!! - not all is used and / or needed any more !!!!!

  // given the array arr of keys and (string-typed) values, parse properties of this array into its place for this object
  private function pickupDataFromArray ( $arr ) {
    $this->userName            =  ( isset ($arr["Wiki-wgUserName"])             ?  $arr["Wiki-wgUserName"]                 : null ); 
    $this->ns                  =  intval (( isset ($arr["Wiki-wgNamespaceNumber"])      ?  $arr["Wiki-wgNamespaceNumber"]  : null )); 
    $this->pageName            =  ( isset ($arr["Wiki-wgPageName"])             ?  $arr["Wiki-wgPageName"]                 : null );     // full name of page, including localized namespace name, if namespace has a name (except 0) with spaces replaced by underscores. 
    $this->title               =  ( isset ($arr["Wiki-wgTitle"])                ?  $arr["Wiki-wgTitle"]                    : null );   // includes blanks, no underscores, no namespace

    $this->dbkey               =  ( isset ($arr["Wiki-dbkey"])                  ?  $arr["Wiki-dbkey"]                      : null ); 

//    $this->hiding              =  ( isset ($arr["Wiki-hiding"])                 ?   strcmp ($arr["Wiki-hiding"], "true")==0  :  false ); 
//    $this->sect              =  ( isset ($arr["sect"])                 ?   $arr["sect"] :  NULL ); 
//    if ($this->sect != NULL) {$this->sect = (int) $this->sect;}
  }



  private function printData () {
    EndpointLog ("DanteEndpoint.php sees the following headers:\n" );
    EndpointLog ("  userName:               " . $this->userName            . "\n" );
    EndpointLog ("  ns:                     " . $this->ns                  . "\n" );
    EndpointLog ("  pageName:               " . $this->pageName            . "\n" );
    EndpointLog ("  title:                  " . $this->title               . "\n" );
  }


// may be used by an endpoint to add further headers
protected function setResponseHeaders () {
  header("X-EndpointGenerated-Time-musec:"    . $this->startTime  );
  header("X-EndpointStartSending-Time-musec:" . microtime (true)  );
}


// this function prepares the content which the endpoint shall send back when given the input  $input  in the body
// this function may use the other fields and methods of this function
// this function is expected to be overwritten by inheritance
// the function returns the manner in which it prepared the contents for the client. It returns:
//   1  USE_STRING       caller should use the information in                   $this->stringContent
//   2  USE_FILE_NAME    caller should use the information in the file of name  $this->fileName
//   3  USE_FILE_HANDLE  caller should use the information in the file          $this->filePointer
//                       assuming filePointer is to an open file and will be closed as side-effect
//   THROWS in case of an error

  public function getContent ( ) {
    // EndpointLog ("DanteEndpoint: getContent\n");
    $this->stringContent = "Hello World: This function getContent is defined in danteEndpoint.php and should be overwritten by extending this class ";
    return 1;
  }


// function used to obtain the mime type generated by an endpoint; may be overridden by derived classes
protected function getMimeType () { return "text/html"; }



/* interface to the parser
 *   $text     text to be parsed
 *   $hiding   <hide>...</hide> blocks removed from the rendering   TODO: better: tags with the attribute hide shall be removed
 *
 */
public function parseText ( $text, $hiding, $section = NULL ) {

 // TODO: add ALL affecting parameters into cache key !!!!!!!!!!!!!!!!!!!
  $cacheKey   = md5 ($text);     
  $value      = apcu_fetch ( $cacheKey, $cacheHit);
  if ($cacheHit) {
    EndpointLog ("Cache hit on $cacheKey");
    return $value;}
  else {
     EndpointLog ("Cache miss on $cacheKey");
  }

  // get an instance of UserIdentity
  $userId  = new DanteDummyUserIdentity ( $this->userName );

  // get an instance of ParserOptions
  $options = new ParserOptions ( $userId );                 // let the parent class provide a user identity
  $options->setRemoveComments (false);                      // do not remove html comments in the preprocessing phase
  $options->setSuppressTOC (true);                          // do not generate TOC; will be deprecated in 1.42

  // get an instance of the parser
  $revid         = null;
  $mwServices    = MediaWiki\MediaWikiServices::getInstance();
  $parser        = $mwServices->getParserFactory()->create();

  $parser->danteTag    = "danteEndpoint";  // TODO: ????

  $parserOutput = NULL;
  $parsedText   = NULL;

  try {
//    $parser->setHook ( "hide", [ "HideRenderer", ($hiding ? 'renderHidden' : 'renderProminent') ] );        
    if ($hiding) { $parser->setHook ( "hide", [ "HideRenderer", 'renderHidden'    ] ); }
    else         { $parser->setHook ( "hide", [ "HideRenderer", 'renderProminent' ] ); }

    EndpointLog ("\nDanteEndpoint: Sees the section type: " . gettype ($section) . " and section value: ($section) \n");

//  $section = 0;
  
  if ( strcmp (gettype ($section), "integer") == 0  ) { 
    EndpointLog ("\n DanteEndpoint: Restricted section parsing requested for section=$section");
    $text = $parser->getSection ($text, $section, "NOT FOUND - see danteEndpoint.php"); 
    EndpointLog ("\n\n Sees: $text \n\n");
  }

  $pageRef = new DanteDummyPageReference ( 
    null,                             // wikiId for  getWikiId() 
    $this->ns,                        // namespace of the page
    null,                             // dbkey  ??????????? UNCLEAR see DanteDummy!
    $this->title,                     // title object ????
    $this->pageName                   // pagename ????
  );

  $parserOutput  = $parser->parse ( 
    $text,       // text we want to parse
    $pageRef, 
    $options,       // the ParserOptions object generated earlier
    true,           // lineStart:  should the text be treated as starting at the beginning of a line
    true,           // clearState: should we clear the parser state before parsing
    $revid
  );    

  // EndpointLog ("\nDanteEndpoint: Test has been parsed\n");

  // use a specific skin object for post treatment (requires internal skin name to be used)    TODO: make this selectable  // does this have an effect ???? TODO
 // $skinObject = MediaWiki\MediaWikiServices::getInstance()->getSkinFactory()->makeSkin ("cologneblue");
  $skinObject = MediaWiki\MediaWikiServices::getInstance()->getSkinFactory()->makeSkin ("vector");    // EndpointLog ("DanteEndpoint: parseText: did generate skin object\n");

  $parsedText =  $parserOutput->getText ( array ( 
     "allowTOC"               => false, 
     "injectTOC"              => false, 
     "enableSectionEditLinks" => false, 
     "skin"                   =>  $skinObject ,  // skin object for transforming section edit links
     "unwrap"                 => true,  
     "wrapperDivClass"        => "classname", 
     "absoluteURLs"           => true, 
     "includeDebugInfo"       => false 
  ) ); 

  }
     catch (\Exception $e) { EndpointLog ("***** DanteEndpoint: Parser: Caught exception:\n" );    $parsedText = "EXCEPTION: " . $e->__toString(); }
     catch(Throwable $t)   { EndpointLog ("***** DanteEndpoint: Parser: Caught Throwable:\n" );
                             EndpointLog ("DanteEndpoint Throwable is: " . $t->__toString()."\n");  }
     finally               { EndpointLog ("DanteEndpoint: in finally block\n");                     }

  EndpointLog ("DanteEndpoint: parseTexte will leave now\n");

  apcu_store ( $cacheKey, $parsedText, 1000 );

  return $parsedText;
}


// MediawikiEndpoint gets its contents from the post body
protected function getInput () {
  $body = file_get_contents("php://input");         // get the input; here: the raw body from the request
  $text = base64_decode ($body);                    // in an earlier version we used, unsuccessfully, some conversion, as in:   $body = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $body); 
  // EndpointLog ("MediawikiEndpoint: getInput sees text: ".$text . "\n");
  return $text;
}


public function execute () {

  try {
    $input    = $this->getInput();
   ///// $params   = getParams ();

    $parsedText = $this->parseText ( $input, false );
    $decor   = $this->decorate ($parsedText);

    header ("Content-Length: " . strlen ( $decor ) );
    header("Content-type:" . $this->getMimeType ());         // set Mime Type header 
    $this->setResponseHeaders ();                            // set other response headers
  }
   catch (\Exception $e) { EndpointLog ("***** DanteEndpoint: execute: Caught exception:\n" );    $decor = "EXCEPTION: " . $e->__toString(); }
   catch(Throwable $t)   { EndpointLog ("***** DanteEndpoint: execute: Caught Throwable:\n" );
                           EndpointLog ("DanteEndpoint Throwable is: " . $t->__toString()."\n");  }
   finally               { EndpointLog ("DanteEndpoint: in finally block of execute\n");                     }

  echo $decor;
}



// this is the main function of an endpoint
public function executeOLD () {
  $VERBOSE    = false; 

  $contentFlag = $this->getContent();

  // Content-length header
  if       ($contentFlag == 1) { header("Content-Length: " . strlen ($this->stringContent) ); } // strlen returns bytes not characters for UTF-8 stuff
  else if  ($contentFlag ==2 || $contentFlag == 3) {
    if (filesize($name) == 0) { throw new Exception ("DanteEndpoint content consists of a file of size zero at filename: " . $name); }
    header("Content-Length: " . filesize($name));
  }

  header("Content-type:" . $this->getMimeType ());         // set Mime Type header 
  $this->setResponseHeaders ();                            // set other response headers

  switch ( $contentFlag ) {
    case  DanteEndpoint::USE_STRING:  echo ($this->stringContent); break;
    case  DanteEndpoint::USE_FILE_HANDLE:
      $this->filePointer = fopen($this->fileName, 'rb');
      if ($this->filePointer == FALSE)         { throw new Exception ("DanteEndpoint could not open content file with filename: " . $this->fileName );  }
       // NO BREAK
    case  DanteEndpoint::USE_FILE_HANDLE: 
      fpassthru($this->filePointer); 
      fclose ($this->filePointer);
      break;
    default: throw new Exception ("Illegal content status received from converter");
  }

} // function


/*

// we need some classes on the body to better mimick the original styles of the skin; these here are hand-collected and experimental

decorate ($text, cssPaths: array('load.php?lang=en&modules=ext.Parsifal%2Cpygments%7Cskins.vector.styles.legacy&only=styles&skin=vector',
  'extensions/DantePresentations/endpoints/mediawikiEndpoint.css'), 
jsPaths: array('extensions/Parsifal/js/runtime.js'), bodyClasses: array('mw-body', 'mw-body-content',  'vector-body',  'mw-parser-output'), htmlClasses: array());

*/

public function decorate ( $text ) {
  $ret = "";
  $ret .= "<!DOCTYPE html>";
  $ret .= "<html lang='en' dir='ltr' classes='";
  $ret .= implode (' ', $this->getHtmlClasses() );
  $ret .= "'>";
  $ret .= "<head>";
  $ret .= "<meta charset='UTF-8'/>";
  foreach ( $this->getCssPaths() as &$value) { $ret .= ("<link rel='stylesheet' href='" . $value . "'>");  }
  foreach ($this->getJsPaths() as &$value)  { $ret .= ("<script src='" . $value . "'></script>") ;        }
  $ret .= "</head>";
  $ret .= "<body class='";
  $ret .= implode (' ', $this->getBodyClasses() );
  $ret .= "'>";
  $ret .= $text;
  $ret .= "</body></html>";
  return $ret;
}

public function getCssPaths ()    { return array(); }
public function getJsPaths ()     { return array(); }
public function getBodyClasses () { return array(); }
public function getHTMLClasses () {return array (); }


  
} // class


class DanteConfig implements Config {
  public function get( $name ) { EndpointLog ("DanteConfig was queried for:     " .$name. "\n");   return "";}
  public function has( $name ) { EndpointLog ("DanteConfig was asked if it had: " .$name. "\n");   return false;}
}