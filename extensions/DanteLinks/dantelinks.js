function checkPopup(e) {
  let url = e.target.dataset.snip;
  if (!url) {return;}  // nothing to do for this link

  url = "./index.php?title=" + e.target.dataset.snip;

  // get and normalize snip size
  let snipWidth  = localStorage.getItem ("snipWidth");    snipWidth = parseInt (snipWidth); console.log ("snipWidth found: ", snipWidth);
  let snipHeight = localStorage.getItem ("snipHeight");   snipHeight = parseInt (snipHeight);
  if (typeof snipWidth  !== 'number' || isNaN(snipWidth)  || snipWidth < 100  || snipWidth > window.innerWidth   ) { console.warn ("snipWidth is reset" );  snipWidth  = 600;}
  if (typeof snipHeight !== 'number' || isNaN(snipHeight) || snipHeight < 100 || snipHeight > window.innerHeight ) { console.warn ("snipHeight is reset");  snipHeight = 800;}
  // TODO: it might also be an approach just to set it fixed. must see how it works

  let ifra = document.getElementById ( url );
  if (ifra) {return;} else {ifra = getSnipFrame ( e.target.dataset.snip, snipWidth, snipHeight );}



  // calculate the space we have to the boundaries of the window
  let spaceToTheRight  = window.innerWidth - e.pageX;
  let spaceToTheLeft   = e.pageX;
  let spaceToTheTop    =   e.pageY;
  let spaceToTheBottom = window.innerHeight - e.pageY;

  if (spaceToTheRight > spaceToTheLeft) { 
    //console.log ("placing to the right of the cursor, .left=", e.pageX);
    ifra.parentNode.style.left =   e.pageX + "px";} 
  else { 
    //console.log ("placing to the left, .right=", e.pageX - snipWidth);
    let d = e.pageX - snipWidth;
    ifra.parentNode.style.left = (d > 0 ? d : 0 ) + "px";  }

  if (spaceToTheTop < spaceToTheBottom) { 
    //console.log ("placing below, .top=", e.pageYY);
    ifra.parentNode.style.top   =  e.pageY + "px"; }
  else { 
    //console.log ("placing on top, e.pageY", e.pageY, " snipHeight ", snipHeight, " .top=", (e.pageY - snipHeight) );
    let d = e.pageY -snipHeight;
    ifra.parentNode.style.top   = (d > 0 ? d : 0 ) + "px"; }

    // open the requested URL
  if (ifra.checkSrc != url) { // ensure we only load it once even though iframe resolves the src
    ifra.checkSrc = url;
    ifra.src = url;
    //console.log("dantelinks: ifra.src is", ifra.src);
  }

};




// todo: double escape turns off the nippets for this page - and gives feedback 
// todo: do these snippets also work on top of latex parsifal links ????

function getSnipFrame ( id, width, height ) {
  let ifra = document.getElementById ( id );
  if (ifra) {return ifra;}

  let container = document.createElement ("div");
  container.style = "box-sizing:border-box;position: absolute; resize:both; overflow-y:scroll; overflow-x:hidden; width: "+width+"px; height:"+height+"px; min-width:100px; min-height:20px; border:1px solid black; background-color:white;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1), 0 6px 20px rgba(0, 0, 0, 0.1);";
  
  ifra      = document.createElement ("iframe");
  ifra.id   = id;
  ifra.style = "position:absolute; bottom:0px;background:white; box-sizing:border-box;border:0px; height:calc(100% - 20px); width:100%; overflow:hidden;"; // height:400px; width:800px; z-index:80000; position:relative; top:30px;";

  let bar = document.createElement ("div"); // titlebar of the frame
  bar.style = "position:absolute; box-sizing:border-box;text-align:center; height:20px; width:100%;left:20px;user-select:none; background-color:AliceBlue;border-width:0px 0px 1px 1px;border-color:grey;border-style:solid; cursor:move;";
  bar.innerHTML = "Snip";
  bar.title = "Doubleclick to toggle";


  // if the container has a .hasOldHeight then this denotes the size to which we open it after a double click
  // if the container has no .hasOldHeight then it closes to a small line only 
  bar.addEventListener ("dblclick", (e) => {
    if (e.target.parentNode.hasOldHeight)  { e.target.parentNode.style.height = e.target.parentNode.hasOldHeight; e.target.parentNode.hasOldHeight = false;}
    else                                    { e.target.parentNode.hasOldHeight = e.target.parentNode.style.height;  e.target.parentNode.style.height="20px";}
  });

  const resizeObserver = new ResizeObserver(ents => {
    for (let ent of ents) {
      console.log (`DIV resized to ${ent.contentRect.width}px x ${ent.contentRect.height}px`);
      if (ent.contentRect.width < 0 || ent.contentRect.height < 100) {return;}  // ignore too small as well as == 0 (happens when removed)
      localStorage.setItem ( "snipWidth", ent.contentRect.width);
      localStorage.setItem ( "snipHeight", ent.contentRect.height);
      ent.target.hasOldHeight = null; // if we do a double click after a resize we collapse it to small

    }
  });

  resizeObserver.observe (container);



  let closer = document.createElement ("div");
  closer.style = "box-sizing:border-box; border-bottom:1px solid grey; position:absolute; top:0px; left:0px; width:20px; min-width:20px; height:20px; min-height:20px; max-height:20px; background-color:lightgray; text-align:center; cursor:pointer;";
  closer.title = "Close this frame";
  closer.innerHTML = "&times;";

  closer.addEventListener ("click", (e) => {e.target.parentNode.parentNode.removeChild (e.target.parentNode);});

  container.appendChild (bar);
  container.appendChild (closer);
  container.appendChild (ifra);

   document.body.appendChild(container);

  let isDragging = false;
  let offsetX, offsetY;

  bar.addEventListener('pointerdown', (e) => {
    bar.setPointerCapture (e.pointerId);
    isDragging = true;
    offsetX = e.clientX - container.getBoundingClientRect().left;
    offsetY = e.clientY - container.getBoundingClientRect().top;
    bar.style.cursor = 'grabbing';
    e.preventDefault();  // Prevent text selection
  });

  bar.addEventListener('pointermove', (e) => { if (isDragging) { container.style.left = `${e.clientX - offsetX}px`; container.style.top = `${e.clientY - offsetY}px`; } } );
  bar.addEventListener('pointerup', (e) => { isDragging = false;  bar.releasePointerCapture (e.pointerId);  bar.style.cursor = 'move'; });
  bar.addEventListener('pointercancel', (e) => { if (isDragging) {isDragging = false; bar.releasePointerCapture(e.pointerId); bar.style.cursor = 'move'; } } );

  return ifra;
}






let windowList = [];

let slots = new Array (null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null);

// implement proper opening of links by the user
//
// target=_window    opens in a new window, similar size as opener
// target=_sside      opens on the left side in a reasonable small sizehttps://localhost:4443/wiki-dir/index.php?title=Special:RecentChanges
// target=_lside      open in a larger window
// CAVE: attributes are not case sensitive in html!
// CAVE: we need the Math.random below since otherwise we have the same name for the target window and this may lead to some spurous "about:" window opening somewhere else. do not know why.

function onReady () {
  const GAP = 30;

  const SKIP = 80;

  const helper2 = () => {
    const index = slots.findIndex ( entry => entry === null || (entry && entry.closed === true) );
    let left = -1000 + index * GAP;   // -1000 for the left monitor
    let top  = SKIP + index * GAP;
    let h = window.screen.availHeight - SKIP - index * GAP;
    let w = 800;
    //if (h===null) {h = window.screen.availHeight-100-windowList.length*GAP;}
    //  if (h < 100) {h=100;}
    // top=200; h=300; w=300;left=100;
    let ret = `left=${left},top=${top},height=${h},width=${w}`;  // note: if we add noopener here then window.open returns null and we cannot determine the closing status of the window any longer
    console.log ("helper2 returning: " + ret);
  return ret;
  };

  const ins = (win) => { // inserts a fresh window into the slot table, into an empty slot or a slot whose window was closed by the user.
    const index = slots.findIndex ( entry => entry === null || (entry && entry.closed === true) );
    slots [index] = win;
  };

  // given the target anchor element, return the URL for the endpoint to be loaded: either normal or full view endpoint
  // full === true        forces content endpoint
  // full === false       forces normal page
  // full === undefined   do auto detection
  const getEndpoint = (t, full) => { console.log ("getEndpoint called with: " + t);
    if ( full === false || ( full === undefined && t.classList.contains ("external") ) ) { return t.href;}    // for external links it is always the original href attribute

    const urlObj = new URL(t);
    const params = new URLSearchParams(urlObj.search);
    const title  = params.get('title');      // Returns null if 'title' does not exist
    const action = params.get ('action');    // need to check this to prevent error on edit links 
    const pathComponents = urlObj.pathname.split('/').filter(Boolean);
    const lastComponent = pathComponents.length > 0 ? pathComponents[pathComponents.length - 1] : null;      // Return the last component, or null if there is none

  // derive meta data as set in DantePresentations.pgp full view link for the entire page
    const elementQ         = document.querySelector('[data-fullview-query]');
    const fullviewQuery    = elementQ ? elementQ.getAttribute('data-fullview-query') : null;
    const elementE         = document.querySelector('[data-fullview-query]');
    const fullviewEndpoint = elementE ? elementE.getAttribute('data-fullview-endpoint') : null;

    const finalist = fullviewEndpoint + "?" + fullviewQuery + "&" + title;

    if (fullviewQuery === null || fullviewEndpoint === null ) {console.error ("Could not properly form DanteLink endpoint" ); return t.href;}
    if (action !== null) {console.warn ("non-view action found for DanteLink mechanism"); return t.href;}

   return finalist; 
  };


    // console.log ("DanteLinks instrumentation is now done!");
    $('a[target=_window]').click( (e)  => {  e.preventDefault();   var win = window.open( getEndpoint (e.currentTarget) , "_"+Math.random(), helper2 () ) ;  ins (win);  return false; });
    $('a[target=_sside]').click( (e)   => {  e.preventDefault();   var win = window.open( getEndpoint (e.currentTarget) , "_"+Math.random(), helper2 () ) ;  ins (win);  return false; });
    $('a[target=_lside]').click( (e)   => {  e.preventDefault();   var win = window.open( getEndpoint (e.currentTarget) , "_"+Math.random(), helper2 () ) ;  ins (win);  return false; });

    // external links always open in a fresh tab
   // $('a[class*="external"]').click( () => { e.preventDefault (); window.open(this.href, "_blank", "noopener=1,noreferrer=1");  return false; });

    $('a[data-snip]').on("mouseover", function(e) { // only instrument links with a data-snip attribute !
        checkPopup(e);
    });

  let startX, startY;  // for the detection of the drag distance
  $("a").on("dragstart", async function(event) {startX = event.originalEvent.pageX; startY = event.originalEvent.pageY; 
   let details = await window.getScreenDetails();
    console.log (details);
   // await requestPermission();
  });

 $("a").on("drag", function(e) {
    let endX = e.originalEvent.pageX; let endY = e.originalEvent.pageY;
    let dist = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
    if      ( dist > 20 && dist < 40) {e.originalEvent.target.style.border= "3px solid blue";}
    else if ( dist >= 40) {e.originalEvent.target.style.border= "3px solid red";}
  } );

  $("a").on("dragend", function (e) {let endX = e.originalEvent.pageX; let endY = e.originalEvent.pageY;
     let dist = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
     e.originalEvent.target.style.border= "0px solid red";
     if ( dist > 20 && dist < 40) { var win = window.open (getEndpoint (e.currentTarget.href, true), "_"+Math.random(), helper2 () ); ins (win); }
     else if ( dist >= 40)        { var win = window.open (getEndpoint (e.currentTarget.href, true), "_"+Math.random(), helper2 () ); ins (win); }

  });
}




$(onReady);

// console.error ("dantelinks.js loaded");