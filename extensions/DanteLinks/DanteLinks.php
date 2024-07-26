<?php

class DanteLinks {

static function debugLog ($text) {
  global $wgAllowVerbose;
  if (!$wgAllowVerbose) {return;}
  if($tmpFile = fopen( __DIR__."/DANTELINKS-LOGFILE", 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile); }    // __DIR__ is important so this works for Dante-ndpoints and mediawiki calls
  else {throw new Exception ("debugLog could not log");}
  
}

// TODO: if necessary, add this in later for selection of one out of several Dante wikis.
/*
// inject a selector for other dantewikis provided MediaWiki:Dantewikis is properly populated
public static function onSkinAfterPortlet ( $skin, $portlet, &$html ) {
  global $wgSitename, $wgOut;  

  // self::debugLog ("\n\n---------- Portlet: " . $portlet);

  // inject a selector to other sites depending on the configuration of the page Mediawiki:Dantewikis
  // window.selectorChanged (event) in kundry.js takes care of this in Javascscript side
  if ( strcmp ("personal", $portlet) == 0) {
    $selector = "<select class='personal-wiki-select' onchange='window.selectorChanged(event);' title='Name of the specific dantewiki to distinguish multiple variants. Click to display links to others as registered in MediaWiki:DanteWikis'>";    
    $configPage = "Dantewikis";                                                                 // name of the MediaWiki:Dantewikis configuration page of this thing
    $title      = Title::newFromText( $configPage, NS_MEDIAWIKI );                              // build title object for MediaWiki:DanteWikis

//// see: DanteTree Categories how to fix this


    $wikipage   = new WikiPage ($title);                                                        // get the WikiPage for that title
    $contentObject = $wikipage->getContent();                                                   // and obtain the content object for that
    if ($contentObject ) {                                                                      // IF we have found a content object for this thing
      $parserOutputObject = $contentObject->getParserOutput ($title, null, null, true);         // parse the content object on this page
      $options = array( 'unwrap' =>true, 'wrapperDivClass' => "myWRAPPER" );
      $code = $parserOutputObject->getText ( $options );  
       
     self::debugLog ("Before matching: " . $code . "\n");  
      preg_match ('/<pre>(.*)<\/pre>/ism', $code, $matches);  
      self::debugLog ("MATCH: " . print_r ($matches, true). "\n");
      
      $arr = json_decode ($matches[1], true);
      
      // ensure that array contains the current site
      $found = false;
      foreach ($arr as $val) {if (strcmp ($val["name"], $wgSitename) == 0) {$found = true;}}
        if (!$found) {      
        $obj = array();
        $obj["name"] = $wgSitename;       $obj["class"] = "";   $obj["base"] = "";
        array_unshift ( $arr, $obj );      
      }
            
      if (is_array ($arr) && count ($arr) > 0) {
        foreach ($arr as $val) {
          $name  = $val["name"];
          $class = $val["class"];
          $base  = $val["base"];
          $selected = ($name == $wgSitename ? "selected"  : "");
          $selector .= "<option data-class='$class' data-base='$base' data-name='$name' value='$name' $selected>$name</option>";
        }
        $selector .= "</select>";    
        $html =  $selector . $html;
        return true;
      }
      else {
        return false;
      }
    }  
  }
}

*/





// &$url: The URL of the external link
// &$text: The link text that would normally be displayed on the page
// &$link: The link HTML if you choose to override the default.
// &$attribs: Link attributes (added in MediaWiki 1.15, r48223)
// $linktype: Type of external link, e.g. 'free', 'text', 'autonumber'. Gets added to the css classes. (added in MediaWiki 1.15, r48226)
public static function onLinkerMakeExternalLink( &$url, &$text, &$link, &$attribs, $linktype ) {
  global $wgServer, $wgScriptPath;
  global $wgAllowVerbose; $VERBOSE = true && $wgAllowVerbose;

    self::debugLog ("onLinkerMakeExternalLink called, parameters seen are: \n");
    self::debugLog ("  url      =" . $url ."\n"); 
    self::debugLog ("  text     =" . HtmlArmor::getHtml ($text) ."\n"); 
    self::debugLog ("  link     =" . $link ."\n");     
    self::debugLog ("  attribs  =" . print_r ($attribs, true) ."\n");  
    self::debugLog ("  linktype =" . $linktype ."\n\n\n");  

    // some links might have looked like external hmlt links to the mediawiki parser, since they started as a normal URL
    // however, we do not want them to display the markup used for external links (ie the specific icon for it)
    if ( str_starts_with ($url, $wgServer.$wgScriptPath."/" ) ||
         str_starts_with ($url, "javascript:")
    ) { $attribs["class"] = str_replace ("external", "", $attribs["class"]); }

  if ( str_ends_with ($text, "\w")) { $attribs["target"] = "_window"; $attribs["class"] .= " windowlink";  $text= rtrim (substr ($text,0, strlen($text)-2));  }
  if ( str_ends_with ($text, "\s")) { $attribs["target"] = "_side";   $attribs["class"] .= " windowlink";  $text= rtrim (substr ($text,0, strlen($text)-2));  }
  if ( str_ends_with ($text, "\S")) { $attribs["target"] = "_Side";   $attribs["class"] .= " windowlink";  $text= rtrim (substr ($text,0, strlen($text)-2));  }


  $text = str_replace ("\\|", "¦", $text);  //    \| is treated the same as a broken pipe symbol

  $flag = self::extractAttributes ($text, $attribs);      // implements the neccessary modifications in $text and $attribs
  // self::debugLog ("MakeExt: AFTER attributes=" . print_r ($attribs, true) ."\n");  

  if ($flag) {
    $aText ="";
    foreach ($attribs as $key => $value) {  $aText .= $key."='".$value."'";}
    $link="<a href='$link' " .$aText. ">$text</a>  ";
  return false;                                    // modify the link
}
   else {return true;}                             // do not modify the link
}

// Called when generating internal and interwiki links in LinkRenderer
// $text        what Mediawiki believes should be shown as text inside of the anchor
// $target      what Mediawiki believes should be the target of the link
// Samples:  [[target]]  make $target and $text equal to  target
// [[target | text]]  overwrites $text with the given text, some special stuff with underlines however.
//public static function onHtmlPageLinkRendererBegin( MediaWiki\Linker\LinkRenderer $linkRenderer, &$target,  &$text, &$attribs, &$query, &$ret ) {
//public static function onHtmlPageLinkRendererBegin( MediaWiki\Linker\LinkRenderer $linkRenderer, &$target,  &$text, &$attribs, &$query, &$ret ) {  
public static function onHtmlPageLinkRendererEnd( MediaWiki\Linker\LinkRenderer $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
  global $wgScript;

  self::debugLog ("HtmlPageLinkRendererEnd: (internal and interwiki links) 1 \n");
  self::debugLog ("  text    =" . HtmlArmor::getHtml ($text) ."\n");  
  self::debugLog ("  target  =" . $target ."\n");   
  self::debugLog ("  isKnown =" . $isKnown ."\n");
  self::debugLog ("  attribs =" . print_r ($attribs, true) ."\n");
  self::debugLog ("  ret     =" . $isKnown ."\n\n\n");

  return true;  // Do not modify

  $myText = HtmlArmor::getHtml($text);       // the text of the anchor 
  $endPos = strpos ( $myText, "¦");          // search for a broken pipe extension symbol
  $snipInfo = self::getSnipInfo ($target);   // search for snippet info

  if ( $endPos === false && strlen ( $snipInfo ) == 0 ) {return true; }  // return unmodified link

  // we must adjust the notion of isKnown, since the check if we have this page already must focus on the title without the broken pipe
  // we must go from the target (with broken pipe) to the target without the broken pipe
  $targetEndPos = strpos ( $target, "¦");
  $myTarget = substr ( $target, 0, $targetEndPos );
  $myTarget = trim ( $myTarget );                  // target without any broken pip portion
  $targetTitle = Title::newFromText( $myTarget );  // according to doc: uses the namespace encoded into the target

  if ($targetTitle === null) { 
    $isKnown = false;
    self::debugLog ("DanteLinks: targetTitle not found for: " . $myTarget);
  }
  else {
    $targetWP =  WikiPage::factory( $targetTitle );
    if ($targetWP->exists ()) {$isKnown = true;}
  }

  self::debugLog ("  isKnown =" . $isKnown . "   (after correction)\n");

  unset ($attribs['href']);   // remove from MediaWiki attribs the attribute href, as it will be set here
  unset ($attribs['class']);  // remove from MediaWiki attribs the attribute class, as it might be a "new", which is no longer correct after this processing

  $flag = self::extractAttributes ($text, $attribs); // did we obtain additional attributes ?
  // self::debugLog ("LinkRendererBegin: after text=" . HtmlArmor::getHtml ($text) ."\n"); self::debugLog ("LinkRendererBegin: after target=" . $target ."\n\n");     
  
  self::debugLog ("  BEFORE flag test\n");

  if ($flag) {
    self::debugLog ("  did find some attributes\n\n\n");
    $attribText = "";      // collect the attribute values
    $title = $target;      // what we want to show as title in the 
    $anchorText = substr ( $myText, 0, $endPos );         // what we want to show as anchor(text) portion inside of the <a> link.
    $anchorText = trim ( $anchorText );

    foreach ($attribs as $key => $value) {
      // self::debugLog ("Attrib: " . $key. " IS: " . $value ."\n\n");
      if ( strcmp ($key, "title") == 0 ) { $title = $value;}
      else { $attribText .= " " . $key . "='" . addslashes ($value) . "' "; }
    }

    if (!$isKnown) {  // for unknown internal links we need a special formatting
      self::debugLog ("  Writing for unknown page\n");
      $ret = "<a href='".$wgScript."?title=".$target."&action=edit&redlink=1' class='new'  $snipInfo  title='". $target. " (page does not exist!)'>".$anchorText."</a> ";  }
    else           { 
        self::debugLog ("  Writing for KNOWN page\n");
      $ret = "<a href='".$text."' $snipInfo  title='". $myTarget ."' " . $attribText. ">".$anchorText."</a>"; }
    
    self::debugLog ("  Supposed link is: ".$ret."\n");
    return false;           // false: use our new, dante-patched link
  }

  else {  self::debugLog ("  did NOT find any attributes\n\n\n");
    $anchorText = $myText;
    $ret = "<a href='".$wgScript."?title=".$target."&action=edit&redlink=1' class='new'  $snipInfo  title='". $target. " (page does not exist!!)' >".$anchorText."</a> ";

    return false;  // use our new anchor form
    return true;      // true: keep the original anchor as it is 
  }

}


// TODO: check for injection problems. can the string injected into data-* do some rubbish somehow when containing quotes?
private static function getSnipInfo ($target) {
  if (str_starts_with ($target, "Snip:") ) { return "data-snip='$target'";}         // target starts with Snip:  as namespace indication

  // Ok, we are not linking to Snip: explicitely but there still might be a snip page for where we are linking to
  $title = Title::newFromText("Snip:" . $target);
  if ( !$title )             { self::debugLog ("Title not valid error for target=Snip:$target");  return ""; }
  if ( !$title->isKnown() )  { self::debugLog ("Title not known for target=Snip:$target");        return ""; }
  $wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle($title);
  if (!$wikiPage->exists())  { self::debugLog ("Snip Page does not exist for Snip:$target"); return "";}
  return "data-snip='$title'"; 
}



// $text is a string or Armor object which may contain a broken pipe symbol
// $attribs is an array of attributes
// return false if we did not find a match
private static function extractAttributes ( &$text, &$attribs ) {
  $text = HtmlArmor::getHtml ($text);                    // need a text here but text might also be an HtmlArmor object.
  if ( $text === null ) {return false;} 

  $arr  = preg_split( '/¦/u', $text );     // split the input text on the broken pipe symbol;  need u for unicode matching
  //self::debugLog ("*** extractAttributes: preg_split input:  ".$text."\n");
  //self::debugLog ("*** extractAttributes: preg_split output: ".print_r ($arr, true)." with length ". count($arr)."\n\n\n");

  if ( count($arr) < 2 ) {return false;}               // did not find a match

  $text = array_shift( $arr );             // the text to be used for the link is the part before the first vertical bar
    
  foreach ( $arr as $a ) {                 // iterate over the remaining portions as they are seperated by a pipe symbol ¦
    $pair = explode( '=', $a );           
    if ( isset( $pair[1] ) ) {            // we found an x=y form
      if (  in_array( trim($pair[0]), array ('class', 'style') ) ) {  // for class and style we AMEND existing values
        if ( isset( $attribs[trim($pair[0])] ) ) {$attribs[trim($pair[0])] = $attribs[trim($pair[0])] . ' ' . trim($pair[1]);}  // if set, amend
        else                                     {$attribs[trim($pair[0])] = trim($pair[1]); }                                  // if not yet set: set freshly                          
      }
      else if ( in_array( trim($pair[0]), array ('title', 'target') ) ) { $attribs[trim($pair[0])] = trim($pair[1]); }    // found an attribute for a set freshly strategy     
      else {}                                                                                                             // other attribute names are ignored
    }
    else { $attribs["data-other"]= trim($a); }  // if it is only a value and NOT an x=y structure, then place the value into data-other 
  }
  return true;
}





public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) { 
  global $wgServer, $wgScriptPath;
  $out->addModules('ext.dantelinks');
  $out->addHeadItem ("dantelink", "<style>a.windowlink {background-image: url({$wgServer}{$wgScriptPath}/skins/Vector/resources/common/images/link-external-small-rtl-progressive.svg?30a3a) !important;}</style>");
}


}