// https://stackoverflow.com/questions/30106476/using-javascripts-atob-to-decode-base64-doesnt-properly-decode-utf-8-strings
// first we use encodeURIComponent to get percent-encoded UTF-8, then we convert the percent encodings into raw bytes which can be fed into btoa.


// TODO: check for optimization: could we eventually get rid of this additional base64 coding / decoding step ??? // MUST check ß äüö and stuff

// function used for encoding unicode to base64
function b64EncodeUnicode(str) {return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function toSolidBytes(match, p1) {return String.fromCharCode('0x' + p1);} )); }


/** PROCESS: Slow down issuing requests when the user is typing fast
*
*   We do not want to kick off a server interaction for every individual user interaction.
*   Example: user is typing very fast and does not need a preview for every fresh single letter.
*
*   Interface: PROCESS.process is a function to be called when a new processing step for a preview is necessary after a user interaction.
*     The function is called with a single parameter eventObject
*       eventObject == "INIT"                called for the first time after initialization 
*       eventOBject == "RESIZE"              called due to a resize of the preview area
*       eventObject instanceof InputEvent    called due to change in input
*/

const PROCESS = (() => {  // OPEN local scope for local variables
  const MAX_EVENT_WAIT = 1000;
  const MAX_FALLBACK   = 1100;

  const VERBOSE = false;
  let queueing = false;  // we have at least one preview request which has not yet been served
  let timeout  = null;   // a scheduled task to run the processing at any cost
  let fct;               // function to be called for execution
  let oldestUnservedTS;

  let setFct = (f) => { fct = f; };  // setter for fct

  let process = ( eventObject ) => { // the fraw function as it is called from the outside

    if (VERBOSE) {
      if      ( eventObject == "INIT"   )           { console.info  ("preview.js: process called at initialization ");}
      else if ( eventObject == "RESIZE" )           { console.info  ("preview.js: process called after resize" );}
      else if ( eventObject instanceof InputEvent)  { console.info  ("preview.js: process called after InputEvent ", "type=" , eventObject.inputType, ( eventObject.isComposing ? " COMPOSING " : " NOT_COMPOSING" ), "data=", eventObject.data, eventObject);}
      else                                          { console.error ("preview.js process called in unclear situation"); }
    }

    // CASE 1: There are some scenarios where we want a preview immediately
    const IMMEDIATELY = ["insertFromDrop", "insertFromPaste", "insertFromPasteAsQuotation", "deleteByDrag", "deleteByCut", "historyUndo", "historyRedo"];
    if ( eventObject == "INIT" || eventObject == "RESIZE" || IMMEDIATELY.includes (eventObject.inputType) )     
      { if (VERBOSE) {console.info ("CASE 1: Special request with immediate processing: " + (typeof eventObject == "string" ? eventObject : eventObject.inputType ) );}   fct(); return; }

    // CASE 2: When the oldest inputevent which has not yet been served is longer ago than MAX_EVENT_WAIT [ms]

    if (queueing) {  // in case we are already in queueing mode
      if ( eventObject.timeStamp - oldestUnservedTS > MAX_EVENT_WAIT ) { console.info (`CASE 2: ${eventObject.timeStamp} ${oldestUnservedTS}`); if (timeout) {window.clearTimeout (timeout); timeout= null;} queueing = false; fct();  return;}
      else { if (VERBOSE) {console.info (`CASE 2-: Not yet time to execute a call: current=${eventObject.timeStamp} oldestUnserved=${oldestUnservedTS}  diff=${eventObject.timeStamp - oldestUnservedTS }`);} }
    } 
    else {  // in case we are not yet in queueing mode we enter queueing mode but at most for MAX_FALLBACK
      queueing = true;
      oldestUnservedTS = eventObject.timeStamp;
      if (VERBOSE) {console.info ("CASE 3a: Setting up a timeout event");}
      timeout = window.setTimeout ( ()=> { if (VERBOSE) {console.info ("CASE 3b: Timeout fired");}  queueing = false; fct();}, MAX_FALLBACK );   
    }
  }; 

  return {process, setFct};

})();  // CLOSE local scope



/***
 ***  DPRES: Dante Presentations
 ***/

// define namespace DPRES for extension DantePresentations to ensure proper separation from other components
const DPRES = (() => {

// when the client receives any response from the preview endpoint: patch the information into the area where we show the preview
function receivedEndpointResponse(e) {
  const VERBOSE = true; 

//  const myNow = Date.now();
//  if (V_TIME) {console.warn (`Request was made at ${e.target.timeRequestMade}, now is ${myNow} and duration ${myNow - e.target.timeRequestMade}`);}

  if (e.target.status != 200) { 
    console.warn (`DantePresentations: preview: Received an ERROR message from an endpoint. Status Code=${e.target.status} Status Text=${e.target.statusText});`);
    if (confirm (`Server Error: Status Code ${e.target.status} ${e.target.statusText} \nURL: ${e.target.responseURL}\nConfirm to open more detailed error window`)) {
      window.open ( e.target.responseURL, "_blank");

    };
    return; } 
  else {
    if (VERBOSE) {console.info (`DantePresentations: preview: Received an endpoint response with status Code=${e.target.status}`);}
    
    // now check if the response is outdated
    if (e.target.timeRequestMade) {  // do we have a timestamp?
      if (e.target.timeRequestMade > QUEUE.currentTimestamp) { if (VERBOSE) {console.log ("  Will display the endpoint response"); }
                                                               displayEndpointResponseDoubleBuffer (e); }
      else                                                   {if (VERBOSE) { console.log (`  Will discard endpoint response. Tool old: From ${e.target.timeRequestMade} and thus older than current status ${QUEUE.currentTimestamp}`); }
                                                               QUEUE.remove (e.target);}
    }
    else {console.warn ("  The XHR object had no timestamp, this is a protocol error!");}
  }
}



// double buffered preview frame prevents flickering and ensures faster and smoother UI reaction
let previewFrames = [];
let currentFrame = 0;
let currentZoom = 1;



// this is the old function without double buffer which is eventually TODO: deprecated
function displayEndpointResponseSingleBuffer (e) {
  let VERBOSE = true;
  if (VERBOSE) {console.log ("displayEndpointResponse called");}

  var previewFrame = document.getElementById ("previewFrame");                            // get a handle of the previewFrame

//  console.log (e.target);
//  console.log (e.target.response);

  document.getElementById ("previewFrame").setAttribute ("srcdoc", e.target.response);    // show current content in the previewFrame

//  document.getElementById ("previewFrame").style.visibility="visible";                    // ensure visibility of the content

  QUEUE.currentTimestamp = e.target.timeRequestMade;                                      // update time stamp of the content currently on display
  QUEUE.remove (e.target);                                                                // remove the response just received so the topmost queue element is the most recent invocation we are waiting for 
  QUEUE.nonCancelable ();                                                                 // make most recent nonCancelable and cancel all others
  if (QUEUE.getLen () == 0) {document.body.classList.remove ("xhrPending", "inputChanged");}              // show by decoration of frame whether we still are awaiting responses

  // we want azoom change inside of only the iframe with the preview content alone
// TODO:  for some stupid reason this does not work.  srcdoc ??  maybe other reason.
// TODO: if it really does not work: add this to the html code as it is built in the mediawiki.php endpoint and the other endpoints 

  document.getElementById ("previewFrame").onload = function() {
    document.getElementById ("previewFrame").contentWindow.addEventListener ("focus", (e) => {console.warn ("FOCUS");  previewFrame.classList.add ("hasFocus");   }); 
    document.getElementById ("previewFrame").contentWindow.addEventListener ("blur",  (e) => {console.warn ("BLUR", ); previewFrame.classList.remove("hasFocus"); }); 
   // document.getElementById ("previewFrame").contentWindow.onbeforeunload = function() { console.warn ("beforeunload");previewFrame.classList.remove ("hasFocus");}

    document.getElementById ("previewFrame").contentWindow.document.addEventListener ("keydown" , (e) => { 
      console.log ("Key pressed: ", e.key);
      if (e.metaKey && (e.key=="+" || e.key=="-") ) {
         e.preventDefault (); e.stopPropagation();
         var zoomIs = parseFloat (previewFrame.contentWindow.document.body.style.zoom);
         zoomIs = (isNaN (zoomIs) ? 1.0 : zoomIs );
          console.log ("zoomIs found:", zoomIs, previewFrame.contentWindow.document.body.style.zoom);
          console.log ("active ", document.activeElement);
          let newZoom = zoomIs * (e.key == "+" ? 1.2 : 0.8 );
         previewFrame.contentWindow.document.body.style.zoom =  newZoom ; 
      }
    });
  };

  if (VERBOSE) {console.log ("will exit displayEndpointResponse, queue is ", QUEUE.getLen());}
  if (QUEUE.getLen() > 0) {console.warn ("will exit displayEndpointResponse, queue still is ", QUEUE.getLen());}
}




// this is the new function
function displayEndpointResponseDoubleBuffer (e) {
  let VERBOSE = true;
  if (VERBOSE) {console.log ("displayEndpointResponseDoubleBuffer called");}

  var curFrame = previewFrames[currentFrame];
  var newFrame = previewFrames[ (currentFrame == 0 ? 1 : 0) ];    // the frame which is currently not showing and waiting underneath for drawing new stuff

  newFrame.setAttribute ("srcdoc", e.target.response);                                    // show current content in the previewFrame

  QUEUE.currentTimestamp = e.target.timeRequestMade;                                      // update time stamp of the content currently on display
  QUEUE.remove (e.target);                                                                // remove the response just received so the topmost queue element is the most recent invocation we are waiting for 
  QUEUE.nonCancelable ();                                                                 // make most recent nonCancelable and cancel all others


  newFrame.onload = function () {  // the frame we just wrote has, as consequence of the writing, lost its handlers - so add them again after the load has completed 
    let current = curFrame.contentWindow.pageYOffset;
    newFrame.contentWindow.scrollTo (0, current);
    // console.log ("******* newFrame.onload ", curFrame.contentWindow.pageYOffset, newFrame.contentWindow.pageYOffset);

    newFrame.contentWindow.addEventListener ("focus", (e) => {console.warn ("FOCUS");  newFrame.classList.add ("hasFocus");   }); 
    newFrame.contentWindow.addEventListener ("blur",  (e) => {console.warn ("BLUR", ); newFrame.classList.remove("hasFocus"); }); 
   // document.getElementById ("previewFrame").contentWindow.onbeforeunload = function() { console.warn ("beforeunload");previewFrame.classList.remove ("hasFocus");}

    newFrame.contentWindow.document.addEventListener ("keydown" , (e) => { 
      if (e.metaKey && (e.key=="+" || e.key=="-") ) {
         e.preventDefault (); e.stopPropagation();
         currentZoom = currentZoom * (e.key == "+" ? 1.2 : 0.8 );
         newFrame.contentWindow.document.body.style.zoom     =  currentZoom ; 
         curFrame.contentWindow.document.body.style.zoom = currentZoom;
      }
    });

    // adapt the frames for double buffering
    curFrame.style.visibility ="hidden";                     // current frame becomes invisible
    newFrame.contentWindow.document.body.style.zoom     =  currentZoom ; 
    $("iframe").contents().find(".collapseResult").show();   // the collapsible elements (eg of Parsifal) must be opened in the preview window 

    newFrame.style.visibility = "visible";               // new frame becomes visible

    if (QUEUE.getLen () == 0) {document.body.classList.remove ("xhrPending", "inputChanged");}              // show by decoration of frame whether we still are awaiting responses
    currentFrame = (currentFrame == 0 ? 1 : 0);          // adjust the pointer
  }

  if (VERBOSE) {console.log ("will exit displayEndpointResponse, queue is ", QUEUE.getLen());}
  if (QUEUE.getLen() > 0) {console.warn ("will exit displayEndpointResponse, queue still is ", QUEUE.getLen());}
}




function processAll() {
  var body = b64EncodeUnicode ( document.getElementById ( "wpTextbox1" ).value );
  const xhr = new XMLHttpRequest();
  xhr.open ("POST", "./extensions/DantePresentations/endpoints/mediawikiEndpoint.php", true);   //////// TODO: how do we select the specific endpoitn : reveal or parsifal or markdwon or whatever ????
  xhr.setRequestHeader ("Content-Type", "text/plain;charset=UTF-8");

  // set specific header information for the preview endpoint
  xhr.setRequestHeader ("Wiki-wgUserName",            RLCONF.wgUserName);
  xhr.setRequestHeader ("Wiki-wgUserId",              RLCONF.wgUserId);
  xhr.setRequestHeader ("Wiki-wgNamespaceNumber",     RLCONF.wgNamespaceNumber);
  xhr.setRequestHeader ("Wiki-wgPageName",            RLCONF.wgPageName);                     // full name of page, including localized namespace name, if namespace has a name (except 0) with spaces replaced by underscores. 
  xhr.setRequestHeader ("Wiki-wgTitle",               RLCONF.wgTitle);                        // includes blanks, no underscores, no namespace
  xhr.setRequestHeader ("Wiki-wpPageContentLanguage", RLCONF.wgPageContentLanguage);

  // header and xhr object gets a timestamp when the request was issued
  xhr.timeRequestMade = Date.now();
  xhr.setRequestHeader ("X-Dante-ClientRequestTime", xhr.timeRequestMade);
  xhr.onload = (e) => {receivedEndpointResponse (e);};

  QUEUE.submit ( xhr );
  document.body.classList.remove ("inputChanged");
  document.body.classList.add ("xhrPending");
  xhr.send ( body );
}













// Apply path to the edit page of Mediawiki. Called by <script> tag injected in DantePresentations.php:onEditPageshowEditForminitial
function initializeTextarea() { 
  // console.warn ("DantePresentations:preview.js: initializeTextarea");

  var storeResize = true;                                   // shall the resize observer store the resize values?

  // pick up the textarea
  var textarea = document.getElementById ( "wpTextbox1" );  

  const wasResized = () => {
    const VERBOSE = true;
    var textareaWrapper = document.getElementById ("textarea-wrapper");
    shouldReset   = true;  // next time we call the processing function, do a complete reset of all images (due to the changed resolution in the reset
    if (storeResize) {
      if (VERBOSE) {console.log ("preview.js: resize observer storing textarea dimensions ");}
      window.localStorage.setItem ("textareaWidth",  Math.max ( 100, textarea.offsetWidth ) );
      window.localStorage.setItem ("textareaHeight", Math.max ( 100, textarea.offsetHeight) );
    } 
    waitBeforeInvoke ( 300 );  // invoke a redisplay of the preview after some waiting time
  };

  new ResizeObserver (wasResized).observe (textarea);
  new ResizeObserver (wasResized).observe (document.body);

  Object.assign (textarea.style, { minWidth: "150px", resize: "horizontal", display:"inline-block" } );

  if (window.localStorage.getItem ("textareaWidth"))  {textarea.style.width = window.localStorage.getItem ("textareaWidth") + "px";}
  //if (window.localStorage.getItem ("textareaHeight")) {textarea.style.height = window.localStorage.getItem ("textareaHeight") + "px";}

  textarea.myFontSize = 14; textarea.style.fontSize = textarea.myFontSize + "pt";

  // keyboard based resizing inside of textarea only resizes the textarea font size itself
  textarea.addEventListener ("keydown", (e) => { // console.log ("Key pressed: ", e.key);
    if (e.metaKey && (e.key=="+" || e.key=="-") ) {e.preventDefault (); e.stopPropagation(); textarea.myFontSize += ( e.key=="+" ? 2 : -2 ); textarea.style.fontSize = textarea.myFontSize + "pt"; }  });
  
  var newEditContainer = document.createElement ("div");   // container of (textarea for editing) and (preview area)
  newEditContainer.id  = "new-edit-container";
  Object.assign (newEditContainer.style, {display: "flex", height: "calc(100vh - 540px)"} ); 

  var parent = textarea.parentNode; parent.replaceChild (newEditContainer, textarea);
  newEditContainer.appendChild (textarea);

  var previewContainer = document.createElement ("div");          // generate a container for the preview
  previewContainer.id = "inline-edit-preview-container";
  Object.assign (previewContainer.style, {minWidth: "18px", display: "inline-block", flex:"1 1 auto", "box-sizing": "border-box", position: "relative"} ); 

  //var previewImage = document.createElement ("img");     previewImage.id = "previewImage";  Object.assign (previewImage.style, {width:"100%", height:"100%", position:"absolute", border: "0px solid yellow", left:"0px", top:"0px"} );
  
  previewFrames[0] = document.createElement ("iframe"); Object.assign (previewFrames[0].style, {width:"100%", height:"100%", position:"absolute", left:"0px", top:"0px"} );
  previewFrames[1] = document.createElement ("iframe"); Object.assign (previewFrames[1].style, {width:"100%", height:"100%", position:"absolute", left:"0px", top:"0px"} );

  previewFrames[0].classList.add ("previewFrameClass");
  previewFrames[1].classList.add ("previewFrameClass");


  //var previewFrame = document.createElement ("iframe");  previewFrame.id = "previewFrame";  Object.assign (previewFrame.style, {width:"100%", height:"100%", position:"absolute", left:"0px", top:"0px"} );
  
// previewImage.style.visibility = previewFrame.style.visibility = "hidden";

 // previewContainer.appendChild ( previewImage );
//  previewContainer.appendChild ( previewFrame );
  previewContainer.appendChild (previewFrames[0]);   previewContainer.appendChild (previewFrames[1]);

  newEditContainer.appendChild (previewContainer);
  
  let editform = document.getElementById ("editform"); 

  PROCESS.setFct ( processAll );                                       // define which function to use for processing for previewing
  editform.addEventListener ("input", PROCESS.process );               // install an event listener for changes in the textarea
  window.setTimeout ( () => { PROCESS.process ( "INIT" );} , 0);       // kick off display of first preview

  editform.addEventListener ("input", () => {document.body.classList.add ("inputChanged");});  
}




function initializeCodeMirror () {
  var myTextArea   = document.getElementById("wpTextbox1");
  var myCodeMirror = CodeMirror.fromTextArea ( myTextArea, { lineNumbers:true, matchBrackets:true} );    // returns an abstract CodeMirror object

  var cmElement = document.querySelector (".CodeMirror");
  cmElement.myFontSize = 14; 
  cmElement.style.fontSize = cmElement.myFontSize + "pt";  
  cmElement.CodeMirror.refresh();
  cmElement.addEventListener ("keydown", (e) => { // console.log ("Key pressed: ", e.key);
    if (e.metaKey && (e.key=="+" || e.key=="-") ) {e.preventDefault (); e.stopPropagation(); 
      cmElement.myFontSize += ( e.key=="+" ? 2 : -2 ); cmElement.style.fontSize = cmElement.myFontSize + "pt"; 
      cmElement.CodeMirror.refresh();            // needed by code mirror after a font change
    }  });

  if (window.localStorage.getItem ("textareaWidth"))  {cmElement.style.width = window.localStorage.getItem ("textareaWidth") + "px";}

  var storeResize = true;
  
  const wasResized = () => {
    const VERBOSE = true;
    var textareaWrapper =  cmElement;     // WAS : document.getElementById ("textarea-wrapper");
    var iepc            = document.getElementById ("inline-edit-preview-container");
    if (VERBOSE) console.log (`SIZE CHECK: textarea-wrapper = ${textareaWrapper.clientHeight} and inlineEditpreviewcontainer = ${iepc.clientHeight}`  );
   
     shouldReset   = true;  // next time we call the processing function, do a complete reset of all images (due to the changed resolution in the reset
    if (storeResize) {
      if (VERBOSE) {console.log ("DantePresentations:preview.js: resize observer storing textarea dimensions: ", cmElement.offsetWidth);}
      window.localStorage.setItem ("textareaWidth", cmElement.offsetWidth);
    } 

    console.warn ("what oes this do - waitBeforeInvoke"); /////////////////////////////////////// TODO!
    waitBeforeInvoke (300);  // invoke a redisplay of the preview after some waiting time

    if (status == 1) { previewContainer.style.height = "" + (newEditContainer.clientHeight - wrapper.clientHeight) + "px" };
  };

  new ResizeObserver (wasResized).observe (cmElement);          // NEW: cmElement
  new ResizeObserver (wasResized).observe (document.body);
}


function editPreviewPatch () {  // the clutch to PHP; we may adapat it to use CodeMirror, textarea or whatever client side editor we desire  
  initializeTextarea();
  let params = (new URL (document.location)).searchParams;
    if (params.get("editormode") == "codemirror") {
    initializeCodeMirror ();  // additionally initialize a code mirror instance
  }
}


  return {editPreviewPatch};

}  )();
/*** END DPRES **/


window.editPreviewPatch = DPRES.editPreviewPatch;

/** Request freshness
* 
*  To cut down on the number of pending requests we provide the request on client side and on server side (via header) with a time stamp.
*  1) The client tracks the reply time and only shows responses to newer requests than its current state.
*  2) When a response comes in, the client aborts all earlier pending requests
*
* CAVE: We cannot always cancel all older requests when a newer request comes in. If we did so, a fast request rate
*       would always lead to cancellation and we would never get a result.
* BUT:  When a response comes in, we mark the most recent request as non-cancelable and discard all earlier requests.
*
*/

var QUEUE = (() => {
  const VERBOSE = false;
  let pendingRequests = [];                                                           // list of pending requests waiting for a server reply
  let currentTimestamp = 0;                                                           // time stamp of the request whose content is showing currently
  var submit = ( x ) => { pendingRequests.unshift (x); };                             // submit a fresh request to the queue
  var nonCancelable = () => { if (pendingRequests.length > 0) {pendingRequests[0].nonCancelable = true; cancel();} };   // mark top of the queue (which is the most recently added) as non-cancelable
  var getLen = () => pendingRequests.length;
  var remove = (ele) => { pendingRequests=pendingRequests.filter ( x => x!=ele);}
  var cancel = () => {                                                                // cancel all but the non-cancelable, and remove them from the queue as well
    pendingRequests = pendingRequests.filter ( ele => {
      if (ele.nonCancelable) { if (VERBOSE) {console.log ("keeping request made at ", ele.timeRequestMade);}  return true;} 
      else { if (VERBOSE) {console.log ("aborting request made at", ele.timeRequestMade);}  ele.abort(); return false;}
    });
  };
  return {submit, nonCancelable, currentTimestamp, getLen, remove};  // export object
})();


/** slow down the issuing of requests in case of a resizing of the preview area 
*/
var timer = null;
function waitBeforeInvoke (ms) { // this MUST be used when the preview area is resized (otherwise we get a too high rate of server requests - for every resie event fired by client)
  window.clearTimeout (timer);   
  timer = window.setTimeout ( () => PROCESS.process ("RESIZE") , ms);       // start a timer and only then invoke processing
}