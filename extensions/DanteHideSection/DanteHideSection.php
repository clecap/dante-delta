<?php

class DanteHideSection {

public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
  $out->addModules( 'ext.dantehideSection' );                                    // injects complete module, css and js


  // need this as inline to prevent FOUC
  // need the .mw-parser-output to prevent any other parts of the skin to be counted (this must be a feature present initially, not added later by js)
  // need the #mw-toc-heading rule to prevent the TOC to show a counter as well
 $out->addInlineStyle('
  .mw-parser-output h1 {counter-reset: h2-counter; counter-increment: h1-counter; }
  .mw-parser-output h1 .mw-headline::before {content: counter(h1-counter) ". "; }
  .mw-parser-output h2 {counter-reset: h3-counter; counter-increment: h2-counter; }
  .mw-parser-output h2 .mw-headline::before {content: counter(h1-counter) "." counter(h2-counter) ". "; }
  .mw-parser-output h3 { counter-reset: h4-counter; counter-increment: h3-counter; }
  .mw-parser-output h3 .mw-headline::before { content: counter(h1-counter) "." counter(h2-counter) "." counter(h3-counter) ". ";  }
  .mw-parser-output h4 {  counter-reset: h5-counter;   counter-increment: h4-counter; }
  .mw-parser-output h4 .mw-headline::before {content: counter(h1-counter) "." counter(h2-counter) "." counter(h3-counter) "." counter(h4-counter) ". "; }
  .mw-parser-output h5 {counter-increment: h5-counter;}
  .mw-parser-output h5 .mw-headline::before {content: counter(h1-counter) "." counter(h2-counter) "." counter(h3-counter) "." counter(h4-counter) "." counter(h5-counter) ". "; }
  #mw-toc-heading::before {content: "";}
');


/*
$out->addInlineStyle('
  .mw-parser-output h1 {counter-reset: h2-counter; counter-increment: h1-counter; }
  .mw-parser-output h1::before {content: counter(h1-counter) ". "; }
  .mw-parser-output h2 {counter-reset: h3-counter; counter-increment: h2-counter; }
  .mw-parser-output h2::before {content: counter(h1-counter) "." counter(h2-counter) ". "; }
  .mw-parser-output h3 { counter-reset: h4-counter; counter-increment: h3-counter; }
  .mw-parser-output h3::before { content: counter(h1-counter) "." counter(h2-counter) "." counter(h3-counter) ". ";  }
  .mw-parser-output h4 {  counter-reset: h5-counter;   counter-increment: h4-counter; }
  .mw-parser-output h4::before {content: counter(h1-counter) "." counter(h2-counter) "." counter(h3-counter) "." counter(h4-counter) ". "; }
  .mw-parser-output h5 {counter-increment: h5-counter;}
  .mw-parser-output h5::before {content: counter(h1-counter) "." counter(h2-counter) "." counter(h3-counter) "." counter(h4-counter) "." counter(h5-counter) ". "; }
  #mw-toc-heading::before {content: "";}
');


*/



  return true;
}

}