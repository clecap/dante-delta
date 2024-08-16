<?php 



// this endpoint will translate raw input sent to the endpoint


/* Endpoint for integrating deepl into Dantewiki
   This MUST be a backend endpoint, as otherwise we would have to include the deepl api key into the javascript sent to the client
 */


namespace DeepL;

opcache_reset(); // ONLY TODO: during development

require __DIR__ . '/../../../vendor/autoload.php';

require __DIR__. '/../../../mediawiki-PRIVATE.php'; // TODO: isnt this already outdated ? not sure ?


// need the following two lines to obtain reasonable errors from the endpoint instead of only 500 er status from webserver
error_reporting(E_ALL);
ini_set('display_errors', 'On');


$authKey = $DEEPL_API_KEY;  


$translationOptions = [
  "split_sentences"        => "on",
  "preserve_formatting"    => true,
  "formality"              => "prefer_more",
  "tag_handling"           => "xml",
  "outline_detection"      => true,
  "splitting_tags"         => [],
  "non_splitting_tags"     => [],
  "ignore_tags"            => []
];

$options = [
 // 'app_info' => new \DeepL\AppInfo('my-custom-php-chat-client', '1.2.3'),
  'send_platform_info' => false,
  "max_retries" => 5,
  "timeout"  => 10.0,
//  "logger"
];

  

function generateResponse ( $input ) {
  global $authKey, $options;
  $phpSon = [];

  $translator = new \DeepL\Translator($authKey, $options);

  if (! $translator) {echo "ERROR"; return;}

  // get available languages
  $phpSon["sourceLanguages"] = $translator->getSourceLanguages();
  $phpSon["targetLanguages"]  = $translator->getTargetLanguages();

  $phpSon["usage"] = $translator->getUsage();    // get usage


//  if ($usage->anyLimitReached()) { echo 'Translation limit exceeded.'; }
//  if ($usage->character)     {echo 'Characters: ' . $usage->character->count . ' of ' . $usage->character->limit;}
//  if ($usage->document) { echo 'Documents: ' . $usage->document->count . ' of ' . $usage->document->limit; }


  $phpSon["result"] = $translator->translateText ( $input , null, 'fr');

  return $phpSon; // Bonjour, le monde!

//  return "RESPO";

}



function sendResponse ( $stringContent ) {
  header("Content-Length: " . strlen ($stringContent) );          // strlen returns bytes not characters for UTF-8 stuff 
  header("Content-type: application/json");                                // set Mime Type header 
  echo ($this->stringContent); 
}



function main () {
  // get input from caller
  $body = file_get_contents("php://input");         // get the input; here: the raw body from the request
  // $body = base64_decode ($body);                    // in an earlier version we used, unsuccessfully, some conversion, as in:   $body = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $body); 

  $data = json_decode($body, true);
  if (json_last_error() === JSON_ERROR_NONE) {
    // print_r($data);
    $response = generateResponse ( $body );
    $jsonResponse = json_encode ($response);
    echo $jsonResponse;
    //echo print_r ($jsonResponse);

  } 
  else { echo "Error: Invalid JSON data received.";}

}


main ();


