//alert ("ext.DanteBackup.specialpage.js loaded");


var siteName = mw.config.get('wgSiteName');

// alert (siteName);

function timestamp () {
  const d = new Date();
  return ( d.getFullYear().toString() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0") + "-at-" + String(d.getHours()).padStart(2, "0") + "-" + String(d.getMinutes()).padStart(2, "0")
  );
}

// return a string describing the source selection
function getSrces () {
  return ["nopages", "listed", "category", "categories", "all"].map (
    txt => {
      let ele = document.getElementById ("mw-input-srces-" + txt);
      return (ele.checked ? txt : "" )
    }
   ).join('');
}


function getFiles () {
  return ["nofiles", "metadata", "separate", "include"].map (
    txt => {
      let ele = document.getElementById ("mw-input-files-" + txt);
      return (ele.checked ? txt : "" )
    }
   ).join('');
}


// return a string describing the source selection
function getSrcFeatures () {
  return ["current", "allrevisions"].map (
    txt => {
      let ele = document.getElementById ("mw-input-srcFeatures-" + txt);
      return (ele.checked ? txt : "" )
    }
   ).join('');
}

function getSuffix () {
  let compressed = document.getElementById ("mw-input-compressed").checked;
  let encrypted  = document.getElementById ("mw-input-compressed").checked;
  return (compressed ? ".gz" : "") + (encrypted ? ".aes"  : "");
}

function wireup () {
  ["nopages", "listed", "category", "categories", "all"].forEach ( txt => document.getElementById ("mw-input-srces-" + txt).addEventListener ('change', initialize));
  ["current", "allrevisions"].forEach ( txt => document.getElementById ("mw-input-srcFeatures-" + txt).addEventListener ('change', initialize));
  ["mw-input-compressed",  "mw-input-encrypted"].forEach ( txt => document.getElementById (txt).addEventListener ('change', initialize) );
  ["nofiles", "metadata", "separate", "include"].forEach ( txt => document.getElementById ("mw-input-files-" + txt).addEventListener ('change', initialize));
  document.getElementById ("mw-input-tag").addEventListener ("input", initialize );

  // prevent automatic submit on enter in the input field
 // document.getElementById ("mw-input-tag").addEventListener ('keydown', (e) => {
 //   if (e.key === 'Enter' || e.keyCode === 13) {e.preventDefault();}
 // });

}


function initialize () {
  console.error ("running initialize");
  let eleTag = document.getElementById ("mw-input-tag");   
  let eleFinalName = document.getElementById ("mw-input-archiveName");
  let nodb = document.getElementById ("mw-input-db-nodb").checked;
  let db   = document.getElementById ("mw-input-db-db").checked;

  let separate   = document.getElementById ("mw-input-files-separate").checked;
  let nofiles    = document.getElementById ("mw-input-files-nofiles").checked;
  let metadata   = document.getElementById ("mw-input-files-metadata").checked;

  eleFinalName.value = siteName + "-" + eleTag.value + "-" +timestamp () + "-" + getSrces() + "-" + getSrcFeatures() + "-" + getFiles() + ".xml" + getSuffix();
  let eleDbName = document.getElementById ("mw-input-dbName");
  eleDbName.value = siteName + "-" + eleTag.value  + "-" + timestamp () + ".sql" + getSuffix();
  let eletarName = document.getElementById ("mw-input-tarName");
  eletarName.value = siteName + "-" + eleTag.value  + "-" + timestamp ();

  if (nodb) {eleDbName.value = " *** NO DATABASE DUMP DONE *** ";}

  if (separate) {eletarName.value = siteName + "-" + timestamp () + ".tar" + getSuffix();}
   else {eletarName.value = " *** NO FILE ARCHIVE GENERATED *** ";}

  


}

wireup();
initialize ();

