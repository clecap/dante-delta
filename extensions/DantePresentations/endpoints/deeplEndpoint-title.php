<?php

/** This endpoint ppicks up a title and translates the article beloning to the title */

// NOTE: Debugging this: Apache log has error messages if we get no result by direct call to endpoint

require '../../../vendor/autoload.php';

error_reporting(E_ALL); ini_set('display_errors', 'On'); // uncomment to obtain reasonable errors from the endpoint instead of only 500 er status from webserver

require_once ("danteEndpoint.php");

use DeepL\Translator;

use MediaWiki\Revision\SlotRecord;

class DeeplEndpoint_Title extends DanteEndpoint {

const DEEPL_OPTIONS =
['split_sentences'       => 'nonewlines',
  'preserve_formatting'  => 'false',
  'formality'            => 'prefer_more',
// glossary_id
  'tag_handling' => 'xml',
  "non_splitting_tags" => "",  // tags which do not break text into seperately translated portions
  "splitting_tags"     => "",   // tags which do break text into seperately translated portions
  "ignore_tags"        => "",  // text containing these elements is not translated
  'send_platform_info' => false,
  'max_retries'        => 5,
  'timeout'            => 15.0
];

private $translator;  // caches the translator object for all translations to be done in thios endpoint

private $status = array();         // array of stati; maps a language code to a status message

private $hasError = false;    // flag which checks if an error occured in any of the different language codes



function __construct () { 
  parent::__construct(); 
  $deeplApiKey      = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption ( $this->userId, 'dante-deepl-apikey' );
  $this->translator = new \DeepL\Translator($deeplApiKey);  

  // TODO: error handling when api key missing or wrong !
}


/*
public function getContent ( ) {
  EndpointLog ("DeeplEndpoint-title:  getContent entered \n"); 

  $titleText      =  "Algebra";
  $targetLanguage =  "fr"; // TODO

  $title = Title::newFromText( $titleText );              // Create a Title object from the text of the title
  if ( $title instanceof Title && $title->exists() ) {    // Get the WikiPage object for the given title
    $wikiPage = WikiPage::factory( $title );
    $content = $wikiPage->getContent();                   // Get the wikitext content of the page
    if ( $content instanceof TextContent ) { $wikiText = $content->getText(); } 
    else {$wikiText = 'Content is not text';}
  } 
  else {  EndpointLog ("DeeplEndpoint-title: ERROR: title did not exist: " . $title . "\n"); }

  $translatedWikiText = translate ( $wikiText );

//  $translatedTitleText = 

  $translatedTitleObject = Title::makeTitle( NS_TRANSLATED, $translatedTitleText );  // Create a Title object with the specified namespace and title
  if ( $translatedTitleObject instanceof Title ) {
    $translatedWikiPage = WikiPage::factory( $translatedTitleObject );    // Create a WikiPage object for the given title
    $translatedContent = ContentHandler::makeContent( $translatedWikiText, $translatedTitleOBject );    // Create a Content object with the given text
    $user = RequestContext::getMain()->getUser();    // Get the current user
    $summary = "Translation of page by deepl";       // Edit summary
    $translatedWikiPage->doEditContent( $translatedContent, $summary, 0, false, $user );    // Save the page with the provided content
  } 
  else {
    throw new MWException( "Invalid title for the new page." );
  }

  EndpointLog ("DeeplEndpoint-title: sees translation: " . $translatedWikiText);
  return 1;
}
*/


// store a page under title $title with content $content
// NOTE: a cat file | php ... did not work, it looks like the pipe construction was not working correctly somehow.
private function storePage ( string $title, string $content, string $index) {
  EndpointLog ("Translating $title \n");
  try {
    $cmd = 'php ../php/pageCreator.php --title "' .$title .'"';
    $proc=proc_open($cmd, array(0=>array('pipe', 'r'), 1=>array('pipe', 'w'), 2=>array('pipe', 'w')), $pipes);
    fwrite($pipes[0], $content);
    fclose($pipes[0]);
    $output=stream_get_contents($pipes[1]);fclose($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
    $rtn=proc_close($proc);

    if ($output === null) { throw new Exception ('Failed to execute command or no output produced'); }
    $this->status[$index] = "OK"; }
  catch (Exception $e) { $this->status[$index] = $e->getMessage(); $this->hasError = true; }
  finally { }
}





public function runIt () {
  $langs = ["de", "en-US", "fr"];     // array of languages

  $titleObject = Title::newFromText( $this->title );              // Create a Title object from the text of the title
  if ( $titleObject instanceof Title ) {                          // Get the WikiPage object for the given title
    $wikiPage = WikiPage::factory( $titleObject );
    $content = $wikiPage->getContent();                           // Get the wikitext content of the page
    if ( $content instanceof TextContent ) { $wikiText = $content->getText(); } 
    else {$wikiText = 'Content is not text';}
  } 
  else {  
    EndpointLog ("DeeplEndpoint-title: ERROR: title did not exist: " . $titleObject . "\n"); 
    $wikiText = "title did not exist";}  // TODO error 

  // do some necessary transformations on wikiText before submitting it to translations
  // $newText = $wikiText;
  $newText = $this->parseText ($wikiText, false, NULL, array ("amstex", "beamer", "tex") );

  foreach ($langs as &$targetLanguage) {  // iterate over all languages
    try {
      $result = $this->translator->translateText( $newText, null, $targetLanguage, DeeplEndpoint_Title::DEEPL_OPTIONS); // source, target;  may throw
      $translatedWikiText = $result->text;
      EndpointLog ("\n\nTranslated wiki text is " . print_r ($translatedWikiText, true) . "\n");

      $translatedTitleText = $this->title . "/" . $targetLanguage;
      EndpointLog ("DeeplEndpoint-title: title under which the translated text will be stored: " . $translatedTitleText . "\n");

// TODO deprec?
//      $translatedTitleObject = Title::makeTitle( NS_TRANSLATED, $translatedTitleText );  // Create a Title object with the specified namespace and title

      $this->storePage ( "Translated:".$translatedTitleText, $translatedWikiText, $targetLanguage);                     // store the translated page
    }
    catch (Exception $e) { $this->status[$targetLanguage] = $e->getMessage (); $this->hasError = true;}
  }

  http_response_code( $this->hasError ? 500 : 200);
  header('Content-Type: application/json');
  echo json_encode($this->status); 
  exit ();
}

} // class


$point = new DeeplEndpoint_Title ();
$point->runIt();