/* This module loads all functionality required in DantePresentations */

// TODO: check if we can use a global object kind of namespace in javascript: handlers 

(() => {  // scope protection
   

window.present = startPresentation;      // cludge for javascript: handler

var presentationConnection = null;

var beforeunloadHandler =(event) => {  // remind user of a possibility not to exit, as this will terminate the presentation as well (we lose the controller)
  event.preventDefault();
  return event.returnValue = "Are you sure you want to exit?";
}

var pagehideHandler = () => presentationConnection.terminate();


/* Entry point to show this article on an external monitor in a simple font without edit, navigation and SideBar and more */
window.show = function show (path) {
   let masterWindow = window.open ( path, "_blank", "left=20,top=20,width=1000,height=1000,toolbar=yes,location=yes,directories=yes,status=yes,menubar=yes");

// toolbar=no, location=no, directories=no,status=no, menubar=no, scrollbars=yes, resizable=yes, copyhistory=yes, width=600, height=600

  let slaveUrl=mw.config.get("wgServer") + mw.config.get ("wgScriptPath") + "/extensions/DantePresentations/externalMonitor.html?presentation=" + encodeURIComponent ( path );  
  let slaveWindow = window.open ( slaveUrl, "_blank", "left=0,top=0,width=1000,height=1000,toolbar=1,status=1,location=1");

// experiment worked out
/*
  window.setTimeout ( ()=> { console.info ("will scroll");
    let ifra = slaveWindow.document.getElementById ("ifra");
    console.info ("frame is", ifra);
    ifra.contentWindow.scroll(0, 800);     /////////// THIS, scrolling the window, seems to work !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    ifra.contentWindow.scrollBy (0, 400);
     console.info ("did scroll");  }, 9000);

*/
}


/** MAIN ENTRY POINT called by UI
 *  called by javascript: handler which is added to the Wiki UI in Dantepresentations.php
 */
function startPresentation (path) {
  // console.log ("DantePresentations: Path obtained: " + path);

//  if (presentationConnection) {alert ("We already show a presentation!"); return;}

//  let url = path + "/extensions/DantePresentations/receiver.html";  // console.log ("opening ", url);


  openControlWindow ();

//  startPresentationExternal (path);

  /*
  makeButton ("first",     {"command": "first"} );  
  makeButton ("previous",  {"command": "previous"} );  
  makeButton ("next",      {"command": "next"} );
  makeButton ("last",      {"command": "last"} );  
  makeButton ("blank",     {"command": "blank"} );
  makeButton ("unblank",   {"command": "unblank"} );
  makeButton ("announceUrls",  []);       // arbitrary URLs
  makeButton ("announcePages",   []);     // arbitrary pages of this dantewiki
  makeButton ( "terminate", () => {presentationConnection.terminate(); presentationConnection = null;
    //window.removeEventListener ('beforeunload', beforeunloadHandler);
    window.removeEventListener ('pagehide', pagehideHandler);
    } );

  //window.addEventListener('beforeunload', beforeunloadHandler);
  window.addEventListener('pagehide',     pagehideHandler );  // when leaving this page, terminate the presentation (we have no possibility to control it anyhow)

*/


}


function makeButton (label, obj) {
  var btn = document.createElement ("button");
  btn.innerHTML = label;
  btn.style="position:fixed; top:" + (300 + makeButton.num*20) + "px; left:300px;width:100px;";
  document.body.appendChild (btn);
  makeButton.num++;
  if (typeof obj == "function") { btn.addEventListener ("click", obj); }
  else {btn.addEventListener ("click", () => {  /* console.log ("sending: ", obj); */ presentationConnection.send ( JSON.stringify(obj)  ) }); }
}

makeButton.num = 0; // counter for dynamic positioning TODO: improve and place into CSS and container and flex etc.



/** start a presentation on an external monitor */
function startPresentationExternal (path) {

  let skin = "dantePresentationSkin";  // use the dantePresentationSkin in this presentation (this is reveal and more !)


  let url = window.location.href + "?useskin=" + skin;    
  const presentationRequest = new PresentationRequest([url]);

  presentationRequest.start()
    .then  ( connection => { presentationConnection = connection; console.log (' Connected to ' + connection.url + ', id: ' + connection.id, connection);
     
      })
    .catch ( error      => { console.log ( error ); });

}


var controllerWindow;


// opens a view / window / tab / popup for controlling and monitoring the presentation
// this should include speaker notes, tweedback interaction and more
// probably it should not consist of a different skin but of a window with frames where one frame shows a scaled (monitor) version of the presentation


// TODO: PARTIALLY BROKEN  // TODO: DEPRECATE
window.openControlWindow = 
function openControlWindow () {

  mw.config.get("wgServer") + mw.config.get ("wgScriptPath")

  let url = path + "/extensions/DantePresentations/controller.html?presentation=" + encodeURIComponent ( window.location.href );  
  console.log ("Controller window for " + url);
  controllerWindow = window.open (url, "controlWindow", "left=0, top=0, width=600, height=600");
  console.log ("Controller window ", controllerWindow);
};

 



// CAVE: define this function inside of a scope. there is *some* obscure isssue with the Mediawiki minimizer for js which otherwise breaks the code
window.showExternalFS =
async function showExternalFS () {
  if (! 'getScreenDetails' in window) {console.error ("The Window Management API is not supported by this browser "); return Promise.reject ("not supported"); }


  try {
    const perms  = await navigator.permissions.query( { name: 'window-management' } );
    console.error ("Permission query returned: ", perms);
    if (perms.state != "granted") {console.error ("Permission not granted by user"); return Promise.reject ("not granted by user"); }
  } catch (x) { console.error ("Window management permission exception" ); console.error (x); return Promise.reject ("permission exception");}


  if (window.screen.isExtended) {
    console.info ("The current setup is multi-screen" );
    const screenDetails = await window.getScreenDetails();
    console.warn ("Screen details are as follows: "); console.warn (screenDetails);

    const secondaryScreen = (await getScreenDetails()).screens.filter((screen) => !screen.isPrimary)[0];
    await document.body.requestFullscreen({ screen: secondaryScreen });

  }


}


})();



// injection mechanism as seen in HideSection extension 
// mw.hook documented at https://doc.wikimedia.org/mediawiki-core/master/js/#!/api/mw.hook
( function ( $, mw ) {
  'use strict';

let danteBC = new BroadcastChannel ("danteBC" );

//danteBC.onmessage = (e) => { alert ("message" + e.data ); };

// broadcasts to all contexts of same origin that we want a positioning at selector
function dantePositionAtSection ( e ) {
  e.preventDefault();
  console.info ("dantePositionAtSection called - ext.DantePresentations.js: ", e.target);

  let dataSection       = e.target.dataset.section;
  let dataSectionMarker = e.target.dataset.sectionMarker;
  console.info ("Id in dantePositionAtSecion is: ", {dataSection, dataSectionMarker});
  
  danteBC.postMessage ( {"positionAtSection": {dataSection, dataSectionMarker}} );
};

   

  const showSection = (e) => {
    e.preventDefault();
    let url = e.target.getAttribute ("data-href");
    //show (url);
    //  showExternalFS (url);
    openControlWindow (url); 
  };


  const danteAnnotationAtSection = (e) => {

  };



//  console.error (mw);

// TODO: below 1 line no longer needed ???
  mw.hook( 'wikipage.content' ).add( function () {$('.section-show-link').click( showSection );} );  // when clicking on a section-show-link call showSection
  mw.hook( 'wikipage.content' ).add( function () {$('.section-present-link').click( dantePositionAtSection );} );  // when clicking on a section-present-link 
  mw.hook( 'wikipage.content' ).add( function () {$('.section-annotation-link').click( danteAnnotationAtSection );} );  // when clicking on a section-annotation-link 



  mw.hook( 'postEdit' ).add ( () => { // console.warn ("ext.DantePresentations.js: we now are postedit");
    danteBC.postMessage ( {reloadIframe:"true"} );  // request all externalMonitor pages to reload with the new content - needed for live editing stuff
} );


}( jQuery, mediaWiki ) );




// function for persisting the resize of the table of contents in localstore
function initializeToc () {  // initialize TOC functions - called by initialize in here
  const toggleMyToc = () => { 
    console.log ("toggleMyToc called");
    const toc = document.getElementById ("toc"); toc.classList.toggle ("showtoc");}  // service function for toggeling the table of contents
  const initTocSize = () => { 
    const toc = document.getElementById ("toc");
    var width = parseInt (localStorage.getItem ("tocWidth"));
    console.log ("tocWidth found in localStorage is: " + width);
    if (width !== null) {
      if (width <=18) {width = 0;}          // correct for the browser not really properly reacting with Resize Observer for small sizes
      toc.style.width = width + "px";}
    toc.style.display = "block";
  };
  
  // install handler for TOC only after DOMContentLoaded, only then the TOC is present in the DOM
  //window.addEventListener('DOMContentLoaded', (event) => { });

  function instrumentalize () {
    var toc = document.getElementById ("toc");
    if (!toc) {return;}                          // bail out: there are some situations where we have no toc
    initTocSize ();
    new ResizeObserver( () => {
      //console.log ("ext.dantePresentations.js: storing toc width: " + toc.style.width + " clientWidth:" + toc.clientWidth);
      localStorage.setItem ("tocWidth", parseInt (toc.style.width));
    } ).observe(toc);
    
    var ele = document.querySelector (".toctitle");
    if (ele) {
      ele.addEventListener ("click", toggleMyToc); 
      ele.setAttribute ("title", "Click to toggle visibility of a large table of contents"); 
      // console.log (".toctitle instrumented");
    }
    else {console.error ("no toctitle found");}
  };

  instrumentalize();
}

initializeToc ();



// probably deprecate; not compatible with the DanteLinks click UI
/*
$(document).ready(function() {
  $(document).on('dblclick', '.mw-parser-output', function(event) {
    var pageTitle = mw.config.get('wgPageName');
    var sectionName = $(event.target).closest('h2, h3, h4, h5, h6').text() || 'No section clicked';

    console.log ("ext.DantePresentations: found pageTitle ", pageTitle, " section ", sectionName);

    $.post(mw.config.get('DoubleClickEmailSection').apiEndpoint, 
      {action: 'doubleclickemailsection',  pageTitle: pageTitle,  sectionName: sectionName,  format: 'json' })
     .done(function(data) {
      if (data.error) {console.error(data.error);} else {alert('Email sent successfully!');}
    });
  });
});
*/


// report this page to the shared worker
// not yet working - not used
function reportPage () {
  let sp = mw.config.get('wgScriptPath');

  const worker = new SharedWorker(sp + '/extensions/DantePresentations/js/allWindowsSharedWorker.js');  // Connect to the SharedWorker

  worker.port.start(); // Start the communication channel

  // Listen for messages from the worker
  worker.port.onmessage = (event) => {
    const data = event.data;
    if       (data.type === 'tabConnected') {console.log(`A new tab connected. Total tabs: ${data.connectionsCount}`);} 
    else if  (data.type === 'tabDisconnected') {console.log(`A tab disconnected. Total tabs: ${data.connectionsCount}`);} 
    else if  (data.type === 'connectionsList') {console.log(`Current number of tabs: ${data.connectionsCount}`); }
  };

  worker.port.postMessage('getWindows'); // Request the current number of windows (tabs) from the shared worker
}

// reportPage();  // not working reliably


// console.error ("ext.dantepresentations.js loaded");


try { require ("./audio.js");}      catch (e) { console.error (e);}  // necessary to load the second file of loader, see Javascript example at https://www.mediawiki.org/wiki/ResourceLoader/Developing_with_ResourceLoader
try { require ("./languages.js");}  catch (e) {console.error (e);}




























