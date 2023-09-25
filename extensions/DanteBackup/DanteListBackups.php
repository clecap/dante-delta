<?php

require_once ("DanteCommon.php");

class DanteListBackups extends SpecialPage {

public function __construct () {parent::__construct( 'DanteListBackups' ); }

public function getGroupName() {return 'dante';}
  
public function execute( $par ) {
  $request = $this->getRequest();
  $names   = $request->getValueNames();   MWDebug::log ( "names:  " . print_r ( $names,  true )  );
  $values  = $request->getValues (...$names);

  $this->setHeaders();

  // get access data from preferences
  $accessKey = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-accesskey' );
  $secretAccessKey = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-secretaccesskey' );
  $defaultSpec = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  putenv ("AWS_ACCESS_KEY_ID=$accessKey"); putenv ("AWS_SECRET_ACCESS_KEY=$secretAccessKey"); 

  // s3 ls returns the time in UTC timezone - since the operating system has no concept of a local time zone
  $cmd = "aws s3 ls $defaultSpec --human-readable ";  $stdout = "";  $stderr = "";
  $retval = DanteCommon::fullExec($cmd, $stdout, $stderr);  

  $prep = new AWSEnvironmentPreparatorUser ( $this->getUser() );
  executeAWS_FG_RET ( $prep, $cmd, $output, $error );

  if ($retval != 0) { $this->getOutput()->addHTML ( "ERROR " . print_r ($stderr, true) );} 
  else              { $this->getOutput()->addHTML ( DanteListBackups::formatLSOutput ( $stdout, $opsDB, $opsPage ) ); }


  $formDescriptor2 = [
    'field-type' => ['section' => 'section1', 'type' => 'hidden', 'name' => 'hidden', 'default' => 'AWS', ],
    'radio'      => ['section' => 'listform-db',  'type' => 'radio', 'label' => '',  'options' => $opsDB ] ,
   'radio'      => ['section' => 'listform-page',  'type' => 'radio', 'label' => '', 'options' => $opsPage ] ];
  $htmlForm2 = new HTMLForm( $formDescriptor2, $this->getContext(), 'listform' );
  $htmlForm2->setFormIdentifier( 'AWS' );
  $htmlForm2->setSubmitText( 'Restore' );
  $htmlForm2->setSubmitCallback( [ $this, 'processInput' ] );  
  $htmlForm2->show();

 // if this function execution was a call (ie we have a hidden value), then go to execution and return
  // if ( strcmp ($type, "AWS") == 0) {
  //  $this->getOutput()->addHTML ( $this->dumpToAWS ( $accessKey, $secretAccessKey, $name, $zip, $enc ) );       return;}

}


/** Format the output of an "aws s3 ls" command
 *  
 *   $resu:   string, result of that command
 *   $opsDB:  associative array, gets filled with $key => filename structures for use in a form descriptor, for database dump files
 *   
 */

private static function formatLSOutput ($resu, &$opsDB, &$opsPage) {

 $opsPage = [];
    $opsDB = [];
    $expo = explode ("\n", $resu); // array of result lines
    $num = 0;
    foreach ($expo as $line) {
       $line = preg_replace ('/\s\s+/', " ", $line );
       if ( strlen ($line) == 0 ) {break;}
       $items = explode (" ", $line);  // date, time, size, unit, name
       MWDebug::log ( print_r ($items, true));
     //  if (count ($item) ) {break;}
       [$date, $time, $size, $unit, $name] = $items; 
       if (strlen ($name) == 0) {break;}
       $key = "<span style='display:inline-block;width:600px;'>$name</span> <span style=''>$date $time [UTC]</span> <span style='text-align:right;display:inline-block;width:100px;'>$size [$unit]</span>";
       MWDebug::log (  $key . "-------------" . $name);
       if ( str_ends_with ($name, ".sql.gz.aes") || str_ends_with ($name, ".sql.aes") || str_ends_with ($name, ".sql.gz") || str_ends_with ($name, ".sql") ) { $opsDB[$key]   = $name; }
       if ( str_ends_with ($name, ".xml.gz.aes") || str_ends_with ($name, ".xml.aes") || str_ends_with ($name, ".xml.gz") || str_ends_with ($name, ".xml") ) { $opsPage[$key] = $name; }
       $num++;
    }

///// construct a form for selecting from here !

    $impo = implode ( "<br>", $expo);
  return $impo;
}





public static function processInput( $formData ) { return true; }


}

