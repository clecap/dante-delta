(() => { // begin scope


function initLangs ( ) {

  let div = document.createElement ("div");
  div.id="dante-language-bar";
  div.style = "position: absolute; top:5px; left:500px; border:1px solid blue;z-index:500;";
  let head = document.getElementById ("mw-head-base");
  head.appendChild (div);
  console.log ("lang div added");

  let removeNS = (input) => {let i = input.indexOf(':'); if (i === -1) {return input;} return input.substring( i + 1); }

  ["de","gb-eng"].forEach ( x => {
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
    console.log ("flag added: ", x);
  } );

 
}

initLangs ();

})(); // end scope


console.error ("languages.js loaded");

 