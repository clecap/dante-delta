<?php

require_once ("DanteCommon.php");
require_once ("Executor.php");

class DanteInitialStore extends SpecialPage {

public function __construct () {parent::__construct( 'DanteInitialStore' ); }

public function getGroupName() {return 'dante';}
  
public function execute ( $subPage ) {
  global $IP;
  $this->setHeaders();
  $this->outputHeader();
  $out = $this->getOutput();
    
  // Check if form is submitted
  $request       = $this->getRequest();
  $owner         = $request->getText ('owner');
  $repository    = $request->getText ('repository');
  $path          = $request->getText ('path');
  $filename      = $request->getText ('filename');
  $token         = $request->getText ('token');

  // Add explanatory text and form
  $text = <<<EOT
<p>This special page uploads the current content of the Dante Initialization Pages to the dante-wiki github repository</p>
<p>It is meant for use only by dante-wiki maintainers</p>
<form method="post" action="">
  <table>
    <tr><td><label>Owner</label></td>            <td><input type="text" name="owner"       size="80"  value="clecap"/></td></tr>
    <tr><td><label>Repository</label></td>       <td><input type="text" name="repository"  size="80"  value="dante-wiki"/></td></tr>
    <tr><td><label>Path</label></td>             <td><input type="text" name="path"        size="80"  value="assets/initial-contents"/></td></tr>
    <tr><td><label>Filename</label></td>         <td><input type="text" name="filename"    size="80"  value="initial-content-saved.xml"></td></tr>
    <tr><td><label>Access Token</label></td>     <td><input type="text" name="token"       size="80"  value="must-enter-a-valid-github-authorization-token-here"/></td></tr>
  </table>
  <input type="submit" value="Submit"/>
</form>
EOT;

  $out->addHTML( $text );


// NOTE: for the github upload api we need the contents in a shell variable (max 2MB)
// Two strategies: Size restriction or iterating over individual files



  // Display submitted input
  if ( $token !== '' ) {
    $filepath = self::makeList ("DanteInitialContents");
    $out->addHTML( '<h3>got filepath:</h3><p><pre>' . $filepath . '</pre></p>' );

    $cmd = "php  $IP/maintenance/dumpBackup.php --current --uploads  --include-files  --pagelist=$filepath";
    $ret = Executor::execute ( $cmd, $output, $error, $duration );

    //  $out->addHTML( '<h3>dumpBackup said as output:</h3><p><pre>' . $output . '</pre></p>' );

    $out->addHTML( '<h3>dumpBackup said to stderror:</h3><p><pre>' . $error . '</pre></p>' );
  
    $response = self::storeToGithub ($owner, $repository, "$path/$filename", $token, $output);
    $json = json_decode ($response);
    $json_indented_by_4 = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    $json_indented_by_2 = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $json_indented_by_4);
    $out->addHTML( '<h3>Github server replied to API call:</h3><p><pre>' . $json_indented_by_2 . '</pre></p>' );
    @unlink ($filepath);
  }
}



static private function makeList ( $cat ) {
  // Get the list of pages in the category
  $dbr = wfGetDB( DB_REPLICA );
  $categoryTitle = Title::newFromText( $cat, NS_CATEGORY );
  if ( !$categoryTitle ) {  // illegal category ??
    return -1;   }

  $categoryId = $categoryTitle->getArticleID();
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


  // Get the list of pages in the MediaWiki namespace
  //  $dbr = wfGetDB( DB_REPLICA );
  $res = $dbr->select(
    'page',
    array( 'page_namespace', 'page_title' ),
    array( 'page_namespace' => NS_MEDIAWIKI ),
      __METHOD__
    );

  foreach ( $res as $row ) {
    $title = Title::makeTitle( $row->page_namespace, $row->page_title );
    fwrite( $file, $title->getFullText() . PHP_EOL );
  }

    fclose( $file );
  return $filepath;
}


static private function storeToGithub ($owner, $repo, $path, $token, $content) {
   $apiUrl = "https://api.github.com/repos/$owner/$repo/contents/$path";    // GitHub API URL to create/update a file

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
  $data = ['message' => 'Updating file with new data', 'content' => base64_encode($content), 'sha' => $fileSha, ];

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

} // end class

?>