<?php

class DanteDBRestore extends SpecialPage {

public function __construct () {parent::__construct( 'DanteDBRestore', 'dante-dbrestore' ); }

public function getGroupName() {return 'dante';}
  
public function execute( $par ) {

  if (! $this->getUser()->isAllowed ("dante-dbrestore") ) {
    $this->getOutput()->addHTML ("You do not have the permission to restore a database dump.");
    return;}  

  $request = $this->getRequest();
  $names   = $request->getValueNames();
  $values  = $request->getValues (...$names);

  $types   = $request->getValues ("hidden");
  $type    = (isset ($types["hidden"])  ? $types["hidden"]  :  null );

  $zips     = $request->getValues ("compressed");
  $zip     = (isset ($zips["compressed"])  ? $zips["compressed"]  :  null );

  $encs    = $request->getValues ("encrypted");
  $enc     = (isset ($encs["encrypted"])   ? $encs["encrypted"]   :  null );

  $this->setHeaders();

  MWDebug::log ( "execute names:  " . print_r ( $names,  true )  );
  MWDebug::log ( "execute values: " . print_r ( $values, true )  );
  MWDebug::log ( "execute type: " .   print_r ( $type,   true )  );
  MWDebug::log ( "execute zip: " .   print_r ( $zip,   true )  );
  MWDebug::log ( "execute enc: " .   print_r ( $enc,   true )  );


  switch ( $type ) {
    case 'ContainerFile':  $this->getOutput()->addHTML ("CONTAINER....");            return;
    case 'AWS':           
      $accessKey        = $request->getValues ("username"); $accessKey        = (isset ($accessKey["username"])       ? $accessKey["username"] : null );
      $secretAccessKey  = $request->getValues ("password"); $secretAccessKey  = (isset ($secretAccessKey["password"]) ? $secretAccessKey["password"] : null );
      $name             = $request->getValues ("wpfield-aws3"); $name         = (isset ($name["wpfield-aws3"])            ? $name["wpfield-aws3"] : null );
      $this->getOutput()->addHTML ( $this->restoreFromAWS ( $accessKey, $secretAccessKey, $name, $zip, $enc ) );       return;
    default:
  }

  $defaultAccessKey = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-accesskey' );
  $defaultSecretAccessKey = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-secretaccesskey' );
  $defaultSpec = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  MWDebug::log ( "defaultAccessKey:  " . print_r ( $defaultAccessKey,  true )  );
  MWDebug::log ( "defaultSecretAccessKey: " . print_r ( $defaultSecretAccessKey, true )  );
  MWDebug::log ( "defaultSpec: " .   print_r ( $defaultSpec,   true )  );



  $formDescriptor2 = [
  'xmlimport' => [ 'type' => 'file', 'name' => 'xmlimport', 'accept' => [ 'application/xml', 'text/xml' ], 'label-message' => 'import-upload-filename',  'required' => true, ],
    'field-aws1' => [ 'section' => 'section1', 'class' => 'HTMLTextField',  'label' => 'AWS Access Key',       'name' => 'username', 'default' => $defaultAccessKey],
    'field-aws2' => [ 'section' => 'section1', 'class' => 'HTMLTextField',  'label' => 'AWS Secret Access Key', 'name' => 'password',  'default' => $defaultSecretAccessKey],
    'field-aws3' => [ 'section' => 'section1', 'class' => 'HTMLTextField',  'label' => 'S3 Bucket File Specification',  'default' => $defaultSpec      ],
    'field-type' => ['section' => 'section1', 'type' => 'hidden', 'name' => 'hidden', 'default' => 'AWS', ],

    'field-aws-zip' => [ 'section' => 'section2', 'class' => 'HTMLCheckField',  'label' => 'Compress', 'name' => 'compressed', 'type' => 'check'],
    'field-aws-enc' => [ 'section' => 'section2', 'class' => 'HTMLCheckField',  'label' => 'Encrypt',  'name' => 'encrypted', 'type' => 'check'],

  ];
  $htmlForm2 = new HTMLForm( $formDescriptor2, $this->getContext(), 'myform2' );
  $htmlForm2->setFormIdentifier( 'AWS' );
  $htmlForm2->setSubmitText( 'Restore from S3' );
  $htmlForm2->setSubmitCallback( [ $this, 'processInput' ] );  
  $htmlForm2->show();

  $this->getOutput()->addHTML ("<p><hr>");

  $script = "<script>";
  $script .= "document.getElementById ('mw-input-wpfield-aws1').setAttribute('autocomplete', 'username');";
  $script .= "document.getElementById ('mw-input-wpfield-aws2').setAttribute('autocomplete', 'current-password');";
  // $script .= "document.getElementById ('mw-input-wpfield-aws3').setAttribute('autocomplete', 'username');";
  $script .= "</script>";
  $this->getOutput()->addHTML ($script);
}



  private function restoreFromAWS ( $accessKey, $secretAccessKey, $name, $zip, $enc) {
    //if (! $this->getUser()->isAllowed ("resetParsifal") ) {return false;}                                       // check for permission "resetParsifal" 
    set_time_limit(300);

  putenv ("AWS_ACCESS_KEY_ID=$accessKey");
  putenv ("AWS_SECRET_ACCESS_KEY=$secretAccessKey");

  // NOTE: https://loige.co/aws-command-line-s3-content-from-stdin-or-to-stdout/
  // works for files upto 50 GB.

  // shell_exec('php /var/www/html/wiki-dir/maintenance/dumpBackup.php --full | aws s3 cp - s3://dantebackup.iuk.one/back1 ');
  // TODO: currently we have no secure way to inject the credentials of aws into the docker file.
  

// Using openssl encryption / decryption via piping:  https://serverfault.com/questions/326658/encrypt-tape-files-with-openssl-and-tar


// TODO: test if we get the error messages from aws s3 (eg when using inextistan eys etc)

  $cmd = " aws s3 cp s3://dantebackup.iuk.one/back1 -  | " .  ($enc ? " openssl aes-256-cbc -d -salt -pbkdf2 -iter 100000 -pass pass:password | " : "" )  .  ($zip ? " gunzip -c | " : "") . " php /var/www/html/wiki-dir/maintenance/importDump.php --uploads "; 
  $output=null;
  $retval=null;
  MWDebug::log ( "will execute: " .   print_r ( $cmd,   true )  );
  exec ($cmd, $output, $retval);
  
  $retText = implode ("<br>", $output) . "<br>return value = " . $retval;

  // now do an ls 
   $cmd = " aws s3 ls dantebackup.iuk.one/back1 --human-readable 2>&1 ";
  $output=null;
  $retval=null;
  exec ($cmd, $output, $retval);
  $retText .= "<hr>".implode ("<br>", $output) . "<br>return value = " . $retval;

  // remove any environment settings to prevent further processes from using them
  putenv ("AWS_ACCESS_KEY_ID=A");
  putenv ("AWS_SECRET_ACCESS_KEY=A");
  putenv ("AWS_DEFAULT_REGION=A");

  return $retText;
}

}

