<?php

require_once ("Executor.php");

class DanteRestore extends SpecialPage {
	
	public function __construct() {parent::__construct( 'DanteRestore', 'dante-restore' ); }

	public function doesWrites() {return true;}

	public function execute( $par ) {
    // TODO: test alternative of an exception 
    if (! $this->getUser()->isAllowed ("dante-restore") ) { $this->getOutput()->addHTML ("You do not have the permission to dump."); return;}  

		$this->useTransactionalTimeLimit();  // raise time limit for this operation

		$this->setHeaders();
		$this->outputHeader();

	//	$this->getOutput()->addModules( 'mediawiki.misc-authed-ooui' );
	//	$this->getOutput()->addModuleStyles( 'mediawiki.special.import.styles.ooui' );

		$this->checkReadOnly();  // TODO: what does this do ????

		$request = $this->getRequest();
		if ( $request->wasPosted() && $request->getRawVal( 'action' ) == 'submit' ) {
      $request = $this->getRequest();
      $sourceName = $request->getVal( 'source' );
      danteLog ("DanteBackup", "sourcename: $sourceName \n");
      danteLog ("DanteBackup", "ALL: " . print_r ($_FILES, true) . "\n");
      danteLog ("DanteBackup", "name " . $_FILES['xmlimport']['name'] . "\n");
      danteLog ("DanteBackup", "mime: " . $_FILES['xmlimport']['type'] . "\n");
      danteLog ("DanteBackup", "size " . $_FILES['xmlimport']['size'] . "\n"); 
      danteLog ("DanteBackup", "tmp name " . $_FILES['xmlimport']['tmp_name'] . "\n");      
      danteLog ("DanteBackup", "error " . $_FILES['xmlimport']['error'] . "\n");
      // danteLog ("DanteBackup", "full path " . $_FILES['xmlimport']['full_path'] . "\n");  // produces PHP  NOTICE: undefined index.

			$this->doImport ($_FILES['xmlimport']['tmp_name']);


		}
		$this->showForm();
	}



	private function showForm() {
		$action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );
		$user = $this->getUser();
		$out = $this->getOutput();
	//	$this->addHelpLink( 'https://meta.wikimedia.org/wiki/Special:MyLanguage/Help:Import', true );

		$uploadFormDescriptor = [];

			$uploadFormDescriptor += [
//				'intro' => [ 'type' => 'info', 'raw' => true, 'default' => $this->msg( 'importtext' )->parseAsBlock() ], // use this for some info text
				'xmlimport' => ['type' => 'file','name' => 'xmlimport', 'accept' => [ 'application/xml', 'text/xml' ],
					'label-message' => 'import-upload-filename', 'required' => true,
				],
				'source' => ['type' => 'hidden',	'name' => 'source',	'default' => 'upload',	'id' => '',],
			];


      $htmlForm = new HTMLForm( $uploadFormDescriptor, $this->getContext() );
			$htmlForm->setAction( $action );

      $htmlForm->setId( 'mw-import-upload-form' );
			$htmlForm->setWrapperLegendMsg( 'import-upload' );
			$htmlForm->setSubmitTextMsg( 'uploadbtn' );
			$htmlForm->prepareForm()->displayForm( false );
	}



	protected function getGroupName() {return 'dante';}



  public static function getCommandAWS ( $accessKey, $secretAccessKey, $name, $zip, $enc ) {
    global $IP; 
  
    // putenv ("AWS_ACCESS_KEY_ID=$accessKey"); putenv ("AWS_SECRET_ACCESS_KEY=$secretAccessKey");  // TODO: where do we set the environment ???
  
  // TODO: USE $name !!!!
    $cmd1 = " aws s3 cp s3://dantebackup.iuk.one/$name -  | " .  ($enc ? " openssl aes-256-cbc -d -salt -pass pass:password | " : "" )  .  ($zip ? " gunzip -c | " : "") . " php $IP/maintenance/importDump.php --namespaces '8' --debug 2>&1 "; 
    $cmd2 = " aws s3 cp s3://dantebackup.iuk.one/$name -  | " .  ($enc ? " openssl aes-256-cbc -d -salt -pass pass:password | " : "" )  .  ($zip ? " gunzip -c | " : "") . " php $IP/maintenance/importDump.php --namespaces '10' --debug 2>&1"; 
    $cmd3 = " aws s3 cp s3://dantebackup.iuk.one/$name -  | " .  ($enc ? " openssl aes-256-cbc -d -salt -pass pass:password | " : "" )  .  ($zip ? " gunzip -c | " : "") . " php $IP/maintenance/importDump.php --uploads --debug 2>&1" ; 
    // $rebuild   = "php /var/www/html/wiki-dir/maintenance/importDump.php --uploads --debug"; //// TODO this is not rebuild but a damaged importDump for error reporting tests 
    $rebuild   = "php $IP/maintenance/rebuildrecentchanges.php"; 
    $initStats = "php maintenance/initSiteStats.php --update  2>&1";
    return array ($cmd1, $cmd2, $cmd3, $rebuild, $initStats);
  }


  public static function getCommandFile ( $name, $zip, $enc ) {
    global $IP; 
    
    $cmd0 = " cp $name /tmp/keepme.xml";

    $cmd1 = " php $IP/maintenance/importDump.php --namespaces '8' --debug $name  "; 
    danteLog ("DanteBackup", "CMD1: " . $cmd1 . "\n"); 
    
    $cmd2 = " php $IP/maintenance/importDump.php --namespaces '10' --debug $name "; 
    danteLog ("DanteBackup", "CMD1: " . $cmd2 . "\n");     
    
    $cmd3 = " php $IP/maintenance/importDump.php --debug $name " ; 
    danteLog ("DanteBackup", "CMD1: " . $cmd3 . "\n"); 
    
    $cmd4 = " php $IP/maintenance/importDump.php --uploads --debug $name " ; 
    danteLog ("DanteBackup", "CMD1: " . $cmd4 . "\n"); 
    
    $rebuild   = "php $IP/maintenance/rebuildrecentchanges.php"; 
    danteLog ("DanteBackup", "CMD1: " . $rebuild . "\n"); 
    
    $initStats = "php maintenance/initSiteStats.php --update ";
    danteLog ("DanteBackup", "CMD1: " . $initStats . "\n"); 

    $rebuildImages   = "php $IP/maintenance/rebuildImages.php"; 

    $rebuildAll   = "php $IP/maintenance/rebuildall.php"; 
    $checkImages   = "php $IP/maintenance/checkImages.php"; 
 
    $refresh = "php $IP/maintenance/refreshFileHeaders.php --verbose";

    return array ($cmd4);

//    return array ($cmd0, $cmd1, $cmd2, $cmd4, $rebuild, $initStats, $rebuildImages, $rebuildAll, $checkImages, $refresh);
  }









  private function doImport ($fileName) {

   $arr =self::getCommandFile ( $fileName, false, false);
  Executor::liveExecuteX ($arr, "<br><br><a href='../index.php/Main_Page'>Main Page</a>");
 // putenv ("AWS_ACCESS_KEY_ID=A"); putenv ("AWS_SECRET_ACCESS_KEY=A");
 return true;


  }




}






/*
require_once ("DanteCommon.php");
require_once ("Executor.php");

class DanteRestore extends SpecialPage {

public function __construct () {parent::__construct( 'DanteRestore', 'dante-restore' ); }

public function getGroupName() {return 'dante';}
  

public function execute( $par ) {
  if (! $this->getUser()->isAllowed ("dante-restore") ) {$this->getOutput()->addHTML ("You do not have the permission to restore."); return;}  

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
  $this->getOutput()->addStyle ("../extensions/DanteBackup/danteBackup.css");  // htmlform-tip  // TODO: really needed ?? // TODO: IMPROVE PATH // YES for classes !

// get the values stored in the preferences
  $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );


  $retCode = "";
  try {
    $retCode = Executor::executeAWS_FG_RET ( new AWSEnvironmentPreparatorUser ($this->getUser()), " aws s3 ls $bucketName --human-readable ", $output, $error );
  
  (new AWSEnvironmentPreparatorUser ($this->getUser()))->prepare();  // to compensate for the clearing which we do in executeAWS_FG_RET;  TODO: MUST clear this later 

} catch (\Exception $x) {}

  $retText = "";
  if ($retCode != 0) {$retText .= "<hr>ERROR ".   preg_replace ("/\n/", "<br>", $error) . "<hr>"; return;}   // TODO: no, we could still offer local file selection !!

  $output = "";
  $arrObs = Executor::parseColumns ($output, ["date", "time", "size", "unit", "name"]);
  $opts = [];  // generate the options for the selection area
  foreach ($arrObs as $ob) {$opts [ $ob["name"]." <b>". $ob["date"]. " ". $ob["time"] . " (UTC)</b> " . $ob["size"] . "["  .$ob["unit"] . "]"] = $ob["name"];}

  $formDesc = [ 
   // 'radioRes' => [ 'section' => 'restore-from-s3',   'type' => 'radio', 'label' => '', 'options' => $opts, 'name' => 'selected' ],
    'filesel'  => [ 'section' => 'restore-form-file', 'type' => 'file',  
      'uploadable' => true, 'label' => 'Fileselec', 'name' => 'selfile', 'cssclass' => 'fileselector' ]
  ];

  $htmlForm2 = new HTMLForm( $formDesc, $this->getContext(), 'restform' );
 
 
   $htmlForm2->setSubmitText( 'Restore' );

   $htmlForm2->setSubmitCallback( [ $this, 'processInput' ] );

  $request = $this->getRequest();
  if ( $request->wasPosted() && $request->getRawVal( 'action' ) == 'submit' ) {
    //$this->doImport();
  
    $source = ImportStreamSource::newFromUpload( "xmlimport" );
    throw new Exception (  print_r ($source, true));
  }
  $htmlForm2->show();
}











private function restoreFromAWS ( $accessKey, $secretAccessKey, $name, $zip, $enc) {
  set_time_limit(300);  // TODO fix

  putenv ("AWS_ACCESS_KEY_ID=$accessKey"); putenv ("AWS_SECRET_ACCESS_KEY=$secretAccessKey");

  $cmd = " aws s3 cp s3://dantebackup.iuk.one/back1 -  | " .  ($enc ? " openssl aes-256-cbc -d -salt -pass pass:password | " : "" )  .  ($zip ? " gunzip -c | " : "") . " php /var/www/html/wiki-dir/maintenance/importDump.php --uploads "; 
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
  putenv ("AWS_ACCESS_KEY_ID=A"); putenv ("AWS_SECRET_ACCESS_KEY=A");
  return $retText;
}
 


public static function getCommand ( $accessKey, $secretAccessKey, $name, $zip, $enc ) {
  global $IP; 

  // putenv ("AWS_ACCESS_KEY_ID=$accessKey"); putenv ("AWS_SECRET_ACCESS_KEY=$secretAccessKey");  // TODO: where do we set the environment ???

// TODO: USE $name !!!!
  $cmd1 = " aws s3 cp s3://dantebackup.iuk.one/$name -  | " .  ($enc ? " openssl aes-256-cbc -d -salt -pass pass:password | " : "" )  .  ($zip ? " gunzip -c | " : "") . " php $IP/maintenance/importDump.php --namespaces '8' --debug 2>&1 "; 
  $cmd2 = " aws s3 cp s3://dantebackup.iuk.one/$name -  | " .  ($enc ? " openssl aes-256-cbc -d -salt -pass pass:password | " : "" )  .  ($zip ? " gunzip -c | " : "") . " php $IP/maintenance/importDump.php --namespaces '10' --debug 2>&1"; 
  $cmd3 = " aws s3 cp s3://dantebackup.iuk.one/$name -  | " .  ($enc ? " openssl aes-256-cbc -d -salt -pass pass:password | " : "" )  .  ($zip ? " gunzip -c | " : "") . " php $IP/maintenance/importDump.php --uploads --debug 2>&1" ; 
  // $rebuild   = "php /var/www/html/wiki-dir/maintenance/importDump.php --uploads --debug"; //// TODO this is not rebuild but a damaged importDump for error reporting tests 
  $rebuild   = "php $IP/maintenance/rebuildrecentchanges.php"; 
  $initStats = "php maintenance/initSiteStats.php --update  2>&1";
  return array ($cmd1, $cmd2, $cmd3, $rebuild, $initStats);
}



// return:   true: form will not display again
//           false: from WILL be displayed again
//           string: show the string as error message together with the form
public static function processInput( $formData ) {

//  $fileName = $formData["radioRes"];  

  $file = $formData["filesel"];
 
 // throw new Exception (  print_r ($source, true));


  // get the values stored in the preferences
  // $bucketName       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

 // $arr =self::getCommand ("", "", $fileName, false, false);
 // Executor::liveExecuteX ($arr, "<br><br><a href='../index.php/Main_Page'>Main Page</a>");
 // putenv ("AWS_ACCESS_KEY_ID=A"); putenv ("AWS_SECRET_ACCESS_KEY=A");
  return true;
  // return print_r ( $formData,  true ) ;
}


}

*/

