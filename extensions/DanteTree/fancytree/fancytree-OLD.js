// glue code to fancytree


function buildRegex (categ) {
  var regex = new RegExp ( "\\[\\[\\s*Category\\s*\\:\\s*" + categ + "\\s*\\]\\]", 'g');
  console.log ("built regex, which is: ", regex);
  return regex;
}

// return the <text> but without a mention of category link <categ> ; assume links in text have blanks, not underscores
function removeTextCategory (text, categ) {
  //console.log ("removeTextCategory: removing: ", categ);
  return text.replace ( buildRegex(categ), ""); }

function addTextCategory    (text, categ) { 
  //console.log ("addTextCategory: adding: ", categ);
  return text.trimEnd() + "\n[[Category:"+ categ+"]]";}

function hasTextCategory    (text, categ) { return buildRegex(categ).test (text) }


var catWindow = null;

// function offered to open link in a popup once
window.openAsPopup = function (event) {
  event.preventDefault();
  var obj = getPersistCatWindow ();     // get size and position information, if it was persisted, get the persisted info, else get sane defaults
  catWindow = window.open (event.target.href, "newWin", "width="+obj.width+", height="+obj.height);
  catWindow.moveTo (obj.left, obj.top);
}

// persist size and position of the opened category window 
function persistCatWindow (e) {
  e.preventDefault();
  var win = null;     // handle to the window whose size and position we will persist
  if (window.name == "newWin") { // looks like we are the category window, we are open and the user clicked the command in the open category window
    win = window;                // use us to persist
  }
  else {  // looks like we are the main window
    if (!catWindow || catWindow.closed) {console.log ("ignoring persistCatwindow from Mediawiki TreeAndMenu as there is no current cat window or it is closed"); return;} // do we have a reasonable cat window?
    win = catWindow;             
  }

  var width  = win.outerWidth;
  var height = win.outerHeight;
  var left   = win.screenX;
  var top    = win.screenY;
  localStorage.setItem ("catWidth",  width);
  localStorage.setItem ("catHeight", height);
  localStorage.setItem ("catLeft",   left);
  localStorage.setItem ("catTop",    top);
  console.log (`TreeAndMenu:fancytrees.js:persistCatWindow persisted ${width} and ${height} and ${left} and ${top}`);
}

function getPersistCatWindow () {
  var width  = localStorage.getItem ("catWidth");  width  = parseInt (width);  console.log ("w", width); if ( isNaN (width) )   {width=window.screen.availWidth/5;}
  var height = localStorage.getItem ("catHeight"); height = parseInt (height); console.log ("h", height); if ( isNaN (height) )  {height = window.screen.availHeight;}
  var left   = localStorage.getItem ("catLeft");   left   = parseInt (left);   console.log ("l", left); if ( isNaN (left) )    {left= 0;}
  var top    = localStorage.getItem ("catTop");    top    = parseInt (top);   console.log ("t", top);  if ( isNaN (top ) )    {top = 0;}
  return {width, height, left, top};
}







function openCategories (tree, arr) {  // open and select all nodes in the tree with names in array arr; CAVE: tree not fully expanded; CAVE: persistence may keep some state
  function visi (node) {
     for (var i = 0; i < arr.length; i++) {
       if ( node.title.indexOf ( ">" + arr[i] + "<" ) != -1 ) {
          // console.log ("++++++++++ selecting " + node.title);
          node.setSelected (true); /* node.setExpanded(true); */  
         var run = node.parent; do {run.setExpanded (true);} while ( run = run.parent);  // expand all parents of the node, to make selection visible
       break;}
     }
  }
  tree.visit ( node => {node.setSelected (false);} );  // first, clear all selections (counteracting selection persistence)
  tree.visit (visi);
}



// return a promise which resolves into an array of all categories the page with title <title> has.
// GOOD: we do not need this, since mw.config.get('wgCategories'); already has the categories of the page
function promiseGetCat (title) {
  const VERBOSE = false;
  var prom = new Promise ( (resolve, reject) => {
    const params = {action: 'query', format: 'json', prop: 'categories', titles: title};
    var api = new mw.Api();
    api.get( params ).done( function ( data ) {
      if (VERBOSE) console.log ("promiseGetCat: ", data);
      var pages = data.query.pages;
      var p;
      var numPages =  Object.keys (pages).length;
      if (VERBOSE) console.log ("promiseGetCat: found pages: " , numPages);
      if (numPages > 1) {reject ("More than one page found: " + numPages);}
//      if (numPages > 1) {console.error ("Problem: found more than one page" ); alert ("cannot");return;}
      var cats = [];
      for (p in pages) {
        if (VERBOSE) console.log ("page id " , p, " has categories ", pages[p].categories);
        if (pages[p].categories) {
          pages[p].categories.forEach( function ( cat ) { console.log( "Has category: " + cat.title ); cats.push (cat.title); } ); }  }
       if (VERBOSE) console.log ("found cats: ", cats);    
      resolve (cats );  
    });
  });  
  return prom;
}
 
function promiseGetContent (title) {
  const VERBOSE = false;
  var prom = new Promise ( (resolve, reject) => {
    const params = {action: 'query',  prop: 'revisions', titles: title,     rvslot: "*", rvprop:"content",  formatversion:2  };
    var api = new mw.Api();
    api.get( params ).done( function ( data ) {
      if (VERBOSE) console.log ("GOT: ", data);
      resolve (data);
    });
    
  });
  return prom;
}


function promiseStoreText (txt) {
  var prom = new Promise ( (resolve, reject) => {
    var params = {action: 'edit', title:  mw.config.get('wgPageName'), text: txt, format: 'json', summary: "Automated change: Category Edit by TreeAndMenu new generation, file: fanytree.js"};
    var api = new mw.Api();
    api.postWithToken( 'csrf', params ).done( function ( data ) {console.log( "promiseStoreText received: ", JSON.stringify(data) );  resolve (); } );    
  });
  return prom;
}



async function addCat (cat) {
  const VERBOSE = false;
  if (VERBOSE) {console.log ("------------------------ addCat: "  , cat);}
  var content = await promiseGetContent (  mw.config.get('wgPageName') );  // wgTitle  is without namespace prefix

  var wikiContent = content.query.pages[0].revisions[0].content;  
  if (VERBOSE) {console.log ("addCat sees content preedit: " , wikiContent);}
  wikiContent = addTextCategory (wikiContent, cat);
  if (VERBOSE) {console.log ("addCat will store content: " , wikiContent);}     
  await promiseStoreText (wikiContent);
  window.location.reload();
}

async function delCat (cat) {
  const VERBOSE = false;
  if (VERBOSE) {console.log ("------------------------ delCat: "  , cat);}
  var content = await promiseGetContent (  mw.config.get('wgPageName') );  // wgTitle  is without namespace prefix
  var wikiContent = content.query.pages[0].revisions[0].content;
  //console.log ("delCat sees content preedit: ", wikiContent);   
  wikiContent = removeTextCategory (wikiContent, cat);
  //console.log ("delCat will store content: " , wikiContent);       
  await promiseStoreText (wikiContent);
  window.location.reload();
}

// ??????????????????   need to take care of situations where for whatever reason we get multiple category links for the same category



// instrument the checkboxes of the category tree
function instrumentCatCheckBoxes () {
  const VERBOSE = false;
  if ( document.body.classList.contains("action-edit") ) {  // on edit pages // TODO: NOT REALLY WORKING WELL - RATHER DISABLE THIS   TODO:    ?????????????????  not good idea when only editing part of the page !
    //// TODO: disable clicks on the tree checkboxes 
    $("span.fancytree-checkbox").not(".fancytree-is-instrumented").each( (idx, val) =>  {
       val.addEventListener ("click", (e) => { 
          e.preventDefault(); e.stopPropagation(); 
          alert ("Please do not edit categories in the Edit mode of a page. Do so in Read mode. There is a good reason for this design decision.");
     });
    }).addClass("fancytree-is-instrumented"); 
  }

  if ( document.body.classList.contains("action-view") ) {  // on view pages
   $("span.fancytree-checkbox").not(".fancytree-is-instrumented").each( (idx, val) =>  {
      val.addEventListener ("click", (e) => { 
        var flag =  e.target.parentNode.classList.contains("fancytree-selected")
        var cat =   e.target.nextSibling.textContent; 
        console.log ("flag: ", flag, "cat", cat);
        if (flag) { delCat (cat);} else { addCat (cat);}  // note: in ths handler the further processing by the browser is done later - so we need a reverse logic here now
    });
   }).addClass("fancytree-is-instrumented");
 }
}



// This can be called again later and any unprepared trees and menus will get prepared - this was done so that trees and menus can work when received via Ajax  such as in a live preview
window.prepareTAMX = function prepareTAMX (id) {
  const VERBOSE = true;
  if (VERBOSE) {console.info ("TreeAndMenu:fancytree.js: prepareTAMX each was called for id=", id);}
  var myTree;
  
  $((id ? "#" + id: "") + ' .fancytree.todo').each(function() {   // convert all nodes with .fancytree and .todo into tree nodes
    var domOpts = this.dataset.treeoption;
    if (domOpts) {domOpts = JSON.parse (domOpts); if (VERBOSE) {console.log ("TreeAndMenu:fancytree.js: domOpts", domOpts);}  } 
    else {if (VERBOSE) {console.log ("TreeAndMenu:fancytree.js: no domOpts found");} }
    
    $(this).removeClass('todo');    // nodes has been dealt with, remove todo marker

    var div = $('div.opts', $(this));
    var opts = {};
    if (div.length > 0) {opts = $.parseJSON(div.text()); div.remove();}
 
    if (id == "p-catus" || id == "p-startus") {opts['extensions'] = ['persist', 'mediawiki'];} else {opts['extensions'] = ['mediawiki'];}

    //  opts.icons = false;  // TODO: NOTE: in newer versions of fancytree I guess this option is called .icon without trailing s  // may want this !!
    opts.toggleEffect = false;  // CHC added, to have a quick opening on display of the page and no sliding opening of the open parts
     //  opts.init =  function(event, data, flag) {  console.error ("INIT FIRED ");  };
    opts.activeVisible = true;  // this option ensures that if a lower tree node is selected, then all higher nodes are visible

    if (domOpts) {Object.assign (opts, domOpts);}
    myTree =  $(this).fancytree(opts);
  });

  $('.suckerfish.todo').each(function() {  // Prepare menus (add even and odd classes)
    $(this).removeClass('todo');
    $('li', this).each(function() { var li = $(this); li.addClass((li.index() & 1) ? 'odd' : 'even');  });
  });

  if (id == "p-catus") {
    instrumentCatCheckBoxes();
    $("#p-catus").click( (e) => {
      instrumentCatCheckBoxes(); });  // must reinstrument on every click since tree-nodes built only on demand
  }   // only for the category tree do an instrumenation of the check boxes

  return myTree;
};


function adjustToPersistence (name) {
    var status = localStorage.getItem (name);
    //console.error ("retrieved for " + name, status);
    if (status == "close") { $("#switcher-"+name+"-label").addClass("img-negative").parent().nextAll(".body:first").hide(); }
    else {$("#switcher-"+name+"-label").removeClass("img-negative").parent().nextAll(".body:first").show();}
}




$(document).ready(function() {
  const VERBOSE = true;  
  if (VERBOSE) {console.log ("TreeAndMenu: ready function in fancytress.js called");}

  // if we have in the sidebar category tool a link with this title
  $('li > a:contains("Persist Cat Window")').click ( (e) => { persistCatWindow(e) });


  document.getElementById ("p-startus-label").innerHTML = "Areas";         // patch sidebar header name for areas
  document.getElementById ("p-catus-label").innerHTML   = "Categories";    // patch sidebar header name for categories

  // implement toggeling for all sidebar headers and the space below them
  // the h3 elements below #mw-panel are the labels of Areas, Categories and more
  
  $("#mw-panel").find("h3").each( (idx, ele) =>   { $(ele).prepend ("<span class='img-switcher' id='switcher-"+ele.id+"'></span>")  }  );
  
 // $("#mw-panel").find("h3").prepend("<span class='img-switcher'></span>");
  $(".img-switcher").click(  (e) => {
    console.error ("togg: ", e.target.parentNode.parentNode.id);  // this is the name of the area
    var name = e.target.parentNode.parentNode.id;
    var flag = localStorage.getItem (name);
    var newFlag = (flag == "open" ? "close" : "open");
    console.error ("Setting ", name, newFlag);
    localStorage.setItem (name, newFlag);
    //console.error ("core point ", e.target.id);
    $("#"+e.target.id).toggleClass("img-negative").parent().nextAll(".body:first").slideToggle(200);
   });
  $(".img-switcher").parent().click(  (e) => {  $(e.target).children("span").click(); });  // clicks on the label are delegated to the plus/minus sign

  adjustToPersistence ("p-startus"); adjustToPersistence ("p-catus"); adjustToPersistence ("p-navigation"); adjustToPersistence ("p-startus");adjustToPersistence ("p-Cats"); adjustToPersistence ("p-tb");

  var startusTree = prepareTAMX ("p-startus");    // obtain the raw tree handle
  var catusTree   = prepareTAMX ("p-catus");
  var arrs        = mw.config.get('wgCategories');  // get categories. here we get them without underscore

  openCategories(catusTree.fancytree("getTree"), arrs);
  instrumentCatCheckBoxes();   // ????????????????????????????????? TODO: THIS is the right place for instrumenting the cat boxes - maybe we do not need the other calls !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  
  document.getElementById ("sidebar-cattree").style.display="block";   // make the area below "Categories" visible
  document.getElementById ("p-startus-label").style.display="block";

  // switch the trees from no-effet behavior (necessary for immediate, non-animate display) to animated effect (necessary for user feedback)
  startusTree.fancytree("option", "toggleEffect", { effect: "blind", options: {direction: "vertical", scale: "box"}, duration: 200 });
  catusTree.fancytree("option", "toggleEffect",   { effect: "blind", options: {direction: "vertical", scale: "box"}, duration: 200 });

  prepareTAMX ();  // take care of tree nodes not yet converted

  $(".removeTargetClass").find('a[target]').removeAttr("target");
  $(".blankTargetClass").find('a').attr("target","_blank");
  $(".showReferrer").find('a[rel]').each( (idx,ele) => {
    var newAtt = ele.getAttribute("rel").split(" ").filter( x => (x != 'noreferrer') ).join(" ");
    ele.setAttribute("rel", newAtt)
  } );


});


// preload some icons NOW so they will not flicker/fault in later
const path = mw.config.get('fancytree_path');  (new Image()).src = path + '/loading.gif';  (new Image()).src = path + '/icons.gif';
