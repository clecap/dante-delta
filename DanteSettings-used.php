<?php
 
#
# System internal parameters to be used in development
#

$wgDanteOperatingMode      = "Development";
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

error_reporting( -1 );
ini_set( 'display_errors', 1 );

opcache_reset();

# how long are we caching responses from the ressource loader. values in seconds
$wgResourceLoaderMaxage = ['versioned' => 0, 'unversioned' => 0 ];
