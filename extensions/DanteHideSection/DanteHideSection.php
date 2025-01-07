<?php

class DanteHideSection {


public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
  $out->addModules( 'ext.dantehideSection' );                                    // injects complete module, css and js
  return true;
}


public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $parserOutput ) {
  $out->addHTML ("SOMESTUFF");
}




public static function onSkinEditSectionLinks( $skin, $title, $section, $tooltip, &$links, $lang ) {

  return; 

  $hidetext  = wfMessage( 'hidesection-hide' )->text();
  $showtext  = wfMessage( 'hidesection-show' )->text();
  $titletext = wfMessage( 'hidesection-hidetitle' )->text();



 if ($section === 0) {
    $links['hidesection'] = [       // Add hide/show link next to edit links
      'targetTitle' => $title,
      'text' => "TESTTER",
      'attribs' => [
          "class" => "hidesection-link internal",
          "data-show" => $showtext,
          "data-hide" => $hidetext,
          "data-section" => $section,
          "title" => $titletext,
       ],
       'query' => array(),
       'options' => array(),
    ];
  }




 
  if ($section !== 0) {
    $links['hidesection'] = [       // Add hide/show link next to edit links
      'targetTitle' => $title,
      'text' => $hidetext,
      'attribs' => [
          "class" => "hidesection-link internal",
          "data-show" => $showtext,
          "data-hide" => $hidetext,
          "data-section" => $section,
          "title" => $titletext,
       ],
       'query' => array(),
       'options' => array(),
    ];
  }

        // Add hide all/show all link on first section
        if ($section == 1) {
            $showall  = wfMessage( 'hidesection-showall' )->text();
            $hideall  = wfMessage( 'hidesection-hideall' )->text();
            $titleall = wfMessage( 'hidesection-hidealltitle' )->text();

            $links['hidesectionall'] = [
                'targetTitle' => $title,
                'text' => $hideall,
                'attribs' => [
                    "class" => "hidesection-all internal",
                    "data-show" => $showall,
                    "data-hide" => $hideall,
                    "title" => $titleall,
                    ],
                'query' => array(),
                'options' => array(),
            ];
        }
    }



}


