/* JavaScript for the DantePresentationSkin skin */

// show the sizing situation
function showSize () {
  console.log ("DantePresentationSkin: resources: skin.js showSize is reporting: ");
  console.log("outer: " + window.outerWidth+' x '+window.outerHeight + "\n" + "screen: " + screen.width+ ' x '+screen.height + "\n"
   + "inner:" + window.innerWidth + " x " + window.innerHeight + "\n" + "body:" + 
   document.body.clientWidth + " x " + document.body.clientHeight   + "\n" + 
   "html:" + document.documentElement.clientWidth + " x " + document.documentElement.clientHeight);
   console.log ("Fullscreen by window.fullscreen: " + window.fullScreen);
   console.log ("Fullscreen by size comparison: " + (window.innerWidth == screen.width && window.innerHeight == screen.height) );
}

// adjust Size
function adjustSize () {
  console.log ("DantePresentationSkin: resources: skin.js adjustSize ");
  var scale = window.innerHeight / document.body.clientHeight;
  scale = 1 / scale;
  console.log ("scale = " + scale + " = " + window.innerHeight + " / " + document.body.clientHeight);
  document.body.style.transform = "scale("+scale+")";
  console.log (document.body.style.transform);
}





// defines command which may be issued by a controlling instance
class Command {
  static library = {};            // library of commands which we defined; maps names to functions
  constructor (name, fct) { 
    if ( Command.library[name] ) {throw new Exception ("Command " + name + " is already defined !");}
    Command.library[name] = fct;
  }
  static execute (name, opt) {if (Command.library[name]) {Command.library[name](opt);} else {console.warn ("Now we should execute command " + this.name + " which is not yet implemented");}}
}


var POINTER = (() => { // begin scope
  return;
  let pointer = document.createElement ("div");
  Object.assign (pointer.style, {position: 'fixed', float: 'left', borderRadius: '50%', width: '30px', height: '30px',backgroundColor: 'rgba(255, 0, 0, 0.4)',zIndex: 20 , cursor: "none", left:"200px", top:"200px"} );
  document.body.appendChild (pointer);
  // console.log ("Pointer has been placed");
  const place = (x,y) => {

    return;
    // console.log ("place received", x, y, typeof x, typeof y);
    // console.log ("body client size: ", document.body.clientWidth, document.body.clientHeight);
    x = x * document.body.clientWidth;  y = y * document.body.clientHeight;
    // console.log ("placing pointer at ", x, y);
    Object.assign (pointer.style, {display:"block", left: (x-15) + "px", top: (y-15) + "px"} ); }
  const off   = () => {};
  const on    = () => {};
  return {place, on, off};
})(); // end scope



new Command ("show", () => {});

new Command ("right", () => Reveal.right());
new Command ("left",  () => Reveal.left());


new Command ("next", () => Reveal.right());


new Command ("pointer", (opt) => {
  //console.log ("pointer command received: ", opt);
  POINTER.place (opt.partX, opt.partY);
});



// helper function; sends size of the presentation to the controller via connection
function sendSize (connection) {
  connection.send ( JSON.stringify ( {command: "size", param: {
    outerWidth: window.outerWidth, outerHeight: window.outerHeight, screenWidth:  screen.width, screenHeight:screen.height, windowInnerWidth: window.innerWidth, windowInnerHeight: window.innerHeight,  
    bodyClientWidth:  document.body.clientWidth, bodyClientHeight: document.body.clientHeight, htmlClientWidth: document.documentElement.clientWidth,  htmlClientHeight: document.documentElement.clientHeight, windowFullScreen: window.fullScreen } } ) );
}


function listen () {          // call this to set up conenction listenting system
  let connectionIdx = 0;      // index of the controlling connection - there may be several
  let messageIdx = 0;         // index of all the messages received
  
  function addConnection (connection) {    // called for every connection we find
    if (connection.connectionId) { console.warn (`addConnection: The connection which just came in already has an id (${connection.connectionId}) and therefore already has handlers registered `);} 
    else {
      connection.connectionId = ++connectionIdx;
      console.log ('addConnection: New connection registered under number: ' + connectionIdx);
      sendSize (connection);

      connection.addEventListener ('message', function(event) {  //console.log ("message arrived ", event.data);
        messageIdx++;
        const data = JSON.parse(event.data);
        // console.log ('Message ' + messageIdx + ' from connection #' + connection.connectionId + ': ', data);
        Command.execute (data.command, data.param);
      });
  
    connection.addEventListener('close', function(event) {console.log ('Connection #' + connection.connectionId + ' closed, reason = ' +  event.reason + ', message = ' + event.message);});
    } // end else
  };
  
  if (navigator.presentation.receiver) {
    console.log ("presentation receiver supported");
    navigator.presentation.receiver.connectionList.then(list => {
      console.log ("list of connections: ", list);
      list.connections.map (connection => addConnection(connection));                                  // add all the connections of the current list
      list.addEventListener('connectionavailable', (event)  => {addConnection(event.connection);} );   // and observe the list object for fresh ones coming up as well
  });
  } 
  else {console.warn ("No support for presentation.receiver");}

}



function initialize () {
  console.log ("DantePresentationSkin: skin.js: initialize called");
  if (typeof Reveal == "undefined") {
    console.warn ("Reveal is still undefined");
  }
}


console.warn ("I am skin.js in DantePresentationSkin");

/*** MAIN function block */

listen();

//showSize();
//adjustSize();

// window.setTimeout ( showSize, 5000); // leave some time for scaling to settle and display information again

// initialize ();
// window.setTimeout ( initialize, 0); // need a context switch to ensure that Reveal is already loaded


