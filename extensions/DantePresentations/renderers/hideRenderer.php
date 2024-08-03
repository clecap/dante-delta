<?php

class HideRenderer {

// hook function for rendering hidden blocks <hide> as hidden
public static function renderHidden ( $input, array $args, Parser $parser, PPFrame $frame ) {
    return "";
}

// hook function for rendering hidden blocks <hide> as normal, as if they were there.
public static function renderNormal ( $input, array $args, Parser $parser, PPFrame $frame ) {
  $output = $parser->recursiveTagParse( $input, $frame );                                   // the tag works recursively, 
  return $output;
}

public static function renderAdaptive ( $input, array $args, Parser $parser, PPFrame $frame ) {
    $output = $parser->recursiveTagParse( $input, $frame );                                   // the tag works recursively, 
      // see https://stackoverflow.com/questions/7639863/mediawiki-tag-extension-chained-tags-do-not-get-processed 
      // see https://www.mediawiki.org/wiki/Manual%3aTag_extensions#How_do_I_render_wikitext_in_my_extension.3F
    $none   = "";
    $hidden = "<div class='seHidden' style='border:2px solid red; border-radius:10px; padding:20px;background-color:yellow;'>" . $output . "</div> XXXXXXXXX";
   // $hint   = "<div style='color:red;background-color:yellow; border-radius:10px; border:2px solid red;'>&nbsp;</div>";
   // $script = "<script> var ele = document.currentScript; ele.previousSibling.style.display='block';</script>";
   // $scriptTwo = "<script>if (RLCONF.wgUserGroups.includes('docent')) {document.currentScript.previousSibling.style.display='block';}";
    return $hidden;
  }


// hook function for renderin hidden blocks <hide> as prominent
public static function renderProminent ( $input, array $args, Parser $parser, PPFrame $frame ) {
    $output = $parser->recursiveTagParse( $input, $frame );                                   // the tag works recursively, 
      // see https://stackoverflow.com/questions/7639863/mediawiki-tag-extension-chained-tags-do-not-get-processed 
      // see https://www.mediawiki.org/wiki/Manual%3aTag_extensions#How_do_I_render_wikitext_in_my_extension.3F
    $hidden = "<div style='border:2px solid red; border-radius:10px; padding:20px;background-color:yellow;'>" . $output . "</div>";
    return $hidden;
  }






}