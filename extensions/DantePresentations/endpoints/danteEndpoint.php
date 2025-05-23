<?php

/*! 
 *  \author   Clemens H. Cap
 *  \brief    Bundle the static and job specific functionalities. Represent a Job for storing a Snippet in NS_SNIP
 */

require_once ("../../../includes/WebStart.php");
require_once ("../../../DanteSettings-used.php");  // also needed to pick up production or development conventions in the endpoint


error_reporting(E_ALL); error_reporting (0); ini_set('display_errors', 'On');  // NOTE: uncomment this for debugging in case server delivers a 500 error

require_once ("../helpers/DanteDummyPageReference.php");
require_once ("../renderers/hideRenderer.php");

use MediaWiki\MediaWikiServices;

class DanteEndpoint { 

  // instance variables which define the interface to the endpoint
  protected $stringContent;
  protected $fileName;
  protected $filePointer;

  // operational parameters
  protected $startTime;                               // microtime when this object was constructed

  // mandatory parameters (??)
  protected ?string   $pageName = "uninitialized";    // needed for constructing the page reference  // TODO: not clear why we need this AND the title ??
  protected ?int      $ns = null;                     // number of the namespace; needed for constructing the page reference
  protected ?string   $title = "uninitialized";       // TODO: title name???ß of the page    // needed for constructing the page reference
  protected ?string   $dbkey = "uninitialized";       // TODO: not clear if needed for paghe reference since currently we use NULL for it

  // derived entity 
  protected ?MediaWiki\User\UserIdentity $userId;     // object with interface type UserIdentity

  // dependant entities
  protected ?string   $nsName = null;    // TODO: see also above: maybe rather null than "uniniialized" ?????

  // optional parameters of the endpoint api
  protected bool      $hiding;
  protected ?string   $curRevisionId;
  protected float     $scale = 1;


  // additional information
  protected $parserOutput = null;        // keeps the parserOutput object of the last parser run (will be used in other endpoints where we need mor info on the parse)

  protected $caching = true;             // in some scenarios we must turn off caching !


function __construct () {
  $this->startTime = microtime (true);
  
  // pickup API data first from headers (we set them for example in preview.js)
  $headers  = getallheaders();                                           // get all http headers
  $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);      // Normalize the header name by converting all keys to lowercase as some browsers do this on their own
  $this->pickupDataFromArray ( $normalizedHeaders );                     // pick up data
  //self::Log ("\n Found headers: \n" . print_r ( $headers, true));    
  // self::Log ("\n Found normalized headers: \n" . print_r ( $normalizedHeaders, true)); // DEBUG

  parse_str ($_SERVER['QUERY_STRING'], $query);                          // get API data from query portion of URL - if both header and query are used: query overwrites the header
  $normalizedQuery = array_change_key_case($query, CASE_LOWER);          // normalize
  $this->pickupDataFromArray ( $normalizedQuery );                       // pick up data
  // self::Log ("\nParsed Query String is " . print_r ($query, true));    // DEBUG
  // self::Log ("\nParsed Query String is " . print_r ($normalizedQuery, true)); // DEBUG

    $this->userId = User::newFromSession();  // derive userId object from the current session into which we might be logged in (or not)

  if ( isset ($this->ns) ) $this->nsName = MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalName ( $this->ns );
}


public function Log ($text) {
  global $wgAllowVerbose;
  if (!$wgAllowVerbose) {return;}
  $fileName = "ENDPOINT_LOG";
  $text = "\n*** " . get_called_class() . " says:\n".$text."\n---------\n";
  if($tmpFile = fopen( $fileName, 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  // NOTE: close immediatley after writing to ensure proper flush
  else {throw new Exception ("debugLog in danteEndpoint.php could not log"); }

  $fileSize = filesize ($fileName);
  if ($fileSize == false) { return; }
  if ($fileSize > 100000) {  $handle = fopen($fileName, 'w'); }  // truncate too long files
}


// given the array arr of keys and (string-typed) values, parse properties of this array into its place for this object
// these values are set in DantePresentations.php or as header elements in preview.js or similar
// TODO: this is not yet completely harmonized between the different places which use these fields - some have more some less
// preview.js und DantePresentations.php must be harmonized TODO: make a common php file for this !!
private function pickupDataFromArray ( $arr ) {
  // self::Log ("\n Pickup function sees: \n" . print_r ( $arr, true));

  if ( isset ( $arr["nocache"] ) )                 $this->caching = false;
  if ( isset ( $arr["wiki-wgnamespacenumber"] ) )  $this->ns        =  intval ( $arr["wiki-wgnamespacenumber"] ) ; 
  if ( isset ( $arr["wiki-wgpagename"] ) )         $this->pageName  =  $arr["wiki-wgpagename"];                     // full name of page, including localized namespace name, if namespace has a name (except 0) with spaces replaced by underscores. 
  if ( isset ( $arr["wiki-wgtitle"] ) )            $this->title     =  $arr["wiki-wgtitle"];                        // includes blanks, no underscores, no namespace
  if ( isset ( $arr["wiki-dbkey"] ) )              $this->dbkey     =  $arr["wiki-dbkey"]; 
  if ( isset ( $arr["wiki-wgCurRevisionId"] ) )    $this->curRevisionId     =  $arr["wiki-wgCurRevisionId"]; 

  if ( isset ( $arr["scale"] ) )                   $this->scale     =  floatval  ( $arr["scale"] ); 

//    $this->hiding              =  ( isset ($arr["Wiki-hiding"])                 ?   strcmp ($arr["Wiki-hiding"], "true")==0  :  false ); 
//    $this->sect              =  ( isset ($arr["sect"])                 ?   $arr["sect"] :  NULL ); 
//    if ($this->sect != NULL) {$this->sect = (int) $this->sect;}


}


protected function dumpStatus () {

  $text = "\nDUMPING STATUS:\n";
  $text .= "  caching=" . $this->caching . "\n";
  $text .= "  dbkey="   . $this->dbkey . "\n";
  $text .= "  ns="      . $this->ns . "\n";
  $text .= "  title="  . $this->title . "\n";

  $text .= "\n\n";
  return $text;
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

//                       assuming filePointer is to an open file and will be closed as side-effect
//   THROWS in case of an error

  public function getContent ( ) {
    // self::Log ("DanteEndpoint: getContent\n");
    $this->stringContent = "Hello World: This function getContent is defined in danteEndpoint.php and should be overwritten by extending this class ";
    return 1;
  }


// function used to obtain the mime type generated by an endpoint; may be overridden by derived classes
protected function getMimeType () { return "text/html"; }


/* interface to the parser
 *   $text     text to be parsed
 *   $hiding   <hide>...</hide> blocks removed from the rendering   TODO: better: tags with the attribute hide shall be removed
 *
 *   $removeTags  array of tags which are removed  eg: for translation:  array ("amstex")
 */
public function parseText ( $text, $hiding, $section = NULL, $removeTags = array() ) {

  $cacheKey = "";  // need to define outside of { } since used below as well
  if ( $this->caching ) {
    // TODO: add ALL affecting parameters into cache key !!!!!!!!!!!!!!!!!!!
    $cacheKey   = md5 ($text . ($hiding ? "true": "false") . $section. print_r ($removeTags, true)) ;     
    $value      = apcu_fetch ( $cacheKey, $cacheHit);
    if ($cacheHit) {
      self::Log ("Cache hit on $cacheKey");
      return $value;}
    else {
       self::Log ("Cache miss on $cacheKey");
    }
  }

  // get an instance of ParserOptions
  $options = new ParserOptions ( $this->userId );           // let the parent class provide a user identity
  $options->setRemoveComments (false);                      // do not remove html comments in the preprocessing phase

  //  $options->setSuppressTOC (true);                          // do not generate TOC; will be deprecated in 1.42 
  // CAVE: need TOC info to be set or we do not get section information in the parser output!

  // get an instance of the parser
  $revid         = null;
  $mwServices    = MediaWiki\MediaWikiServices::getInstance();
  $parser        = $mwServices->getParserFactory()->create();

  $parser->danteTag    = "danteEndpoint";  // TODO: ????

  $parsedText   = NULL;

  try {
  //    $parser->setHook ( "hide", [ "HideRenderer", ($hiding ? 'renderHidden' : 'renderProminent') ] );        
    if ($hiding) { $parser->setHook ( "hide", [ "HideRenderer", 'renderHidden'    ] ); }
    else         { $parser->setHook ( "hide", [ "HideRenderer", 'renderProminent' ] ); }

    foreach ($removeTags as $tag) { $parser->setHook ( $tag, [ "HideRenderer", 'renderHidden' ] );}

  //  self::Log ("\nDanteEndpoint: Sees the section type: " . gettype ($section) . " and section value: ($section) \n");

  
  if ( $section !== NULL  ) { 
  //  self::Log ("\n DanteEndpoint: Restricted section parsing requested for section=$section");
    $text = $parser->getSection ($text, $section, "NOT FOUND - see danteEndpoint.php"); 
  //  self::Log ("\n\n DanteEndpoint sees: $text \n\n");
  }

  $pageRef = new DanteDummyPageReference ( 
    null,                             // wikiId for  getWikiId() 
    $this->ns,                        // namespace of the page
    null,                             // dbkey  ??????????? UNCLEAR see DanteDummy!
    $this->title,                     // title object ????
    $this->pageName                   // pagename ????
  );

  $this->parserOutput  = $parser->parse ( 
    $text,           // text we want to parse
    $pageRef,        
    $options,       // the ParserOptions object generated earlier
    true,           // lineStart:  should the text be treated as starting at the beginning of a line
    true,           // clearState: should we clear the parser state before parsing
    $revid
  );    

  //$sec = $this->parserOutput->getSections();
  //self::Log ("\n-----------DanteEndpoint: ".print_r ($sec, true)."\n");

  // use a specific skin object for post treatment (requires internal skin name to be used)    TODO: make this selectable  // does this have an effect ???? TODO
  // $skinObject = MediaWiki\MediaWikiServices::getInstance()->getSkinFactory()->makeSkin ("cologneblue");
  $skinObject = MediaWiki\MediaWikiServices::getInstance()->getSkinFactory()->makeSkin ("vector");    // self::Log ("DanteEndpoint: parseText: did generate skin object\n");

  $parsedText =  $this->parserOutput->getText ( array ( 
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
     catch (\Exception $e) { self::Log ("***** DanteEndpoint: Parser: Caught exception:\n" );     $parsedText = "EXCEPTION: " . $e->__toString(); }
     catch(Throwable $t)   { self::Log ("***** DanteEndpoint: Parser: Caught Throwable:\n" );
                             self::Log ("DanteEndpoint Throwable is: " . $t->__toString()."\n");  $parsedText = "THROWABLE: " . $t->__toString()."\n";}
     finally               { //self::Log ("DanteEndpoint: in finally block\n");                 
      }

  // self::Log ("DanteEndpoint: parseTexte will leave now\n");
  if ( $this->caching ) { apcu_store ( $cacheKey, $parsedText, 1000 ); }
  if ($parsedText == null) { $parsedText = "Parser for preview returned null instead of string.";}  // to prevent 

  return $parsedText;
}

// get input for this endpoint; default is: get content from the post body
protected function getInput () {
  $body = file_get_contents("php://input");         // get the input; here: the raw body from the request
  $text = base64_decode ($body);                    // in an earlier version we used, unsuccessfully, some conversion, as in:   $body = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $body); 
  // self::Log ("danteEndpoint: getInput sees text: ".$text . "\n");
  return $text;
}



protected function getInputNSKey () {
  // self::log ("Title=" . $this->title."\n");  self::log ("Dbkey=" . $this->dbkey."\n");  self::log ("NS=" . $this->ns."\n");  self::log ("NS-NAME=" . $this->nsName."\n");

  $searchKey =  $this->nsName . ":".  $this->dbkey;
  $title      =  Title::newFromDBkey ( $searchKey );  // TODO: lacks optional interwiki prefix   -   see documentaiton of class Title
  if ($title === null) {
    throw new Exception ("ShowEndpoint: could not generate title from dbkey: (" . $this->dbkey . ")\n");
  }

  $wikipage    = new WikiPage ($title);                              // get the WikiPage for that title
  $contob      = $wikipage->getContent();                            // and obtain the content object for that
  $contenttext = ContentHandler::getContentText( $contob );
  return $contenttext;
}




protected function getInputTitle () {
  $pageIdentity      =  Title::newFromDBkey ( $this->title );
  $wikipage    = new WikiPage ($pageIdentity);                              // get the WikiPage for that title
  $contob      = $wikipage->getContent();                            // and obtain the content object for that
  $contenttext = ContentHandler::getContentText( $contob );
  return $contenttext;
}






// This is the core function for obtaining the output of a (generic) endpoint
public function process () : string {
  $input         = $this->getInput();
  $parsedText    = $this->parseText ( $input, false );
  $decoratedText = $this->decorate ($parsedText);
  return $decoratedText;
}

// This is the core function for executing an endpoint
// It is written in a way that we (should) not need to overwrite it often
public function execute () {
  try {
    $decoratedText = $this->process();
    self::Log ("***** DanteEndpoint: execute sees :\n" . $decoratedText ); 
  }
  catch (\Exception $e) { self::Log ("***** DanteEndpoint: execute: Caught exception:\n" );    $decoratedText = "<pre>EXCEPTION: " . $e->__toString(). "</pre>"; }
  catch(Throwable $t)   { self::Log ("***** DanteEndpoint: execute: Caught Throwable:\n" );
                          self::Log ("DanteEndpoint Throwable is: " . $t->__toString()."\n");  $decoratedText = "<pre>THROWABLE: " . $t->__toString()."</pre>"; }
  finally               { self::Log ("DanteEndpoint: in finally block of execute\n");                     
                          self::Log ( self::dumpStatus() );
                        }

  header ("Content-Length: " . strlen ( $decoratedText ) );
  header ("Content-type:" . $this->getMimeType ());         // set Mime Type header 
  header ("X-Debug-Dante:" . "START: " . $decoratedText. ":END"); 
  $this->setResponseHeaders ();                             // set other response headers
  echo $decoratedText;
}


/*

// we need some classes on the body to better mimick the original styles of the skin; these here are hand-collected and experimental

decorate ($text, cssPaths: array('load.php?lang=en&modules=ext.Parsifal%2Cpygments%7Cskins.vector.styles.legacy&only=styles&skin=vector',
  'extensions/DantePresentations/endpoints/mediawikiEndpoint.css'), 
jsPaths: array('extensions/Parsifal/js/runtime.js'), bodyClasses: array('mw-body', 'mw-body-content',  'vector-body',  'mw-parser-output'), htmlClasses: array());

*/


public function decorate ( $text ) {
  $ret  = "<!DOCTYPE html>";
  $ret .= "<html lang='en' dir='ltr' classes='" .implode (' ', $this->getHtmlClasses()) . "'>";
  $ret .= "<head classes='"  .implode (' ', $this->getHeadClasses()) . "'>";
  $ret .= "<meta charset='UTF-8'/>";
  $ret .= '<meta name="generator" content="DanteWiki">';
  $ret .= $this->getHeadText();
  foreach ( $this->getCssPaths()      as &$value)  { $ret .= ("<link rel='stylesheet' href='" . $value . "'>");  }
  foreach ( $this->getAsyncJsPaths()  as &$value)  { $ret .= ("<script async src='" . $value . "'></script>") ;        }
  foreach ( $this->getJsPaths()       as &$value)  { $ret .= ("<script src='" . $value . "'></script>") ;        }
  $ret .= "</head>";
  $ret .= "<body class='";
  $ret .= implode (' ', $this->getBodyClasses() );
  $ret .= "'>";
  $ret .= $this->decorateBody ($text);
  $ret .= "</body></html>";
  return $ret;
}


public function getCssPaths ()                         { return array (); }
public function getJsPaths ()                          { return array (); }
public function getAsyncJsPaths ()                     { return array (); }
public function getHTMLClasses ()                      { return array (); }
public function getHeadClasses ()                      { return array (); }
public function getBodyClasses ()                      { return array (); }
public function getHeadText ()              : string   { return ""; }
public function decorateBody (string $text) : string   { return $text; }

} // class



class DanteConfig implements Config {
  public function get( $name ) { self::Log ("DanteConfig was queried for:     " .$name. "\n");   return "";}
  public function has( $name ) { self::Log ("DanteConfig was asked if it had: " .$name. "\n");   return false;}
}