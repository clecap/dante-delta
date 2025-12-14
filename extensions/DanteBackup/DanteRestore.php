<?php

require_once ("Executor.php");       // TODO: should move away from this
require_once ("DanteCommon.php");    // TODO: really? check ?!

require_once ("extensions/DanteCommon/ServiceEndpointHelper.php");


class DanteRestore extends DanteSpecialPage {

public function __construct() {parent::__construct( 'DanteRestore', 'dante-restore' ); }

// page provides hint to read-only mode engine that it might do a write
public function doesWrites() {return true;}


protected function showForm  (): void  {
  // send post data to THIS url and add action=submit to the URL so we can distinguish showing this page from submitting data to it
  $action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );  

  $out    = $this->getOutput();
  $out->addModuleStyles ( ['ext.DanteBackup.specialpage.styles'] );  // this is a styles-only module which mediawiki loads early enough to prevent FOUC

  $out->addHTML (wfMessage ("dante-page-restore-intro"));

  // provide a help link
  $out->addHelpLink( 'index.php?title=Help:DanteRestore', true );  // provide a help link   // TODO: must fill with contents

  // POSSIBILITY 1
  $out->addHTML ("<h2>Possibility 1: Restore from a file on your computer</h2>"); 
  $form = [ 'xmlimport' => ['type' => 'file','name' => 'xmlimport', 'accept' => [ 'application/xml', 'text/xml', 'application/x-gzip-compressed', 'application/octet-stream' ], 'section' => 'select-local-file', 'required' => true, ] ];
  self::standardForm ($form, $action, "LOCAL", "Restore from file");

  // POSSIBILITY 2
  $out->addHTML ("<h2>Possibility 2: Restore from a file from the AWS S3 storage area</h2>"); 
  $out->addHTML ("The type (Pages, Database, Files) determines the restored elements.");
  $form = [ 'awsRadio'  => [ 'type' => 'radio', 'section' => 'restore-from-aws', 'options' => $this->getFileArray (),   'name' => 'awsRadio', 'required' => true  ] ];
  self::standardForm ($form, $action, "AWS", "Restore from AWS");

  // POSSIBILITY 3
  $out->addHTML ("<h2>Possibility 3: Restore from a file at an Internet URL</h2>"); 
  $form = [ 'url' => [ 'type' => 'text', 'name' => 'url', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];
  self::standardForm ($form, $action, "URL", "Restore from URL");

  // POSSIBILITY 4
  $out->addHTML ("<h2>Possibility 4: Restore from a file accessible by SSH (not yet operative)</h2>"); 
  $form = [ 'scp' => [ 'type' => 'text', 'name' => 'url', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ]
           + [ 'scpUser' => [ 'type' => 'text', 'label-message' => 'label-textfield', 'section' => 'restore-from-url',  'required' => true,  ] ];
  self::standardForm ($form, $action, "SCP", "Restore via SCP");
}



protected function getSpecificCommands ( $formId ): mixed {
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
      $fileName = $request->getVal ("awsRadio");                                           // pick up the selected file name
      danteLog ("DanteBackup", "radio selected file is: ".$fileName. "\n");     // log the relevant data if needed
      $arr = self::getCommandsAWS ($fileName);                                          // get an array of commands
      break;

     case 'formId_URL':
      $url = $request->getVal ("url");
      $this->doImportURL ( $url, $this->getUser() );

      break;
     case 'formId_SCP':
     // TODO
       throw new Exception ("Not yet implemented"); // TODO     
  

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





// generates a prefix for the choice form
private static function getPrefix ($name) {
  if (str_ends_with ($name, ".sql.gz.aes") || str_ends_with ($name, ".sql.aes") || str_ends_with ($name, ".sql.gz") || str_ends_with ($name, ".sql") ) return "<b  class='prefix-database'>Database: </b>";
  if (str_ends_with ($name, ".xml.gz.aes") || str_ends_with ($name, ".xml.aes") || str_ends_with ($name, ".xml.gz") || str_ends_with ($name, ".xml") ) return "<b  class='prefix-pages'>Pages: </b>";
  if (str_ends_with ($name, ".tar") || str_ends_with ($name, ".tar.aes") ) return "<b class='prefix-files'>Files:    </b>";
}



// does an ls of the  AWS S3 bucket and returns the result apropriately formatted for a radio button selection form for the file
private function getFileArray () {
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  $env = DanteCommon::getEnvironmentUser ($this->getUser());
  $cmd = "/opt/myenv/bin/aws s3api list-objects-v2 --bucket {$bucketName} --query 'Contents[].[Key,LastModified,Size]' --output json";
  $retCode = Executor::executeAWS_FG_RET ( $cmd, $env, $output, $error );  // TODO: still written in blocking mode - might want to fix eventually
  $retArray = array ();

  // parse the result
  if ($retCode == 0) {
    $objects = json_decode($output, true);  // Decode the JSON output into a PHP array // TODO: could be in error
    if (is_array($objects)) {
      $filtered = array_filter($objects, function ($object) { 
        $name = $object[0];
        return 
          str_ends_with ($name, ".sql.gz.aes") || str_ends_with ($name, ".sql.aes") || str_ends_with ($name, ".sql.gz") || str_ends_with ($name, ".sql") ||
          str_ends_with ($name, ".xml.gz.aes") || str_ends_with ($name, ".xml.aes") || str_ends_with ($name, ".xml.gz") || str_ends_with ($name, ".xml") ||
          str_ends_with ( $name, ".tar") || str_ends_with ($name, ".tar.aes");
      });
      $filtered = array_values ( $filtered );

      usort($filtered, function ($a, $b) {return strtotime($b[1]) - strtotime($a[1]);});    // Sort the objects by LastModified in descending order

      foreach ($filtered as $object) {
        $retArray[  "<span class='table-name'  >". self::getPrefix($object[0]). trim($object[0])."</span>".
                    "<span class='table-stamp' >". $object[1] . "</span>".
                    "<span class='table-size'  >". number_format ($object[2]/ (1024*1024), 2)  . "[MB] </span>"]   = $object[0];
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




