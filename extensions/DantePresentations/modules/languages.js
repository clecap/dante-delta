(() => { // begin scope


function initLang ( lang ) {
  let img = document.createElement ("img");
  img.src = "/dante-assets/flags/${lang}.png"
  img.style = "position:fixed; top:5px; left:500px;";
  let head = document.getElementById ("mw-head-base");
  head.appendChild (img);
}

["de","gb-eng"].forEach ( x => initLang (x) );

})(); // end scope


console.error ("languages.js loaded");

