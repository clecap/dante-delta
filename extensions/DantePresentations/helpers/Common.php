<?php

/* Common code for supplying additional parameter data to DantePresentation endpoints */

namespace DPCommon;    // DantePresentation Common 




// generate query portion for DantePresentation endpoints
// input: $user   user Object
//        $title  title Object
function makeQuery ( $user, $title, $hiding=truie ) {
  $userName           = $user->getName();                       // user name or (in case of anonymous user) the IP address
  $userId             = $user->getId();                         // 0 if not existant or anonymous
  $namespaceIndex     = $title->getNamespace();                 // get number of namespace
  $dbkey              = $title->getDBKey();

  $query =     "Wiki-wgUserName="         .urlencode($userName)         . "&" .
               "Wiki-wgUserId="           .urlencode ($userId)          . "&" .
               "Wiki-wgNamespaceNumber="  .urlencode ($namespaceIndex)  . "&" .
               "Wiki-dbkey="              .urlencode ($dbkey)           . "&" .
               "Wiki-hiding=true";

  return $query;
}


// given the array arr of keys and (string-typed) values, parse properties of this array into its place for this object
// these values are set in DantePresentations.php or as header elements in preview.js or similar
// TODO: this is not yet completely harmonized between the different places which use these fields - some have more some less
// preview.js und DantePresentations.php must be harmonized TODO: make a common php file for this !!
function pickupDataFromArray ( $arr ) {
  // EndpointLog ("\n Pickup function sees: \n" . print_r ( $arr, true));

  if ( isset ( $arr["wiki-wgusername"] ) )         $this->userName  = $arr["wiki-wgusername"];
  if ( isset ( $arr["wiki-wgnamespacenumber"] ) )  $this->ns        =  intval ( $arr["wiki-wgnamespacenumber"] ) ; 
  if ( isset ( $arr["wiki-wgpagename"] ) )         $this->pageName  =  $arr["wiki-wgpagename"];                     // full name of page, including localized namespace name, if namespace has a name (except 0) with spaces replaced by underscores. 
  if ( isset ( $arr["wiki-wgtitle"] ) )            $this->title     =  $arr["wiki-wgtitle"];                        // includes blanks, no underscores, no namespace
  if ( isset ( $arr["wiki-dbkey"] ) )              $this->dbkey     =  $arr["wiki-dbkey"]; 
  if ( isset ( $arr["wiki-wgCurRevisionId"] ) )    $this->curRevisionId     =  $arr["wiki-wgCurRevisionId"]; 

//    $this->hiding              =  ( isset ($arr["Wiki-hiding"])                 ?   strcmp ($arr["Wiki-hiding"], "true")==0  :  false ); 
//    $this->sect              =  ( isset ($arr["sect"])                 ?   $arr["sect"] :  NULL ); 
//    if ($this->sect != NULL) {$this->sect = (int) $this->sect;}
}



?>
