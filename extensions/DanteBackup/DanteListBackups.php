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
  $accessKey       = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-accesskey' );
  $secretAccessKey = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-secretaccesskey' );
  $defaultSpec     = MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $this->getUser(), 'aws-bucketname' );

  // s3 ls returns the time in UTC timezone - since the operating system has no concept of a local time zone
  $cmd = "/opt/myenv/bin/aws s3 ls $defaultSpec --human-readable ";      // $cmd="printenv";
  $stdout = "";  $stderr = "";
  $duration=null;
  $retval = DanteUtil::executor ( $cmd, $stdout, $stderr, null, $duration, null, [ "AWS_ACCESS_KEY_ID" => $accessKey, "AWS_SECRET_ACCESS_KEY" => $secretAccessKey, "AWS_DEFAULT_REGION" => 'eu-central-1'] ); 

   // die (print_r ($stdout, true));

  $options = self::parseOutput ( $stdout );

  $formDescriptor2 = [
    'field-type' =>  ['section' => 'section1',       'type' => 'hidden',  'name' => 'hidden', 'default' => 'AWS', ],
        'radio'      =>  ['section' => 'listform-db',    'type' => 'radio',   'label' => 'Database',  'options' => $options  ] ,
    ];


  $htmlForm2 = new HTMLForm( $formDescriptor2, $this->getContext(), 'listform' );
  $htmlForm2->setFormIdentifier( 'AWS' );
  $htmlForm2->setSubmitText( 'Restore' );
  $htmlForm2->setSubmitCallback( [ $this, 'processInput' ] );  
  $htmlForm2->show();

  // if this function execution was a call (ie we have a hidden value), then go to execution and return
  // if ( strcmp ($type, "AWS") == 0) {
  //  $this->getOutput()->addHTML ( $this->dumpToAWS ( $accessKey, $secretAccessKey, $name, $zip, $enc ) );       return;}

}


private static function parseOutput ($resu) {


function sortByDate(array $items): array {
  usort($items, function ($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
  });
  return $items;
}

  $arr = [];
  $opt = [];
  $expo = explode ("\n", $resu);             // generate an array of result lines
  $num = 0;
  foreach ($expo as $line) {                        // iterate these lines
    $line = preg_replace ('/\s\s+/', " ", $line );  
    if ( strlen ($line) == 0 ) {break;}
    $data = explode (" ", $line);           // expands the line into date, time, size, unit, name
    // MWDebug::log ( print_r ($data, true));
    //  if (count ($item) ) {break;}
    // [$date, $time, $size, $unit, $name] = $items; 

    $item = ["date" => $data[0], "time" => $data[1], "size" => $data[2], "unit" => $data[3], "name" => $data[4]];

    $name=$data[4];
    if ( str_ends_with ($name, ".sql.gz.aes") || str_ends_with ($name, ".sql.aes") || str_ends_with ($name, ".sql.gz") || str_ends_with ($name, ".sql") ) 
      { $item["type"] = "Data Base";}
    elseif ( str_ends_with ($name, ".xml.gz.aes") || str_ends_with ($name, ".xml.aes") || str_ends_with ($name, ".xml.gz") || str_ends_with ($name, ".xml") ) 
      { $item["type"] = "File";}
    else { break;}
    $item["key"] = "<span style='display:inline-block;width:600px;'>".$item["name"]."</span> <span style=''>".$item["date"]."  ".$item["time"]." [UTC]</span> <span style='text-align:right;display:inline-block;width:100px;'>".$item["size"]."[".$item["unit"]."]</span>";
    $item["val"] =  $item["name"]; // $num++;
    MWDebug::log ( print_r ($item, true));
    if (strlen ($item["name"]) == 0) {break;}
    array_push( $arr, $item);
  }
  MWDebug::log ( print_r ($arr, true)) ;
  $arrSorted = sortByDate ($arr);
  MWDebug::log ( print_r ($arrSorted, true)) ;
  foreach ($arrSorted as $item) {
    $opt [ $item["key"] ] = $item["val"];
  }
  MWDebug::log ( print_r ($opt, true)) ;
  return $opt;
}


// called upon submission of the form displayed at the listing
public static function processInput( $formData ) { 
  die (print_r ($formData["radio"], true));

  return true; 
}



}

