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



}

}