<?php
 
#
# System internal parameters to be used in development
# This is copied to DanteSettings-used.php in Special:ParsifalDebug

$wgDanteOperatingMode      = "Development and Deprecation";
$wgShowExceptionDetails    = true;
$wgShowDBErrorBacktrace    = true;
$wgShowSQLErrors           = true;
$wgDebugToolbar            = true;
#$wgShowDebug               = true;
$wgDevelopmentWarnings     = true;

$wgMessageCacheType        = CACHE_NONE;

$wgParserCacheType         = CACHE_NONE;
$wgCachePages              = false;
## $wgMainCache

$wgAllowVerbose            = true;

#error_reporting( -1 );
error_reporting (E_ALL );  # report all errors AND deprecations

ini_set( 'display_errors', 1 );


$wgRawHtml=true;

opcache_reset();

# how long are we caching responses from the ressource loader. values in seconds
$wgResourceLoaderMaxage = ['versioned' => 0, 'unversioned' => 0 ];
