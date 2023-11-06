
// this JS snippet implements correct and new treatment of target attributes in anchors
//
// target=_window    opens in a new window, similar size as opener
// target=_side      opens on the left side in a reasonable size
//
$( function(){
  $('a[target=_window]').click( function(){ window.open(this.href, "_", "noopener=1,noreferrer=1,width=100"); return false; });
  $('a[target=_side]').click(   function(){ window.open(this.href, "_", "height=800,width=800"); return false; });
  $('a[target=_Side]').click(   function(){ window.open(this.href, "_", "height=1200,width=1000"); return false; });

  // external links always open in a fresh tab
  $('a[class*="external"]').click( function(){window.open(this.href, "_blank", "noopener=1,noreferrer=1"); return false;});

} );


// console.error ("dantelinks.js loaded");