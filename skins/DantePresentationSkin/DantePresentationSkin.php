<?php

// logging function for development
function logger ($text) {
  $fileName = dirname(__FILE__) ."/LOGFILE";
  if($tmpFile = fopen( $fileName , 'a')) {fwrite($tmpFile, $text);  fclose($tmpFile);}  
  else {throw new Exception ("local log could not log to $fileName"); }
}

use MediaWiki\MediaWikiServices;

class DantePresentationSkin extends SkinMustache {

/**
 *  This function provides additional data which we want to pass on to the mustache template.
 *  It is the only entry point into this class
 */
public function getTemplateData() {
  $fileName = dirname(__FILE__) ."/LOGFILE"; unlink ($fileName);    // clear logfile 

//  $this->debu();

  $converted = $this->convert ();
  $this->allMySections = $this->getAllSections(); // cache result from getSections

  // logger ( "Page Title: " . $this->thispage . "\n");

  $wikiPage = $this->getWikiPage();
  $po       = $wikiPage ->getParserOutput();  
  //$sections = $po->getSections();

  $tes = $this->getSectionHTML ( 0 );
  // logger ( "*** Null section: " . $tes );

  $data = parent::getTemplateData();
  $data['html-hello'] = '<strong>HELLO WORLD</strong>';

  $data["foreachportion"] = $converted;

  return $data;
}

private function myHeader (DOMNode $node)  { return $node->ownerDocument->saveHTML ($node); }

private function myCopy (DOMNode $node)    { return $node->ownerDocument->saveHTML ($node); }

// returns the innerHTML contents of a DOMNode
private function DOMinnerHTML(DOMNode $element) {
  $innerHTML = ""; 
  $children  = $element->childNodes;
  foreach ($children as $child) { $innerHTML .= $element->ownerDocument->saveHTML($child);}
  return $innerHTML; 
}







/**
 * Convert the HTML version of an article into a fragment suitable for reveal.js interpretation.
 * Advantage:      We have all the data of the entire HTML page (eg the section numbering) correct, as it is the result of a single parser run over the entire input
 * Disadvantage:   If the slide author uses, say, an h2 element, we cannot distinguish this h2 element from the h2 element generated by the parser for marking the section heading
 * 
 * @return string
 */
private function convert () {
  global $wgAllowVerbose; $VERBOSE = false && $wgAllowVerbose;
  $html = $this->myGetArticle()->getParserOutput()->getText( array ( "allowTOC" => false, "enableSectionEditLinks" => false ) );  // get the html text of this article

  $doc = new DOMDocument();                              // prepare an instance of an HTML parser
  $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);     // must enforce proper encoding

  // this is the structure which the parser returns:
  $encNode     = $doc->childNodes->item (0);
  $docTypeNode = $doc->childNodes->item (1);
  $htmlNode    = $doc->childNodes->item (2);

  $bodyNode    = $htmlNode->firstChild;               //  logger ("bodyNode is: " . " tag: " .$bodyNode->tagName." name: " . $bodyNode->nodeName . " type: " . $bodyNode->nodeType . " value: " . $bodyNode->nodeValue . " \n\n");
  $divNode     = $bodyNode->firstChild;               //  logger ("divNode is: " . " tag: " .$divNode->tagName." name: " . $divNode->nodeName . " type: " . $divNode->nodeType . " value: " . $divNode->nodeValue . " \n\n");
  $childNodes  = $divNode->childNodes;                //  logger ("iterating  "  .count($childNodes)." nodes: \n\n");

  $state = 0;
  $output ="";

  foreach($childNodes as $node) {  //    logger ("Node: " . $node->nodeName . "  type: " . $node->nodeType . " value: ". $node->nodeValue . " textcontent: " . $node->textContent . "\n");
    switch ($state) {
      case 0:
        if ( strcmp($node->nodeName, "h2") == 0 )      { if ($VERBOSE) {logger ("State 0, h2 found\n");}  $output .= "<section class='dante1'>". $this->myHeader ($node);   $state = 1;}
        else if ( strcmp($node->nodeName, "h3") == 0 ) { if ($VERBOSE) {logger ("State 0, h3 found\n"); }                                                       $state = 0;}
        else                                           { if ($VERBOSE) {logger ("State 0, other found\n");}                                                     $state = 0;}
        break;

      case 1:
        if ( strcmp($node->nodeName, "h2") == 0 )      { if ($VERBOSE) {logger ("State 1, h2 found\n");}      $output .= "</section><section class='dante1'>".$this->myHeader ($node);   $state = 1;}
        else if ( strcmp($node->nodeName, "h3") == 0 ) { if ($VERBOSE) { logger ("State 1, h3 found\n");}     $output .= "<section class='dante2'>".$this->myHeader ($node);             $state = 2;}
        else                                           { if ($VERBOSE) {logger ("State 1, other found\n");}   $output .= $this->myCopy ($node);                           $state = 1;}
        break;

      case 2:
        if ( strcmp($node->nodeName, "h2") == 0 )       { if ($VERBOSE) {logger ("State 2, h2 found\n");}     $output .= "</section></section><section>".$this->myHeader ($node);  $state = 1;}
        else if ( strcmp($node->nodeName, "h3") == 0 )  { if ($VERBOSE) {logger ("State 2, h3 found\n");}     $output .= "</section><section class='dante1'>".$this->myHeader ($node);            $state = 2;}
        else                                            { if ($VERBOSE) {logger ("State 2, other found\n");}  $output .= $this->myCopy ($node);                                    $state = 2;}
        break;
    } // end switch
  } // end foreach
  return $output . "</section>";  // at the end, close everything
} // end function




/** IMPORTANT TODO: IMPORTANT
 * 
 * 
 * We have to redesign this. The getSection does not use structure or hierarchy but just a linear numbering of sections, subsection and subsubsections etc. alike on the same footing 
 * 
 * See   https://www.mediawiki.org/wiki/Topic:X7u7t2nyltnqywme
 * 
 */

private function debu () { // just for debugging and development
  $content = $this->getRev()->getContent( \MediaWiki\Revision\SlotRecord::MAIN );                  // get the WikitextContent of the article
  logger ("---- WIKITEXT of the ARTICLE ----\n". $content->getText(). "\n ------------\n\n\n\n");
 
  $sections = $this->getSections();
  logger("---- SECTION STRUCTURE of the ARTICLE ----\n" . print_r ($sections, true) . "\n --------\n\n\n\n");

    try {  // we might not have 6 sections 
      logger("--- SECTION 0 TEXT ----\n:" . print_r($content->getSection("0")->getText(), true) . "\n --------\n\n\n\n");
      logger("--- SECTION 1 TEXT ----\n:" . print_r($content->getSection("1")->getText(), true) . "\n --------\n\n\n\n");
      logger("--- SECTION 2 TEXT ----\n:" . print_r($content->getSection("2")->getText(), true) . "\n --------\n\n\n\n");
      logger("--- SECTION 3 TEXT ----\n:" . print_r($content->getSection("3")->getText(), true) . "\n --------\n\n\n\n");
      logger("--- SECTION 4 TEXT ----\n:" . print_r($content->getSection("4")->getText(), true) . "\n --------\n\n\n\n");
      logger("--- SECTION 5 TEXT ----\n:" . print_r($content->getSection("5")->getText(), true) . "\n --------\n\n\n\n");
      logger("--- SECTION 6 TEXT ----\n:" . print_r($content->getSection("6")->getText(), true) . "\n --------\n\n\n\n");
    } catch ( Throwable $ex)   {} finally {}

  logger ("--- SECTION 0 PARSE ----\n:" . $this->partialParse (0) . "\n ---------\n\n\n\n");
  logger ("--- SECTION 1 PARSE ----\n:" . $this->partialParse (1) . "\n ---------\n\n\n\n");
  logger ("--- SECTION 2 PARSE ----\n:" . $this->partialParse (2) . "\n ---------\n\n\n\n");
  logger ("--- SECTION 3 PARSE ----\n:" . $this->partialParse (3) . "\n ---------\n\n\n\n");
  logger ("--- SECTION 4 PARSE ----\n:" . $this->partialParse (4) . "\n ---------\n\n\n\n");
  logger ("--- SECTION 5 PARSE ----\n:" . $this->partialParse (5) . "\n ---------\n\n\n\n");




}



// return the Wiki-parsed version of the (linear) section number $sectionIndex
private function partialParse ( $sectionIndex ) {
  $content = $this->getRev()->getContent( \MediaWiki\Revision\SlotRecord::MAIN );                  // get the WikitextContent of the article
  $input = $content->getSection( $sectionIndex )->getText();
  $title =  $this->myGetArticle()->getPage()->getTitle();
  $parserOptions = ParserOptions::newFromContext( $this->getContext() );
  $revId =  $this->getRev()->getId();
  $parseResult = MediaWikiServices::getInstance()->getParser()->parse( $input, $title, $parserOptions, true, true, $revId);
  return $parseResult->getText();
}



protected function getAllSections() {
	$parserOptions = $this->myGetArticle()->getPage()->makeParserOptions( $this->getContext() );
	$parserOutput = $this->myGetArticle()->getPage()->getParserOutput( $parserOptions, $this->getRev()->getId() );
  $mySections = $parserOutput->getSections();
  return $mySections;
}


/**
	 * Get an array of sections
	 *
	 * @return Section[]
	 */
protected function getSections() {

	$parserOptions = $this->myGetArticle()->getPage()->makeParserOptions( $this->getContext() );
	$parserOutput = $this->myGetArticle()->getPage()->getParserOutput( $parserOptions, $this->getRev()->getId() );
  $mySections = $parserOutput->getSections();
  logger ("++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++**** getSections: \n " . print_r ($mySections, true). "\n");

	return array_filter(
			$mySections,
			//
			//  Only level 2 sections are slides
			 // @param $section []
			 // @return bool
			 //
			function ( $section ) {
				if ( $section["toclevel"] == 2 ) {
           //logger ("filter found level 2 section \n");
					// store subsection in memory
          $idx = explode( ".", $section["number"] )[0];
          //logger ("STORING: into " . print_r ($idx, true). "a value of " . print_r ($section, true) . "\n ------------------ \n" );
					$this->subSection[$idx][] = $section;
				}
        //logger ("filter found other level section ");
				return $section["toclevel"] == 1;
			}
		);
	}


	// get string for beginning of a section
	// public function isNotEmpty( $html ) {return trim( strip_tags( $html, 'img' ) );}

	/**
	 * Retrieve the begining of a section
	 *
	 * @param Section|null $section index
	 *
	 * @return string
	 */
/*
	public function getIntro( $section = null ) {
		if ( !$section ) {
			return explode( '<h2>', $this->context->getOutput()->getHTML() )[0] . '</div>';
		}
		$ending = '</div>';
		if ( isset( $this->subSection[$section['number']] ) ) {
			if ( count( $this->subSection[$section['number']] ) < 2 ) {
				$ending = "";
			}
		}
		return explode( '<h3>', $this->getSectionHTML( $section ) )[0] . $ending;
	}
*/

	/**
	 * Generate html for a given section
	 *
	 * @param Section $section index
	 *
	 * @return string
	 */
  /*

	public function getSubSectionHTML( $section ) {
		if ( isset( $this->subSection[$section['number']] ) ) {
			$subSections = $this->subSection[$section['number']];
			$subSectionsHTML = array_map( [ $this, "getSectionHTML" ], $subSections );
			return implode( '</section><section>', $subSectionsHTML );
		}
		return '';
	}
*/


  /** 
   * Get current revision
   * 
   * @return Mediawiki\Revision\RevisionRecord
   */
	public function getRev() { return MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $this->myGetArticle()->getPage()->getTitle() ); }

  // need to access article from here
public function myGetArticle () {return new Article( Title::newFromText( $this->thispage ) ) ;}

public function myGetPage () {}


// get html for a given section index
// $sectionIndex is either a number (3) or a string (3.2)
public function getSectionHTML( $sectionIndex ) {

  logger ("getSectionHTML called for section with index = " . $sectionIndex. "\n");
  $content = $this->getRev()->getContent( \MediaWiki\Revision\SlotRecord::MAIN ); // type: WikitextContent
  // logger ("getSectionHTML found text: " . $content->getText(). "\n");

  $section = $content->getSection( $sectionIndex );

  if ($section === false) {
    return "NO SUCH SECTION FOUND in getSectionHTML of DantePresentationskin ($sectionIndex)";   
  }
  if ($section === null)  {return "*** SECTIONS not supported";}

  $template = $content->getSection( $sectionIndex )->getText();

  $text = MediaWikiServices::getInstance()->getParser()->parse(
    $template,
    $this->myGetArticle()->getPage()->getTitle(),
    ParserOptions::newFromContext( $this->getContext() ),
    true,
    true,
    $this->getRev()->getId()
  );
  return $text->getText();
}



protected function foreachPortion () {
  $back = "";

  //logger ("foreachPortion starting to iterate sections \n");
  foreach ($this->allMySections as $heading) {
     //logger ("**** Found section: " . print_r ($heading, true) ."\n");
    $back .= "<section>";
    if ( isset( $this->subSection[$heading['number']] ) ) {
       //logger ("Case one\n");
      if ($this->isNotEmpty($this->getIntro($heading['number']))) {$back .= "<section>" . $this->getIntro($heading['number']). "</section>"; }
      $back .= "<section>" . $this->getSubSectionHTML( $heading['number'] ) ."</section>";
    }
    else { 
      //logger ("Case two \n");
      $back .= $this->getSectionHTML( $heading['number'] ); } 
    $back .= "</section>";
 } // end foreach
  return $back;
} // end function




public function isNotEmpty( $html ) {return trim( strip_tags( $html, 'img' ) ); }


/**
	 * Retrieve the begining of a section
	 *
	 * @param Section|null $section index
	 *
	 * @return string
	 */
public function getIntro( $sectionIndex = null ) {
  if ( !$sectionIndex ) {return explode ( '<h2>', $this->context->getOutput()->getHTML() )[0] . '</div>';}
  $ending = '</div>';
  if ( isset( $this->subSection[$sectionIndex] ) ) {
    if ( count( $this->subSection[$sectionIndex] ) < 2 ) {$ending = "";}
  }
  return explode( '<h3>', $this->getSectionHTML( $sectionIndex ) )[0] . $ending;
}



/**
	 * Generate html for a given section
	 *
	 * @param Section $section index
	 *
	 * @return string
	 */
	public function getSubSectionHTML( $sectionIndex ) {
    //logger ("getSubSectionHTML called with argument: " . print_r ($sectionIndex, true) . "\n");
		if ( isset( $this->subSection[$sectionIndex] ) ) {
			
      $subSections = $this->subSection[$sectionIndex];

  $subSectionIdx = array_map ( function ($s) {return $s["number"];}, $subSections );
  //logger ("subSectionIdx number list: " . print_r ($subSectionIdx, true). "\n");

			$subSectionsHTML = array_map( [ $this, "getSectionHTML" ], $subSectionIdx );
      //logger ("html produced: " . print_r ($subSectionsHTML , true). "\n\n EE-----------------------EE\n\n");
			return implode( '</section><section>', $subSectionsHTML );
		}
		return 'getSubSectionHTML - ----- did not find stuff';
	}






  protected $allMySections;
}  // end class
