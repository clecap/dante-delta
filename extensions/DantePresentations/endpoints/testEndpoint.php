<?php




// need the following two lines to obtain reasonable errors from the endpoint instead of only 500 er status from webserver
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once ("danteEndpoint.php");


class TestEndpoint extends DanteEndpoint {

public function getContent ( $input ) {
  $this->stringContent = "Hello World";
  return 1;
}

}

$point = new TestEndpoint();
$point->execute();



