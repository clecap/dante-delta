<?php

require_once ("Executor.php");
require_once ("DanteCommon.php");

class DanteRestore extends SpecialPage {

public function __construct() {parent::__construct( 'DanteRestore', 'dante-restore' ); }

public function doesWrites() {return true;}





public function execute($subPage) {
    $this->setHeaders();
    $output = $this->getOutput();
    $output->setPageTitle('Upload and Display File');

    // Check if the form was submitted
    $request = $this->getRequest();

  danteLog ("DanteBackup", "Seeing FILES: ".print_r ($_FILES, true)."\n");

    if ($request->wasPosted() && isset($_FILES['file'])) {
      $this->handleUpload($request);
    } else {
      $this->showUploadForm();
    }
  }

  private function showUploadForm() {
    $output = $this->getOutput();
    $output->addHTML(
      '<form method="post" enctype="multipart/form-data">
         <input type="file" name="file" required>
         <input type="submit" value="Upload">
       </form>'
    );
  }

  private function handleUpload($request) {
    $output = $this->getOutput();
    $file = $_FILES['file'];

 


    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $output->addHTML('<p>Error uploading file.</p>');
      return;
    }

    // Read the file content
    $fileContent = file_get_contents($file['tmp_name']);
    $fileContent = htmlspecialchars($fileContent); // Escape HTML characters

    // Display the file content
    $output->addHTML("<pre>$fileContent</pre>");
  }





/*


public function execute( $par ) {
    if (! $this->getUser()->isAllowed ("dante-restore") ) { $this->getOutput()->addHTML ("You do not have the permission to restore."); return;}  

  $this->useTransactionalTimeLimit();  // raise time limit for this operation

//  $this->setHeaders();
//  $this->outputHeader();

//  $this->checkReadOnly();  // TODO: what does this do ????

  danteLog ("DanteBackup", "Entered execute \n");
  $request = $this->getRequest();
  if ( $request->wasPosted() && $request->getRawVal( 'action' ) == 'submit' ) {

    danteLog ("DanteBackup", "We obtained: " . print_r ($request, true). "\n");
      danteLog ("DanteBackup", "ALL: " . print_r ($_FILES, true) . "\n");

    $formIdentifier = $request->getVal( 'wpFormIdentifier' );

    danteLog ("DanteBackup", "Form identifier: " . print_r ($formIdentifier, true));
    switch ($formIdentifier) {
      case 'formLOCAL':
        danteLog ("DanteBackup", "ALL: " . print_r ($_FILES, true) . "\n");
        danteLog ("DanteBackup", "name " . $_FILES['xmlimport']['name'] . "\n");
        danteLog ("DanteBackup", "mime: " . $_FILES['xmlimport']['type'] . "\n");
        danteLog ("DanteBackup", "size " . $_FILES['xmlimport']['size'] . "\n"); 
        danteLog ("DanteBackup", "tmp name " . $_FILES['xmlimport']['tmp_name'] . "\n");      
        danteLog ("DanteBackup", "error " . $_FILES['xmlimport']['error'] . "\n");
        $this->doImport ($_FILES['xmlimport']['tmp_name']);

        break;
      case 'formAWS':
        $fileName = $request->getVal ("srces");
        danteLog ("DanteBackup", "radio selected file is: ".$fileName. "\n");
        $this->doImportAWS ($fileName);

        break;
      case 'formURL':
        
        break;
    }

  }

  $this->showForm();
}


private function showForm() {
  $action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );
  $user   = $this->getUser();
  $out    = $this->getOutput();



  $out->addHTML ("<h2>Possibility 0: Uploading a file from your local computer</h2>"); 

  $form0 = [];
  $form0 += [ 'urlnull' => [ 'type' => 'text', 'label-message' => 'label-textfield', 'section' => 'restore-from-url-null',  'required' => true,  ] ];
  $form0 += [ 'urlnullzwo' => ['type' => 'file','name' => 'nextes', 'section' => 'restore-from-url-null', 'required' => true, ] ];
  $htmlForm0 = new HTMLForm( $form0, $this->getContext() );
  $htmlForm0->setAction( $action );
  $htmlForm0->setId( 'mw-import-upload-form-null' );
  $htmlForm0->setSubmitText( 'Restore from URLNULL' );
  $htmlForm0->setFormIdentifier( 'formURLNULL' );   // TODO: SELTSAMERWEISE IST DAs HIEr IMMER FEHLEND IM LOG ?????
  $htmlForm0->prepareForm()->displayForm( false );


$out->addHTML ("<h2>Possibility 1: Uploading a file from your local computer</h2>"); 

  $formLOCAL = [];
  $formLOCAL += [ 'xmlimport' => ['type' => 'file','name' => 'xmlimport', 'accept' => [ 'application/xml', 'text/xml', 'application/x-gzip-compressed', 'application/octet-stream' ], 'section' => 'select-local-file', 'required' => true, ] ];
  $htmlFormLOCAL = new HTMLForm( $formLOCAL, $this->getContext() );
  $htmlFormLOCAL->setAction( $action );
  $htmlFormLOCAL->setId( 'mw-import-upload-form' );
  $htmlFormLOCAL->setSubmitText( 'Restore from this file' );
  $htmlFormLOCAL->setFormIdentifier( 'formLOCAL' );
  $htmlFormLOCAL->prepareForm()->displayForm( false );

 // $htmlFormLOCAL->setSubmitCallback( [ $this, 'processInputLOCAL' ] );
//   $htmlFormLOCAL->show();


// TODO: check if we can upload a gzip., aes, aes and gzip file here !

//   $out->addHTML (wfMessage ("dante-page-restore-intro"));  // show some intro text


//	$this->addHelpLink( 'https://meta.wikimedia.org/wiki/Special:MyLanguage/Help:Import', true );

  $out->addHTML ("<h2>Possibility 2: Uploading a file from the AWS S3 storage area</h2>"); 
  $formAWS = [];
  $formAWS += [ 'source' => ['type' => 'hidden',	'name' => 'source',	'default' => 'upload',	'id' => '',] ];
  $formAWS += [ 'zradio88'  => [ 'type' => 'radio', 'section' => 'restore-from-aws', 'options' => $this->getFileArray (),   'name' => 'srces',  'default' => 'all',  ] ];
  $htmlFormAWS = new HTMLForm( $formAWS, $this->getContext() );
  $htmlFormAWS->setAction( $action );
  $htmlFormAWS->setId( 'mw-import-upload-form' );
  $htmlFormAWS->setSubmitText( 'Restore from AWS' );
  $htmlFormAWS->setFormIdentifier( 'formAWS' );
  $htmlFormAWS->prepareForm()->displayForm( false );

  $out->addHTML ("<h2>Possibility 3: Uploading a file to be retrieved from an Internet URL</h2>"); 
  $form2 = [];
  $form2 += [ 'url' => [ 'type' => 'text', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];
  $htmlForm2 = new HTMLForm( $form2, $this->getContext() );
  $htmlForm2->setAction( $action );
  $htmlForm2->setId( 'mw-import-upload-form' );
  $htmlForm2->setSubmitText( 'Restore from URL' );
  $htmlForm2->setFormIdentifier( 'formURL' );
  $htmlForm2->prepareForm()->displayForm( false );



//  $htmlForm->setWrapperLegendMsg( 'import-upload' );  // this provides an entire wrapper around the form
//  $htmlForm->setSubmitTextMsg( 'uploadbtn' );

//  $uploadFormDescriptor += ['intro' => [ 'type' => 'info', 'raw' => true, 'default' => $this->msg( 'importtext' )->parseAsBlock() ] ];  // only demo - not used
  // $htmlFormLOCAL->setWrapperLegendMsg( 'upload-local' );

}

*/

// return:   true: form will not display again
//           false: from WILL be displayed again
//           string: show the string as error message together with the form
public static function processInputLOCAL ( $formData ) {
  //return true;
   return print_r ( $formData,  true ) ;
}


protected function getGroupName() {return 'dante';}



private function getFileArray () {

 $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );
 // $aesPW            = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-encpw' );


  $env = DanteCommon::getEnvironmentUser ($this->getUser());


  $cmd = "/opt/myenv/bin/aws s3api list-objects-v2 --bucket {$bucketName} --query 'Contents[].[Key,LastModified,Size]' --output json";
  $retCode = Executor::executeAWS_FG_RET ( $cmd, $env, $output, $error );
  
  $retText = "";  // TODO: actually this is not really needed - only in error - which we do not deal with currently anyhow
  $retText .= "<h3>Command</h3><code>$cmd</code>"; 
  $retText .= "<h3>Information sent to <code>stdout</code></h3>" . nl2br (htmlspecialchars ($output, ENT_QUOTES, 'UTF-8')) . "";
  $retText .= "<h3>Information sent to <code>stderr</code></h3>" . nl2br (htmlspecialchars ( $error,  ENT_QUOTES, 'UTF-8')) . "";
  if ($retCode == 0) { $retText .= "<h3>Execution successful</h3>";}
  else               { $retText .= "<h3 style='color:red;'>Execution failed, return code $retCode </h3>";}

  $retText .= "<h3>Directory listening of $bucketName</h3>";

  $cmd = "/opt/myenv/bin/aws s3api list-objects-v2 --bucket {$bucketName} --query 'Contents[].[Key,LastModified,Size]' --output json";
  $retCode = Executor::executeAWS_FG_RET ( $cmd, $env, $output, $error );
  $objects = json_decode($output, true);  // Decode the JSON output into a PHP array

  $retArray = array ();
  if (is_array($objects)) {
    usort($objects, function ($a, $b) {return strtotime($b[1]) - strtotime($a[1]);});    // Sort the objects by LastModified in descending order
    $retText .= "<ul>";

    foreach ($objects as $object) { $retText .= '<li><span style="display:inline-block;width:400px;min-width:400px;">' . $object[0] . '</span><span style="display:inline-block;width:300px;">' . $object[1] . "</span>";
      $retText .= "<span style='display:inline-block;width:400px;'>". number_format ($object[2]/ (1024*1024), 2)  . "[MB] </span></li>\n"; 
      $retArray[  "<span style='display:inline-block;width:400px;'>".$object[0]."</span><span style='display:inline-block;width:300px;'>". $object[1] . "</span>".
       "<span style='display:inline-block;width:400px;'>". number_format ($object[2]/ (1024*1024), 2)  . "[MB] </span>"] = $object[0];

   }

    $retText .= "</ul>";
  }
  else { $retText .= "Did not get reply from aws";}
  return $retArray;
}







// TODO: plug this in into doIMport and then deprecate it here.
/*
public static function getCommandFile ( $name, $zip, $enc ) {
  global $IP; 
  $cmd = array ();


// TODO: WRONG SEQUENCE of zip and encrypt !!
///// TODO: can we allow to specify an upload pipe filter, eg for updating the timestamps ??????  and doing other filtering stuff ??????
///// TODO_ HOW to we import the PASSWORD fENVIRONMENT VARIABLE ???
  $prefix = "set -o pipefail; " . ($enc ? " openssl aes-256-cbc -d -salt -pass env:LOCAL_FILE_ENC | " : "" )  . ($zip ? "gunzip -c $name | " : "cat $name | ");

  array_push ( $cmd,  $prefix . " php $IP/maintenance/importDump.php --namespaces '8'  ");    // get MediaWiki: namespace (need Parsifal templates on board first)
  array_push ( $cmd,  $prefix . " php $IP/maintenance/importDump.php --namespaces '10' ");    // get Template: namespace
  array_push ( $cmd,  $prefix . " php $IP/maintenance/importDump.php --uploads  " );           // TODO: can we really merge this into "all the rest" ?????
  $cmd = self::addPostImport ( $cmd );                                                              // do maintenance stuff we need to do after every import
  return $cmd;
}

*/



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
  return $cmd;
}

private function doImport ($fileName) {
  global $IP;
  $enc = DanteCommon::checkSuffix ( $fileName, ".aes");
  $zip = DanteCommon::checkSuffix ( $fileName, ".gz" );

  $arr = array ();
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --namespaces '8'  ", $zip, $enc ) );    // get MediaWiki: namespace (need Parsifal templates on board first)
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --namespaces '10' ", $zip, $enc ) );    // get Template: namespace
  array_push ( $arr,  DanteCommon::cmdZipEncRestore ( "cat $fileName | ",  " php $IP/maintenance/importDump.php --uploads         ", $zip, $enc ) );    // TODO: can we really merge this into "all the rest" ?????
  $cmd = self::addPostImport ( $cmd );  // do maintenance stuff we need to do after every import

  danteLog ("DanteBackup", "\n WIll now enter executor \n");
  Executor::liveExecuteX ($arr);
  danteLog ("DanteBackup", "\n Left executor \n");
  return true;
}

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

  array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --namespaces '8' ",  $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --namespaces '10' ", $zip, $enc ) ); 
  array_push ($arr,  DanteCommon::cmdZipEncRestore ("/opt/myenv/bin/aws s3 cp s3://$bucketName/$fileName -  | ", " php $IP/maintenance/importDump.php --uploads ",         $zip, $enc ) ); 
  $arr = self::addPostImport ( $arr );



  Executor::liveExecuteX ($arr, $env);
  return true;
}


} // end class




