<?php

$flag  = false;  // the previous event was a start element

$hiding = false;

$tag = "HIDE";

function XLOG ($text) {
  $fileName = dirname(__FILE__) . "/TAGFILTER-LOGFILE";
  if($tmpFile = fopen( $fileName , 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  
  else {throw new Exception ("LOG could not log to $fileName for extension $extension"); }
}


function startElements($parser, $name, $attrs) {
  global $flag, $hiding, $tag;
  XLOG ("START ($name)\n");
  if ($hiding) {return;}
  if (strcmp ($name, $tag) != 0) {
    fwrite(STDOUT, "<$name");
    foreach ($attrs as $key => $value) {fwrite (STDOUT, " $key=\"$value\"");}
    $flag = true;}
  else {
    $hiding = true;
  }
}

function endElements ($parser, $name) {
  global $flag, $hiding, $tag;
  if (strcmp ($name, $tag)==0) {$hiding = false; return;}
  if ($hiding) {return;}
  XLOG ("END ($name)\n");
  if ($flag) {fwrite (STDOUT, " />");} else {fwrite (STDOUT, "</$name>");}
  $flag = false;
}


function characterData($parser, $data) {  // Called on the text between the start and end of the tags
  global $flag, $hiding, $tag;
  XLOG ("CHAR ($data)\n");
  if ($hiding) {return;}
  if ($flag) { fwrite (STDOUT, ">"); $flag = false;}
  $data = htmlspecialchars ($data);
  fwrite (STDOUT, "$data");
}


function refHandler($parser, string $open_entity_names, string $base, string $system_id, string $public_id) {
  fwrite (STDOUT, "MINE: ($open_entity_names) END");

}




  $parser = xml_parser_create();   // create XML SAX parser
  xml_parser_set_option ($parser, XML_OPTION_CASE_FOLDING, 0); 

  xml_set_element_handler              ($parser, "startElements", "endElements");
  xml_set_character_data_handler       ($parser, "characterData");
  xml_set_external_entity_ref_handler  ($parser, "refHandler");  


  while(!feof(STDIN)){
    $line = fgets(STDIN);
    xml_parse ($parser, $line);
  }
  
  xml_parser_free($parser); // delete the parser

?>