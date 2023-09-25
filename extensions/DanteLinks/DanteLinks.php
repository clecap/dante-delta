<?php

class DanteLinks {

static function debugLog ($text) {
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

public static function onLinkerMakeExternalLink( &$url, &$text, &$link, &$attribs, $linktype ) {
  self::debugLog ("onLinkerMakeExternalLink\n");
  self::debugLog ("  url      =" . $url ."\n"); 
  self::debugLog ("  text     =" . HtmlArmor::getHtml ($text) ."\n"); 
  self::debugLog ("  link     =" . $link ."\n");     
  self::debugLog ("  attribs  =" . print_r ($attribs, true) ."\n");  
  self::debugLog ("  linktype =" . $linktype ."\n");  

  $flag = self::extractAttributes ($text, $attribs);      // implements the neccessary modifications in $text and $attribs

  self::debugLog ("MakeExt: AFTER attributes=" . print_r ($attribs, true) ."\n");  

  if ($flag) {
    self::debugLog ("** external link is modified\n\n\n");
    $aText ="";
    foreach ($attribs as $key => $value) {  $aText .= $key."='".$value."'";}
    $link="<a href='$link' " .$aText. ">$text</a>  ";
  return false;                                    // modify the link
}
   else {return true;}  // do not modify the link
}


// $text        what Mediawiki believes should be shown as text inside of the anchor
// $target      what Mediawiki believes should be the target of the link
// Samples:  [[target]]  make $target and $text equal to  target
// [[target | text]]  overwrites $text with the given text, some special stuff with underlines however.
//public static function onHtmlPageLinkRendererBegin( MediaWiki\Linker\LinkRenderer $linkRenderer, &$target,  &$text, &$attribs, &$query, &$ret ) {
//public static function onHtmlPageLinkRendererBegin( MediaWiki\Linker\LinkRenderer $linkRenderer, &$target,  &$text, &$attribs, &$query, &$ret ) {  
public static function onHtmlPageLinkRendererEnd( MediaWiki\Linker\LinkRenderer $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
  global $wgScript;

  self::debugLog ("LinkRendererEnd:\n");
  self::debugLog ("  text    =" . HtmlArmor::getHtml ($text) ."\n");  
  self::debugLog ("  target  =" . $target ."\n");   
  self::debugLog ("  isKnown =" . $isKnown ."\n");
  self::debugLog ("  attribs =" . print_r ($attribs, true) ."\n");
  self::debugLog ("  ret     =" . $isKnown ."\n\n");

  $myText = HtmlArmor::getHtml($text);  // the text  
  $endPos = strpos ( $myText, "¦");
  if ( $endPos === false ) {return true;}  // text contains no broken pipe: keep anchor as it is

  // we must adjust the notion of isKnown, since the check if we have this page already must focus on the title without the broken pipe
  // we must go from the target (with broken pipe) to the target without the broken pipe
  $targetEndPos = strpos ( $target, "¦");
  $myTarget = substr ( $target, 0, $targetEndPos );
  $myTarget = trim ( $myTarget );                              // target without any broken pip portion
  $targetTitle = Title::newFromText( $myTarget );  // according to doc: uses the namespace encoded into the target
  $targetWP =  WikiPage::factory( $targetTitle );
  if ($targetWP->exists ()) {$isKnown = true;}
  self::debugLog ("  isKnown =" . $isKnown . "   (after correction)\n");

  unset ($attribs['href']);   // remove from MediaWiki attribs the attribute href, as it will be set here
  unset ($attribs['class']);  // remove from MediaWiki attribs the attribute class, as it might be a "new", which is no longer correct after this processing

  $flag = self::extractAttributes ($text, $attribs);
  // self::debugLog ("LinkRendererBegin: after text=" . HtmlArmor::getHtml ($text) ."\n"); self::debugLog ("LinkRendererBegin: after target=" . $target ."\n\n");     
  
  if ($flag) {
    self::debugLog ("  did find some attributes\n\n\n");
    $attribText = "";      // collect the attribute values
    $title = $target;      // what we want to show as title in the 
    $anchorText = substr ( $myText, 0, $endPos );         // what we want to show as anchor(text) portion inside of the <a> link.
    $anchorText = trim ( $anchorText );
    // self::debugLog ("EEEEEEEEEE: ".$anchorText."\n  AND NUMBER IS " .$endPos. "\n");

    foreach ($attribs as $key => $value) {
      // self::debugLog ("Attrib: " . $key. " IS: " . $value ."\n\n");
      if ( strcmp ($key, "title") == 0 ) { $title = $value;}
      else { $attribText .= " " . $key . "='" . addslashes ($value) . "' "; }
    }

    if (!$isKnown) {  // for unknown internal links we need a special formatting
      self::debugLog ("  Writing for unknown page\n");
                     $ret = "<a href='".$wgScript."?title=".$target."&action=edit&redlink=1' class='new' title='". $target. " (page does not exist)' data-dante=''>".$anchorText."</a> ";  }
    else           { 
        self::debugLog ("  Writing for KNOWN page\n");
      $ret = "<a href='".$text."' title='". $myTarget ."' " . $attribText. " >".$anchorText."</a>"; }
    
    self::debugLog ("  Supposed link is: ".$ret."\n");



    return false;           // false: use our new, dante-patched link
  }
  else { 
        self::debugLog ("  did NOT find any attributes\n\n\n");
    return true; }     // true: keep the original anchor as it is 
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

}