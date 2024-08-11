<?php

require '../../../vendor/autoload.php';

use DeepL\Translator;

$deeplApiKey = getenv('DEEPL_API_KEY');

$translator = new \DeepL\Translator($deeplApiKey);

/*

split_sentences: specify how input text should be split into sentences, 

'on':           input text will be split into sentences using both newlines and punctuation.  (default)
'off':          input text will not be split into sentences. Use this for applications where each input text contains only one sentence.
'nonewlines':   input text will be split into sentences using punctuation but not newlines.


preserve_formatting: controls automatic-formatting-correction. Set to true to prevent automatic-correction of formatting, default: false.


formality: controls whether translations should lean toward informal or formal language. This option is only available for some target languages, see Listing available languages. 
Use the prefer_* options to apply formality if it is available for the target language, or otherwise fallback to the default.

'less':          use informal language.
'more':          use formal, more polite language.
'default':       use default formality.
'prefer_less':   use informal language if available, otherwise default.
'prefer_more':   use formal, more polite language if available, otherwise default.

tag_handling: type of tags to parse before translation, options are 'html' and 'xml'.


context: specifies additional context to influence translations, that is not translated itself. Characters in the context parameter are not counted toward billing. 
See the API documentation for more information and example usage.

glossary: glossary ID of glossary to use for translation.

The following options are only used if tag_handling is 'xml':

outline_detection: specify false to disable automatic tag detection, default is true.

splitting_tags: list of XML tags that should be used to split text into sentences. 
Tags may be specified as an array of strings (['tag1', 'tag2']), or a comma-separated list of strings ('tag1,tag2'). The default is an empty list.

non_splitting_tags:     list of XML tags that should not be used to split text into sentences. Format and default are the same as for splitting_tags.
ignore_tags:            list of XML tags that containing content that should not be translated. Format and default are the same as for splitting_tags.

The TranslateTextOptions class defines constants for the options above, for example TranslateTextOptions::FORMALITY is defined as 'formality'.

*/


$non_splitting_tags = "";     // tags which do not break text into seperately translated portions
$splitting_tags = "";          // tags which do break text into seperately translated portions
$ignore_tags ="";               // text containing these elements is not translated

$options =
['split_sentences'       => 'nonewlines',
  'preserve_formatting'  => 'false',
  'formality'            => 'prefer_more',
// glossary_id
  'tag_handling' => 'xml',
  "non_splitting_tags" => $non_splitting_tags,
  "splitting_tags"     => $splitting_tags,
  "ignore_tags"        => $ignore_tags,
  'send_platform_info' => false,
  'max_retries'        => 5,
  'timeout'            => 15.0,
];



$result = $translator->translateText('Hello, world!', 'en', 'fr');
echo "Result text is: ". $result->text; // Bonjour, le monde!


$usage = $translator->getUsage();

echo "Usage: " . print_r ($usage, true) . "\n";

if ($usage->anyLimitReached()) {echo 'Translation limit exceeded.';}
if ($usage->character) {echo 'Characters: ' . $usage->character->count . ' of ' . $usage->character->limit;}
if ($usage->document) {echo 'Documents: ' . $usage->document->count . ' of ' . $usage->document->limit;}



function translate () {




}








?>