
/**
 *  Code which is loaded directly and which is responsible for initializing Reveal
 */
  
    

function initReveal ( over ) {

  var pluginsNotes   = []; // [ RevealMarkdown, RevealHighlight, RevealNotes, RevealSearch, RevealZoom, RevealMath ];
  var pluginsNoNotes = []; // [ RevealMarkdown, RevealHighlight, RevealSearch, RevealZoom, RevealMath ];

  var myDefaults = { mouseWheel:true, previewLinks:true, hash: true, 
    width:960, height:700, transitionSpeed: "fast", disableLayout: false,
    margin: 0.04,
    // Bounds for smallest/largest possible scale to apply to content
    minScale: 0.01,
    maxScale: 10.0
  }; 

  var doit = (over) => { return Reveal.initialize ( Object.assign ( myDefaults, over) ); };   // an overriding version of Reveal.initialize

  if ( window.location !== window.parent.location && window.parent.location.pathname.includes ("controller.html")) { // running in an iframe and in particular in the controller
    console.log ("immediate.js: initializing for controller");
    const searchParams = new URLSearchParams(window.location.search);
    if      (searchParams.get("details") == "overview")  { doit ( { plugins: pluginsNoNotes } ).then ( () =>  {Reveal.toggleOverview( true );} );  }
    else if (searchParams.get("details") == "notes"   )  { doit ( { plugins: pluginsNotes } ).then ( () =>  { } );                               }
    else                                                 { doit ( { plugins: pluginsNotes} ).then ( () =>  { } );  }

    Reveal.on("slidechanged", event => {});
  }

  else if ( window.location !== window.parent.location && window.parent.location.pathname.includes ("external.html") ) { // running in an iframe and in particular in the external
    console.log ("immediate.js: initializing for external");
    doit ( {controls:false} );
  }

  else if ( window.location === window.parent.location ) {  // running top-level
   console.log ("immediate.js: initializing for top-level");
   doit ();
  }

  else {  // default: we MUST get initialized somehow
    console.log ("immediate.js: initializing for default");
    doit ();
  }


}


function initController () {

}


document.addEventListener('DOMContentLoaded', function() {initReveal();} );