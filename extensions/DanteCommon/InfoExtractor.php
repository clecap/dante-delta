<?php

// Infoextractor is a collection of functions which extract information from MediaWiki in certain convenient forms

class InfoExtractor {


/**
 *   Given the name of a MediaWiki article, optionally with namespace prefix, return its contents.
 *  
 *  @param [string]  $titleName  Name of article, may include namespace prefix
 *  @param [?bool]   $filter     If true, only return contents between the first <pre> tag and the first </pre> tag in the article
 *                               where both, <pre> and </pre> must stand at the beginning of a line
 *  @param [?string] $fileName   If not null, generate a file by this name and place the contents into this file.
 *  @param [?bool]   $value      If true, return the contents as value from this function
 *                               If null or undefined, just return a void. 
 *  @return string|null
 */
public static function articleExtract ( string $titleName, ?bool $filter = null, ?string $fileName = null, ?bool $value = null ): mixed {
  $title = Title::newFromText($titleName);
  if (!$title || !$title->exists()) { throw new Exception ("Title $title not given or nor existent"); }

  $page = WikiPage::factory($title);
  $content = $page->getContent();
  if (!$content) { throw new Exception ("Content obtained from $title is null");}
  $text = ContentHandler::getContentText($content);
  if ($filter) {
    if (preg_match('/^<pre>\R?(.*?)^<\/pre>/ms', $text, $m)) {$text = $m[1];} else {$text = '';}
  }

  if ($fileName !== null) {file_put_contents($fileName, $text);}

  if ($value) {return $text;}
  return null;
}


/**
 *  Given the name of a MediaWiki category, append to file $fileName the title of all articles belonging to this category 
 *  @param [string] $category  Name of the category
 *  @param [string] $fileName  Name of the file
 */
public static function appendCategoryArticlesToFile (string $category, string $fileName): void {
  if (stripos($category, 'Category:') === 0) {$category = substr($category, strlen('Category:'));}    // Allow passing "Category:Foo" or just "Foo"
  $cat = Category::newFromName($category);    // Category::newFromName expects the bare category name (no prefix)
  if (!$cat) { throw new Exception ("Invalid Category name $category");}
  $members = $cat->getMembers();    // TitleArray of members
  $lines = [];
  foreach ($members as $title) {
    if (!$title instanceof Title) {continue;}
    // if ($title->getNamespace() !== NS_MAIN) {continue;}     // Only “articles” in the main namespace; remove this check if you want all members
    $lines[] = $title->getPrefixedText() . "\n";
  }

  if ($lines) {
    file_put_contents($fileName, implode('', $lines), FILE_APPEND | LOCK_EX);
  }
}



/**
 *  Given the name of a MediaWiki $category, return a list of all categories which are direct or indirect subcategories of $category.
 *  Note that the category structure can be circular - this should not lead to a non-terminating algorithm.
 *  Note that a category may be a subcategory of another category in direct or indirect ways (ie. over several steps)
 *  The returned list should be unique, every category should occur only once
 *
 * @param [string] $category
 */
 /**
 * Given the name of a MediaWiki $category, return a list of all categories
 * which are direct or indirect subcategories of $category.
 * The result is unique (each category appears only once) and cycles are handled.
 *
 * @param string $category Name of the category, with or without "Category:" prefix
 * @return string[] List of category titles (e.g. "Category:Foo")
 */
/**
 * Given the name of a MediaWiki category, return a list of all categories that are
 * direct or indirect subcategories of it. Cycles are handled safely.
 *
 * @param string $category Category name with or without "Category:" prefix
 * @return string[] Array of unique category titles (e.g. "Category:Foo")
 */
function getAllSubcategories(string $category): array {
  // Normalize input so Category::newFromName() receives the bare name
  if (stripos($category, 'Category:') === 0) {
    $category = substr($category, strlen('Category:'));
  }

  $rootCat = Category::newFromName($category);
  if (!$rootCat) {
    return [];
  }

  $result = [];   // DBkey => "Category:Name"
  $visited = [];  // DBkey => true
  $queue = [$rootCat];

  while ($queue) {
    /** @var Category $cat */
    $cat = array_shift($queue);

    $page = $cat->getPage(); // PageIdentity
    if (!$page) {
      continue;
    }

    $dbKey = $page->getDBkey();
    if (isset($visited[$dbKey])) {
      continue;
    }
    $visited[$dbKey] = true;

    // Fetch category members (as Title objects)
    $members = $cat->getMembers();

    foreach ($members as $title) {
      if (!$title instanceof Title) {
        continue;
      }

      // We're only interested in subcategories
      if ($title->getNamespace() !== NS_CATEGORY) {
        continue;
      }

      $subKey = $title->getDBkey();

      // Record result (unique)
      $result[$subKey] = $title->getPrefixedText();

      // Prevent infinite loops in cyclic category graphs
      if (!isset($visited[$subKey])) {
        $subCat = Category::newFromTitle($title);
        if ($subCat) {
          $queue[] = $subCat;
        }
      }
    }
  }

  // Return only the unique category titles
  return array_values($result);
}














} // end class
