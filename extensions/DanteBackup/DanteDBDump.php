<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Mail\UserEmailContact;

require_once ("DanteCommon.php");

class DanteDBDump extends SpecialPage {

public function __construct () { parent::__construct( 'DanteDBDump', 'dante-dbdump' ); }

public function getGroupName() {return 'dante';}




public function execute( $par ) {
  if (! $this->getUser()->isAllowed ("dante-dbdump") ) { $this->getOutput()->addHTML ("You do not have the permission to dump."); return;}  

  $this->setHeaders(); 

  // pick up the request data
  $request            = $this->getRequest();
  $names              = $request->getValueNames();                                                         MWDebug::log ( "names:  " . print_r ( $names,  true )  );
  $values             = $request->getValues (...$names);                                                   MWDebug::log ( "values: " . print_r ( $values, true )  );

  // get the values stored in the preferences
  $accessKey          = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-accesskey' );
  $secretAccessKey    = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-secretaccesskey' );
  $bucketName         = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );
  $aesPW              = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-encpw' );


/*
  if ( isset ($values["target"] ) ) {  // if we have this set, we are called from the form and execute the tasks
    if      (strcmp ($values["target"],   "local" )   == 0)    {  DanteCommon::dumpToLocal   ( $this, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW ); } 
    else if (strcmp ($values["target"],   "browser" ) == 0)    {  DanteCommon::dumpToBrowser ( $this, isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW ); } 
    else {    // awsBack  or  awsFore
      $txt = DanteCommon::dumpToAWS ( $this, $accessKey, $secretAccessKey, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), (strcmp ( $values["target"], "awsBack") == 0) , $aesPW); 
      $this->getOutput()->addHTML ($txt);
    }
    return;
  }

  if ( isset ($values["target"] ) ) {  // if we have this set, we are called from the form and execute the tasks
    if (strcmp ($values["target"], "local" )== 0)    {     $this->dumpDBToLocal ( isset ($values["compressed"]), isset ($values["encrypted"]), $aesPW ); } 
    else {    // awsBack  or  awsFore
      $txt = $this->dumpDBToAWS ( $accessKey, $secretAccessKey, $bucketName, isset ($values["compressed"]), isset ($values["encrypted"]), (strcmp ( $values["target"], "awsBack") == 0) , $aesPW); 
      $this->getOutput()->addHTML ($txt);
    }
    return;
  }
*/

  // dispatch function
  if ( isset ($values["target"] ) ) {  // if we have this set, we are called from the form. We execute the tasks and return.
    switch ($values["target"]) {
      case "window":          break;
      case "browser ":      break;
      case "awsFore":     break;
      case "awsBack":       break;
      case "serverFore":       break;
      case "serverBack":      break;
      default:              throw new Exception ("Illegal value found for target:" . $values["target"] . " This should not happen");
    }
  return;
  }

  $this->getOutput()->addHTML (wfMessage ("dante-database-dump-intro"));  // show some intro text

  // describe the form to be displayed
  $formDescriptor2 = array_merge ( DanteCommon::getTARGET_FORM(), DanteCommon::FEATURES );  // generate the form
  $htmlForm2 = new HTMLForm( $formDescriptor2, $this->getContext(), 'dbdumpform' );
  $htmlForm2->setSubmitText( 'Dump Database' );
  $htmlForm2->setSubmitCallback( [ $this, 'processInput' ] );
  $htmlForm2->show();
}



// return the decorated command for dumping this object type
  public function generateCommand ($zip, $enc, $aesPW) {
    global $wgDBname, $wgDBserver, $wgDBpassword, $wgDBuser;
    return "mysqldump --host=$wgDBserver --user=$wgDBuser --password=$wgDBpassword --single-transaction $wgDBname " . ($zip ? " | gzip " : "") . ($enc ? " | openssl aes-256-cbc -e -salt -pass $aesPW " : "" );
  }

  // return the native file extension this dumper would return
  public function getNativeExtension () { return "sql";}


/*
/// TODO: DANTECOMMON ersetzt das teilweise wie bei DanteDump
  private function dumpDBToLocal ($zip, $enc, $aesPW) {
    $this->getOutput()->disable();                                              // disable regular output
    $filename = DanteCommon::generateFilename( "sql", $zip, $enc);
    DanteCommon::contentTypeHeader ($zip, $enc);
    header( "Content-disposition: attachment;filename={$filename}" );
    $result = 0; $cmd = $this->generateDumpDBCommand ($zip, $enc, $aesPW);
    passthru ($cmd, $result);
  }
*/

/*
/// TODO: DANTECOMMON ersetzt das teilweise wie bei DanteDump
  private function dumpDBToAWS ( $accessKey, $secretAccessKey, $bucketName, $zip, $enc, $bg, $aesPW) {
    set_time_limit(3000);  // TODO: we might need to adjust this

    putenv ("AWS_ACCESS_KEY_ID=$accessKey"); putenv ("AWS_SECRET_ACCESS_KEY=$secretAccessKey");
    $name    = "s3://$bucketName/" . DanteCommon::generateFilename( "sql", $zip, $enc);
    $cmd = $this->generateDumpDBCommand ($zip, $enc, $aesPW) . " | aws s3 cp - $name 2>&1 ";
    if ($bg) { $cmd = "( $cmd ) &>DANTEDBDump_LOCAL_ERROR_FILE & ";}
   

///// $retText = DanteCommon::executeNO ( $cmd, "Piped dump command");

    if ($bg) { // running in the background
      
    }
    else  { // running in the foreground
      $cmd = " aws s3 ls dantebackup.iuk.one --human-readable 2>&1 ";
      $output=null; $retval=null;
      exec ($cmd, $output, $retval);
      $retText .= "<hr>".implode ("<br>", $output);
    }

   // putenv ("AWS_ACCESS_KEY_ID=A"); putenv ("AWS_SECRET_ACCESS_KEY=A");   // remove any environment settings to prevent further processes from using them

    return $retText;
  }

*/


// return:   true: form will not display again
//           false: from WILL be displayed again
//           string: show the string as error message together with the form
public static function processInput( $formData ) {
  return true;
  // return print_r ( $formData,  true ) ;
}

}