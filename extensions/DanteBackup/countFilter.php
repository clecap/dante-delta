<?php


class PageCounter
{
  private int $pageCount = 0;
  private $parser;
  private $stdin;

  // A small sliding window of lines for error reporting
  private array  $lineBuffer = [];
  private int    $lineNumber = 0;
  private string $leftover = '';

  public function __construct() {
    $this->stdin = fopen('php://stdin', 'r');
    $this->parser = xml_parser_create();

    xml_set_object($this->parser, $this);
    xml_set_element_handler($this->parser, 'startElementHandler', null);
  }

  public function run(): void {
    while ($data = fread($this->stdin, 4096)) {
      $this->captureLines($data);

      if (!xml_parse($this->parser, $data, feof($this->stdin))) {
        $errorCode = xml_get_error_code($this->parser);
        $errorLine = xml_get_current_line_number($this->parser);

        $this->reportError($errorCode, $errorLine);
        $this->cleanup();
        exit(1);
      }
    }

    $this->cleanup();
    echo "Number of <PAGE> elements found: " . $this->pageCount . PHP_EOL;
  }

  public function startElementHandler($parser, string $name, array $attrs): void { if ($name === 'PAGE') { $this->pageCount++; } }

  private function captureLines(string $chunk): void
  {
    // Prepend any leftover from previous chunk
    $chunk = $this->leftover . $chunk;
    $lines = explode("\n", $chunk);
    $this->leftover = array_pop($lines);

    foreach ($lines as $line) {
      $this->lineNumber++;
      $this->lineBuffer[$this->lineNumber] = $line;

      // Keep only relevant recent lines
      if (count($this->lineBuffer) > 500) {
        array_shift($this->lineBuffer);
      }
    }
  }

  private function reportError(int $errorCode, int $errorLine): void {
    $msg = xml_error_string($errorCode);
    fprintf(STDERR, "XML Error: %s at line %d\n", $msg, $errorLine);

    $before = $this->lineBuffer[$errorLine - 1] ?? null;
    $at     = $this->lineBuffer[$errorLine]     ?? null;
    $after  = $this->lineBuffer[$errorLine + 1] ?? null;

    if ($before !== null) { fprintf(STDERR, "  %d: %s\n", $errorLine - 1, $before); }
    if ($at !== null)     { fprintf(STDERR, "> %d: %s\n", $errorLine, $at); }
    if ($after !== null) { fprintf(STDERR, "  %d: %s\n", $errorLine + 1, $after); }
  }

  private function cleanup(): void {
    if ($this->parser) {xml_parser_free($this->parser); $this->parser = null; }
    if ($this->stdin) { fclose($this->stdin); $this->stdin = null; }
  }
}

$counter = new PageCounter();
$counter->run();

?>