<?php

#
# System internal parameters to be used in production
#


## DEBUG messages
$wgDanteOperatingMode      = "Production";
$wgShowExceptionDetails  = false;
$wgShowDBErrorBacktrace  = false;
$wgShowSQLErrors         = false;
$wgDebugToolbar          = false;
$wgShowDebug             = false;
$wgDevelopmentWarnings   = false;

## CACHING
$wgParserCacheType       =  CACHE_ACCEL; 
$wgCachePages            = true;
$wgMainCacheType         = CACHE_ACCEL;

$wgEnableSidebarCache    = false;    # we must not cache sidebar since we often change the sidebar, even dynamically

# we need to invalidate cache when a configuration is changed or when Parsifal cache is cleared.
# setting this variable ensures this since Parsifal cache clearing special page will touch LocalSettings.php
$wgInvalidateCacheOnLocalSettingsChange=true;

# how long are we caching responses from the ressource loader. values in seconds
$wgResourceLoaderMaxage = [
  'versioned'   => 30 * 24 * 60 * 60, // 30 days
  'unversioned' => 5 * 60 ];          // 5 minutes


$wgCacheDirectory = "$IP/cache";