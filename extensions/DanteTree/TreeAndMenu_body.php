<?php

use MediaWiki\MediaWikiServices;

class TreeAndMenu {
  const TREE = 1; 
  const MENU = 2;

public static function onParserFirstCallInit (Parser $parser) {                 // set the parser functions for  #tree   and  #menu
  // self::debugLog ("Hello !");
  $parser->setFunctionHook('tree', array('TreeAndMenu', 'expandTreeX') );
  $parser->setFunctionHook('menu', array('TreeAndMenu', 'expandMenuX') );
  return true;
}

static function debugLog ($text) {
  global $wgAllowVerbose;
  if (!$wgAllowVerbose) {return;}
  if($tmpFile = fopen( "extensions/DanteTree/LOGFILE", 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);} 
  else {throw new Exception ("debugLog could not log"); }
}


public static function onSkinBuildSidebar ($skin, &$bar ) {
  global $wgOut;
  global $IP;
  global $wgServer, $wgScriptPath;
  global $wgAllowVerbose; $VERBOSE = false && $wgAllowVerbose;
  // self::debugLog ("onSkinBuildSidebar called \nsees bar=".print_r ($bar, true)."\n");
  $arrKeys = array_keys ($bar);

  $entryTime = hrtime(true);

  // when served from the cache on MacPro this takes less than 1 ms, when served by generating it may take some 60 ms. Thus a cache here is helpful.
  if (file_exists ( $IP . "/sidebarcache" )) {
    $stored = file_get_contents ( $IP. "/sidebarcache" );
    $bar = unserialize ($stored); 
    // echo "" . (hrtime(true)-$entryTime)/1000 . " mu-sec";   // development: measuring cache hit time
    return true;
  }


  foreach ($arrKeys as $key) {

    if (in_array ( $key,  array ("SEARCH", "TOOLBOX", "LANGUAGES") ) )  { 
 
      continue; }  // ignore these standard sidebar items 

    // the category tree is specified as an array of objects of which there are two variants    {tree: CatName, depth:4}   or   {name: CatName}
    // every object may also have an additional   className   and an additional  style  attribute  to be used for styling the entry
    if (strcmp ("catus", $key) == 0) {
      $cHtml = "<ul>";                     // start rendering the category tree information
      $catConfig = self::getCatConfig ();  // array of objects {name, style} or {root, depth, style}
    
      // self::debugLog ( "catConfig array we got: \n".print_r ($catConfig, true));

      foreach ($catConfig as $obj) {       // iterate all objects in the configuration array
        if ($VERBOSE) {self::debugLog ("Loop looking at configuration object: ".print_r ($obj, true)."\n");}
        $style = ( array_key_exists ("style", $obj) ? $obj["style"]: "");
        if (array_key_exists ("name", $obj))  {    
          if ($VERBOSE) {self::debugLog ("\n\n Formatting link: " . self::buildLink ($obj["name"], $style).  "\n\n");}
          $cHtml = $cHtml . "<li>".self::buildLink($obj["name"], $style)."</li>";
         }
        if (array_key_exists ("root", $obj))  { 
          $kids  = self::getChildren ($obj["root"]);
          foreach ($kids as $key => $value) {$cHtml .= self::renderCatKids ($key, $obj["depth"], $style );};
        }
      }
      $cHtml .= "</ul>";
      if ($VERBOSE) {elf::debugLog ("The cHtml generated is: " .$cHtml);}

 //     $html  =    "<div class=\"fancytree todo\" id=\"sidebar-cattree\" style=\"display:none;\" >$cHtml </div>";  // style: show only after cats are adjusted; todo: MUST mark for later tree expansion  fancytree: MUST mark as being a fancytree base 
     $html  =    "<div class=\"fancytree todo\" style=\"display:none;\" id=\"sidebar-cattree\" >$cHtml </div>";  // style: show only after cats are adjusted; todo: MUST mark for later tree expansion  fancytree: MUST mark as being a fancytree base 
     
  // here we add the button-like stuff below the category
  $add = <<<HERE
   <div style="margin-left:4px; margin-top:6px;line-height:20px;font-size:12pt;">
  <a href="$wgServer/$wgScriptPath/index.php/Special:Categories" class="dicon" title="Paged list of all categories">&forall;</a>
  <a href="$wgServer/$wgScriptPath/index.php/MediaWiki:CategoryTree"  class="dicon" title="Category tree">&#x1F332;</a>
  <a href="$wgServer/$wgScriptPath/index.php/MediaWiki:CategoryCloud"  class="dicon" title="Category cloud">&#9729;</a>
  <a href="$wgServer/$wgScriptPath/index.php/Special:UncategorizedPages"  class="dicon" title="List of pages without category">&empty;</a>
  <a href="$wgServer/$wgScriptPath/index.php/Special:UncategorizedCategories" 
  style="color:red;"
 class="dicon" title="List of categories without category">&empty;</a>
  <a href="$wgServer/$wgScriptPath/index.php?title=Special:AllPages&from=&to=&namespace=14"  class="dicon" title="Query page">?</a>
</div>
HERE;

  $html = $html . $add;

     $bar[ 'catus' ] = [ [ 'html' =>  $html ]];
    }

    if (is_int ($key)) {continue;}
    // if we are still here then it probably is an override case

    // for the rest, we do not have a special treatment but do an override mechanism by our own
    $override = self::overrideSidebar ( $key );
    if ($override) {$bar[$key] = [ ['html' => $override ]];}

  }  // end FOR loop

  file_put_contents ( $IP. "/sidebarcache", serialize ($bar), LOCK_EX );
  // echo "" . (hrtime(true)-$entryTime)/1000 . " mu-sec";  // development: measuring cache miss time
  return true;
}


// ensure that after editing a sidebar relevant page in the MediaWiki namespace, the sidebar cache is deleted
// ensure that editing the page Sidebar (and Mainpage) also generates a copy of it in directory assets so we can later pick it up again
//   this is necessary since Sidebar (and Mainpage) are the only pages we need to inject separately apart from the xml backup archive
public static function onEditPageattemptSaveafter( EditPage $editPage, Status $status, $details ) {
  global $IP;
  // danteLog ("DanteTree", "oneditpageattempt \n" );
  if ( $editPage->getTitle()->getNamespace() == NS_MAIN) {     
    $titleText = $editPage->getTitle()->getText();
    //danteLog ("DanteTree", "oneditpageattempt: ".$titleText."\n" );
    if ($titleText == "Main Page") {
      $textData = $editPage->getArticle()->getPage()->getContent()->getNativeData();
      //danteLog ("DanteTree", "oneditpageattempt: ".$titleText." with " . $textData."\n" );
      $retVal = file_put_contents ($IP."/assets/Main Page", $textData);
      //danteLog ("DanteTree", "oneditpageattempt:stored:  ". print_r ($retVal, true).  "  type " .gettype($retVal)."\n" );
    }
  }
  else if ( $editPage->getTitle()->getNamespace() == NS_MEDIAWIKI) {            // only if the edit takes place in the MediaWiki namespace
    $titleText = $editPage->getTitle()->getText();
    if ( str_starts_with($titleText, "Sidebar") ) { $name = $IP.'/sidebarcache'; if(file_exists($name)) {unlink($name); } }
    if ( $titleText == "Sidebar" ) {
      $textData = $editPage->getArticle()->getPage()->getContent()->getNativeData();
      file_put_contents ($IP."/assets/Sidebar", $textData);
    }
  }
}  // end function afterAttemptSave


// changing category membership requires redoing the sidebar
public static function onCategoryAfterPageRemoved( $category, $wikiPage, $id ) { global $IP; $name = $IP.'/sidebarcache'; if(file_exists($name)) {unlink($name); } }

// changing category membership requires redoing the sidebar
public static function onCategoryAfterPageAdded( $category, $wikiPage ) {  global $IP; $name = $IP.'/sidebarcache'; if(file_exists($name)) {unlink($name); } }



// generate the html text for the additional, overridden sidebar portions
private static function overrideSidebar ($name) {
  global $wgOut;
  $configPage = "Sidebar/$name";                                                              // name of the MediaWiki:Sidebar$name configuration page of this portlet
  $title      = Title::newFromText( $configPage, NS_MEDIAWIKI );                              // build title object for MediaWiki:SidebarTree
  if ($title == null) {return false;}                                                         // signal the caller that we did not find a configuration page for this portlet
  $wikipage   = new WikiPage ($title);                                                        // get the WikiPage for that title
  if ($wikipage == null) {return false;}                                                      // signal the caller that we did not get a WikiPage
  $contentObject = $wikipage->getContent();                                                   // and obtain the content object for that
  if ($contentObject ) {                                                                      // IF we have found a content object for this thing
    $contentText = ContentHandler::getContentText( $contentObject );    
    $contentText = extractPreContents ($contentText);
    $html = "<div class='noglossary\' id='wikitext-sidebar'>" . $wgOut-> parseInlineAsInterface( $contentText ) . "</div>"; 
    return $html; }   
  else { return false;}                      // signal the caller that we did not find a config object
}




// get the JSON config object for categories
private static function getCatConfig () {
  global $wgAllowVerbose; $VERBOSE = false && $wgAllowVerbose;
  $configPage = "Sidebar/Categories";                                                         // name of the MediaWiki:Sidebar$name configuration page of this treelet
  $title      = Title::newFromText( $configPage, NS_MEDIAWIKI );                              // build title object for MediaWiki:SidebarTree
  $wikipage   = new WikiPage ($title);                                                        // get the WikiPage for that title
  $contentObject = $wikipage->getContent();                                                   // and obtain the content object for that
  if ($contentObject ) {                                                                      // IF we have found a content object for this thing
    $code    = ContentHandler::getContentText ( $contentObject );
    if ($VERBOSE) {self::debugLog ("\n\n Categories configuration text before scanning is: " . $code . "  \n\n");}      
        
    $code = extractPreContents ($code);

    if ($VERBOSE) {self::debugLog ("\n\n Categories configuration text is: " . $code . "  \n\n");}
    
    $obj = json_decode ($code, true);
    if ($obj == null) { 
      if ($VERBOSE) {self::debugLog ("\n\n Categories configuration object could not be parsed since we received this:\n\n $code \n");}
      return "Could not parse MediaWiki:Sidebar/Categories - expected a json object";} 
    else {  // self::debugLog ("\n\n Categories configuration object in php is: " . print_r ($obj, true) . "  \n\n");
    return $obj;}
  }   
  else { 
   if ($VERBOSE) {self::debugLog ("\n\n Categories configuration object could not be found \n\n");}
    return "Could not find MediaWiki:Sidebar/Categories";}
}


public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
  global $wgExtensionAssetsPath;
  $out->addHeadItem("earlyIcons",    "<link rel='preload' as='image' href='../extensions/DanteTree/fancytree/icons.gif'>");  // preload the DanteTree images (does work on Chrome)

  $out->addHeadItem ("sidebarstyle", <<<EOT
<script data-src="TreeAndMenu_body.php">
  let pers = window.localStorage.getItem ("sidebar-width");
  if (pers) {
    let style = `<style>`+
      `#mw-panel {width: \${pers} ; height: 100%;}
      #content   {margin-left: \${pers};}
      #left-navigation  {margin-left: \${pers};}
      #footer {margin-left: \${pers};}
    </style>`;
    document.write (style);
  }
</script>        
EOT);




  $out->addModules('ext.fancytree');                                                         // scripts may load via ressource loader
  $out->addStyle("../extensions/DanteTree/danteTree.css");                                   // style is added directly since the loader would result in a FOUC
  $out->addStyle("../extensions/DanteTree/fancytree/fancytree.css"); 
  $out->addJsConfigVars('fancytree_path', $wgExtensionAssetsPath . "/DanteTree/fancytree");  // define the preload path for preloading some icons; not sure if really needed and an advantage // TODO: currently still need this wgExtensionsassetsPath here
    // Suckerfish menu script and styles
    $out->addModules('ext.suckerfish');
    $out->addStyle("../extensions/DanteTree/suckerfish/suckerfish.css");
 }


// format the $name of a category in an <a> and <span> for fancy tree
// note: we need the span wrapping in order for the <a> to make it into the tree
static  function buildLink ($name, $style = "") {
  global $wgScript;
  $name = str_replace ("_", " ", $name);          // we get the category names with _ from the DB but we render them without the _ to the tree
  return "<span ><a style='$style' target='_blank' title='Open category page in new window or tab'  onclick='window.openAsPopup(event);'  href='$wgScript/Category:".$name."'>".$name."</a></span>";} 

// $depth <= 0 number of ul's we are still allowed to open
//
// Render the category tree
// $names
// $depth number of levels we are still going to descend 
// $me    true means: also render myself, i.e. $name
//        false means: only render my kids
static function renderCat ($name, $depth, $me) {
  if ($depth <= 0) {return "<li>" . self::buildLink($name) . "</li>" ;}                  

  $local = "";
  if ($me) {$local = "<li>".self::buildLink($name);}
  $categs = self::getChildren($name);                        // get categories which are children of $name
  if ( count ($categs) > 0 ) {                               // if we found children of $name, warp them in <ul>...</ul>
    $local .= "<ul>";
    foreach ($categs as $key => $value) { $local .=   self::renderCat ( $key, $depth - 1, true) ; }
    $local .= "</ul>";
  }
  else {}
  if ($me) {$local .= "</li>";}
  return $local;
}



// render first the categories in $prefix, then the kids of $name up to depth $depth and finally the categories in $postfix
static function renderCatTop ($name, $depth, $prefix, $infix, $postfix) {
  $ret = "<ul>";    
  foreach ($prefix as $val)  { $ret .= "<li>".self::buildLink($val, "font-weight:bold;")."</li>"; }
  
  $num = 0;
  $color = array ("color:red;", "color:blue;"); 
  foreach ($infix as $val)  {
    $kids = self::getChildren ($val);
    foreach ($kids as $key => $value) {$ret .= self::renderCatKids ($key, $depth, $color[$num % 2]);};
    $num++;
  }
  
  /*
  $rootKids = self::getChildren ("Root");
  foreach ($rootKids as $key => $value) {$ret .= self::renderCatKids ($key, $depth);};
  */
  
  foreach ($postfix as $val) { $ret .= "<li>".self::buildLink($val)."</li>"; }
  $ret = $ret . "</ul>";
  //self::debugLog ("renderCatTop returns: " . $ret);
  return $ret;
}

static function renderCatKids ($name, $depth, $style="") {
  if ($depth <= 0) {return "<li>" . self::buildLink($name, $style) . "</li>" ;}    // we have reached the depth limit, only return a packaged <li> ... </li> link   
  $local = ""; 
  $local = "<li>".self::buildLink($name, $style);                    // render the $name itself and then descend into its children
  $categs = self::getChildren($name);                                     // get categories which are children of $name
  if ( count ($categs) > 0 ) {                                            // if we found children of $name, warp them in <ul>...</ul>
    $local .= "<ul>";
    foreach ($categs as $key => $value) { $local .=   self::renderCatKids ( $key, $depth - 1, $style) ; }  // and descend into them
    $local .= "</ul>";
  }
  else {}
  $local .= "</li>";
  return $local;
}

// the vector font has an unfortunate CSS rule for   #mw-panel .portal .body ul  which affects the tree in a manner that (if placed in the sidebar)
// the identation of nested folders is not correct. This cannot easily be corrected by css adaptaions. Therefore we patch it here by removing the class name of "portal"
// and adding a different class name  freshClass. Function has to be called as inline <script> via php inject where apropriate

static function sidebarSkinPatch ($name) {    // $name: id of the element below which we want to patch   // p-startus

  return "";   // TODO: it looks like this is no longer needed - take it out currently but leave the code in for a while should we still need it later.


  return "<script>
      var elem = document.getElementById ('".$name."');
      if (elem) {var newClass = elem.className.split(' ').filter( item => (item != 'portal') ).join(' ')+ ' portal-sidebar-tree'; elem.className = newClass;}
      else {console.error ('TreeAndMenu_body.php: could not find element: " .$name . "');}   </script>";
}

  // # Get all categories from the wiki - starting with a given root or otherwise detect root automagically (expensive) Returns an array as in  array ( 'Name' => (int) Depth, ... )
  public static function getAllCategories($namespace) {
    global $wgSelectCategoryRoot;

    // Get current namespace (save duplicate call of method)
    if ($namespace >= 0 && array_key_exists($namespace, $wgSelectCategoryRoot) && $wgSelectCategoryRoot[$namespace]) {
      // Include root and step into the recursion
      $allCats = array_merge(array($wgSelectCategoryRoot[$namespace] => 0), self::getChildren($wgSelectCategoryRoot[$namespace]));
    }
    else {
      $allCats = array();  // init return value
      $dbObj = wfGetDB(DB_MASTER);  // Get a database object   // CHC: changed to DB_MASTER  for freshness
      $tblCatLink = $dbObj->tableName('categorylinks');  // Get table names to access them in SQL query
      $tblPage = $dbObj->tableName('page');

      // Automagically detect root categories
      $sql = "  SELECT tmpSelectCat1.cl_to AS title
FROM $tblCatLink AS tmpSelectCat1
LEFT JOIN $tblPage    AS tmpSelectCatPage ON (tmpSelectCat1.cl_to = tmpSelectCatPage.page_title AND tmpSelectCatPage.page_namespace = 14)
LEFT JOIN $tblCatLink AS tmpSelectCat2 ON tmpSelectCatPage.page_id = tmpSelectCat2.cl_from
WHERE tmpSelectCat2.cl_from IS NULL GROUP BY tmpSelectCat1.cl_to";

      $res = $dbObj->query($sql, __METHOD__);   // Run the query

      while ($row = $dbObj->fetchRow($res)) {
        $allCats += array($row['title'] => 0);
        $allCats += self::getChildren($row['title']);
      }
      $dbObj->freeResult($res);   // Free result object
    }
    return $allCats;
  }



  // get all the categories which are children of $root
  public static function getChildren($root, $depth = 1) {
    $allCats    = array();                                                 // Initialize return value
    $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbr = $lb->getConnection ( DB_MASTER );                              // instance of IDatabase
    $CATEGORYLINKS = $dbr->tableName('categorylinks');    // Get table names to access them in SQL query
    $PAGE    = $dbr->tableName('page');

    // The normal query to get all children of a given root category
    $sql =
  'SELECT tmpSelectCatPage.page_title AS title FROM ' . $CATEGORYLINKS . ' AS tmpSelectCat
   LEFT JOIN ' . $PAGE . ' AS tmpSelectCatPage ON tmpSelectCat.cl_from = tmpSelectCatPage.page_id
   WHERE tmpSelectCat.cl_to LIKE ' . $dbr->addQuotes($root) . ' AND tmpSelectCatPage.page_namespace = 14 ORDER BY tmpSelectCatPage.page_title ASC;';

    $res = $dbr->query($sql, __METHOD__);          // Run the query; returns IResultWrapper or false     // TODO: missing error handling in case of false

    while ($row = $res->fetchRow($res)) {          // Process the resulting rows
      if ($root == $row['title']) {continue;}        // Survive category link loops
      $allCats += array($row['title'] => $depth);    // Add current entry to array
   //   $allCats += self::getChildren($row['title'], $depth + 1);  // NOTE: THIS IS ABOUT DOING IT TRANSITIVELY OR NOT !!!!
    }
   
  $res->free();  // Free result object
    // Afterwards return the array to the upper recursion level

    return $allCats;
  }




    // TODO: WORKING ON THIS - CODE NOT CORRECT    CHC
    // get all the categories which are children of $root together with information which of those categories is marked for the page with pagenumber $pageNum
    /*
  public static function getChildrenMarked ($root, $pageNum, $depth = 1) {
    $allCats    = array();                                 // Initialize return value
    $dbObj      = wfGetDB(DB_MASTER);                      // Get a database object  CHC changed to master for freshness// TODO: PERHAPS NOT NECESSARY !!!!!!!!!!!!!!!!!!!!!!!!!!!! ALSO ON OTHER PLACES !!!!!!!!!!!!!!!!
    $CATEGORYLINKS = $dbObj->tableName('categorylinks');   // Get table names to access them in SQL query
    $PAGE    = $dbObj->tableName('page');

    // The normal query to get all children of a given root category
    $sql =
  'SELECT tmpSelectCatPage.page_title AS title FROM ' . $CATEGORYLINKS . ' AS tmpSelectCat
   LEFT JOIN ' . $PAGE . ' AS tmpSelectCatPage ON tmpSelectCat.cl_from = tmpSelectCatPage.page_id
   WHERE tmpSelectCat.cl_to LIKE ' . $dbObj->addQuotes($root) . ' AND tmpSelectCatPage.page_namespace = 14 ORDER BY tmpSelectCatPage.page_title ASC;';


    $res = $dbObj->query($sql, __METHOD__);     // Run the query
    while ($row = $dbObj->fetchRow($res)) {   // Process the resulting rows
      if ($root == $row['title']) {continue;}    // Survive category link loops
      $allCats += array($row['title'] => $depth);   // Add current entry to array
   //   $allCats += self::getChildren($row['title'], $depth + 1);  // NOTE: THIS IS ABOUT DOING IT TRANSITIVELY OR NOT !!!!
    }
    $dbObj->freeResult($res); // Free result object
    // Afterwards return the array to the upper recursion level

    return $allCats;
  }
*/


   // #tree parser-functions
  public static function expandTreeX() { $args = func_get_args(); return TreeAndMenu::expandTreeAndMenuX( 'TREE', $args); }

  // #menu parser-functions
  public static function expandMenuX()  {$args = func_get_args(); return TreeAndMenu::expandTreeAndMenuX( 'MENU', $args);}

  //  Render a bullet list for either a tree or menu structure
  // Called only by expandTreeX or expandMenuX which provide the #tree and #menu parser functions 
  private static function expandTreeAndMenuX($type, $args) {
    global $wgJsMimeType;

  $wgTreeAndMenuPersistIfId = true;  // TODO should be configurable somehow

    // First arg is parser, last is the structure
    $parser  = array_shift($args);
    $bullets = array_pop($args);

    // Convert other args (except class, id, root) into named opts to pass to JS (JSON values are allowed, name-only treated as bool)
    $opts = array();
    $atts = array();
    foreach ($args as $arg) {
      if (preg_match('/^(\\w+?)\\s*=\\s*(.+)$/s', $arg, $m)) {
        if ($m[1] == 'class' || $m[1] == 'id' || $m[1] == 'root') {$atts[$m[1]] = $m[2];}
        else {$opts[$m[1]] = preg_match('|^[\[\{]|', $m[2]) ? json_decode($m[2]) : $m[2];}
      }
      else {$opts[$arg] = true;}
    }

    // If the $wgTreeAndMenuPersistIfId global is set and an ID is present, add the persist extension
    if (array_key_exists('id', $atts) && $wgTreeAndMenuPersistIfId) {
      if (array_key_exists('extensions', $opts)) {$opts['extensions'][] = 'persist';}
      else {$opts['extensions'] = array( 'persist' );}
    }

    // Sanitise the bullet structure (remove empty lines and empty bullets)
    $bullets = preg_replace('|^\*+\s*$|m', '', $bullets);
    $bullets = preg_replace('|\n+|', "\n", $bullets);

    // If it's a tree, wrap the item in a span so FancyTree treats it as HTML and put nowiki tags around any JSON props
    if ($type == 'TREE') {
      $bullets = preg_replace('|^(\*+)(.+?)$|m', '$1<span>$2</span>', $bullets);
      $bullets = preg_replace('|^(.*?)(\{.+\})|m', '$1<nowiki>$2</nowiki>', $bullets);
    }

    // Parse the bullets to HTML
    $opt = $parser->getOptions();
    // if (method_exists($opt, 'setWrapOutputClass')) {$opt->setWrapOutputClass(false);}  // setWrapOutputClass false for indicating no wrapping is deprecated
    $html = $parser->parse($bullets, $parser->getTitle(), $opt, true, false)->getText();

    // Determine the class and id attributes
    $class = $type == 'TREE' ? 'fancytree' : 'suckerfish';
    if (array_key_exists('class', $atts)) {$class .= ' ' . $atts['class'];}
    $id = array_key_exists('id', $atts) ? ' id="' . $atts['id'] . '"' : '';

    if ($type == 'TREE') {
      // Mark the structure as tree data, wrap in an unclosable top level if root arg passed (and parse root content)
      // style below was: display:none; CHC changed to visibility:hidden, which makes it more smooth in display (no flicker)

      $tree = '<ul id="treeData" style="visibility:hidden;">';

      if (array_key_exists('root', $atts)) {
        $root = $parser->parse($atts['root'], $parser->getTitle(), $parser->getOptions(), false, false)->getText();
        $html = $tree . '<li class="root">' . $root . $html . '</li></ul>';
        if (! array_key_exists('minExpandLevel', $opts)) $opts['minExpandLevel'] = 2;
      }
      else {$html = preg_replace('|<ul>|', $tree, $html, 1); }

      // Replace any json: markup in nodes into the li
      $html = preg_replace('|<li(>\s*\{.*?\"class\":\s*"(.+?)")|', "<li class='$2'$1", $html);
      $html = preg_replace('|<(li[^>]*)(>\s*\{.*?\"id\":\s*"(.+?)")|', "<$1 id='$3'$2", $html);
      $html = preg_replace('|<(li[^>]*)>\s*(.+?)\s*(\{.+\})\s*|', "<$1 data-json='$3'>$2", $html);

      // Incorporate options as json encoded data in a div
      $opts = count($opts) > 0 ? '<div class="opts" style="display:none">' . json_encode($opts, JSON_NUMERIC_CHECK) . '</div>' : '';

      $html = "<div class=\"$class todo\"$id>$opts$html</div>";                // Assemble it all into a single div
    } // If its a menu, just add the class and id attributes to the ul
    else { $html = preg_replace('|<ul>|', "<ul class=\"$class todo\"$id>", $html, 1); }
    return array($html, 'isHTML' => true,  'noparse' => true );
  }






   // GET THE CATEGORIES from the page object structure. (probably: of an edit page......)
   // Given a page object - return an array with the categories the article is in. Also removes them from the text the user views in the editbox.
   /*
  public static function getPageCategories($pageObj) {
    if (array_key_exists('SelectCategoryList', $_POST)) {     // We have already extracted the categories, return them instead of extracting zero categories from the page text.
      $catLinks = array();
      foreach ($_POST['SelectCategoryList'] as $cat) {$catLinks[$cat] = true;}
      return $catLinks;
    }


    global $wgContLang;

    $pageText = $pageObj->textbox1;                                                  // Get page contents
    $catString = strtolower($wgContLang->getNsText(NS_CATEGORY));                    // Get localised namespace string
    $pattern = "\[\[({$catString}|category):([^\|\]]*)(\|{{PAGENAME}}|)\]\]";        // Regular expression to find the category links
    $replace = "$2";
    $catLinks = array();      // The container to store all found category links
    $cleanText = '';       // The container to store the processed text
    foreach (explode("\n", $pageText) as $textLine) {                         // Check linewise for category links
      $cleanText .= preg_replace("/{$pattern}/i", "", $textLine) . "\n";      // Filter line through pattern and store the result
      if (! preg_match("/{$pattern}/i", $textLine)) {continue;}               // If we did not find a category link, proceed with next line
      $catLinks[str_replace(' ', '_', preg_replace("/.*{$pattern}/i", $replace, $textLine))] = true;   // Get the category link from the original text and store it in our list
    }
    $pageObj->textbox1 = trim($cleanText);   // Place the cleaned text into the text box
    return $catLinks;
  }
 */


} // class
