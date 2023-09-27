<?php
 
#
# System internal parameters to be used in development
#

$wgShowExceptionDetails    = true;
$wgShowDBErrorBacktrace    = true;
$wgShowSQLErrors           = true;
$wgDebugToolbar            = true;
#$wgShowDebug               = true;
$wgDevelopmentWarnings     = true;
$wgParserCacheType         = CACHE_NONE;
$wgCachePages              = false;


error_reporting( -1 );
ini_set( 'display_errors', 1 );

opcache_reset();

# how long are we caching responses from the ressource loader. values in seconds
$wgResourceLoaderMaxage = ['versioned' => 0, 'unversioned' => 0 ];
