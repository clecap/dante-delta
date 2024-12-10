var BABEL = (() => { // begin scope


function initLangs ( ) {
  let div = document.createElement ("div");
  div.id="dante-language-bar";
  div.style = "position: absolute; top:10px; left:600px; border:1px solid blue;z-index:500; display:inline-block; min-width:10px; min-height:10px;";
  

  div.style.display="none";  // current version does not have this

let head = document.getElementById ("mw-head-base");
  head.appendChild (div);
  console.log ("lang div added");

  let removeNS = (input) => {let i = input.indexOf(':'); if (i === -1) {return input;} return input.substring( i + 1); }

  if (!window.BABEL_LANGUAGES) { console.error ("could not find babel languages"); }

  console.info (window.BABEL_LANGUAGES);

  window.BABEL_LANGUAGES.all.forEach ( x => {
    let a   = document.createElement ("a");
    a.href = "./index.php?title=Translate:" + removeNS (RLCONF["wgPageName"]);
    a.onclick = "alert (1);";

/*
wgPageName":"MediaWiki:Aboutpage","wgTitl
    
"wgCurRevisionId":1357,
"wgRevisionId":1357,
"wgArticleId":1261,

*/

/*
    let img = document.createElement ("img");
    img.src = `./dante-assets/flags/${x}.png`;
    img.title = `Translate to ${x}`;
    img.alt   = `${x}`;
    img.style = "";
*/

a.innerHTML = x;
//    a.appendChild (img);

    div.appendChild (a);
    // console.log ("flag added: ", x);
  } );

  let button = document.createElement ("button");
  button.innerHTML = "Translate";
  button.addAttribute ("data-source","DantePresentations/modules/languages.js");
  button.onclick = translate;
  div.appendChild (button);
}


function translate () {
  let endpoint = "./extensions/DantePresentations/endpoints/deeplEndpoint-title.php";

  let info = {
    "Wiki-wgUserName":         RLCONF.wgUserName, 
    "Wiki-wgTitle":            RLCONF.wgTitle,
    "Wiki-wgCurRevisionId":    RLCONF.wgCurRevisionId,
    "Wiki-wgNamespaceNumber":  RLCONF.wgNamespaceNumber
  };

  fetch(endpoint, {headers: info} )
    .then( response => { display ( `Endpoint replied: ${response.status} and ${response.statusText}  `); return response.text(); })
    .then(data => { display('Data received:'+ data); } )
    .catch(error => {display('There was a problem with the fetch operation: Endpoint sent:\n' + error);});
}


function display (text) {
  alert (text);
}


return {initLangs}     ;  // export  // TODO: really needed ???

})(); // end scope

BABEL.initLangs();

console.error ("languages.js loaded");