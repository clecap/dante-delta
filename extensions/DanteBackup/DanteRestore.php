<?php

require_once ("Executor.php");
require_once ("DanteCommon.php");

class DanteRestore extends SpecialPage {

public function __construct() {parent::__construct( 'DanteRestore', 'dante-restore' ); }

public function doesWrites() {return true;}

public function execute( $par ) {
  if (! $this->getUser()->isAllowed ("dante-restore") ) { $this->getOutput()->addHTML ("You do not have the permission to restore."); return;}  

  $this->useTransactionalTimeLimit();  // raise time limit for this operation

  danteLog ("DanteBackup", "Entered execute \n");
  $request = $this->getRequest();
  if ( $request->wasPosted() && $request->getRawVal( 'action' ) == 'submit' ) {
    $formIdentifier = $request->getVal( 'wpFormIdentifier' );

    danteLog ("DanteBackup", "Form identifier: " . print_r ($formIdentifier, true));
    
    switch ($formIdentifier) {
      case 'formLOCAL':
        // danteLog ("DanteBackup", "ALL: " . print_r ($_FILES, true) . "\n");
        danteLog ("DanteBackup", "name " . $_FILES['xmlimport']['name'] . "\n");
        danteLog ("DanteBackup", "mime: " . $_FILES['xmlimport']['type'] . "\n");
        danteLog ("DanteBackup", "size " . $_FILES['xmlimport']['size'] . "\n"); 
        danteLog ("DanteBackup", "tmp name " . $_FILES['xmlimport']['tmp_name'] . "\n");      
        danteLog ("DanteBackup", "error " . $_FILES['xmlimport']['error'] . "\n");
        $info = "Name: ". $_FILES['xmlimport']['name']. " Size " . number_format (floatval ( $_FILES['xmlimport']['size'] ) / (1024 * 1024), 2) . "[MB]";
        $this->doImport ($_FILES['xmlimport']['tmp_name'], $info);
        break;

      case 'formAWS':
        $fileName = $request->getVal ("awsRadio");
        danteLog ("DanteBackup", "radio selected file is: ".$fileName. "\n");
        $this->doImportAWS ($fileName);
        break;

      case 'formURL':
        $url = $request->getVal ("url");
        $this->doImportURL ( $url, $this->getUser() );
        break;

      case 'formSCP':


       break;
    }
  }
  $this->showForm();
}



private function showForm() {
  $action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );
  $user   = $this->getUser();
  $out    = $this->getOutput();

  $out->addStyle ("../extensions/DanteBackup/danteBackup.css"); 

  $out->addHTML ("<h1>Special:DanteRestore: Restoring DanteWiki Contents</h1>");
  $out->addHTML ("<p>There are numerous possibilities to restore Dantewiki contents from. Chose one of the following possibilities:</p>");

  $out->addHelpLink( 'index.php?title=Help:Import', true ); 

  $out->addHTML ("<h2>Possibility 1: Restoring from a file on your local computer</h2>"); 

  $formLOCAL = [];
  $formLOCAL += [ 'xmlimport' => ['type' => 'file','name' => 'xmlimport', 'accept' => [ 'application/xml', 'text/xml', 'application/x-gzip-compressed', 'application/octet-stream' ], 'section' => 'select-local-file', 'required' => true, ] ];
  $htmlFormLOCAL = new HTMLForm( $formLOCAL, $this->getContext() );
  $htmlFormLOCAL->setAction( $action );
  $htmlFormLOCAL->setId( 'restore-form-local' );
  $htmlFormLOCAL->setSubmitText( 'Restore from file' );
  $htmlFormLOCAL->setFormIdentifier( 'formLOCAL' );
  $htmlFormLOCAL->prepareForm()->displayForm( false );

  $out->addHTML ("<h2>Possibility 2: Restoring from a file from the AWS S3 storage area</h2>"); 
  $formAWS = [];
  $formAWS += [ 'awsRadio'  => [ 'type' => 'radio', 'section' => 'restore-from-aws', 'options' => $this->getFileArray (),   'name' => 'awsRadio', 'required' => true  ] ];
  $htmlFormAWS = new HTMLForm( $formAWS, $this->getContext() );
  $htmlFormAWS->setAction( $action );
  $htmlFormAWS->setId( 'restore-form-aws' );
  $htmlFormAWS->setSubmitText( 'Restore from AWS' );
  $htmlFormAWS->setFormIdentifier( 'formAWS' );
  $htmlFormAWS->prepareForm()->displayForm( false );

  $out->addHTML ("<h2>Possibility 3: Restoring from a file at an Internet URL</h2>"); 
  $formURL = [];
  $formURL += [ 'url' => [ 'type' => 'text', 'name' => 'url', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];
  $htmlFormURL = new HTMLForm( $formURL, $this->getContext() );
  $htmlFormURL->setAction( $action );
  $htmlFormURL->setId( 'restore-form-url' );
  $htmlFormURL->setSubmitText( 'Restore from URL' );
  $htmlFormURL->setFormIdentifier( 'formURL' );
  $htmlFormURL->prepareForm()->displayForm( false );


  $out->addHTML ("<h2>Possibility 4: Restoring from a file accessible by SSH (not yet operative)</h2>"); 
  $formSCP = [];
  $formSCP += [ 'scp' => [ 'type' => 'text', 'name' => 'url', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];
  $formSCP += [ 'scpUser' => [ 'type' => 'text', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];

  $htmlFormSCP = new HTMLForm( $formSCP, $this->getContext() );
  $htmlFormSCP->setAction( $action );
  $htmlFormSCP->setId( 'restore-form-scp' );
  $htmlFormSCP->setSubmitText( 'Restore via SCP' );
  $htmlFormSCP->setFormIdentifier( 'formSCP' );
  $htmlFormSCP->prepareForm()->displayForm( false );
}


// return:   true: form will not display again
//           false: from WILL be displayed again
//           string: show the string as error message together with the form
public static function processInputLOCAL ( $formData ) {
  //return true;
   return print_r ( $formData,  true ) ;
}


protected function getGroupName() {return 'dante';}


// does an ls of the  AWS S3 bucket and returns the result apropriately formatted for a radio button selection form for the file
private function getFileArray () {
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  $env = DanteCommon::getEnvironmentUser ($this->getUser());
  $cmd = "/opt/myenv/bin/aws s3api list-objects-v2 --bucket {$bucketName} --query 'Contents[].[Key,LastModified,Size]' --output json";
  $retCode = Executor::executeAWS_FG_RET ( $cmd, $env, $output, $error );
  $retArray = array ();

  if ($retCode == 0) {
    $objects = json_decode($output, true);  // Decode the JSON output into a PHP array // TODO: could be in error

    if (is_array($objects)) {
      usort($objects, function ($a, $b) {return strtotime($b[1]) - strtotime($a[1]);});    // Sort the objects by LastModified in descending order
      foreach ($objects as $object) {
        $retArray[  "<span style='display:inline-block;width:400px;'>".$object[0]."</span><span style='display:inline-block;width:300px;'>". $object[1] . "</span>".
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



// appends to the command array $cmd all those commands required after an import and return the new command array
private static function addPostImport ( $cmd ) {
  global $IP;
 // see https://www.mediawiki.org/wiki/Manual:ImportDump.php about how we must run this after an import // TODO: really all of this ????
  array_push ($cmd,  "php $IP/maintenance/rebuildrecentchanges.php"); 
  array_push ($cmd,  "php maintenance/initSiteStats.php --update ");
  array_push ($cmd,  "php $IP/maintenance/rebuildImages.php"); 
  array_push ($cmd,  "php $IP/maintenance/rebuildall.php"); 
  array_push ($cmd,  "php $IP/maintenance/checkImages.php"); 
  array_push ($cmd,  "php $IP/maintenance/refreshFileHeaders.php --verbose");
  array_push ($cmd,  "echo '*** COMPLETE: You may close this window now ***'");
  return $cmd;
}

// service function for here and other places
public function doImport ($fileName, $info) {
  global $IP;
  $enc = DanteCommon::checkSuffix ( $fileName, ".aes");
  $zip = DanteCommon::checkSuffix ( $fileName, ".gz" );

  $arr = array ();
  array_push ( $arr, "echo $info" );
  array_push ( $arr, "cat $fileName | php $IP/extensions/DanteBackup/countFilter.php " );  // TODO LACKS unzp and decrypt 
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --namespaces '8'  ", $zip, $enc ) );    // get MediaWiki: namespace (need Parsifal templates on board first)
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --namespaces '10' ", $zip, $enc ) );    // get Template: namespace
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --report=10  --uploads         ", $zip, $enc ) );    // TODO: can we really merge this into "all the rest" ?????
  $arr = self::addPostImport ( $arr );  // do maintenance stuff we need to do after every import

  danteLog ("DanteBackup", "\n WIll now enter executor \n");
  Executor::liveExecuteX ($arr);
  danteLog ("DanteBackup", "\n Left executor \n");
  return true;
}


// funciton importing from AWS
private function doImportAWS ($fileName) {
  global $IP;
  $enc = DanteCommon::checkSuffix ( $fileName, ".aes");
  $zip = DanteCommon::checkSuffix ( $fileName, ".gz" );

  danteLog ("DanteBackup", "doImportAWS: $fileName\n");
  $env = DanteCommon::getEnvironmentUser ($this->getUser());

  $bucketName = $env["AWS_BUCKET_NAME"];
  danteLog ("DanteBackup", "doImportAWS: $fileName, enc:" .$enc. " zip: ". $zip."\n");

  $arr = array ();

// TODO aesPassword !!!!!!
//  cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName - | ", " php $IP/maintenance/importDump.php --namespaces '8' --debug 2>&1 ", $zip, $enc);

  danteLog ("DanteBackup", "\n Selected file is $fileName \n");

  if ($fileName == "ERROR") {
    array_push ( $arr, "echo 'The system signalled an error and so this option is not available'");
    return true;
  } 

  array_push ( $arr, "echo $fileName");
  array_push ( $arr, DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/extensions/DanteBackup/countFilter.php ",                   $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '8' ",  $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '10' ", $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --report=10 --uploads ",         $zip, $enc ) ); 
  $arr = self::addPostImport ( $arr );

  danteLog ("DanteBackup", "\n doImportAWS will now enter executor \n");
  Executor::liveExecuteX ($arr, $env);
  danteLog ("DanteBackup", "\n doImportAWS left executor \n");
  return true;
}

// TODO:  --report in alle importDump Befehle ! rein


public static function doImportURL ($url, $user) {
  global $IP;

  danteLog ("DanteBackup", "\n doImportURL called \n");

  $enc = DanteCommon::checkSuffix ( $url, ".aes");
  $zip = DanteCommon::checkSuffix ( $url, ".gz" );

  $env = DanteCommon::getEnvironmentUser ( $user ); // TODO: needed ?? password ???  // TODO: hopefully not. CAVE: DanteInitialStore.php uses this as well

  danteLog ("DanteBackup", "doImportURL: $url, enc:" .$enc. " gz: ". $zip."\n");

  $arr = array ();
  array_push ( $arr, "echo $url");
  array_push ( $arr, DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/extensions/DanteBackup/countFilter.php ",                   $zip, $enc ) );   
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '8' ",  $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '10' ", $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --uploads ",         $zip, $enc ) ); 
  $arr = self::addPostImport ( $arr );

  danteLog ("DanteBackup", "\n doImportURL will now enter executor \n");
  danteLog ("DanteBackup", "\n Commands are: ".print_r ($arr, true)." \n");
  Executor::liveExecuteX ($arr, $env);
  danteLog ("DanteBackup", "\n doImportURL left executor \n");
  return true;
}



private function doImportSCP ($url) {  // TODO !!!!!!!!!!
  global $IP;
  $enc = DanteCommon::checkSuffix ( $url, ".aes");
  $zip = DanteCommon::checkSuffix ( $url, ".gz" );

  $env = DanteCommon::getEnvironmentUser ($this->getUser()); // TODO: needed ?? password ???

  danteLog ("DanteBackup", "doImpoortSCP: $url, enc:" .$enc. " zip: ". $zip."\n");

  $arr = array ();
  array_push ( $arr, "echo $fileName");
  array_push ( $arr, "curl -L $url | php $IP/extensions/DanteBackup/countFilter.php " );  // TODO LACKS unzp and decrypt 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '8' ",  $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --namespaces '10' ", $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("curl -L $url  | ", " php $IP/maintenance/importDump.php --report=10 --uploads ",         $zip, $enc ) ); 
  $arr = self::addPostImport ( $arr );

  danteLog ("DanteBackup:", "\n doImportAWS will now enter executor \n");
  Executor::liveExecuteX ($arr, $env);
  danteLog ("DanteBackup:", "\n doImportAWS left executor \n");
  return true;
}



} // end class




