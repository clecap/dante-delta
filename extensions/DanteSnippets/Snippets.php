<?php

/*! 
 *  \author   Clemens H. Cap
 *  \brief    Bundle the static and job specific functionalities. Represent a Job for storing a Snippet in NS_SNIP
 */
 
use MediaWiki\MediaWikiServices;

class Snippets extends Job {

  function __construct ( $title, $params) {
    parent::__construct( 'snippetsjob', $title, $params);    // compare https://www.mediawiki.org/wiki/Manual:Job_queue/For_developers
  }


  public static function onParserFirstCallInit( Parser $parser ) {                // Register parser callback hooks
    $parser->setHook ( 'snip', [ self::class, 'renderTagSnip' ] );
  }


  // triggered by the callback property in extension.json
  public static function onRegistration () {
    // self::debugLog ("\n--onRegistration callback called--\n");
    global $wgNamespaceProtection;

// TODO: do we want this? probably yes?
    $wgNamespaceProtection[NS_SNIP] = ['nobody-may-edit-this'];  // prevent anybody from editing a Snippet directly in the Snippet namespace - this ensures consistency of the Snippets with their place of definition
  }


  private static function debugLog ($text) {  // \TODO: inject all these kinds of $IP usages in all the other parts of our code 
    global $IP;
    if($tmpFile = fopen( "$IP/extensions/DanteSnippets/LOG", 'a')) { fwrite($tmpFile, $text);  fclose($tmpFile); }  // NOTE: close immediatley after writing to ensure proper flush
    else  { throw new Exception ("debugLog in Snippets.php could not log"); }
  }


// TODO: HOW do we clear up the snippet namespace - in case we delete the source of the snippet or even the snippet generating page ???



// Render <snip>
//
//  NOTE:  https://www.mediawiki.org/wiki/Manual:Collapsible_elements
//  has attribute   t:  use this as title for the snippet - if not given, use the title of the referencing page
//  has attribute   i:  snip is completely invisible in referencing document  // TODO: align this with Parsifal attributes !!!
//  has attribute   ??:  snip is visible and may be collapsed entirely into a line only showing "expand" on the side  // TODO: align this with the names of the collapsed / opens in Parsifal which work a bit different ???
//  has attribute ??:  snip is visible as a one-liner and may be expanded using a "expand" on the side  

//     c without text    snip shows and is collapsible using a border marker
//     e                 snip does not show but is expandible using a border marker
//     e with text       snip shows the attribute text as oneliner and is expandible using a boder marker

//
  public static function renderTagSnip ( $input, array $args, Parser $parser, PPFrame $frame ) {


    try {  // NOTE: we should always have try-catch blocks around tag functions so we get reasonable feedback and not a http 500 in our DantePresentations/endpoints
    self::debugLog ("\n*** Rendertagsnip got: ".$input."\n");

    $userId          = $parser->getUserIdentity();
    if ( property_exists ($userId, "danteEndpoint" ) )   { self::debugLog ("*** Looks we are coming from a Dantepresentation endpoint. Do not submit a job in this case.\n");  return $input; }

  // Get the current user's name
//    $user = MediaWikiServices::getInstance()->getUserFactory()->newFromSession()->getName();

    $user = RequestContext::getMain()->getUser();
    $userName = $user->getName();
    self::debugLog ("\n*** *** *** User is: " . $user);

/*
    $userName        = $userId->getName();
    $mwServices      =  MediaWiki\MediaWikiServices::getInstance(); 
    $loadBalancer    =  $mwServices->getDBLoadBalancer();                                   self::debugLog ("*** Got loadBalancer\n");
    $userNameUtils   =  $mwServices->getUserNameUtils();                                    self::debugLog ("*** Got userNameUtils\n");
    $userFactory     =  new MediaWiki\User\UserFactory ( $loadBalancer, $userNameUtils );   self::debugLog ("*** Got userFactory\n");
    $user            =  $userFactory->newFromName( $userName );                             self::debugLog ("*** Got user with name: $userName, now checking for user sanity\n");
*/  


    // we do not want 
    if ( !$user )                  { self::debugLog ("*** User evaluates to falsish, not submitting a job!\n");  return $input; }
    if ( $user->isAnon() )         { self::debugLog ("*** User is anonymous, not submitting a job!\n");          return $input; }
    if ( !$user->isRegistered() )  { self::debugLog ("*** User is not registered, not submitting a job!\n");     return $input; }

    // construct the parameters for the job
    $orgTitle        = $parser->getTitle();
    $time       = date ("H:i:s");
    $snipTitleTxt    = ( array_key_exists ("t", $args)  ?  $args['t']  :  $orgTitle->getText() );
    $params          = [ 'submitted' => "".$time, 'sniptitle' => $snipTitleTxt, 'content' => $input, 'orgtitle' => $orgTitle->getText(), 'userName' => $userName ];

    // construct an instance of a job and submit it
    $job             = new Snippets ( $orgTitle, $params );

    self::debugLog ("\n\n*** SUBMITTING a JOB: at time=" . $time ."\n\n");
    $mwServices      =  MediaWiki\MediaWikiServices::getInstance(); 
    $mwServices->getJobQueueGroupFactory()->makeJobQueueGroup()->push( $job );
    self::debugLog ("\n\n*** SUBMITED JOB to the jobqueue\n");

    // complete the local rendering
    if ( array_key_exists ("c", $args) ) { return "<div class='mw-collapsible'>$input</div>";}
    if ( array_key_exists ("e", $args) ) {
      //if ( strlen ($args['e']) == 0 ) {}
      //else   { }
      return "<div class='mw-collapsible mw-collapsed'>".$args['e']."<div class='mw-collapsible-content'>$input</div></div>"; 
    }

    return $input;
  } catch (Exception $ex) { return "Exception in renderTagSnip"; }
  }  // end function


  // run the job
  public function run() {
    self::debugLog ("\n*** Starting to execute a JOB at current time=".  date("H:i:s") . "\n");

    self::debugLog ("*** I am a job submitted at        " . $this->params['submitted'] . "\n");
    self::debugLog ("               with sniptitle:     " . $this->params['sniptitle'] . "\n");
    self::debugLog ("               with orgtitle:      " . $this->params['orgtitle'] . "\n");
    self::debugLog ("               with content:       " . $this->params['content'] . "\n");
    self::debugLog ("               from user by name:  " . $this->params['userName'] . "\n");
    
/*    
    if ( !is_null( $title ) && !$title->isKnown() && $title->canExist() ){
      $wpage   = WikiPage::factory( $title );
      $pageContent = ContentHandler::makeContent( $this->params['content'], $title );
      $wpage->doEditContent( $pageContent, "Page created automatically by parser function on page [[$sourceTitleText]]" ); //TODO i18n
    }
*/


// NOTE: code portions from edit.php 

/*
 

		if ( $userName === false ) {
			$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		} else {
			$user = User::newFromName( $userName );
		}
		if ( !$user ) {$this->fatalError( "Invalid username" );}

*/

    $title = Title::newFromText( $this->params['sniptitle'], NS_SNIP );
    if ( !$title ) {self::debugLog ("*** Problems with title \n");} else { self::debugLog ("*** Got a title object for title: ".$this->params['sniptitle']."\n");}

    $mwServices    =  MediaWiki\MediaWikiServices::getInstance(); 

    $page = $mwServices->getWikiPageFactory()->newFromTitle( $title );
    if ( !$page ) {self::debugLog ("*** Problems with page \n");} else { self::debugLog ("*** Got a page object\n");}

/*
		if ( $remove ) {
			if ( $slot === SlotRecord::MAIN ) {
				$this->fatalError( "Cannot remove main slot! Use --slot to specify." );
			}

			$content = false;
		} else {
			# Read the text
			$text = $this->getStdin( Maintenance::STDIN_ALL );
			$content = ContentHandler::makeContent( $text, $title );
		}
*/

    if ( $this->params['userName'] === null) { self::debugLog ("*** Got only null userName which cannot store. NOT submitting a job"); return true; }
    else {self::debugLog ("*** Username is not null and is ".$this->params['userName']."\n");}

    $loadBalancer    =  $mwServices->getDBLoadBalancer();                                   self::debugLog ("*** Got loadBalancer\n");
    $userNameUtils   =  $mwServices->getUserNameUtils();                                    self::debugLog ("*** Got userNameUtils\n");
    $userFactory     =  new MediaWiki\User\UserFactory ( $loadBalancer, $userNameUtils );   self::debugLog ("*** Got userFactory\n");

    try { $user = $userFactory->newFromName( $this->params['userName'] ); } catch (Exception $exc) {   self::debugLog ("*** CAUGHT an exception ");   }  
    self::debugLog ("*** Got user, now checking for user sanity\n");
  
    if ( !$user )            { self::debugLog ("*** User evaluates to falsish, returning!\n");  return; }
     if ( $user->isAnon() )  { self::debugLog ("*** User is anonymous - returning!\n");         return; }

    self::debugLog ("*** User is sane\n");
    $updater = $page->newPageUpdater( $user );
    self::debugLog ("*** Got updater\n");

//		if ( $content === false ) {$updater->removeSlot( $slot );} else {$updater->setContent( $slot, $content );}  // TODO: propbably we need this !

    $content = ContentHandler::makeContent( $this->params['content'], $title );
    self::debugLog ("*** Got content\n");
    $updater->setContent( MediaWiki\Revision\SlotRecord::MAIN, $content );  
    self::debugLog ("*** did set content and slot\n");

    $flags       = EDIT_SUPPRESS_RC | EDIT_FORCE_BOT;
    $summary     = "Page created automatically by parser function on page [[" . $this->params['sniptitle'] . "]]";  // TODO: distinguish snip title and source title  - CHEKCH THIS EVERYWHERE !!!

     $comment = CommentStoreComment::newUnsavedComment( $summary );
    self::debugLog ("*** got comment, will now save the revision\n");

    try {$updater->saveRevision( $comment, $flags );} 
    catch (MWException $exc)       {  self::debugLog ("*** Caught an MWException when savingRevision\n");        self::debugLog ("*** Message is: " . $exc->getText() . "\n");    }
    catch (RuntimeException $exc)  {  self::debugLog ("*** Caught a RuntimeException when savingRevision\n");    self::debugLog ("*** Message is: " . $exc->getMessage() . "\n"); }
    catch (Exception $exc)         {  self::debugLog ("*** Caught a generic exception when savingRevision\n");                                                                    }

    self::debugLog ("*** saved revision, checking status now\n");
    $status      = $updater->getStatus();

    if ( $status->isOK() )    { self::debugLog ("*** Status: OK \n");    }  else  { self::debugLog ("*** Status: NOT OK \n");    }
    if ( $status->isGood() )  { self::debugLog ("*** Status: GOOD \n");  }  else  { self::debugLog ("*** Status: NOT GOOD \n");  }

    self::debugLog ("******** JOB has completed \n");
    return true;
  }





  public static function submit () {
    $time = date ("H:i:s");
    self::debugLog ("\n\n******** SUBMITTING a JOB: at current time=" . $time ."\n\n");

    $params = [ 'limit' => 17, 'cascade' => true, 'submitted' => $time ];
    $title   = Title::newFromText ( "Demo", NS_MAIN );
    $job = new Snippets ( $title, $params );
    $mwServices      =  MediaWiki\MediaWikiServices::getInstance(); 
    $mwServices->getJobQueueGroupFactory()->makeJobQueueGroup()->push( $job );
  }


  // returns true (or false) if a snippet of the given name exists already in the snippet namespace
  public static function doesSnippetExist ( $name ) {
    $title   = Title::newFromText ( $name, NS_SNIP );
    $wpage = WikiPage::factory( $title );
    return $wpage->exists ();
  }

  public function existsInWiki () { return self::doesSnippetExist ( $this->titleText ); }

  // returns the originator of a snippet, which is encoded into the snippet // TODO MISSING
  public static function getSnippetOriginator ( $name ) {}


  // addSnippet:  add this snippet to wiki system
  public static function addToWiki ( ) {
    $newTitle = Title::newFromText($this->titleText, NS_SNIP );     
    $val = "<span style='color:red;font-family:mono;font-size:20pt;font-weight:bold;'>This is an auto-generated page. Do not edit!</span>" . $this->contents;
    $newContent = ContentHandler::makeContent($val, $newTitle );
    $newPage = new WikiPage( $newTitle );
    $newPage->doEditContent( $newContent, 'Autogenerated file' );
  }


  public function addToQueueIfNew ( ) {
   global $wgTopDante; 
   $VERBOSE = true; 

  TeXProcessor::debugLog ("** Snippets::addToQueueIfNew:  ON ENTRY IN FUNCTIOn: Stack size is now: " . count ( $wgTopDante->makeStack ) . " \n");

   if (! $this->existsInWiki() ) {
     if ($VERBOSE) { TeXProcessor::debugLog ( "** Snippets::addToQueueIfNew: Does not exists yet: " . $this->titleText . "\n" ) ;}
     array_push (  $wgTopDante->makeStack, $this );
      TeXProcessor::debugLog ("** Snippets::addToQueueIfNew:  Stack size is now: " . count ( $wgTopDante->makeStack ) . " \n");

  }
    else { if ($VERBOSE) { TeXProcessor::debugLog ( "** Snippets::addToQueueIfNew: Already exists: " . $this->titleText . "\n" );} }

  }


} // end class






























