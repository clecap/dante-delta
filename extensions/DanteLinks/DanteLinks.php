<?php

use MediaWiki\MediaWikiServices;

class DanteLinks {

static function debugLog ($text) {
  global $wgAllowVerbose;
  if (!$wgAllowVerbose) {return;}
  if($tmpFile = fopen( __DIR__."/DANTELINKS-LOGFILE", 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile); }    // __DIR__ is important so this works for Dante-ndpoints and mediawiki calls
  else {throw new Exception ("debugLog could not log");}
}


// &$url: The URL of the external link
// &$text: The link text that would normally be displayed on the page
// &$link: The link HTML if you choose to override the default.
// &$attribs: Link attributes (added in MediaWiki 1.15, r48223)
// $linktype: Type of external link, e.g. 'free', 'text', 'autonumber'. Gets added to the css classes. (added in MediaWiki 1.15, r48226)
public static function onLinkerMakeExternalLink(  &$url, &$text, &$link, &$attribs, $linktype ) {
  global $wgServer, $wgScriptPath;
  global $wgAllowVerbose; $VERBOSE = false && $wgAllowVerbose;

  if ($VERBOSE) {
    self::debugLog ("onLinkerMakeExternalLink called, parameters seen are: \n");
    self::debugLog ("  url      =" . $url ."\n"); 
    self::debugLog ("  text     =" . HtmlArmor::getHtml ($text) ."\n"); 
    self::debugLog ("  link     =" . $link ."\n");     
    self::debugLog ("  attribs  =" . print_r ($attribs, true) ."\n");  
    self::debugLog ("  linktype =" . $linktype ."\n\n\n");  
  }

    // some links might have looked like external hmlt links to the mediawiki parser, since they started as a normal URL
    // however, we do not want them to display the markup used for external links (ie the specific icon for it)
    if ( str_starts_with ($url, $wgServer.$wgScriptPath."/" ) ||
         str_starts_with ($url, "javascript:")
    ) { $attribs["class"] = str_replace ("external", "", $attribs["class"]); }

  // implement some shorthand notations for the target
  if ( str_ends_with ($text, "\w")) { $attribs["target"] = "_window";    $text= rtrim (substr ($text,0, strlen($text)-2)) . ' ◾' ;  }
  if ( str_ends_with ($text, "\s")) { $attribs["target"] = "_sside";     $text= rtrim (substr ($text,0, strlen($text)-2)) . ' ▪' ;  }
  if ( str_ends_with ($text, "\S")) { $attribs["target"] = "_lside";     $text= rtrim (substr ($text,0, strlen($text)-2)) . ' ▮';  }

  $text = str_replace ("\\|", "¦", $text);  //    \| is treated the same as a broken pipe symbol

  $flag = self::extractAttributes ($text, $attribs);      // implements the neccessary modifications in $text and $attribs
  // self::debugLog ("MakeExt: AFTER attributes=" . print_r ($attribs, true) ."\n");  

  if ($flag) {
    $aText ="";
    foreach ($attribs as $key => $value) {  $aText .= $key."='".$value."'";}
    $link="<a href='$url' " .$aText. ">$text</a>  ";
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
public static function onHtmlPageLinkRendererEnd ( MediaWiki\Linker\LinkRenderer $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
  global $wgScript, $wgServer, $wgScriptPath;
  global $wgAllowVerbose; $VERBOSE = true && $wgAllowVerbose;

  if ($VERBOSE) {
    self::debugLog ("HtmlPageLinkRendererEnd: (internal and interwiki links) 1 \n");
    self::debugLog ("  text    =" . HtmlArmor::getHtml ($text) ."\n");  
    self::debugLog ("  target  =" . $target ."\n");   
    self::debugLog ("  isKnown =" . $isKnown ."\n");
    self::debugLog ("  attribs =" . print_r ($attribs, true) ."\n");
  }

  $text   = HtmlArmor::getHtml($text);        // the text of the anchor 
  $text   = str_replace ("\\|", "¦", $text);  //    \| is treated the same as a broken pipe symbol
  // if ($VERBOSE) { self::debugLog ("After replace text is: $text \n");}

  $parts = explode('¦', $text, 2);
  $beforeBrokenPipe = $parts[0];
  $afterBrokenPipe = isset($parts[1]) ? $parts[1] : '';
  $snipInfo = self::getSnipInfo ($target);     // search for snippet info

  self::debugLog ("beforeBrokenPipe=($beforeBrokenPipe) afterBrokenPipe=($afterBrokenPipe) \n\n");

  // internal links, when clicked for opening in a side or smaller window must be opened by a different endpoint, as we do not want to see the entire DanteWiki page
  // but a reduced skin DanteWiki page. The relevant information is provided in attribute data-useendpoint of the link
  $attribs["data-useendpoint"] = $wgServer."/".$wgScriptPath . "/extensions/DantePresentations/endpoints/showEndpoint.php";


  /* shorthands in title */
  if (trim ($text) == "\w") { $attribs["target"] = "_window";  $text = $target . ' ◾'; return true;} 
  if (trim ($text) == "\s") { $attribs["target"] = "_sside";   $text = $target . ' ▪'; return true;} 
  if (trim ($text) == "\S") { $attribs["target"] = "_lside";   $text = $target . ' ▮'; return true;} 
  if (trim ($text) == "\t") { $attribs["data-snip"] = "_lside";   $text = $target . ' ONE'; return true;}    // tooltip

  // implement some shorthand notations for the target
  if ( str_ends_with ($text, "\w")) { $attribs["target"] = "_window";  /* $attribs["class"] .= " windowlink"; */  $text= rtrim (substr ($text,0, strlen($text)-2)) . ' ◾';  }
  if ( str_ends_with ($text, "\s")) { $attribs["target"] = "_sside";   /* $attribs["class"] .= " windowlink"; */  $text= rtrim (substr ($text,0, strlen($text)-2)) . ' ▪';  }
  if ( str_ends_with ($text, "\S")) { $attribs["target"] = "_lside";   /* $attribs["class"] .= " windowlink"; */  $text= rtrim (substr ($text,0, strlen($text)-2)) . ' ▮';  }

  if ( str_ends_with ($text, "\\t")) { $attribs["data-snip"] = $target;  $text= rtrim (substr ($text,0, strlen($text)-2)) . ' ♢'; }    // tooltip

  if ( $afterBrokenPipe === '' && strlen ( $snipInfo ) == 0 ) {
    if ($VERBOSE) {
      self::debugLog ("NO broken pipe extension and NO snippet info found: returning with those values:\n\n");
      self::debugLog ("  text    =" . $text ."\n");  
      self::debugLog ("  isKnown =" . $isKnown ."\n");
      self::debugLog ("  attribs =" . print_r ($attribs, true) ."\n");
    }
    return true; }   // return modified link

  // if we arrive here then there is either a SNIP or a broken pipe situation

 


  unset ($attribs['href']);   // remove from MediaWiki attribs the attribute href, as it will be set here
  unset ($attribs['class']);  // remove from MediaWiki attribs the attribute class, as it might be a "new", which is no longer correct after this processing

  $attribs['data-snip'] = $snipInfo;

  $flag = self::extractAttributes ($text, $attribs); // did we obtain additional attributes ?
  

 if ($beforeBrokenPipe==='') { if ($VERBOSE) { self::debugLog ("beforeBrokenPipe there is no text so using target as anchor text\n");}
    $text=$target; }
  else {  if ($VERBOSE) { self::debugLog ("beforeBrokenPipe has text - using this as anchor text \n");}
    $text=$beforeBrokenPipe;
  }


  if ($VERBOSE) {  self::debugLog ("extractAttributes returned: $flag and we have $text and " . print_r ($attribs, true) . "\n");}
  
  return true; // use the anchor with the modified attributes and variables
               // return false would mean: use value of ret as anchor

/*
  if ($flag) {             if ($VERBOSE) {self::debugLog ("  did find some attributes\n\n\n");}
    $attribText = "";      // collect the attribute values
    $title = $target;      // what we want to show as title
    $anchorText = substr ( $myTarget, 0, $endPos );         // what we want to show as anchor(text) portion inside of the <a> link.
    $anchorText = trim ( $anchorText );

    foreach ($attribs as $key => $value) {
      // self::debugLog ("Attrib: " . $key. " IS: " . $value ."\n\n");
      if ( strcmp ($key, "title") == 0 ) { $title = $value;}
      else { $attribText .= " " . $key . "='" . addslashes ($value) . "' "; }
    }

    if (!$isKnown) {  // for unknown internal links we need a special formatting
      if ($VERBOSE) {self::debugLog ("  Writing for unknown page\n");}
      $ret = "<a href='".$wgScript."?title=".$target."&action=edit&redlink=1' class='new'  $snipInfo  title='". $target. " (page does not exist!)'>".$anchorText."</a> ";  }
    else           { 
        self::debugLog ("  Writing for KNOWN page\n");
      $ret = "<a href='".$text."' $snipInfo  title='". $myTarget ."' " . $attribText. ">".$anchorText."</a>"; }
    
    self::debugLog ("  Supposed link is: ".$ret."\n");
    return false;           // false: use our new, dante-patched link
  }

  else {                    if ($VERBOSE) {self::debugLog ("  did NOT find any attributes\n\n\n");}
    $anchorText = $myText;
    $ret = "<a href='".$wgScript."?title=".$target."&action=edit&redlink=1' class='new'  $snipInfo  title='". $target. " (page does not exist!!)' >".$anchorText."</a> ";
    

    return false;     // use our new anchor form
    return true;      // true: keep the original anchor as it is 
  }


*/


}

// given a text $myTarget for a Dantewiki page, return true if this is known and false if not
private static function doesExist ( $myTarget ) {
  global $wgAllowVerbose; $VERBOSE = true && $wgAllowVerbose;
  $targetTitle  = Title::newFromText( $myTarget );     // according to doc: uses the namespace encoded into the target
  if ($targetTitle === null) {        if ($VERBOSE) {self::debugLog ("DanteLinks: Title not found for: " . $myTarget. "\n");}
     return false;
  }
  else {                             if ($VERBOSE) {self::debugLog ("DanteLinks: Title found for: " . $myTarget. "\n");}
    $targetWP =  WikiPage::factory( $targetTitle );
    if ($targetWP->exists ()) {      if ($VERBOSE) {self::debugLog ("DanteLinks: WikiPage found for: " . $myTarget. "\n");}
      return true;}
    else {                           if ($VERBOSE) {self::debugLog ("DanteLinks: WikiPage not found for: " . $myTarget. "\n");}
      return false;
    }
  }
}






// given the target of a link, extract snippet information

// TODO: check for injection problems. can the string injected into data-* do some rubbish somehow when containing quotes?
private static function getSnipInfo ($target) {
  global $wgAllowVerbose; $VERBOSE = true && $wgAllowVerbose;

  // CASE 1: The target is itself a Snip target in the Snip namespace - we directly link to a snippet
  if (str_starts_with ($target, "Snip:") ) { return $target;}         // target starts with Snip:  as namespace indication

  // CASE 2: The target is not by itself a Snip target but the target itself might HAVE a snip page
  $title = Title::newFromText("Snip:" . $target);
  if ( !$title )             { if ($VERBOSE) {self::debugLog ("Page Snip:$target is not valid\n");}        return ""; }
  if ( !$title->isKnown() )  { if ($VERBOSE) {self::debugLog ("Page Snip:$target is not known\n");}        return ""; }
  $wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle($title);
  if (!$wikiPage->exists())  { if ($VERBOSE) {self::debugLog ("WikiPage Snip:$target does not exist\n");}  return "";}
  return $target; 
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

// $out->addHeadItem ("dantelink", $text);

// this would get added before the category links, still as part of the article
/*
  $out->addHTML ("<details>
  <summary>Details</summary>
  Something small enough to escape casual notice.
</details>");
*/

}




}