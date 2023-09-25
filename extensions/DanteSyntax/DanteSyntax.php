<?php


class DanteSyntax {
  
public static function onParserBeforeInternalParse( Parser &$parser, &$text, &$strip_state ) { 
  

}

// Parse function
private static function dollarParse( &$parser, &$text, &$stripState ) {
  // We replace '$...$' by '<amsmath>...</amsmath>' and '\$' by '$'.
  $pattern = array('/([^\\\\]|^)\$([^\$]*)\$/', '/\\\\\$/');
  $replace = array('$1<amsmath>$2</amsmath>', '\$');
  $text = preg_replace($pattern, $replace, $text);
  return true;
}

// See: https://www.mediawiki.org/wiki/Extension_talk:BacktickCode

private static function backtickParse ( &$parser, &$text, &$stripState ) {

  // MediaWiki itself uses backticks in the `UNIQ and QINU` blocks. Changing those breaks the stripstate of the parser
  
  // MARKER_PREFIX = "\x7f'\"`UNIQ-";    and      MARKER_SUFFIX = "-QINU`\"'\x7f";
  // FIRST: Construct alternative markers using a tilde instead of a backtick by going to $fixprefix and $fixsuffix
  $fixprefix = preg_replace('/`/', '~', Parser::MARKER_PREFIX);
  $fixsuffix = preg_replace('/`/', '~', Parser::MARKER_SUFFIX);

  // SECOND: in the text, go from Parser::MARKER_PREFIX to $fixprefix and similar for the suffix.
  $text = str_replace(Parser::MARKER_PREFIX, $fixprefix, $text);
  $text = str_replace(Parser::MARKER_SUFFIX, $fixsuffix, $text);

  // THIRD: Produce the code blocks
  // Include the \x7f to ensure our pair of backticks does not span a UNIQ ... QINU pair
  // See https://stackoverflow.com/questions/11044136/right-way-to-escape-backslash-in-php-regex  for why four backslashes match a backslash
  $text = preg_replace ('/([^\\\\]|^)`([^`\x7f]*)`/', '$1<code data-src="DanteSyntax">$2</code>', $text);

  // ALTERNATIVE code which preserves the backticks between <amsmath> ... </amsmath> pairs.
  // NOTE that the <amsmath> opening tag also may contain attributes
  $text = preg_replace_callback ('/(<amsmath[^>]*>)(.*?)<\/amsmath>/s', function ($match) {return $match[1] . preg_replace('/`/', '\`', $match[2]) . '</amsmath>';}, $text);

  // FORTH: replace escaped backticks by backticks
  $text = preg_replace ('/\\\\\`/', '`', $text);

  // FIFTH: go back from our hiding prefix/suffix to the proper ones.
  $text = str_replace($fixprefix, Parser::MARKER_PREFIX, $text);
  $text = str_replace($fixsuffix, Parser::MARKER_SUFFIX, $text);

  return true;
};


}