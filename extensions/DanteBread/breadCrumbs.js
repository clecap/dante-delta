 console.error ("--------------------DanteBread will be  loaded ");

(() => {  // scope protection

// Declaration
class BreadCrumbs {
  static globalMaxCrumbs =  8;
  static maxLength       = 20;  

  static add (pageName, pageKey, prefix) {
    // console.info ("breadCrumbs.add: ", pageName, pageKey, prefix);
    if (pageName.startsWith ("Special:") ) { // console.info ("BreadCrumbs:add not including Special: pages into breadcrumbs");
      return;
    }
    let bc = BreadCrumbs.get();
    if ( bc.map ( x => x.pageName ).includes(pageName) ) { // console.info ("breadCrumbs.add: breadcrumbs already include this ", pageName, pageKey);
      return;}
    bc.push ( {pageName, pageKey, prefix} );
    // truncate breadcrumbs to maximal length
    if ( bc.length > BreadCrumbs.globalMaxCrumbs ) { bc = bc.slice( bc.length - BreadCrumbs.globalMaxCrumbs ); }
    localStorage.setItem ("breadcrumbs", JSON.stringify( bc ));      
  }

  static get () {                                             // console.info ("BreadCrumbs.get: obtaining a healthy breadcrumbs array");
    let breadcrumbs = localStorage.getItem ("breadcrumbs");   // console.info ("BreadCrumbs.get: got from local storage: ", breadcrumbs);
    try {
      if ( !breadcrumbs ) { throw new Error ("breadcrumbs in storage were falsish "); }                                                   
      breadcrumbs = JSON.parse( breadcrumbs );
      if ( !Array.isArray (breadcrumbs) ) { throw new Error ("breadcrumbs in storage were not an array ");}
      return breadcrumbs; }
    catch (e) { 
      console.info ("BreadCrumbs.get exception triggered, reinitializing. Reason was: ", e);
      localStorage.setItem ("breadcrumbs", JSON.stringify( [] ));     
      return [];
    } // catch 
  }

  static insert () {
    let bc = BreadCrumbs.get ();  //  console.info ("breadCrumbs.insert: found ", bc);
    var vc = [];
    for ( let index = bc.length - 1; index >= 0; index-- ) {    // step backwards through the crumbs
      var link = '<a href="' + bc[index].prefix + '/index.php?title=' + bc[index].pageKey + '" title="Go to: ' + bc[index].pageName + '" >';
      var text = bc[index].pageName;
      if ( text.length > BreadCrumbs.maxLength ) {text = text.substr( 0, BreadCrumbs.maxLength ) + '...';}
      link += text + '</a>';
      vc.push( link );
    }
    // console.info ("BreadCrumbs.insert: vc is ", vc);
    let txt = vc.reduce ( (ac, cv) =>  ac + cv + ' &nbsp;&nbsp;&nbsp; ' , "") + 
      "<a href='javascript:window.clearBreadcrumbs();' style='font-size:9pt; font-variant-caps: all-small-caps;padding:1pt;' title='Clear breadcrumbs'>del</a>";
    // console.info ("BreadCrumbs.inster: txt is ", txt);
    document.getElementById ("breadcrumbinsert").innerHTML = txt;
  }
} // end class

  // INTERFACE. Called when we should insert the bread crum into the DOM
  window.doBreadNow = function () { BreadCrumbs.insert();}

  // INTERFACE. Called when a fresh crumb should be inserted.
  window.addFreshCrumb = function (pageName, pageKey, prefix) { BreadCrumbs.add (pageName, pageKey, prefix); }

  // INTERFACE. Called when user requested to clear the breadcrumbs trail by clicking on the DEL element in the bread crumb trail
  window.clearBreadcrumbs = function () { 
    localStorage.setItem ("breadcrumbs", JSON.stringify( [] ));   // store empty value
    BreadCrumbs.insert();   // and, to be responsive, regenerate immediately
  }

    
})(); // close scope protection

console.error ("--------------------DanteBread has loaded ");