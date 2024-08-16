<?php

require_once '/var/www/html/wiki-dir/maintenance/Maintenance.php';

class CreateTestPage extends Maintenance {

  public function execute() {
    $titleText = "Translated:testeratu/en";
    $contentText = "just a test";

    $title = Title::newFromText($titleText);
    if (!$title) {
      $this->output("Invalid title.\n");
      return;
    }

    if ($title->exists()) {
      $this->output("Page already exists.\n");
      return;
    }

    $wikiPage = WikiPage::factory($title);
    $content = ContentHandler::makeContent($contentText, $title);
    $summary = "Creating a new page via maintenance script";

    $user = User::newSystemUser('Maintenance script', ['stealth' => true]);      // Get a system user to perform the page update

    $pageUpdater = $wikiPage->newPageUpdater($user);
    $pageUpdater->setContent('main', $content);
    $pageUpdater->saveRevision(CommentStoreComment::newUnsavedComment($summary));

    $this->output("Page '{$titleText}' created successfully.\n");
  }
}

$maintClass = CreateTestPage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
