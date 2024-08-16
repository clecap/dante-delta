<?php

require_once '../../../maintenance/Maintenance.php';

class CreatePage extends Maintenance {

public function __construct() {
  parent::__construct();
  $this->addOption('title', 'The title of the wiki page to create', true, true);  // first true: option is required. second true: expects a value (is not a flag)
}

protected function output ( $text, $channel=null ) { echo "$text"; }  // overwrites output 

public function execute() {
  $titleText = $this->getOption('title');  // may contain namespace and subpage as in   "Translated:testeratu/en"

  $contentText = '';  while ($line = fgets(STDIN)) { $contentText .= $line; }      // Read content from stdin

//  myLog ("read from stdin: " . $contentText . "\n");


  $title = Title::newFromText($titleText);
  if (!$title) {$this->output("Invalid title"); return;}

  // if ($title->exists()) {$this->output("Page already exists.\n"); return;}

  $wikiPage = WikiPage::factory($title);
  $content = ContentHandler::makeContent($contentText, $title);
  $summary = "Created by a DanteWiki ai or translation script";

  $user = User::newSystemUser('Maintenance script');      // Get a system user to perform the page update
  $pageUpdater = $wikiPage->newPageUpdater($user);
  $pageUpdater->setContent('main', $content);
  $pageUpdater->saveRevision(CommentStoreComment::newUnsavedComment($summary));

  $this->output("\"Page {$titleText} created successfully.\"");
  }
}

function myLog ($text) {
  $fileName = "LOG";
  if($tmpFile = fopen( $fileName, 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  // NOTE: close immediatley after writing to ensure proper flush
  else {throw new Exception ("could not log"); }

  $fileSize = filesize ($fileName);
  if ($fileSize == false) { return; }
  if ($fileSize > 100000) {  $handle = fopen($fileName, 'w'); }  // truncate too long files
}


$maintClass = CreatePage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
