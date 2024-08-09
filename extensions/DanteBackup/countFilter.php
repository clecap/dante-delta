<?php

$pageCount = 0;                        // Initialize the counter for <PAGE> elements
$stdin = fopen('php://stdin', 'r');    // Open a handle to read from stdin
$parser = xml_parser_create();         // Create an XML parser

// Define a handler for the start element
function startElementHandler($parser, $name, $attrs) {
  global $pageCount;
  if ($name === 'PAGE') {$pageCount++;}
}

// Set the element handler for the XML parser
xml_set_element_handler($parser, 'startElementHandler', null);

// Read the XML input in chunks and parse it
while ($data = fread($stdin, 4096)) {
  if (!xml_parse($parser, $data, feof($stdin))) {
    // Handle XML parsing errors
    fprintf(
      STDERR,
      "XML Error: %s at line %d\n",
      xml_error_string(xml_get_error_code($parser)),
      xml_get_current_line_number($parser)
    );
    exit(1);
  }
}

// Free the XML parser
xml_parser_free($parser);

// Close the stdin handle
fclose($stdin);

// Output the count of <PAGE> elements
echo "Number of <PAGE> elements found: " . $pageCount . PHP_EOL;
?>