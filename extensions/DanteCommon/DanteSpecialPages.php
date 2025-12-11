<?php


abstract class DanteSpecialPage extends SpecialPage {


protected function getGroupName() {return 'dante';}


public function execute( $par ) {
  $this->setHeaders();
  $this->checkPermissions();
  $this->outputHeader();
  $request = $this->getRequest();
  $action = $request->getVal( 'action', 'view' );  // Read `action` query parameter; if not present use 'view' as fallback value

  if ( $action === 'submit' && $request->wasPosted() ) { $this->handleSubmission ( $request ); } 
  else {$this->showForm();}
}



protected function handleSubmission () {
  $request = $this->getRequest();
  $user = $this->getUser();
  $postedToken = $request->getVal( 'wpEditToken' );

  // check CSRF token 
  if ( !$user->matchEditToken( $postedToken, 'token_salt' ) ) {   // check with MATCHING salt above // TODO: matchEditToken will be deprecated in versions higher than MW 1.39
    $this->getOutput()->addWikiTextAsContent("'''Invalid or expired token. Please try again.'''");
    $this->showForm();    // re-show form with a fresh token
    return false;
  }

  try {
    $formId = $request->getVal( 'wpFormIdentifier' );  // get formId to see, which form was used
    danteLog ("DanteBackup", "On submission: Form identifier: " . print_r ($formId, true) ."\n");
    
    $arr = $this->getSpecificCommands ( $formId );    // now that we know which form was used, dispatch the execution of the forms submission
    $env = DanteCommon::getEnvironmentUser ($this->getUser());                // get the environment for the user (needed for execution)

    $this->executeCommands ( $arr, $env );            // finally dispatch the execution of these commands

  } catch ( Exception $x) {
    $this->getOutput()->addWikiTextAsContent("Exception occured: ". $x);
  }


}


/**
 * Execute the commands in array $cmd and stream stdou and stderr by an event stream mechanism to the browser.
 *
 * @param [type] $cmd
 * @param [type] $env
 * @return void
 */
protected function executeCommands ( $cmd, $env ): void {
  $envJson = json_encode  ($env );                                          // convert PHP environment array into json text format                        
  $cmdJson = json_encode ( $cmd );                                          // convert PHP command Array into json text format
  ServiceEndpointHelper::attachToSession ( $cmdJson, $envJson );         // attach command Array and environment in string form to the current session
  $this->getOutput()->addHTML ( ServiceEndpointHelper::getIframe () );       // send a general html template which then contains javascript which activates a serviceEndpoint sending event streams 
  return;
}


/** Helper function for generating standard forms */
protected function standardForm ( $descriptor, $action, $acro, $textOnButton ): void {
  $htmlForm = new HTMLForm( $descriptor, $this->getContext() );
  $htmlForm->setMethod( 'post' );                                 // method to be used is POST, only this allows proper CSRF checks
  $htmlForm->setTokenSalt( "token_salt" );                          // enables CSRF token handling with the given salt, must match salt in the check below
  $htmlForm->setAction( $action );                                // form is submitted to this URL
  $htmlForm->setId( "htmlId_$acro" );                                 // sets html id attribute on the form, helpful for css access and more
  $htmlForm->setSubmitText( t: $textOnButton );                           // text to be used on the submit button of this form
  $htmlForm->setFormIdentifier( "formId_$acro" );                  // used to identify form when multiple forms are used
  $htmlForm->prepareForm()->displayForm( false );
}

/**
 * Should be overwritten by subclass, provides and shows the form to be displayed
 * @return void 
 */
abstract protected function showForm () : void;


abstract protected function getSpecificCommands ( $formId ) : mixed;



} // end class






class Command {





}





