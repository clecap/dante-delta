<?php
if ( function_exists( 'wfLoadExtension' ) ) {
    wfLoadExtension( 'DanteHideSection' );
    return;
}
else {
    die( 'This extension requires MediaWiki 1.25+' );
}

