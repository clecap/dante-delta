<?php
// Converts the JSON output of Phan (--output-mode json) into SARIF 2.1.0, as Phan has no SARIF output, for upload to GitHub code scanning
// Usage: php phan-to-sarif.php phan-results.json phan-results.sarif

/**
 * Map a Phan severity (0 low, 5 normal, 10 critical) to a SARIF level.
 *
 * @param int $severity Phan severity
 * @return string SARIF level: note, warning or error
 */
function sarifLevel ( int $severity ): string {
  return $severity >= 10 ? 'error' : ( $severity >= 5 ? 'warning' : 'note' );
}

/**
 * Build a SARIF log from the issues reported by Phan.
 *
 * @param array $issues Decoded Phan JSON output: list of issues with check_name, description, severity, location
 * @return array SARIF log, ready for json_encode
 */
function phanToSarif ( array $issues ): array {
  $rules = [];  $results = [];
  // one SARIF rule per Phan issue type, one SARIF result per reported issue
  foreach ( $issues as $issue ) {
    $ruleId = $issue['check_name'];
    $level  = sarifLevel ( (int) $issue['severity'] );
    $rules[$ruleId] ??= [ 'id' => $ruleId, 'shortDescription' => [ 'text' => $ruleId ], 'helpUri' => 'https://www.mediawiki.org/wiki/Phan-taint-check-plugin', 'defaultConfiguration' => [ 'level' => $level ] ];
    $region = [ 'startLine' => max ( 1, (int) $issue['location']['lines']['begin'] ) ];
    if ( isset ( $issue['location']['lines']['begin_column'] ) ) { $region['startColumn'] = (int) $issue['location']['lines']['begin_column']; }
    $results[] = [
      'ruleId'    => $ruleId,
      'level'     => $level,
      'message'   => [ 'text' => $issue['description'] . ( isset ( $issue['suggestion'] ) ? ' (' . $issue['suggestion'] . ')' : '' ) ],
      'locations' => [ [ 'physicalLocation' => [ 'artifactLocation' => [ 'uri' => preg_replace ( '@^\./@', '', $issue['location']['path'] ), 'uriBaseId' => '%SRCROOT%' ], 'region' => $region ] ] ],
    ];
  }
  return [
    '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
    'version' => '2.1.0',
    'runs'    => [ [ 'tool' => [ 'driver' => [ 'name' => 'Phan', 'informationUri' => 'https://github.com/phan/phan', 'rules' => array_values ( $rules ) ] ], 'results' => $results ] ],
  ];
}

// read Phan output, convert, write SARIF; a missing or broken input is an error, so a failed Phan run is not mistaken for a clean one
[ , $in, $out ] = $argv + [ null, null, null ];
if ( $in === null || $out === null ) { fwrite ( STDERR, "Usage: php phan-to-sarif.php <phan.json> <out.sarif>\n" ); exit ( 2 ); }
$issues = json_decode ( (string) @file_get_contents ( $in ), true );
if ( !is_array ( $issues ) ) { fwrite ( STDERR, "Cannot read Phan JSON output from $in\n" ); exit ( 1 ); }
file_put_contents ( $out, json_encode ( phanToSarif ( $issues ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo count ( $issues ) . " Phan issues converted to $out\n";
