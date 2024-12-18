function checkPopup(e) {
  let url = e.target.dataset.snip;
  if (!url) {return;}  // nothing to do for this link

  url = "./index.php?title=" + e.target.dataset.snip;


 // url = "https://www.spiegel.de"; // refuses to connect 

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


// this JS snippet implements correct and new treatment of target attributes in anchors
//
// target=_window    opens in a new window, similar size as opener
// target=_sside      opens on the left side in a reasonable small size
// target=_lside      open in a larger window
// CAVE: attributes are not case sensitive in html!
// CAVE: we need the Math.random below since otherwise we have the same name for the target window and this may lead to some spurous "about:" window opening somewhere else. do not know why.
$(function() {
    console.log ("DanteLinks instrumentation is now done!");
    $('a[target=_window]').click( (e)  => { console.log ("_window ", e.currentTarget.href); e.preventDefault();  window.open(e.currentTarget.href, "_"+Math.random(), "noopener=1,noreferrer=1,width=100");    return false; });
    $('a[target=_sside]').click( (e)   => { console.log ("_side", e.currentTarget.href);    e.preventDefault();  window.open(e.currentTarget.href, "_"+Math.random(), "height=800,width=800");                 return false; });
    $('a[target=_lside]').click( (e)   => { console.log ("_Side", e.currentTarget.href);    e.preventDefault();  window.open(e.currentTarget.href, "_"+Math.random(), "height=1200,width=1000");               return false; });

    // external links always open in a fresh tab
    $('a[class*="external"]').click( () => {  window.open(this.href, "_blank", "noopener=1,noreferrer=1");  return false; });

    $('a[data-snip]').on("mouseover", function(e) { // only instrument links with a data-snip attribute !
        checkPopup(e);
    });

});

// console.error ("dantelinks.js loaded");