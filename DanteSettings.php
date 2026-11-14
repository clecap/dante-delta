<?php

require_once ("DantePolyfill.php");                          // fill in some common functions for Dante and otherwise to keep config file clean from common function definitions.


#require_once ("DanteSettings-development.php");             // load configuration specifics for development
#require_once ("DanteSettings-production.php");              // load configuration specifics for production
require_once ("DanteSettings-used.php");                     // loads one of development or production as selected in the system based switch

require ("mediawiki-PRIVATE.php");                           // loads the secrets of the docker file and injects them into mediawiki


// Check the password "remember me" box by default (sitewide)
$wgHooks['AuthChangeFormFields'][] = function ($requests, $fieldInfo, &$formDescriptor, $action) {
    $formDescriptor['rememberMe']['type'] = 'check';
    $formDescriptor['rememberMe']['default'] = true;
    return true;
};

date_default_timezone_set( $wgLocaltimezone );  // causes all time zone functions to adhere to the value of $wgLocaltimezone, as it was set in mediawiki-PRIVATE.php

// protocol specifiers which are accepted for external links; adjusted, since we want to have javascript: included for activating scripts
$wgUrlProtocols = [
'bitcoin:', 'ftp://', 'ftps://', 'geo:', 'git://', 'gopher://', 'http://',
'https://', 'irc://', 'ircs://', 'magnet:', 'mailto:', 'matrix:', 'mms://',
'news:', 'nntp://', 'redis://', 'sftp://', 'sip:', 'sips:', 'sms:',
'ssh://', 'svn://', 'tel:', 'telnet://', 'urn:', 'worldwind://', 'xmpp:', 
'javascript:',
'//',
];


$wgDanteVersion = is_readable ("/etc/dante-version") ? mb_substr (trim (file_get_contents ("/etc/dante-version")), 0, 10) : "version-file-not-found";   // Dante version string from /etc/dante-version, at most 10 characters; fallback marker if file is missing or unreadable

##
## adjust look-and-feel
##
$wgLogo="/dante-assets/logo-180x180.png";
$wgLogos = false;
$wgFavicon = "/favicon.ico";


$wgRightsText=  "Copyright";   // This variable must be non-empty in order that the contents of MediaWiki:Copyright is shown 

$wgFooterIcons = [
/* we do not want a separate copyright notice as that icon interferes too much with the side chicks */
/*
  "copyright" => [
    "copyright" => [
      "src" => "/dante-assets/copyright-32.png",
      "url" => $wgServer.$wgScriptPath."/index.php?title=MediaWiki:CopyrightNotice",
      "alt" => "Copyright may be reserved",
      "id"  => "copyyright-icon-element",
      "style" => "object-fit:contain; transform: scale(0.5);  transform-origin: center; position:relative; left:20px;",  // NOTE: we want this here since in a css stylesheet via load.php we would see a FOUC
      "title" => "Links to the page explaining copyright"
], // placeholder for the built in copyright icon
  ], */
  "poweredby" => [
    "mediawiki" => [
			// Defaults to point at
			// "$wgResourceBasePath/resources/assets/poweredby_mediawiki_88x31.png"
			// plus srcset for 1.5x, 2x resolution variants.
			"src" => "/dante-assets/poweredby_dantewiki_88x31.png",
			"url" => "https://github.com/clecap/dante-wiki",
			"alt" => "Powered by DanteWiki",
      "title" => "Links to this software",
       "style" => "margin-right:50px;"   /* need distance from side chicks  */
  ]
  ],
];







$wgEnableUploads         = true;                     // allow uploads
$wgMaxUploadSize         = 100 * 1024 * 1024;        // allow large uploads of up to 100 MB
$wgAllowExternalImages   = true;                     // allow extenal images to be used 
$wgFileExtensions[]      = 'svg';                    // add svg to list of permitted file types



$wgJobRunRate = 10;             ## Job queue run rate


// MAIN must allow subpages
$wgNamespacesWithSubpages[NS_MAIN] = true;




$wgEditPageFrameOptions ="SAMEORIGIN";                // required to allow the preview iframe in the edit view to navigate to pages of the same 






wfLoadExtension( 'CategoryTree' );
wfLoadExtension( 'Gadgets' );                    // allows access to CSS and JS resources in MediaWiki namespace
wfLoadExtension ('RandomSelection');             // show a random selection of content (Spruch des Tages)
wfLoadExtension ('LabeledSectionTransclusion');  // allows transclusion of labeled sections of a page
wfLoadExtension ('Poem');                        // adds tag <poem> for better formatting of poems
wfLoadExtension ('Cite');                        // adds tags <ref> and <references /> to add citations to the page
wfLoadExtension ('CiteThisPage');                // adds Special:CiteThisPage and toolbox link to provide citations in Bibtex et al formats to the page
wfLoadExtension ('InputBox');                    // adds <inputbox> for allowing forms, eg create page box
wfLoadextension ('ImageMap');                    // adds <imagemap> tag for HTML clickable image maps 
wfLoadExtension ('Interwiki');                   // adds interwiki link formats
wfLoadExtension ('SyntaxHighlight_GeSHi');       // adds <syntaxhighlight> tag for syntax highlighting


##
## Complicated extensions: Manual configuration of extensions loaded in dockerfile but needing special adjustments
##
## 
$wgDrawioEditorBackendUrl=$wgServer.$wgScriptPath. "/external-services/draw-io/drawio-dev/src/main/webapp";

# fix default values in extension NativeMarkdown
$wgNativeMarkdownEverywhere=false;         // new pages do not default to markdown
$wgNativeMarkdownSuffixDetection=true;     // new pages whose title ends in .md default to Markdown, except in the Template and MediaWiki namespaces


###
### Dante Extensions: Configure and Load
###
wfLoadExtension( ext: 'DanteCommon' ); // core part for all dante extensions
wfLoadExtension( 'DanteBread' );
wfLoadExtension( 'DanteLinks' );
wfLoadExtension( 'DanteTree' );
wfLoadExtension( 'DanteTabs' );
wfLoadExtension( 'DanteSyntax' );
wfLoadExtension( 'DanteSnippets' );

// the sequence of loading should be:  1) Parsifal  2) DantePresentations  3) DanteHideSection  -  this influences the sequence of interaction possibilities in the edit links part of the page
wfLoadExtension( 'Parsifal' );
wfLoadExtension( 'DantePresentations' );
wfLoadExtension ('DanteHideSection');                 // allows to hide/collapse individual sections



##
## Permission system
##
$wgGroupPermissions['*']['edit'] = false;             # No anonymous editing
$wgGroupPermissions['docent']['edit'] = true;         # only used to generate the group docent
$wgGroupPermissions['*']['createaccount'] = false;    # currently prevent account creation

$wgGroupPermissions['sysop']['dante-restore']   = true;  // TODO: this should go into the registration function of the extension as a default somehow and not be required here. // NOTE: here: it must be before loading the extension 
$wgGroupPermissions['sysop']['dante-dump']      = true;
$wgGroupPermissions['sysop']['dante-dbrestore'] = true;
$wgGroupPermissions['sysop']['dante-dbdump']    = true;

wfLoadExtension( 'DanteBackup' );


// Switch off patrolling completely: no patrol links, no unpatrolled markers in recent changes, new pages and uploads; this also covers groups which would otherwise get the patrol right
$wgUseRCPatrol   = false;   // patrolling of edits in recent changes
$wgUseNPPatrol   = false;   // patrolling of new pages
$wgUseFilePatrol = false;   // patrolling of file uploads






$wgTopDante = new stdClass();  // a generic top level object helper for parsifal and dante, must be defined here, before loading Parsifal

##
## Skins
##
wfLoadSkin( 'skinny' );
wfLoadSkin( 'DantePresentationSkin' );
$wgDefaultSkin = "vector";       // use legacy Vector as default skin; overrides the "vector-2022" written into LocalSettings.php by the installer


# include installations of all skins which we did install here
if ( file_exists('DanteSkinsInstalled.php') ) {include ("DanteSkinsInstalled.php");}


# include stuff generated dynamically by scripts
require ("DanteDynamicInstalls.php");


## must manually insert this here
# wfLoadExtension( 'SemanticMediaWiki' );
####  TODO: adjust this accordingly; needed for sparql endpoints  compare: https://www.semantic-mediawiki.org/wiki/Help:EnableSemantics
# enableSemantics( );






##
## Configuring Extension wikEdDiff
##

$wgWikEdDiffFullDiff = false;             # Show complete un-clipped diff text (false)
$wgWikEdDiffShowBlockMoves = true;        # Enable block move layout with highlighted blocks and marks at their original positions (true)
$wgWikEdDiffCharDiff = true;              # Enable character-refined diff (true)
$wgWikEdDiffRepeatedDiff = true;          // Enable repeated diff to resolve problematic sequences (true)
$wgWikEdDiffRecursiveDiff = true;         // Enable recursive diff to resolve problematic sequences (true)  
$wgWikEdDiffRecursionMax = 10;            # Maximum recursion depth (10)
$wgWikEdDiffUnlinkBlocks = true;          # Reject blocks if they are too short and their words are not unique, prevents fragmentated diffs for very different versions (true)
$wgWikEdDiffUnlinkMax = 5;                # Maximum number of rejection cycles (5)
$wgWikEdDiffBlockMinLength = 3;           # Reject blocks if shorter than this number of real words (3)
$wgWikEdDiffColoredBlocks = false;        # Display blocks in differing colors (rainbow color scheme) (false)
$wgWikEdDiffNoUnicodeSymbols = false;     # Do not use UniCode block move marks (legacy browsers) (false)
$wgWikEdDiffStripTrailingNewline = true;  # Strip trailing newline off of texts (false)
$wgWikEdDiffDebug = false;                # Show debug infos and stats (block, group, and fragment data objects) in debug console (false)
$wgWikEdDiffTimer = false;                # Show timing results in debug console (false)
$wgWikEdDiffUnitTesting = false;          # Run unit tests to prove correct working, display results in debug console (false)

##
## COnfigure interwiki and transwiki
##

wfLoadExtension( 'Interwiki' );
$wgGroupPermissions['sysop']['interwiki'] = true;    // To grant sysops permissions to edit interwiki data
$wgImportSources = [ 'sourcewiki', 'commons', 'arxiv' ];



/* DEBUG code

if (false) {
  $entityBody = file_get_contents('php://input');
  if($tmpFile = fopen( __DIR__."/extensions/Parsifal/LOGFILE" , 'a')) 
    {
     fwrite($tmpFile, "+++++++++++++++++++++ DanteSettings.pgp was loaded ".$_SERVER['REQUEST_URI']."  " .$_SERVER['QUERY_STRING']."\n"); 
     // fwrite($tmpFile, "++++ $entityBody \n");   // if we also want to display the body of the call
     fclose($tmpFile); 
    }    
  else {throw new Exception ("debugLog in TexProcessor could not log"); }
}

*/







