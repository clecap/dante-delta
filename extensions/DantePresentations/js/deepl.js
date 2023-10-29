

// wire up UI
window.deepl = {};

window.deepl.init = 
function init () {
  document.getElementById ("submit").addEventListener ("click", (e) => translate () );
}


function translate () {
  let text = document.getElementById("text").value;
  fetch('../endpoints/deeplEndpoint.php', {
    method:  'POST',
    headers: {'Content-Type': 'application/json',},
    body:    JSON.stringify(text),
  })
  .then  (response => response.text())
  .then  (data => { 
     let obj = JSON.parse(data); 
     let pp  = "<pre>" + JSON.stringify( obj, null, 2 ) + "</pre>"; // Indented 2 spaces
     //pp = pp.replaceAll (/\n/g, "<br>");

     document.getElementById("translation").innerHTML = pp;

    })
  .catch ( (error) => {console.error('Error:', error); } );
}


