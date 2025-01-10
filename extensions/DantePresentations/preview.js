// https://stackoverflow.com/questions/30106476/using-javascripts-atob-to-decode-base64-doesnt-properly-decode-utf-8-strings
// first we use encodeURIComponent to get percent-encoded UTF-8, then we convert the percent encodings into raw bytes which can be fed into btoa.


// NOTE: preview.js only is loaded when editing and used for the preview functionality
//       The rest is in Parsifal runtime.js !!


console.error ("+++++++++ preview starts loading");

const DOUBLEBUFFERED = true;     // switch betwwen double buffered operation and single buffered operation


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
*       eventObject instanceof InputEvent    called due to a change in input
*/

const PROCESS = (() => {  // OPEN local scope for local variables
  const MAX_EVENT_WAIT = 1000;
  const MAX_FALLBACK   = 1100;

  let getDelay = () => MAX_EVENT_WAIT;

  const VERBOSE = false;
  let queueing  = false;    // we have at least one preview request which has not yet been served
  let timeout   = null;     // a scheduled task to run the processing at any cost
  let fct;                 // the payload function to be called for execution
  let oldestUnservedTS;

  let immediate       = false;  // if true: service always immediately without waiting
  let setImmediate    = ( flag ) => { immediate = flag; };
  let getImmediate    = () => immediate;
  let toggleImmediate = () => {immediate = !immediate;}

  let changeMode      = () => {toggleImmediate(); showMode();}; // toggle the setting and display the change immediately as user feedback

  let showMode  = () => {  // patch in the process mode information into span id='showMode'
    let ele = document.getElementById ('showMode');
    if (ele) {
      let delay = PROCESS.getDelay().toString().padStart (5, '\u00A0');
      ele.innerHTML = "<a onclick='PROCESS.changeMode();' title='Click to change mode; CTRL with focus in editor to trigger one immediate processing'>Mode</a>: " + (PROCESS.getImmediate() ? " immediate " : " delay max " + delay + "[ms]");
    }
  }


  let setFct = (f) => { fct = f; };  // setter for fct

  let process = ( eventObject ) => { // the raw service function as it is called from the outside
    console.log ("process called");
    if (VERBOSE) {
      if      ( eventObject == "INIT"   )           { console.info  ("preview.js: process called at initialization ");}
      else if ( eventObject == "RESIZE" )           { console.info  ("preview.js: process called after resize" );}
      else if ( eventObject instanceof InputEvent)  { console.info  ("preview.js: process called after InputEvent ", "type=" , eventObject.inputType, ( eventObject.isComposing ? " COMPOSING " : " NOT_COMPOSING" ), "data=", eventObject.data, eventObject);}
      else                                          { console.error ("preview.js process called in unclear situation"); }
    }

    // CASE 1: There are some scenarios where we want a preview immediately
    const IMMEDIATELY = ["insertFromDrop", "insertFromPaste", "insertFromPasteAsQuotation", "deleteByDrag", "deleteByCut", "historyUndo", "historyRedo"];

    if ( immediate || eventObject == "INIT" || eventObject == "RESIZE" || IMMEDIATELY.includes (eventObject.inputType) )     
      { if (VERBOSE) {console.info ("CASE 1: Special request with immediate processing: " + (typeof eventObject == "string" ? eventObject : eventObject.inputType ) );}   
//        fct(); 
         window.setTimeout (fct, 0);
        return; }

    // CASE 2: When the oldest inputevent which has not yet been served is longer ago than MAX_EVENT_WAIT [ms]

    if (queueing) {  // in case we are already in queueing 
      if ( eventObject.timeStamp - oldestUnservedTS > MAX_EVENT_WAIT ) { 
        console.info (`CASE 2: ${eventObject.timeStamp} ${oldestUnservedTS}`); 
        if (timeout) {window.clearTimeout (timeout); timeout= null;} 
        queueing = false; 
        fct(); 
        return;}
      else { 
        if (VERBOSE) {console.info (`CASE 2-: Not yet time to execute a call: current=${eventObject.timeStamp} oldestUnserved=${oldestUnservedTS}  diff=${eventObject.timeStamp - oldestUnservedTS }`);} }
    } 
    else {  // in case we are not yet in queueing mode we enter queueing mode but at most for MAX_FALLBACK
      queueing = true;
      oldestUnservedTS = eventObject.timeStamp;
      if (VERBOSE) {console.info ("CASE 3a: Setting up a timeout event");}
      timeout = window.setTimeout ( ()=> { if (VERBOSE) {console.info ("CASE 3b: Timeout fired");}  queueing = false; fct();}, MAX_FALLBACK );   
    }
  }; 

  return {process, setFct, setImmediate, getImmediate, toggleImmediate, getDelay, changeMode, showMode};

})();  // CLOSE local scope



/***
 ***  DPRES: Dante Presentations
 ***/

// define namespace DPRES for extension DantePresentations to ensure proper separation from other components
const DPRES = (() => {


// when the client receives any response from the preview endpoint: patch the information into the area where we show the preview
// function is called by processAll
function receivedEndpointResponse(e) {
  const VERBOSE = false; 

  let arrived = Date.now();  

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
      if (e.target.timeRequestMade > QUEUE.currentTimestamp) { if (VERBOSE) {console.log ("  Will display the endpoint response to request ", e.target.timeRequestMade, e.target.numberOfRequest); }
          (DOUBLEBUFFERED ? displayEndpointResponseDoubleBuffer : displayEndpointResponseSingleBuffer) (e); 
 
    }
      else    { if (VERBOSE) { console.log (`  Will discard endpoint response. Tool old: From ${e.target.timeRequestMade} and thus older than current status ${QUEUE.currentTimestamp}`); }
                QUEUE.remove (e.target);
             if (QUEUE.getLen () == 0 ) { document.body.classList.remove ("xhrPending", "inputChanged");}

          }
    }
    else {console.warn ("  The XHR object had no timestamp, this is a protocol error!");}
  }

  let roundTrip = Date.now() - e.target.timeRequestMade;
  roundTrip = roundTrip.toString().padStart (5, '\u00A0');
  var sum = document.body.querySelector ("label[for=wpSummary]");
  sum.innerHTML = "Status: " + e.target.status + " Roundtrip: " + roundTrip + "[ms] " + "<span id='showMode'></span>";
  PROCESS.showMode();   // patch in the process mode information into span id='showMode'          
}





// double buffered preview frame prevents flickering and ensures faster and smoother UI reaction
let previewFrames = [];
let currentFrame = 0;
let currentZoom = 1;

 
// this is the single buffered display of an endpoint response; we maintain it to have a fallback for debugging purposes
function displayEndpointResponseSingleBuffer (e) {
  let VERBOSE = false;
  if (VERBOSE) {console.log ("displayEndpointResponse SINGLE buffered called");}
  var previewFrame = document.getElementById ("previewFrame");                            // get a handle of the previewFrame
  document.getElementById ("previewFrame").setAttribute ("srcdoc", e.target.response);    // show current content in the previewFrame

  QUEUE.currentTimestamp = e.target.timeRequestMade;                                      // update time stamp of the content currently on display
  QUEUE.remove (e.target);                                                                // remove the response just received so the topmost queue element is the most recent invocation we are waiting for 
  QUEUE.nonCancelable ();                                                                 // make most recent nonCancelable and cancel all others
  if (QUEUE.getLen () == 0) {
    console.info ("displayEndpointResponse: removing pending");
    document.body.classList.remove ("xhrPending", "inputChanged");}              // show by decoration of frame whether we still are awaiting responses
  else {
    console.info ("displayEndpointResponse: queue length currently " + QUEUE.getLen() );
  }

  // we want azoom change inside of only the iframe with the preview content alone
// TODO:  for some stupid reason this does not work.  srcdoc ??  maybe other reason.
// TODO: if it really does not work: add this to the html code as it is built in the mediawiki.php endpoint and the other endpoints 
  document.getElementById ("previewFrame").onload = function() {
    document.getElementById ("previewFrame").contentWindow.addEventListener ("focus", (e) => { /* console.warn ("FOCUS"); */  previewFrame.classList.add ("hasFocus");   }); 
    document.getElementById ("previewFrame").contentWindow.addEventListener ("blur",  (e) => { /* console.warn ("BLUR", ); */ previewFrame.classList.remove("hasFocus"); }); 
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
  let VERBOSE = false;
  if (VERBOSE) {console.log ("displayEndpointResponseDoubleBuffer called");}

  var curFrame = previewFrames[currentFrame];
  var newFrame = previewFrames[ (currentFrame == 0 ? 1 : 0) ];    // the frame which is currently not showing and waiting underneath for drawing new stuff

  newFrame.setAttribute ("srcdoc", e.target.response);                                    // show current content in the previewFrame

  QUEUE.currentTimestamp = e.target.timeRequestMade;                                      // update time stamp of the content currently on display
  QUEUE.remove (e.target);                                                                // remove the response just received so the topmost queue element is the most recent invocation we are waiting for 
  QUEUE.nonCancelable ();                                                                 // make most recent nonCancelable and cancel all others

  newFrame.onload = function () {  // the frame we just wrote has, as consequence of the writing, lost its handlers - so add them again after the load has completed 
    let current = curFrame.contentWindow.pageYOffset;    // get scroll status of the current window

//    const scrollTop = .scrollY;                               // Current vertical scroll position
    const windowHeight = curFrame.contentWindow.innerHeight;                        // Height of the viewport
    const documentHeight = curFrame.contentWindow.document.documentElement.scrollHeight;   // Total height of the document

    let perc = 100.0 * (current + windowHeight) / documentHeight;
    // console.info ("current scrolled=", current, " windowHeight=", windowHeight, "sum=", (current + windowHeight), " documentHeight=", documentHeight,  "percentage scrolled: " , perc);
    if (perc > 98.5) {
      current = (documentHeight - windowHeight + 20);
      // console.info ("adjusted scroll to ", current);

    }
    newFrame.contentWindow.scrollTo (0, current);        // and impose it on the new frame jsut activated

    newFrame.contentWindow.addEventListener ("focus", (e) => { /* console.warn ("FOCUS");*/  newFrame.classList.add ("hasFocus");   }); 
    newFrame.contentWindow.addEventListener ("blur",  (e) => { /* console.warn ("BLUR", ); */ newFrame.classList.remove("hasFocus"); }); 
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

    // the collapsible elements in the preview window (eg those coming Parsifal) must be opened in the preview window 
    document.querySelectorAll('iframe').forEach(function(iframe) {
      var iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
      var elementsInIframe = iframeDocument.querySelectorAll('.collapseResult');
    elementsInIframe.forEach(function(element) {
        element.style.display = 'block'; // or any other appropriate display style
    });
    });

    newFrame.style.visibility = "visible";               // new frame becomes visible

    if (QUEUE.getLen () == 0) {
       // console.log ("remove UI indication that an xhr is pending");
      document.body.classList.remove ("xhrPending", "inputChanged");}              // show by decoration of frame whether we still are awaiting responses
    currentFrame = (currentFrame == 0 ? 1 : 0);          // adjust the pointer
  }

  if (VERBOSE) {console.log ("will exit displayEndpointResponse, queue is ", QUEUE.getLen());}
  if (QUEUE.getLen() > 0) {console.warn ("will exit displayEndpointResponse, queue still is ", QUEUE.getLen());}
}

let numberOfRequest = 0;



function processAll() {

  var body;

  if (codemirrorFlag) {body = b64EncodeUnicode ( myCodeMirror.getValue() ); }
  else {body = b64EncodeUnicode ( document.getElementById ( "wpTextbox1" ).value );}

  // console.info ("DantePresentation: calling processAll with body", body);
  const xhr = new XMLHttpRequest();
  xhr.open ("POST", "./extensions/DantePresentations/endpoints/mediawikiEndpoint.php", true);   //////// TODO: how do we select the specific endpoitn : reveal or parsifal or markdwon or whatever ????
  xhr.setRequestHeader ("Content-Type", "text/plain;charset=UTF-8");

  setDanteHeaderInfo ( xhr ); 

  // header and xhr object gets a timestamp when the request was made and sent to the server
  xhr.timeRequestMade = Date.now();
  xhr.numberOfRequest = numberOfRequest++;
  xhr.setRequestHeader ("X-Dante-ClientRequestTime", xhr.timeRequestMade);
  xhr.onload = (e) => {receivedEndpointResponse (e);};

//  console.log ("Submitting request to queue ", xhr.numberOfRequest);
  QUEUE.submit ( xhr );

//  console.log ("Sending request ", xhr.numberOfRequest);
  xhr.send ( body );
  document.body.classList.remove ("inputChanged");
  document.body.classList.add ("xhrPending");        // signal in UI that a request from the server is pending
}



// set specific header information for the preview endpoint
function setDanteHeaderInfo ( xhr ) {
  xhr.setRequestHeader ("Wiki-wgUserName",            RLCONF.wgUserName);
  xhr.setRequestHeader ("Wiki-wgUserId",              RLCONF.wgUserId);
  xhr.setRequestHeader ("Wiki-wgNamespaceNumber",     RLCONF.wgNamespaceNumber);
  xhr.setRequestHeader ("Wiki-wgPageName",            RLCONF.wgPageName);                     // full name of page, including localized namespace name, if namespace has a name (except 0) with spaces replaced by underscores. 
  xhr.setRequestHeader ("Wiki-wgTitle",               RLCONF.wgTitle);                        // includes blanks, no underscores, no namespace
  xhr.setRequestHeader ("Wiki-wpPageContentLanguage", RLCONF.wgPageContentLanguage);
}





// with a dirty editor we have to deliver a warning message to the user when he is leaving the page
// normally MediaWiki does so, but when we use CodeMirror it looks like this feature is overridden by CodeMirror somehow
// thus we impelement it here again
let isEditorDirty = false;
window.addEventListener('beforeunload', (event) => { if (isEditorDirty) { event.preventDefault(); return ''; } });



// Apply path to the edit page of Mediawiki. Called by <script> tag injected in DantePresentations.php:onEditPageshowEditForminitial
function initializeTextareaDP() {
  console.warn ("initializeTextareaDP called");

  var storeResize = true;                                   // shall the resize observer store the resize values?

  var textarea = document.getElementById ( "wpTextbox1" );     // pick up the textarea
  textarea.setAttribute ("autocomplete",   "off");             // and set some parameters
  textarea.setAttribute ("autocorrect",    "off");
  textarea.setAttribute ("autocapitalize", "off");
  textarea.myFontSize = 14; textarea.style.fontSize = textarea.myFontSize + "pt";

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


  var newEditContainer = document.createElement ("div");   // container of (textarea for editing) and (preview area)
  newEditContainer.id  = "new-edit-container";
  Object.assign (newEditContainer.style, {display: "flex", height: "calc(100vh - 540px)"} ); 

  var parent = textarea.parentNode; parent.replaceChild (newEditContainer, textarea);
  newEditContainer.appendChild (textarea);
  

 var previewContainer = document.createElement ("div");          // generate a container for the preview
  previewContainer.id = "inline-edit-preview-container";
  Object.assign (previewContainer.style, {minWidth: "18px", display: "inline-block", flex:"1 1 auto", "box-sizing": "border-box", position: "relative"} ); 

  previewFrames[0] = document.createElement ("iframe"); 
  Object.assign (previewFrames[0].style, {width:"100%", height:"100%", position:"absolute", left:"0px", top:"0px"} ); 
  previewFrames[0].id="previewFrame";
  if (DOUBLEBUFFERED) {
    previewFrames[1] = document.createElement ("iframe"); 
    Object.assign (previewFrames[1].style, {width:"100%", height:"100%", position:"absolute", left:"0px", top:"0px"} ); 
    previewFrames[1].id="previewFrame1";}

  previewFrames[0].classList.add ("previewFrameClass");
  if (DOUBLEBUFFERED) {previewFrames[1].classList.add ("previewFrameClass");}

  previewContainer.appendChild (previewFrames[0]);   
  if (DOUBLEBUFFERED) {previewContainer.appendChild (previewFrames[1]);}
  newEditContainer.appendChild (previewContainer);

  let editform = document.getElementById ("editform"); 

  PROCESS.setFct ( processAll );                                       // define which function to use for processing for previewing

  editform.addEventListener ("input", (e) => { isEditorDirty=true; PROCESS.process(e); } );               // install an event listener for changes in the textarea
  window.setTimeout ( () => { PROCESS.process ( "INIT" );} , 0);       // kick off display of first preview

  editform.addEventListener ("input", () => { document.body.classList.add ("inputChanged");});  



 // keyboard based resizing inside of textarea only resizes the textarea font size itself
  textarea.addEventListener ("keydown", (e) => {  // console.log ("Key pressed: ", e.key);
    if (e.metaKey && (e.key=="+" || e.key=="-") ) {e.preventDefault (); e.stopPropagation(); textarea.myFontSize += ( e.key=="+" ? 2 : -2 ); textarea.style.fontSize = textarea.myFontSize + "pt"; return;} 
    switch (e.key) {
      // case "Escape":   console.log ("removing input listener");  editform.removeEventListener ("input", PROCESS.process );  break;
      // case "Control":  console.log ("re-adding input listener"); editform.addEventListener ("input", PROCESS.process);  PROCESS.process ( "INIT" );   break;
      case "Control":      console.log ("immediate processing");     PROCESS.process ( "INIT" );   break;
   //   case "Ctrl":    console.log ("toggeling immediate");      PROCESS.changeMode ();   break;
    }

   });

}


//console.error ("preview: after initializeTextareaDP ", initializeTextareaDP);

let myCodeMirror;



function initializeCodeMirror () {
  var myTextArea   = document.getElementById("wpTextbox1");
  myCodeMirror = CodeMirror.fromTextArea ( myTextArea, { lineNumbers:true, matchBrackets:true} );    // returns an abstract CodeMirror object

  var cmElement = document.querySelector (".CodeMirror");
  cmElement.myFontSize = 14; 
  cmElement.style.fontSize = cmElement.myFontSize + "pt";  
  cmElement.CodeMirror.refresh();
  cmElement.addEventListener ("keydown", (e) => { console.log ("CMirror Key pressed: ", e.key);
    if (e.metaKey && (e.key=="+" || e.key=="-") ) {  // handle zoom changes of the browser
      e.preventDefault (); e.stopPropagation(); 
      cmElement.myFontSize += ( e.key=="+" ? 2 : -2 ); cmElement.style.fontSize = cmElement.myFontSize + "pt"; 
      cmElement.CodeMirror.refresh();            // needed by code mirror after a font change
    }  
    switch (e.key) {
      // case "Escape":   console.log ("CM removing input listener");  editform.removeEventListener ("input", PROCESS.process );  break;
      // case "Control":  console.log ("CM re-adding input listener"); editform.addEventListener ("input", PROCESS.process);  PROCESS.process ( "INIT" );   break;
      case "Control":      console.log ("CM immediate processing");     PROCESS.toggleImmediate ();  PROCESS.process ( "INIT" );   break;
      //case "Ctrl":    console.log ("toggeling immediate");      PROCESS.changeMode ();   break;
      case "Backspace": PROCESS.process (e); document.body.classList.add ("inputChanged"); isEditorDirty=true;  break;  // CodeMirror prevents input event from being sent to the textarea, but also need this to take place when deleting text
    }

  } );

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

let codemirrorFlag = false;

const USE_CM = false;  // if this is true, then code mirror is always used, independently from the URL request

// TODO: add a UI link into sidebar for the editor type !!


function editPreviewPatch () {  // the clutch to PHP; we may adapat it to use CodeMirror, textarea or whatever client side editor we desire  
  console.log ("preview: editPreviewPatch before initializeTextareaDP");
  initializeTextareaDP();
  console.log ("preview: editPreviewPatch after initializeTextareaDP");
  codemirrorFlag = false;
  let params = (new URL (document.location)).searchParams;
    if ( USE_CM || params.get("editormode") == "codemirror") {  
    initializeCodeMirror ();  // additionally initialize a code mirror instance
    codemirrorFlag = true;
  }
}


  return {editPreviewPatch};  // export into DPRES

}  )();
/*** END DPRES **/



window.DPRES = DPRES;

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

var QUEUE = (() => { // BEGIN of QUEUE scope
  const VERBOSE = false;
  let pendingRequests  = [];                                                                                               // list of pending requests waiting for a server reply
  let currentTimestamp = 0;                                                                                                // time stamp of the request whose content is showing currently
  var submit           = ( x ) => { pendingRequests.unshift (x); };                                                        // submit a fresh request to the queue
  var nonCancelable    = () => { if (pendingRequests.length > 0) {pendingRequests[0].nonCancelable = true; cancel();} };   // mark top of the queue (which is the most recently added) as non-cancelable
  var getLen           = () => pendingRequests.length;                                                                     // get length of the queue
  var remove           = (ele) => { pendingRequests=pendingRequests.filter ( x => x!=ele);}                                // remove a specific element from the queue
  var cancel           = () => {                                                                                           // cancel all but the non-cancelable, and remove them from the queue as well
    pendingRequests = pendingRequests.filter ( ele => {
      if (ele.nonCancelable) { if (VERBOSE) {console.log ("keeping request ", ele.numberOfRequest, " made at ", ele.timeRequestMade);}  return true;} 
      else { if (VERBOSE) {console.log ("aborting request number ", ele.numberOfRequest, " made at", ele.timeRequestMade);}  ele.abort(); 
              return false;}
    });
  };
  return {submit, nonCancelable, currentTimestamp, getLen, remove};  // export object

})();  // END OF QUEUE scope


/** slow down the issuing of requests in case of a resizing of the preview area 
*/
var timer = null;
function waitBeforeInvoke (ms) { // this MUST be used when the preview area is resized (otherwise we get a too high rate of server requests - for every resie event fired by client)
  window.clearTimeout (timer);   
  timer = window.setTimeout ( () => PROCESS.process ("RESIZE") , ms);       // start a timer and only then invoke processing
}




console.error ("***** preview.js is loaded");