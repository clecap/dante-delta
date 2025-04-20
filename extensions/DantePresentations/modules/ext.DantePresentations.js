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










// TODO: BELOW must be checked and fixed for many aspects


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








window.SET_ACTIVE_COLLECTION = function () {
  var expires = new Date();
  expires.setHours(expires.getHours() + 10);  // Cookie will expire in 10 hours
  var value = encodeURIComponent(mw.config.get('wgPageName'));
  document.cookie = "active_collection=" + value + "; expires=" + expires.toUTCString() + "; path=/; secure; SameSite=strict";
  window.location.reload();
};

window.CLEAR_ACTIVE_COLLECTION = function () {
  document.cookie = "active_collection=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; secure; SameSite=strict";
  window.location.reload();
};


/*
pinned    flag indicating if the sidepanel is pinned to open
width   
height  

isOpen  not stored, indicates if it is currently open

name      used as prefix in the storage

implement   function implementing the state

*/

class SideStatus {

  static PREFIX    = mw.config.get("wgServer") + mw.config.get ("wgScriptPath"); 
  static API_URL   = mw.config.get("wgServer") + mw.config.get ("wgScriptPath") + "/api.php"; 
  static ALL       = {};  // maps the name to the object constructed by this name

  static NEXT_TOP  = 60;    // pixel position where we place the next side chick; initialized to the top where we start
  static GAP       = 60;    // what we add as horizonatl separation from side chick to side chick

  constructor (name, query) {   console.log ("constructing SideStatus object for " + name);
    this.name = name;
    this.query = {action: "query", format: "json"};    // this is the default for all API calls, but it may be overwritten by the query object
    Object.assign (this.query, query);
    this.minWidth  = 80;    this.minHeight  = 100;      // minimal size a user resize action is accepted with
    this.initWidth = 400;   this.initHeight = 400;      // width we initialize this in 
    this.#load ();
    if ( !(this.ele = document.getElementById ( name ) ) )  {console.error ("HTML structure error, missing element: " + this.name);}   // TODO: use this everywhere

    this.ele.style.top = SideStatus.NEXT_TOP+ "pt";  SideStatus.NEXT_TOP += SideStatus.GAP;

    SideStatus.ALL[name] = this;
    this.initialize();
  }

  #load () { // pickup data from localStorage 
    let str  = localStorage.getItem ("side-"+this.name);
    // console.log ("load picksup for name ", this.name, " a value of: " , str);
    let obj = null, self = this;
    try { 
      obj = JSON.parse (str); 
      if (typeof obj == "object") { ["pinned", "width", "height"].forEach (ele => self[ele] = obj[ele]); } 
    } catch (x) { console.warn ("JSON.parse threw ", x, " on ", str); }
    let flag = this.#sanitize ();
    if (flag) {localStorage.setItem ("side-"+this.name, JSON.stringify( {pinned:self.pinned, width:self.width, height:self.height } ) ) }  // if sanitizing had an effect, store it now
    this.isOpen = this.pinned;
  }

  #sanitize () {  // sanitize the values and return true if this required a change
    let flag = false;
    // console.log ("sanitize sees ", this.pinned, this.width, this.height);
    if (this.pinned   === null || typeof this.pinned != "boolean" )  { this.pinned  =  false; flag = true; /* console.error ("fixed broken pinned"); */}
    if (this.width    === null || typeof this.width  != "number"  ||  this.width  < this.minWidth  )  { this.width  = this.initWidth;   flag = true; /* console.error ("fixed broken width"); */ }
    if (this.height   === null || typeof this.height != "number"  ||  this.height < this.minHeight )  { this.height = this.initHeight;  flag = true; /* console.error ("fixed broken height"); */}
    return flag;
  }

  store ( ) {
    // console.log ("store sees name: ", this.name);
    this.#sanitize();
    let obj = {}, self = this;
    ["pinned", "width", "height"].forEach ( (ele) => {obj[ele] = self[ele]; } );
    let str = JSON.stringify (obj);
    localStorage.setItem ("side-"+this.name, str);
  }

  implement = () => {  // console.log ("status is at implementation: pinned=", STATUS_TOC.pinned, " isOpen=", STATUS_TOC.isOpen, " w/h=", STATUS_TOC.width, STATUS_TOC.height);
    let ele = document.getElementById (this.name);
    if (this.isOpen)  {ele.style.width = this.width + "px"; ele.style.height= this.height + "px"; console.log ("setting width to ", this.width);} 
    else              {ele.style.width = "0px";  ele.style.height="0px"; } 
    try {document.getElementById (this.name+"-pin").checked = this.pinned; } catch (x) {console.error ("html structure problem for " + this.name, x);}
  };

  // CAVE: handlers are invoked with this equal to the target. To prevent this we here need not a normal function but an arrow function
  handleClick = (e) => {  // console.log ("clicked on handle element with ", this);
    if      ( this.isOpen  &&  this.pinned ) { this.isOpen = false; this.pinned = true;}
    else if ( this.isOpen  && !this.pinned ) { this.isOpen = false; this.pinned = false;}
    else if ( !this.isOpen &&  this.pinned ) { console.warn ("should not happen"); this.isOpen = true; this.pinned = true;}
    else if ( !this.isOpen && !this.pinned ) { this.isOpen = true; this.pinned = false;}    
    this.store();
    this.implement();
  }; 
  

  pinClick = (e) => {  // console.log ("clicked on PIN UI element");
    // CAVE: handlers are invoked with this equal to the target. To prevent this we here need not a normal function but an arrow function
    e.stopPropagation();
    if      ( this.isOpen  &&  this.pinned ) { this.isOpen = false; this.pinned = false;}
    else if ( this.isOpen  && !this.pinned ) { this.isOpen = true; this.pinned = true;}
    else if ( !this.isOpen &&  this.pinned ) { console.warn ("should not happen"); this.isOpen = false;this.pinned = false;}
    else if ( !this.isOpen && !this.pinned ) { this.isOpen = true;this.pinned = true;}
    this.store();
    this.implement ();
  };


  resize = (e) => {
    // CAVE: arrow function. this will be the SideStatus object and e the entry to the Resize Observer
    let ele = e[0].target;
    let newWidth = parseInt (ele.style.width), newHeight = parseInt (ele.style.height);
    // console.log ("RESIZE OBSERVER CALLED, status is pinned=", this.pinned, " isOpen=", this.isOpen, "  w/h=", this.width, this.height), " wants to set to ", newWidth, newHeight;
    if (newWidth >= this.minWidth || newHeight >= this.minHeight ) {
      this.isOpen = true;
      this.width = parseInt (ele.style.width); 
      this.height = parseInt (ele.style.height);  
      this.store();  }
    };


  initialize ( ) {
    var showName = this.name.toUpperCase();
    try{ document.getElementById ("toctogglecheckbox").remove(); } catch (s) { console.warn ("error removing toctogglecheckbox");}  // TODO: fix - special only for toc 

    try {
    document.getElementById ( this.name + "-title")  .addEventListener ("click", this.handleClick  );
    document.getElementById ( this.name + "-handle") .addEventListener ("click", this.handleClick  );
    document.getElementById ( this.name + "-pin")    .addEventListener ("click", this.pinClick     );
    new ResizeObserver( this.resize).observe(this.ele);
    } catch (x) {console.error ("broken html structure for " + this.name);}

    if (this.pinned) { /* console.log ("at init: is pinned"); */ this.isOpen = true; } else { /* console.log ("at init: is not pinned"); */ } // we are initializing now: implement the pinning state
    this.implement();
    this.ele.style.display = "block";  // has been initialized, may display it now (no FOUC)
  }

  async executeQuery () {  // executes the query 
    const params = this.query;
    const url    = new URL(SideStatus.API_URL);
    Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
    const response   = await fetch(url);
    if (!response.ok) {throw new Error(`Response status to SideStatus.executeQuery: ${response.status} ${response.statusText}`);}
    const data       = await response.json();   // may throw a parse error directly, which is fine
    if (data.error)  { throw new Error ("Error in executeQuery<br>Code=" + data.error.code + "<br>Info=" + data.error.info); }
    if (!data.query) { throw new Error ("Response from API did not contain a query object"); }
    // throw new Error ("executeQuery failed for " + this.name);  //debug: for testing the upstream exception mechanisms
    return data.query;
  }

  injectHTML (txt) {
    document.getElementById (this.name + "-inject").innerHTML = txt;
  }

  injectLinks (res) {
    let txt = "";
    if ( !res || res.length == 0) {document.getElementById (this.name + "-inject").innerHTML = "<li><b>No items found</b></li>"; return;}
    document.documentElement.classList.add ("has_"+this.name+"s"); // TODO change "s"
    document.getElementById (this.name + "-inject").innerHTML  = res.reduce( (acc, link) => acc + `<li><a href='${SideStatus.PREFIX}/index.php?title=${encodeURIComponent(link.title.replace(/ /g, '_'))}'>${link.title}</a></li>` , "");
  }

  async fill () {
    if (!this.query) return;
    try {
      const data = await this.executeQuery();               //    console.error ("fill sees data: ", data);
      var res = this.extract (data);
      this.injectLinks (res);
    } catch (error) {this.injectHTML (error);}
  }

}



let STATUS_TOC = new SideStatus ("toc", "table of contents");


// the sequence of initializing determines the sequence on the screen 
new SideStatus ("cat", {titles: mw.config.get('wgPageName'),  prop: 'categories',  cllimit: 'max' });
new SideStatus ("sub", {list: 'allpages',   apnamespace: mw.config.get('wgNamespaceNumber'), apprefix:  mw.config.get('wgPageName').split(':').pop() + "/", aplimit: 'max'   });
new SideStatus ("col", {list: 'backlinks', bltitle: mw.config.get('wgPageName'), bllimit: 'max', blnamespace: getNamespaceId ("collection") });
new SideStatus ("act");
new SideStatus ("bck", {list: 'backlinks', bltitle: mw.config.get('wgPageName'), bllimit: 'max' });
new SideStatus ("fwd", {prop: 'links', titles: mw.config.get('wgPageName'), lllimit: 'max'});

new SideStatus ("sib", {prop: 'links', titles: mw.config.get('wgPageName'), lllimit: 'max'});  // TODO: sibling query not yet completed !!!!!
new SideStatus ("tdo");  // todos are filled by server

SideStatus.ALL.bck.extract = query => query.backlinks;
SideStatus.ALL.fwd.extract = query => {const pageId  = Object.keys(query.pages)[0]; return query.pages?.[pageId]?.links};
SideStatus.ALL.cat.extract = query => {const pageId  = Object.keys(query.pages)[0]; return query.pages?.[pageId]?.categories;};
SideStatus.ALL.sub.extract = query => query.allpages;
SideStatus.ALL.col.extract = query => query.backlinks;

// SideStatus.ALL.sib.extract = ;
// SideStatus.ALL.tdo.extract = 


//  TODO: act query still missing
// many other new objects as well. 


///// TODO: we want to have less of this stuff done at javascript runtime and more of this done at PHP time since there it can be cached by php!!

function initializeMyToc ( myname, showname, longname, STATUS ) {
  var toc = document.getElementById (myname);
  if (!toc) { console.error ("ex.DantePresentations: no element " + myname + " found"); return;}                          // bail out: there are some situations where we have no toc
  try{ document.getElementById ("toctogglecheckbox").remove(); } catch (s) { console.warn ("error removing toctogglecheckbox");}  

  toc.setAttribute ("title", longname);

  var bar = document.getElementById ( myname + "-title");
  console.error ("BAR element for " + myname + " is ", bar);

  var handle = document.createElement ("span");                 // generate an open/close and info handle
  handle.innerHTML = showname;
  handle.setAttribute ("title", longname + " (toggle)");
  handle.id = myname + "-handle";
  handle.classList.add ("sideHandle");
  toc.appendChild (handle);

  var pin = document.createElement ("input");
  pin.setAttribute ("type", "checkbox");
  pin.setAttribute ("title", longname + " (pin)");
  pin.id = myname + "-pin";
  pin.classList.add ("pinUi");
  bar.appendChild (pin);

  // toc.appendChild (pin);

  const HANDLECLICK = (e) => {  console.log ("clicked on handle element");
    if      ( STATUS.isOpen  &&  STATUS.pinned ) { STATUS.isOpen = false; STATUS.pinned = true;}
    else if ( STATUS.isOpen  && !STATUS.pinned ) { STATUS.isOpen = false; STATUS.pinned = false;}
    else if ( !STATUS.isOpen &&  STATUS.pinned ) { console.warn ("should not happen"); STATUS.isOpen = true; STATUS.pinned = true;}
    else if ( !STATUS.isOpen && !STATUS.pinned ) { STATUS.isOpen = true;STATUS.pinned = false;}    
    STATUS.store();
    STATUS.implement();
  };
  
  // do instrumentation 
  pin.addEventListener ("click", (e) => {  console.log ("clicked on PIN UI element");
    e.stopPropagation();
    if      ( STATUS.isOpen  &&  STATUS.pinned ) { STATUS.isOpen = false; STATUS.pinned = false;}
    else if ( STATUS.isOpen  && !STATUS.pinned ) { STATUS.isOpen = true; STATUS.pinned = true;}
    else if ( !STATUS.isOpen &&  STATUS.pinned ) { console.warn ("should not happen"); STATUS.isOpen = false;STATUS.pinned = false;}
    else if ( !STATUS.isOpen && !STATUS.pinned ) { STATUS.isOpen = true;STATUS.pinned = true;}
    STATUS.store();
    STATUS.implement ();
  });

  // do instrumentation
  handle.addEventListener ("click", HANDLECLICK );
  bar.addEventListener ("click", HANDLECLICK );


  // do instrumentation of a resize to make resizing persistent
  new ResizeObserver( (e) => {     
    let newWidth = parseInt (toc.style.width), newHeight = parseInt (toc.style.height);
     console.log ("RESIZE OBSERVER CALLED, status is pinned=", STATUS.pinned, " isOpen=", STATUS.isOpen, "  w/h=", STATUS.width, STATUS.height), " wants to set to ", newWidth, newHeight;
    if (newWidth >= STATUS.minWidth || newHeight >= STATUS.minHeight ) {
      STATUS.isOpen = true;
      STATUS.width = parseInt (toc.style.width); STATUS.height = parseInt (toc.style.height);  STATUS.store(); }
    }
  ).observe(toc);

  if (STATUS.pinned) { /* console.log ("at init: is pinned"); */ STATUS.isOpen = true; } else { /* console.log ("at init: is not pinned"); */ } // we are initializing now: implement the pinning state
  STATUS.implement();
  toc.style.display = "block";  // has been initialized, may display it now (no FOUC)
}




function getNamespaceId(namespaceName) {      // Function to convert namespace string to its corresponding ID
  const namespaceIds = mw.config.get('wgNamespaceIds');
  if (namespaceIds.hasOwnProperty(namespaceName)) {return namespaceIds[namespaceName];} 
  else {console.error("Namespace not found: " + namespaceName); return null; }
}


initializeMyToc ( "toc", "TOC", "table of contents", SideStatus.ALL.toc ); //// TODO: FIX THIS



// TODO: move up and maybe include into constructor or initializer
SideStatus.ALL.bck.fill();
SideStatus.ALL.fwd.fill();
SideStatus.ALL.cat.fill();
SideStatus.ALL.sub.fill();
SideStatus.ALL.col.fill();

// report this page to the shared worker
// not yet working - not used
/*
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
*/

// reportPage();  // not working reliably


// console.error ("ext.dantepresentations.js loaded");


try { require ("./audio.js");}      catch (e) { console.error (e);}  // necessary to load the second file of loader, see Javascript example at https://www.mediawiki.org/wiki/ResourceLoader/Developing_with_ResourceLoader
try { require ("./languages.js");}  catch (e) { console.error (e);}



