(() => {  // scope protection

  const globalMaxCrumbs = 20;
  const maxLength =   50;  
  const siteMaxCrumbs = 8; 


/** The DATA STRUCTURE used here is an array of objects of type {pageKey, pageName}
 *    pageKey:       the DB key of the page to call when clicking on the breadcrumb
 *    pageName:      the name to display for the breadcrumb
 *    
 */


window.clearBreadcrumbs = function() {
  localStorage.setItem ("breadcrumbs", JSON.stringify([]));
  window.doBreadNow();
}


  window.doBreadNow = function () {   
    var breadcrumbs = localStorage.getItem ("breadcrumbs");                          // get current breadcrumbs from localStorage (allows cross window breadcrumbs)
    //console.warn ("doBreadNow: localStorage delivered: ", breadcrumbs);
    if ( breadcrumbs ) { try {breadcrumbs = JSON.parse( breadcrumbs );} catch ( e ) { breadcrumbs = [];} } else {breadcrumbs = [];}

    // remove this URL from the breadcrumbs if it is already in it
    var url = location.pathname; // + location.search;
    var index = 0;
    while ( index < breadcrumbs.length ) {
      if ( breadcrumbs[ index ].url === url ) {breadcrumbs.splice( index, 1 );} else {index++;}
    }

    // format breadcrumbs for display
    var visibleCrumbs = [];
    for ( index = breadcrumbs.length - 1; index >= 0; index-- ) {  // step backwards through the crumbs
        if ( visibleCrumbs.length < siteMaxCrumbs ) {
          var breadcrumb = breadcrumbs[ index ];
          var link = '<a href="' + breadcrumb.url + '">';
          var title = breadcrumb.title;
          if ( title.length > maxLength ) {title = title.substr( 0, maxLength ) + '...';}
          link += title + '</a>';
          visibleCrumbs.push( link );
        } else {breadcrumbs.splice( index, 1 );}
      
    }

    // truncate breadcrumbs to maximal length
    if ( breadcrumbs.length > globalMaxCrumbs ) { breadcrumbs = breadcrumbs.slice( breadcrumbs.length - globalMaxCrumbs ); }
     
    localStorage.setItem ("breadcrumbs", JSON.stringify(breadcrumbs));   // save rbeadcrumbs
    
    var txt = "";
    for ( index = 0; index < visibleCrumbs.length; index++ ) {txt += ' ' + visibleCrumbs[ index ] + ' &raquo; ';}
    txt += "<a href='javascript:window.clearBreadcrumbs();' class='oo-ui-panelLayout-framed'   style='font-size:7pt; padding:1pt;' title='Clear breadcrumbs'>del</a>";
    document.getElementById ("breadcrumbinsert").innerHTML = txt;
    return txt;
    
  }

  // return true when pagenames p1 and p2 should be considered a duplicate of url2 for purposes of breadcrumb insertion; both parameters must be strings
  const nameEqual = (p1, p2) => {
    console.warn ("nameEqual called for: " + p1 + "    " + p2 );

    if (p1 === p2) { return true; }   // if they are exactly identical, they are identical
 
//   let URL1, URL2;
//    try {URL1 = new URL (url1, base); URL2 = new URL (url2, base);} catch (x) {throw new Error (x + " url1="+url1, "url2="+url2);}
//    let par1 = new URLSearchParams (par1); let par2 = new URLSearchParams (par2);
//    if (par1.title == par2.title) {return true;}
    return false;
  };


  window.addFreshCrumb = function (pageName, pageKey) {
    console.warn ("addFreshCrumb called with pageName=" + pageName + "  and  pageKey=" + pageKey);
    var breadcrumbs = localStorage.getItem ("breadcrumbs");          // get current breadcrumbs from localStorage (this allows cross window breadcrumbs)

    console.warn ("addFreshCrumb got from localStorage: ", breadcrumbs);
    if ( breadcrumbs ) { try {breadcrumbs = JSON.parse( breadcrumbs );} catch ( e ) { breadcrumbs = [];} } else {breadcrumbs = [];}

    var index = 0;
    while ( index < breadcrumbs.length ) {
      if ( nameEqual (breadcrumbs[ index ].pageName, pageName) ) {breadcrumbs.splice( index, 1 );} else {index++;}
    }

    // add the current URL to the breadcrumbs if it points to a valid page
    if (  pageName.substring( pageName.length - 8 ) !== 'Badtitle' ) {
      breadcrumbs.push( {pageKey, pageName} );
    }
    else {
      console.warn ("addFreshCrumb: url found violates rules, it is: " + url + "  at " + pageName);
    }
    
    // truncate breadcrumbs to maximal length
    if ( breadcrumbs.length > globalMaxCrumbs ) { breadcrumbs = breadcrumbs.slice( breadcrumbs.length - globalMaxCrumbs ); }
     
    localStorage.setItem ("breadcrumbs", JSON.stringify(breadcrumbs));   // save rbeadcrumbs
    //console.warn ("addFreshCrumbs stored: ", JSON.stringify(breadcrumbs));
  }  
})();





















