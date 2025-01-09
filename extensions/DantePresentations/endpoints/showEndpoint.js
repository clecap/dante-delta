


(function() {  // trick suggested by https://chatgpt.com/c/677ee8a0-0dec-800f-b236-341faf304fd9

  const LOADER_URL = '/wiki-dir/load.php?modules=startup&only=scripts';

  function loadStartupModule (callback) {
    const script = document.createElement('script');
    script.src = LOADER_URL;
    script.async = true;

    script.onload = function() {
      console.log ("Startup JS script tag has loaded");
      if (typeof mw !== 'undefined' && mw.loader) {
        console.log ("mw and mw.loader are present");
        console.log ("mw.loader is ", Object.keys (mw.loader));
        console.log ("mw is ", Object.keys (mw));
        console.log ("state ", mw.loader.state);
        console.log ("require ", mw.loader.require);
       console.log ("Calling initialization function");
        initializeWhenReady (callback);
        console.log ("Returned from initalization function");

      } else {
        console.error('mw or mw.loader is not available after loading the startup module.');
      }
    };

    script.onerror = function() {console.error('Failed to load the startup module.');};

    document.head.appendChild(script);
  }

  function initializeWhenReady(callback) {
    let jqueryLoaded = false;
    let baseLoaded   = false;
    console.log ("Just entered initialization function");
    const originalDefine = mw.loader.implement;                     // save the original mw.loader.implement function
    mw.loader.implement = function(moduleName, script, ...args) {   // temporarily override the mw.loader.implement function which is used internally to load modules
      console.log ("Just entered patched implement with moduleName: ", moduleName, "and script type: " + typeof (script));
      console.log ("Just entered patched implement found script ", script);

      let res = originalDefine.call(this, moduleName, script, ...args);       // call the original implement function


      console.log ("Completed original implement, result was: ", res);
      if (moduleName.startsWith ('jquery') ) {
        console.log ("jquery loaded, dollar is: ", $);
        mw.loader.require ("jquery");
       console.log ("jquery required, dollar is: ", $);


        jqueryLoaded = true;
      }
      if (moduleName.startsWith ('mediawiki.base') ) {
        console.log ("mediawiki.base loaded");
      


        baseLoaded = true;
      }
      if ( baseLoaded && jqueryLoaded) {
        console.log ("base and jquery are loaded");
        mw.loader.implement = originalDefine;                       // Restore the original implement function to prevent future side effects
        console.log ("Callng callback");
        callback();                                                 // Invoke the callback as mw.loader.using is now available
        console.log ("Returned from callback");
      }
      else { console.log ("not yet both loaded");}
    };
  }

  // Example usage
  loadStartupModule(function() {
    mw.loader.using(['mediawiki.util']).then(function() {
      console.log('MediaWiki utility module loaded.');
      // Add your hooks or other logic here
      mw.hook('your.custom.hook').add(function() { console.log('Custom hook executed!'); });
    }).catch(function(error) {
      console.error('Failed to load required modules:', error);
    });
  });
})();






















// determining zoom level using different methods
function getZoomLevel() {

  let meth1 = Math.round(window.devicePixelRatio * 100);
  console.log ("devicepixelratio: ", window.devicePixelratio);

  let meth2 = Math.round((window.innerWidth / window.outerWidth) * 100);

  const element = document.createElement('div');
  element.style.width = '100px';
  element.style.height = '100px';
  element.style.position = 'absolute';
  element.style.visibility = 'hidden';
  document.body.appendChild(element);
  let meth3 = Math.round((100 / element.offsetWidth) * 100); 
  document.body.removeChild(element);

  console.log ("Zoom levels:", meth1, meth2, meth3);
}


window.addEventListener('resize', () => {
  getZoomLevel ();
});

// getZoomLevel(); // dom not yet fully loaded.



window.addEventListener('load', () => {
  console.log('All scripts and resources have been loaded.');
  console.log (  "mw keys are: ", Object.keys (mw));

  window.setTimeout ( () => {

  console.log ( "mw is: ", mw);
  console.log (  "mw keys are: ", Object.keys (mw));
  console.log (  "mw keys are: ", Object.getOwnPropertyNames (mw));
  console.log ( "mw hook is:", mw.hook );

  console.log ("mw.loader is: ", Object.keys (mw.loader));

}, 2000);


  let bo = $("body");
  console.log (bo);
});

console.info ("showEndpoint.js loaded");