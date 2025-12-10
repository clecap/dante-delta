<?php

require_once ("Executor.php");       // TODO: should move away from this
require_once ("DanteCommon.php");    // TODO: really? check ?!

require_once ("extensions/DantePresentations/helpers/ServiceEndpointHelper.php");


class DanteRestore extends DanteSpecialPage {

public function __construct() {parent::__construct( 'DanteRestore', 'dante-restore' ); }

// protected function getGroupName() {return 'dante';}

// page provides hint to read-only mode engine that it might do a write
public function doesWrites() {return true;}

/*
public function execute( $par ) {
  $this->setHeaders();
  $this->checkPermissions();
  $this->outputHeader();
  $request = $this->getRequest();
  $action = $request->getVal( 'action', 'view' );  // Read `action` query parameter; if not present use 'view' as fallback value

  if ( $action === 'submit' && $request->wasPosted() ) { $this->handleSubmission ( $request ); } 
  else {$this->showForm();}
}
*/


private function showForm() {
  // send post data to THIS url and add action=submit to the URL so we can distinguish showing this page from submitting data to it
  $action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );  

  $out    = $this->getOutput();
  $out->addStyle ("../extensions/DanteBackup/danteBackup.css"); 
  $out->addHTML (wfMessage ("dante-page-restore-intro"));

  // provide a help link
  $out->addHelpLink( 'index.php?title=Help:DanteRestore', true );  // provide a help link   // TODO: must fill with contents

  // POSSIBILITY 1
  $out->addHTML ("<h2>Possibility 1: Restore from a file on your computer</h2>"); 
  $form = [ 'xmlimport' => ['type' => 'file','name' => 'xmlimport', 'accept' => [ 'application/xml', 'text/xml', 'application/x-gzip-compressed', 'application/octet-stream' ], 'section' => 'select-local-file', 'required' => true, ] ];
  self::standardForm ($form, $action, "LOCAL", "Restore from file");

  // POSSIBILITY 2
  $out->addHTML ("<h2>Possibility 2: Restore from a file from the AWS S3 storage area</h2>"); 
  $form = [ 'awsRadio'  => [ 'type' => 'radio', 'section' => 'restore-from-aws', 'options' => $this->getFileArray (),   'name' => 'awsRadio', 'required' => true  ] ];
  self::standardForm ($form, $action, "AWS", "Restore from AWS");

  // POSSIBILITY 3
  $out->addHTML ("<h2>Possibility 3: Restore from a file at an Internet URL</h2>"); 
  $formURL = [ 'url' => [ 'type' => 'text', 'name' => 'url', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];
  self::standardForm ($form, $action, "URL", "Restore from URL");

  // POSSIBILITY 4
  $out->addHTML ("<h2>Possibility 4: Restore from a file accessible by SSH (not yet operative)</h2>"); 
  $form = [ 'scp' => [ 'type' => 'text', 'name' => 'url', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ]
           + [ 'scpUser' => [ 'type' => 'text', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];
  self::standardForm ($form, $action, "SCP", "Restore via SCP");
}


/** Helper function for generating standard forms */
private function standardForm ( $descriptor, $action, $acro, $textOnButton ) {
  $htmlForm = new HTMLForm( $descriptor, $this->getContext() );
  $htmlForm->setMethod( 'post' );                                 // method to be used is POST, only this allows proper CSRF checks
  $htmlForm->setTokenSalt( "token_salt" );                        // enables CSRF token handling with the given salt, must match salt in the check below
  $htmlForm->setAction( $action );                                // form is submitted to this URL
  $htmlForm->setId( "htmlId_$acro" );                             // sets html id attribute on the form, helpful for css access and more
  $htmlForm->setSubmitText( $textOnButton );                      // text to be used on the submit button of this form
  $htmlForm->setFormIdentifier( "formId_$acro" );                 // used to identify form when multiple forms are used
  $htmlForm->prepareForm()->displayForm( false );
}


private function handleSubmission () {
  $request = $this->getRequest();
  $user = $this->getUser();
  $postedToken = $request->getVal( 'wpEditToken' );

  // check CSRF token 
  if ( !$user->matchEditToken( $postedToken, 'token_salt' ) ) {   // check with MATCHING salt above // TODO: matchEditToken will be deprecated in versions higher than MW 1.39
    $this->getOutput()->addWikiTextAsContent("'''Invalid or expired token. Please try again.'''");
    $this->showForm();    // re-show form with a fresh token
    return false;
  }

  $formId = $request->getVal( 'wpFormIdentifier' );  // get formId to see, which form was used
  danteLog ("DanteBackup", "On submission: Form identifier: " . print_r ($formId, true) ."\n");
    
  $arr = $this->getSpecificCommands ( $formId );    // now that we know which form was used, dispatch the execution of the forms submission
  $env = DanteCommon::getEnvironmentUser ($this->getUser());                // get the environment for the user (needed for execution)

  $this->doImportFunctionality ( $arr, $env );            // finally dispatch the execution of these commands
}



private function getSpecificCommands ( $formId ) {
  $request = $this->getRequest();
  switch ($formId) {
    case 'formId_LOCAL':
      // danteLog ("DanteBackup", "ALL: " . print_r ($_FILES, true) . "\n");
      danteLog ("DanteBackup", "name " . $_FILES['xmlimport']['name'] . "\n");
      danteLog ("DanteBackup", "mime: " . $_FILES['xmlimport']['type'] . "\n");
      danteLog ("DanteBackup", "size " . $_FILES['xmlimport']['size'] . "\n"); 
      danteLog ("DanteBackup", "tmp name " . $_FILES['xmlimport']['tmp_name'] . "\n");      
      danteLog ("DanteBackup", "error " . $_FILES['xmlimport']['error'] . "\n");
      $info = "Name: ". $_FILES['xmlimport']['name']. " Size " . number_format (floatval ( $_FILES['xmlimport']['size'] ) / (1024 * 1024), 2) . "[MB]";  // TODO: MAYBE OFR OUTPUT - but where and when ???
      $fileName = $_FILES['xmlimport']['tmp_name'];
      $arr = self::getCommandsFILE ($fileName, $info);                                 // get an array of commands
      break;

    case 'formId_AWS':
      $fileName = $request->getVal ("awsRadio");                               // pick up relevant data from form
      danteLog ("DanteBackup", "radio selected file is: ".$fileName. "\n");    // log the relevant data if needed
      $arr = self::getCommandsAWS ($fileName);                                 // get an array of commands
      break;

     case 'formId_URL':
      $url = $request->getVal ("url");
      $this->doImportURL ( $url, $this->getUser() );
      break;
     case 'formId_SCP':
     // TODO
       break;
    }
    return $arr;
}




// analyze the suffix structure of a filename and return a boolean array
// [db, zip, enc]
private static function checkName ($fileName) {
  if     ( str_ends_with ($fileName, ".xml.gz.aes") ) { return [false, true,  true  ];}
  elseif ( str_ends_with ($fileName, ".xml.aes") )    { return [false, false, true  ];}
  elseif ( str_ends_with ($fileName, ".xml.gz") )     { return [false, true,  false ];}
  elseif ( str_ends_with ($fileName, ".xml") )        { return [false, false, false ];}
  elseif ( str_ends_with ($fileName, ".sql.gz.aes") ) { return [true,  true,  true  ];}
  elseif ( str_ends_with ($fileName, ".sql.aes") )    { return [true,  false, true  ];}
  elseif ( str_ends_with ($fileName, ".sql.gz") )     { return [true,  true,  false ];}
  elseif ( str_ends_with ($fileName, ".sql") )        { return [true,  false, false ];}
  else { throw new Exception ("incompatible file type found in file $fileName");}
}



private function getCommandsRestoreFiles () {

// untar stuff

// ensure proper permissions
/*
chown -R www-data:www-data "$UPLOAD_DIR"
find "$UPLOAD_DIR" -type d -exec chmod 755 {} \;
find "$UPLOAD_DIR" -type f -exec chmod 644 {} \;
*/

/*
// clean up thumb nails
rm -rf "$UPLOAD_DIR/thumb"
mkdir -p "$UPLOAD_DIR/thumb"
chown -R www-data:www-data "$UPLOAD_DIR/thumb"
*/

// regenerate all thumbs
// php maintenance/rebuildImages.php
// could also do this on a fault in base every time we look at a file....


}



// TODO: also need sql variant of this
// generate the commands for restoring from local file
public function getCommandsFILE ($fileName, $info) {
  global $IP;
  [$db, $enc, $zip] = self::checkName ($fileName);

  $arr = array ();
  array_push ( $arr, "echo $info" );
  array_push ( $arr, "cat $fileName | php $IP/extensions/DanteBackup/countFilter.php " );  // TODO LACKS unzp and decrypt 
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --namespaces '8'  ", $zip, $enc ) );    // get MediaWiki: namespace (need Parsifal templates on board first)
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --namespaces '10' ", $zip, $enc ) );    // get Template: namespace
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --uploads         ", $zip, $enc ) );    // TODO: can we really merge this into "all the rest" ?????
  $arr = self::addPostImport ( $arr );  // do maintenance stuff we need to do after every import
  return $arr;
}


// TODO: ALSO NEED a variant for Database files ........
// generate the commands for restoring from $file from AWS S3 instance
private function getCommandsAWS ($fileName) {
  global $IP;
  [$db, $enc, $zip] = self::checkName ($fileName);

  $env = DanteCommon::getEnvironmentUser ($this->getUser());
  $bucketName = $env["AWS_BUCKET_NAME"];
  danteLog ("DanteBackup", "doImportAWS: $fileName, enc:" .$enc. " zip: ". $zip."\n");

  $arr = array ();

  // TODO aesPassword !!!!!!
  //  cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName - | ", " php $IP/maintenance/importDump.php --namespaces '8' --debug 2>&1 ", $zip, $enc);

  danteLog ("DanteBackup", "\n Selected file is $fileName \n");

  if ($fileName == "ERROR") {
    array_push ( $arr, "echo 'The system signalled an error and so this option is not available'");
    return true;  // TODO: check if this is needed and if this works !!
  } 

  if ($db) {
 //   gunzip -c mediawiki_dump.sql.gz | mysql -u wikiuser -p wikidb
  
  }
  else {
    array_push ( $arr, DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/extensions/DanteBackup/countFilter.php ",                   $zip, $enc ) ); 
    array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '8' ",  $zip, $enc ) ); 
    array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '10' ", $zip, $enc ) ); 
    array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --report=10 --uploads ",         $zip, $enc ) ); 
    $arr = self::addPostImport ( $arr );
  }
  return $arr;
}


// TODO: ALSO NEED a variant for Database files ........
public static function getCommandsURL ($url, $user) {
  global $IP;
  [$db, $enc, $zip] = self::checkName ($url);

  $env = DanteCommon::getEnvironmentUser ( $user ); // TODO: needed ?? password ???  // TODO: hopefully not. CAVE: DanteInitialStore.php uses this as well

  $arr = array ();
  array_push ( $arr, "echo $url");

  array_push ( $arr, DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/extensions/DanteBackup/countFilter.php ",                   $zip, $enc ) );   
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '8' ",  $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '10' ", $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --uploads ",         $zip, $enc ) ); 
  $arr = self::addPostImport ( $arr );
  return $arr;
}


// TODO: ALSO NEED a variant for Database files ........
// TODO: fehlt noch
public static function getCommandsSSH ($url) {
  global $IP;
  [$db, $enc, $zip] = self::checkName ($url);
  
// TODO: still stuff missing

}


private function doImportFunctionality ( $cmd, $env ) {
  $envJson = json_encode ($env);                                            // convert PHP environment array into json text format                        
  $cmdJson = json_encode ( $cmd );                                          // convert PHP command Array into json text format
  ServiceEndpointHelper::attachToSession ( $cmdJson, $envJson );         // attach command Array and environment in string form to the current session
  $this->getOutput()->addHTML ( ServiceEndpointHelper::getGeneral () );      // send a general html template which then contains javascript which activates a serviceEndpoint sending event streams 
  return true;
}
// TODO: the serviceEndpoint we use here - should probably not be part of DantePresentations but of DanteBackup - also might require adjustment of Apache configuration for the PHP execution !!



// generates a prefix for the choice form
private static function getPrefix ($name) {
  if (str_ends_with ($name, ".sql.gz.aes") || str_ends_with ($name, ".sql.aes") || str_ends_with ($name, ".sql.gz") || str_ends_with ($name, ".sql") ) return "<b style='color:red;'>Database: </b> ";
  if (str_ends_with ($name, ".xml.gz.aes") || str_ends_with ($name, ".xml.aes") || str_ends_with ($name, ".xml.gz") || str_ends_with ($name, ".xml") ) return "<b style='color:blue;'>Files:    </b>";
}


// does an ls of the  AWS S3 bucket and returns the result apropriately formatted for a radio button selection form for the file
private function getFileArray () {
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  $env = DanteCommon::getEnvironmentUser ($this->getUser());
  $cmd = "/opt/myenv/bin/aws s3api list-objects-v2 --bucket {$bucketName} --query 'Contents[].[Key,LastModified,Size]' --output json";
  $retCode = Executor::executeAWS_FG_RET ( $cmd, $env, $output, $error );  // TODO: still written in blocking mode - might want to fix eventually
  $retArray = array ();

  if ($retCode == 0) {
    $objects = json_decode($output, true);  // Decode the JSON output into a PHP array // TODO: could be in error

    if (is_array($objects)) {
      $filtered = array_filter($objects, function ($object) { 
        $name = $object[0];
        return 
        str_ends_with ($name, ".sql.gz.aes") || str_ends_with ($name, ".sql.aes") || str_ends_with ($name, ".sql.gz") || str_ends_with ($name, ".sql") ||
        str_ends_with ($name, ".xml.gz.aes") || str_ends_with ($name, ".xml.aes") || str_ends_with ($name, ".xml.gz") || str_ends_with ($name, ".xml");
      });
      $filtered = array_values ( $filtered );

      usort($objects, function ($a, $b) {return strtotime($b[1]) - strtotime($a[1]);});    // Sort the objects by LastModified in descending order

      foreach ($objects as $object) {
        $retArray[  "<span style='display:inline-block;width:400px;'>".self::getPrefix($object[0]).$object[0]."</span><span style='display:inline-block;width:300px;'>". $object[1] . "</span>".
       "<span style='display:inline-block;width:400px;'>". number_format ($object[2]/ (1024*1024), 2)  . "[MB] </span>"] = $object[0];
       }  // foreach
    }
    else { $retArray["<div style='color:red;'><p>ERROR: Could not parse the following JSON return from aws:</p><p><code>$output</code></p></div>"]= "ERROR";}
  }
  else {
    $retArray["<div style='color:red;'><p>ERROR: An error occured while processing the following aws listing command:</p/><code>$cmd</code><p>The stderr contained this information: $error.</p><p>The command exit code was $retCode</p></div>"] = "ERROR";
  }
  return $retArray;
}


// service function: appends to the command array $cmd all those commands required after an import of an xml archive; return the new command array
private static function addPostImport ( $cmd ) {
  global $IP;
  // see https://www.mediawiki.org/wiki/Manual:ImportDump.php about how we must run this after an import // TODO: really all of this ????
  array_push ($cmd,  "php $IP/maintenance/rebuildrecentchanges.php"); 
  array_push ($cmd,  "php $IP/maintenance/initSiteStats.php --update ");
  array_push ($cmd,  "php $IP/maintenance/rebuildImages.php"); 
  array_push ($cmd,  "php $IP/maintenance/rebuildall.php"); 
  array_push ($cmd,  "php $IP/maintenance/checkImages.php"); 
  array_push ($cmd,  "php $IP/maintenance/refreshFileHeaders.php --verbose");
  return $cmd;
}



} // end class




