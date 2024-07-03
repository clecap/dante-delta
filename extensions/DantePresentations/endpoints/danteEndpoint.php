<?php

/*! 
 *  \author   Clemens H. Cap
 *  \brief    Bundle the static and job specific functionalities. Represent a Job for storing a Snippet in NS_SNIP
 */
    


// need the following two lines to obtain reasonable errors from the endpoint instead of only 500 er status from webserver  // TODO: adjust this for production !!!



require_once ("../../../includes/WebStart.php");


require_once ("../../../DanteSettings-used.php");  // also needed to pick up production or development conventions in the endpoint

//error_reporting(E_ALL);
//error_reporting (0);
//ini_set('display_errors', 'On');


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

  protected $stringContent;
  protected $fileName;
  protected $filePointer;

  protected $startTime;  

  protected $options;                 // array which is collecting options for displaying at this endpoint


  // information obtained from the header
  // the value of null means that no information has been provided in the header
  protected ?string   $userName;
  protected $sect;

  protected ?int      $userId;                             // 0 if anonymous
  protected  ?int     $ns;
  protected ?string   $nsName;
  protected ?string   $titleText;
  protected bool      $hiding;
  protected bool      $hh;
  protected float     $transformScale;

  protected ?string   $dbkey;


  // TODO: looks like the following is either not needed or secudnary constructible ??
  protected ?string   $pageName;
  protected ?string   $title;
  protected ?string   $pageContentLanguage;

  // certain service objects we might need in our endpoints
  protected ?MediaWiki\User\UserIdentity    $userIdentity = null;
  protected ?MediaWiki\Page\PageReference  $pageReference = null;

  function __construct () {
    $this->startTime = microtime (true);
    $this->options   = array();
    EndpointLog ("\nConstructing DanteEndpoint---------------------\n");
    self::dataFromHeader ();    // get data from http headers (might be set in preview.js)
    self::dataFromQuery  ();    // get data from query string (overwrite data from headers)

    // EndpointLog ("In constructur hiding = ". print_r ($this->hiding, true) . "\n");

    // EndpointLog ("\nDanteEndpoint constructed ---------------------\n");
  }


  // header values are set in preview.js
  // pick up headers in the http header and parse relevant information into this object
  private function dataFromHeader () {
    $headers  = getallheaders();                                 // additional headers get set in preview.js 
    // EndpointLog ("\n Found headers: \n" . print_r ( $headers, true));
    $this->pickupDataFromArray ( $headers );
  }

  // pick up the query string from the URL and parse the information into this object
  private function dataFromQuery () {
    // EndpointLog ("\nFound SERVER=" . print_r ($_SERVER, true));   
    // EndpointLog ("\n Query String is: ". print_r ($_SERVER['QUERY_STRING'], true));
    parse_str ($_SERVER['QUERY_STRING'], $parsed);
    // EndpointLog ("\nParsed Query String is " . print_r ($parsed, true));  
    $this->pickupDataFromArray ( $parsed );
  }

  private function dataFromBody () {}

  private function dataFromCLI () {}


  // given the array arr of keys and (string-typed) values, parse properties of this array into its place for this object
  private function pickupDataFromArray ( $arr ) {
    $this->userName            =  ( isset ($arr["Wiki-wgUserName"])             ?  $arr["Wiki-wgUserName"]                 : null ); 
    $this->userId              =  ( isset ($arr["Wiki-wgUserId"])               ?  $arr["Wiki-wgUserId"]                   : null );  
    $this->ns                  =  intval (( isset ($arr["Wiki-wgNamespaceNumber"])      ?  $arr["Wiki-wgNamespaceNumber"]  : null )); 
    $this->nsName              =  ( isset ($arr["Wiki-wgNamespaceName"])        ? $arr["Wiki-wgNamespaceName"]              : "");
    $this->pageName            =  ( isset ($arr["Wiki-wgPageName"])             ?  $arr["Wiki-wgPageName"]                 : null );     // full name of page, including localized namespace name, if namespace has a name (except 0) with spaces replaced by underscores. 
    $this->title               =  ( isset ($arr["Wiki-wgTitle"])                ?  $arr["Wiki-wgTitle"]                    : null );   // includes blanks, no underscores, no namespace
    $this->pageContentLanguage =  ( isset ($arr["Wiki-wpPageContentLanguage"])  ?  $arr["Wiki-wpPageContentLanguage"]      : null ); 
    $this->dbkey               =  ( isset ($arr["Wiki-dbkey"])                  ?  $arr["Wiki-dbkey"]                      : null ); 
    $this->titleText           =  ( isset ($arr["Wiki-titleText"])              ?  $arr["Wiki-titleText"]                      : null ); 
    $this->hiding              =  ( isset ($arr["Wiki-hiding"])                 ?   strcmp ($arr["Wiki-hiding"], "true")==0  :  false ); 

    $this->sect              =  ( isset ($arr["sect"])                 ?   $arr["sect"] :  NULL ); 
    if ($this->sect != NULL) {$this->sect = (int) $this->sect;}


   $this->hh              =  ( isset ($arr["hh"])                 ?   strcmp ($arr["hh"], "true")==0  :  false );   // hh: header hiding


  //    $this->zoom          = "100";  // bais page size in percent
  $this->transformScale = 1;


// TODO: do a sanity check (against injection attacks)

  }





  private function printData () {
    EndpointLog ("DanteEndpoint.php sees the following headers:\n" );
    EndpointLog ("  userName:               " . $this->userName            . "\n" );
    EndpointLog ("  userId:                 " . $this->userId              . "\n" );
    EndpointLog ("  ns:                     " . $this->ns                  . "\n" );
    EndpointLog ("  pageName:               " . $this->pageName            . "\n" );
    EndpointLog ("  title:                  " . $this->title               . "\n" );
    EndpointLog ("  pageContentLanguage:    " . $this->pageContentLanguage . "\n" );
  }


  // TODO: improve this by injecting values from the header !!
  public function getPageReference () { 
    if ( $this->pageReference === null ) { $this->pageReference = new DanteDummyPageReference ( null, $this->ns, null, $this->title, $this->pageName);}   
    return $this->pageReference; }   // ( $wikiId, $ns, $dbkey, $title, $pageName )


  public function getUserIdentity () { 
    // EndpointLog ("DanteEndpoint: getUserIdentity entered\n");
    if ( $this->userIdentity === null ) {
      // EndpointLog ("DanteEndpoint: getUserIdentity will generate new userIdentity\n");
      $this->userIdentity = new DanteDummyUserIdentity ( $this->userName );}  
    // EndpointLog ("DanteEndpoint: getUserIdentity will leave\n");
    return $this->userIdentity; 
  }


  public function ensureEnvironment ()  {}   // prepare environment required for the converter


  public function getRequestHeaders ()  {   // obtain and sanitize further parameter values from the http header and inject into object


  }


  public function setResponseHeaders () {
    // EndpointLog ("DanteEndpoint: setResponseHeaders entered\n");
    header("X-EndpointGenerated-Time-musec:" . $this->startTime);
    header("X-EndpointStartSending-Time-musec:" . microtime (true));
    // EndpointLog ("DanteEndpoint: setResponseHeaders will leave now\n");
  }   // set additional response headers we might need


// this function prepares the content which the endpoint shall send back when given the input  $input  in the body
// this function may use the other fields and methods of this function
// this function is expected to be overwritten by inheritance
// the function returns the manner in which it prepared the contents for the client. It returns:
//   1  USE_STRING       caller should use the information in                   $this->stringContent
//   2  USE_FILE_NAME    caller should use the information in the file of name  $this->fileName
//   3  USE_FILE_HANDLE  caller should use the information in the file          $this->filePointer
//                       assumes filePointer is to an open file and will be closed as side-effect
//   THROWS in case of an error

  public function getContent ( ) {
    // EndpointLog ("DanteEndpoint: getContent\n");
    $this->stringContent = "Hello World: This function getContent is defined in danteEndpoint.php and should be overwritten by extending this class ";
    return 1;
  }


  public function getMimeType () { 
    // EndpointLog ("DanteEndpoint: getMimeType\n");
    return "text/html";}





/* interface to the parser
 *   $text     text to be parsed
 *   $hiding   <hide>...</hide> blocks removed from the rendering 
 *
 */
public function parseText ( $text, $hiding, $section = NULL ) {

  // EndpointLog ("DanteEndpoint: parseText entered, local variable hiding is " . ($hiding ? "true" : "false") . "\n");
  $userId = $this->getUserIdentity();
  //EndpointLog ("DanteEndpoint: parseText got userId: " . print_r ($userId, true) . "\n");  // print_r of $options leads to memory exhaustion.
  $options = new ParserOptions ( $userId );        // let the parent class provide a user identity
  //EndpointLog ("DanteEndpoint: parseText got parse options:  \n");  // print_r of $options leads to memory exhaustion.

  $options->setRemoveComments (false);

  // $this->setOption( 'suppressTOC', true );  // in 1.39  $options->setSuppressTOC (true);

  //EndpointLog ("DanteEndpoint: parseText will generate instance of parser\n");

  // obtain an instance of the parser
  $lineStart     = true;
  $clearState    = true;
  $revid         = null;
  $mwServices    = MediaWiki\MediaWikiServices::getInstance();
  // ParsifalLog ("*** DanteEndpoint: parseText will now build a fresh parser \n");
  $parser        = $mwServices->getParserFactory()->create();
  $parser->danteTag    = "danteEndpoint"; 
  // EndpointLog ("DanteEndpoint: parseText having instance of parser\n");
  // ParsifalLog ("*** DanteEndpoint: parseText just obtained a parser\n");

  $pageRef = $this->getPageReference();
  // ParsifalLog ("*** DanteEndpoint: parseText just got a page reference\n");

  // EndpointLog ("DanteEndpoint: parseText having instance of page reference\n");
  // EndpointLog ("   PageReference is: ". print_r ($pageRef, true). "\n");

  // EndpointLog ("DanteEndpoint: parseText: text=" . $text. "\n");

  $parserOutput = NULL;  // must define this outside of the try block
  $parsedText   = NULL;  // must define this outside of the try block

  try { 
//    $parser->setHook ( "hide", [ "HideRenderer", ($hiding ? 'renderHidden' : 'renderProminent') ] );        
    if ($hiding) { $parser->setHook ( "hide", [ "HideRenderer", 'renderHidden'    ] ); }
    else         { $parser->setHook ( "hide", [ "HideRenderer", 'renderProminent' ] ); }

    EndpointLog ("DanteEndpoint: Will start to parse now\n");
    EndpointLog ("DanteEndpoint: Sees the section: " . $section . "\n");
    EndpointLog ("DanteEndpoint: Sees the section type: " . gettype ($section) . "\n");

  if ( strcmp (gettype ($section), "integer") == 0  ) { 
    EndpointLog ("DanteEndpoint: In restricted section parsing");
    $text = $parser->getSection ($text, $section, "NOT FOUND - see danteEndpoint.php"); 
  }

  ParsifalLog ("*** DanteEndpoint: parseText: will start to parse\n");
  $parserOutput  = $parser->parse ( $text, $pageRef, $options, $lineStart, $clearState, $revid); 
  EndpointLog ("\nDanteEndpoint: Test has been parsed\n");
  ParsifalLog ("*** DanteEndpoint: parseText has completed parsing\n");

  // use a specific skin object for post treatment (requires internal skin name to be used)    TODO: make this selectable  // does thois have an effect ???? TODO
 // $skinObject = MediaWiki\MediaWikiServices::getInstance()->getSkinFactory()->makeSkin ("cologneblue");

  EndpointLog ("DanteEndpoint: parseText: will generate skin object\n");
  $skinObject = MediaWiki\MediaWikiServices::getInstance()->getSkinFactory()->makeSkin ("vector");
  EndpointLog ("DanteEndpoint: parseText: did generate skin object\n");

  $parsedText =  $parserOutput->getText ( array ( 
     "allowTOC"               => false, 
     "injectTOC"              => false, 
     "enableSectionEditLinks" => false, 
     "skin"                   =>  $skinObject ,  // skin object for transforming section edit links
     "unwrap"                 => true,  "wrapperDivClass" => "classname", "absoluteURLs" => true, "includeDebugInfo" => false ) ); 

  }
     catch (\Exception $e) { EndpointLog ("***** DanteEndpoint: Parser: Caught exception:\n" );    $parsedText = "EXCEPTION: " . $e->__toString(); }
     catch(Throwable $t)   { EndpointLog ("***** DanteEndpoint: Parser: Caught Throwable:\n" );
                             EndpointLog ("DanteEndpoint Throwable is: " . $t->__toString()."\n");  }
     finally               { EndpointLog ("DanteEndpoint: in finally block\n");                     }

  EndpointLog ("DanteEndpoint: parseTexte will leave now\n");

  return $parsedText;
}




public function execute () {
  $VERBOSE    = false; 

  $this->ensureEnvironment ();
  $this->getRequestHeaders ();

  // get input from caller
//  $body = file_get_contents("php://input");         // get the input; here: the raw body from the request
//  $body = base64_decode ($body);                    // in an earlier version we used, unsuccessfully, some conversion, as in:   $body = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $body); 
//  $contentFlag = $this->getContent ($body);         // prepare content and get info where it is available; may return error info; throws in case of irrecoverable error 

// NEW
  $contentFlag = $this->getContent();

  // Content-length header
  if ($contentFlag == 1) { header("Content-Length: " . strlen ($this->stringContent) ); } // strlen returns bytes not characters for UTF-8 stuff
  else if ($contentFlag ==2 || $contentFlag == 3) {
    if (filesize($name) == 0) { throw new Exception ("DanteEndpoint content consists of a file of size zero at filename: " . $name); }
    header("Content-Length: " . filesize($name));
  }
 
  header("Content-type:" . $this->getMimeType ());         // set Mime Type header 
  $this->setResponseHeaders ();                     // set other response headers

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
  
} // class




class DanteConfig implements Config {
  public function get( $name ) { EndpointLog ("DanteConfig was queried for:     " .$name. "\n");   return "";}
  public function has( $name ) { EndpointLog ("DanteConfig was asked if it had: " .$name. "\n");   return false;}
}