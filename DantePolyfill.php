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
  $code  = trim   ($code);                                                               // removes white space at the beginning and at the end
  $lines = explode("\n", $code);                                                         // Split the string into an array of lines
  $filteredLines = array_filter($lines, function($line) {return trim($line) !== '';});   // Use array_filter to remove empty lines
  $result = implode("\n", $filteredLines);                                               // Join the filtered lines back into a single string

  $result .= "\n";    // append one \n so that counting the newlines also counts the lines we have
  return $result;
}

function danteLog ($extension, $text) {
  $fileName = dirname(__FILE__) . "/extensions/".$extension."/LOGFILE";
  
  if($tmpFile = fopen( $fileName , 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  
  else {throw new Exception ("DanteSettings.php: debugLog could not log to $fileName for extension $extension"); }

  $fileSize = filesize ($fileName);
  if ($fileSize == false) { return; }
  if ($fileSize > 1000000) {  $handle = fopen($fileName, 'w'); }  // truncate too long files

}






// bundles some utility functions
class DanteUtil {


/** Store content to github
 * $owner    Repository owner name (in my case clecap)
 * $repo     Name of repository (in my case dante-wiki)
 * $path     Path of the filename to be uploaded
 * $token    Access token for github
 * $content  Content to be uploaded
 */
static public function storeToGithub ($owner, $repo, $path, $token, $content) {
   $apiUrl = "https://api.github.com/repos/$owner/$repo/contents/$path";    // GitHub API URL to create/update a file


// TODO: must trhow an exception if something goes wrong wrt storing so the user is informed !

  // Get the current contents of the file (need the SHA for updating)
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $apiUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERAGENT, $owner);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: token ' . $token));
  $response = curl_exec($ch);
  curl_close($ch);
  $responseData = json_decode($response, true);
  $fileSha = isset($responseData['sha']) ? $responseData['sha'] : null;

  // Prepare data for GitHub API
  $data = ['message' => "Updating $path with new data", 'content' => base64_encode($content), 'sha' => $fileSha, ];

  // Send the data to GitHub
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $apiUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERAGENT, $owner);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: token ' . $token, 'Content-Type: application/json'));
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  $response = curl_exec($ch);
  curl_close($ch);

  return $response;
}



/**
 * Given the name $cat of a category, return the list of files belonging to this category (this includes category files)
 */
static public function catList ( $cat ) {
  // Get the list of pages which are in the given category $cat
  $dbr           = wfGetDB( DB_REPLICA );
  $categoryTitle = Title::newFromText( $cat, NS_CATEGORY );
  if ( !$categoryTitle ) { throw new Exception ("Cannot find category $cat"); }

  $res = $dbr->select(
    array( 'categorylinks',   'page' ),
    array( 'page_namespace',  'page_title' ),
    array( 'cl_from = page_id', 'cl_to' => $categoryTitle->getDBkey() ),
      __METHOD__
    );

  $filepath = tempnam ("/tmp", "DanteInitialStore");   // getting a fresh file name helps us to avoid race conditions of parallel invocations; so we do not need locks
  $file = fopen( $filepath, 'w' );
  foreach ( $res as $row ) {
    $title = Title::makeTitle( $row->page_namespace, $row->page_title );
    fwrite( $file, $title->getFullText() . PHP_EOL );
  }

  fclose( $file );
  return $filepath;
}



/**
 * Given the name of a title (which may include a namespace prefix) an returns the contents of this file in the first <pre> element to be found there
 * Where every line is terminated by a newline.
 *
 *
 */
static public function listOfListed ($name) {
  $title      = Title::newFromText( $name  );                             
  if ($title == null) { throw new Exception ("Title $name not found");}       
  $wikipage   = new WikiPage ($title);                                                      
  if ($wikipage == null) { throw new Exception ("Could not generate Wikipage for title $name");}                                   // signal the caller that we did not get a WikiPage
  $contentObject = $wikipage->getContent();                                                   // and obtain the content object for that
  if ($contentObject ) {                                                                      // IF we have found a content object for this thing
    $contentText = ContentHandler::getContentText( $contentObject );    
    $contentText = extractPreContents ($contentText);
    $filepath = tempnam ("/tmp", "DanteInitialStore");
    file_put_contents ($filepath, $contentText); 
    return $filepath;
  }
  throw new Exception ("Could not get content object for $name");
}


// given a namespace index such as NS_TEST, return an arrayy of pages in the namespace
static public function getPagesInNamespace( $namespaceIndex ) {
  // Query to get all pages in the specified namespace
  $dbr = wfGetDB( DB_REPLICA );
  $res = $dbr->select(
    'page',  // The table to select from
    'page_title',  // The column to select
    [ 'page_namespace' => $namespaceIndex ],  // The condition (where clause)
    __METHOD__  // The name of the calling function, for logging purposes
  );

  $pages = [];  // Initialize an array to hold the page titles
  foreach ( $res as $row ) {$pages[] = $row->page_title;}   // Iterate over the results and add each page title to the array
  return $pages;
}

// returns name of a temporary file containing all parges contained in the given namespace
static public function listOfNamespace ( $nsIndex ) {
  $filepath = tempnam ("/tmp", "DanteInitialStore");
  $arr = DanteUtil::getPagesInNamespace ( $nsIndex );
  file_put_contents ($filepath, implode ("\n",$arr)."\n");
  return $filepath;
}

// returns name of a temporary file
static public function singleList ( $name ) {
  $filepath = tempnam ("/tmp", "DanteInitialStore");
  file_put_contents ( $filepath, $name . "\n");
  return $filepath;
}



}
