<?php

require '../../../vendor/autoload.php';

use OpenAI\Client;

$yourApiKey = getenv('OPENAI_API_KEY');


$client = OpenAI::client($yourApiKey);

$response = $client->models()->list();


echo "List: " . print_r ($response, true);

echo "Object: " . print_r ($response->object, true) . "\n\n";

foreach ($response->data as $result) {
  echo "FULL: " . $result->id . print_r ( $result->object, true) . "\n";
   
}

$response->toArray(); // ['object' => 'list', 'data' => [...]]
?>
