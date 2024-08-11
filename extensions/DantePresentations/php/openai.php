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

$text = file_get_contents('yourfile.txt');  // Read the text file

// Define the question you want to ask about the text
$questions = array ( "What is the main theme of this text?",
  "Provide me with 10 questions which can be asked from students on this text!"
)

// Set up the prompt with the text and the question
$prompt = "Here is a text:\n\n" . $text . "\n\nAnswer the following question based on the text above: $question";

// Make the API request
$response = $client->completions()->create([
  'model' => 'text-davinci-003', // Use the appropriate model
  'prompt' => $prompt,
  'max_tokens' => 150,
  'temperature' => 0.5,
]);

// Output the answer
echo $response['choices'][0]['text'];


?>
