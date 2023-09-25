// glue code, connecting this extension to fancytree

/*** CATAEGORY HANDLING CODE ***/

// given a category name <categ> returns a regular expression which for matching the respective category link
const buildRegex = (categ) => new RegExp ( "\\[\\[\\s*Category\\s*\\:\\s*" + categ + "\\s*\\]\\]", 'g'); 

// return the <text> but without a mention of category link <categ> ; assume links in text have blanks, not underscores
const removeTextCategory = (text, categ) =>  text.replace ( buildRegex(categ), "");

// returns the wikitext when adding category link for categ to text
const addTextCategory    = (text, categ) =>  text.trimEnd() + "\n[[Category:"+ categ+"]]";

// returns if in the page with wikitext <text> there exists a category link for category <categ>
const hasTextCategory    = (text, categ) =>  buildRegex(categ).test (text);


function promiseGetContent (title) {
  const VERBOSE = false;
  var prom = new Promise ( (resolve, reject) => {
    const params = {action: 'query',  prop: 'revisions', titles: title, rvprop:"content",  formatversion:2  };
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
    var params = {action: 'edit', title:  mw.config.get('wgPageName'), text: txt, format: 'json', summary: "Automated change: Category Edit by TreeAndMenu new generation, file: fancytree.js"};
    var api = new mw.Api();
    // console.log ("promiseStoreText: will now post");
    api.postWithToken( 'csrf', params ).done( function ( data ) {console.log( "promiseStoreText received: ", JSON.stringify(data) );  resolve (); } );    
    // console.log ("promiseStoreText; past api.postWithToken");
  });
  // console.log ("promiseStoreTet will return: ", prom);
  return prom;
}


//var contentPromise = promiseGetContent (  mw.config.get('wgPageName') );

async function addCat (cat) {
  const VERBOSE = false; 
  if (VERBOSE) {console.log ("fancytree.js: addCat: "  , cat);}
  addSmokeScreen ();
  var content = await promiseGetContent (  mw.config.get('wgPageName') );  // wgTitle  is without namespace prefix
 // var content = await contentPromise;
  var wikiContent = content.query.pages[0].revisions[0].content;    if (VERBOSE) {console.log ("addCat sees this content before editing: ", wikiContent);}
  wikiContent = addTextCategory (wikiContent, cat);                 if (VERBOSE) {console.log ("addCat will store this content after editing : " , wikiContent);}     
  await promiseStoreText (wikiContent);
  window.location.reload();
}


async function delCat (cat) {
  const VERBOSE = false;  
  if (VERBOSE) {console.log ("------------------------ delCat: "  , cat);}
  addSmokeScreen ();
  var content = await promiseGetContent (  mw.config.get('wgPageName') );  // wgTitle  is without namespace prefix
  //var content = await contentPromise;
  var wikiContent = content.query.pages[0].revisions[0].content;           // if (VERBOSE) {console.log ("delCat sees content preedit: ", wikiContent);}
  wikiContent = removeTextCategory (wikiContent, cat);                     // if (VERBOSE) {console.log ("delCat will store content: " , wikiContent);}   
  await promiseStoreText (wikiContent);
  window.location.reload();
}

async function modCat (add, del) {
  const VERBOSE = false;
  if (VERBOSE) console.log ("DanteTree: Called modCat with ", add, del);
  addSmokeScreen ();
  var content = await promiseGetContent (  mw.config.get('wgPageName') );  // wgTitle  is without namespace prefix
    if (VERBOSE) console.log ("DanteTree: Got content from: ", mw.config.get ('wgPageName'));
    if (VERBOSE) console.log ( Object.keys (content.query.pages) );
    if (VERBOSE) console.log ( Object.keys (content.query.pages[0]) );
    if (VERBOSE) console.log ( Object.keys (content.query.pages[0].revisions[0]) );

  var wikiContent = content.query.pages[0].revisions[0].content;    
  add.forEach ( ele => {wikiContent = addTextCategory (wikiContent, ele);} );
  del.forEach ( ele => {wikiContent = removeTextCategory (wikiContent, ele); } );
    if (VERBOSE) console.log ("will store", wikiContent);
  await promiseStoreText (wikiContent);
    if (VERBOSE) console.log ("will reload");
  window.location.reload();
}


// adds a smoke screen to the wiki so that no user interaction is possible any more (until the edit process is completed)
// removal of the smoke screen will happen through the reload after the edit process
function addSmokeScreen () {
  //console.log ("adding smoke screen");
  var div = document.createElement ("div");
  div.id = "smokescreen";
  Object.assign (div.style, {"background-color": "LightGrey", "opacity": 0.5, position:"absolute", top:"0px", left:"0px", width: "100vw", height:"100vh"});
  document.body.appendChild (div);
}


function addPendingDecoration () {
  var ele = document.body;
  Object.assign (ele.style, {outline:"5px solid red", "outline-offset":"-5px"});
}

////////////////////////////// TODO: we should be able to speed the category editing up by one roundtrip. IDEA: we issue the promiseGetContent call in addCat and delCat earlier, when we can and are minExpandLevel
//   when we then really add or delete a category, we just have to make sure that this promise has resolved - but the server roundtrip can in fact be completed alread

////// TODO: we must measure if this really is faster
// 
//   WHEN we have clicked a checkbox we probably should block the other checkboxes (maybe after a bit of waiting time) wait until the reload returns  -  we get race conditions if user clicks several categories (while at the sampe time the server roundtrip is executed)
// 



var catWindow = null;

// function which can be added as onclick attribute to a link and then it opens a link in a popup or at a gven place
// used in the tree menu in the 
window.openAsPopup = function (event) {
  if (event.shiftKey) {
    event.preventDefault();
    var obj = getPersistCatWindow ();     // get size and position information, if it was persisted, get the persisted info, else get sane defaults
    catWindow = window.open (event.target.href, "newWin", "width="+obj.width+", height="+obj.height);
    catWindow.moveTo (obj.left, obj.top);
  }
};


// TODO: must be called from somewhere via onresize and onfocus and similar stuff 
// persist size and position of the opened category window 
window.persistCatWindow = function persistCatWindow (e) {
  if (e) {e.preventDefault();}  // when called as event handler (could also be called by a javascript: URL)
  var win = null;     // handle to the window whose size and position we will persist
  if (window.name == "newWin") { // looks like we are the category window, we are open and the user clicked the command in the open category window
    win = window;                // use us to persist
  }
  else {  // looks like we are the main window
    if (!catWindow || catWindow.closed) {console.log ("ignoring persistCatwindow from Mediawiki TreeAndMenu as there is no current cat window or it is closed"); return;} // do we have a reasonable cat window?
    win = catWindow;             
  }

  var width  = win.outerWidth, height = win.outerHeight, left   = win.screenX, top    = win.screenY;
  localStorage.setItem ("catWidth",  width); localStorage.setItem ("catHeight", height); localStorage.setItem ("catLeft",   left); localStorage.setItem ("catTop",    top);
  //console.log (`TreeAndMenu:fancytrees.js:persistCatWindow persisted ${width} and ${height} and ${left} and ${top}`);
};

function getPersistCatWindow () {
  let verbose = false;
  var width  = localStorage.getItem ("catWidth");  width  = parseInt (width);  if (verbose) {console.log ("fancytree.js: persisted cat window at w", width);  }  if ( isNaN (width)  )  {width = window.screen.availWidth/5;}
  var height = localStorage.getItem ("catHeight"); height = parseInt (height); if (verbose) {console.log ("fancytree.js: persisted cat window at h", height); }  if ( isNaN (height) )  {height = window.screen.availHeight;}
  var left   = localStorage.getItem ("catLeft");   left   = parseInt (left);   if (verbose) {console.log ("fancytree.js: persisted cat window at l", left);   }  if ( isNaN (left)   )  {left= 0;}
  var top    = localStorage.getItem ("catTop");    top    = parseInt (top);    if (verbose) {console.log ("fancytree.js: persisted cat window at t", top);    }  if ( isNaN (top )   )  {top = 0;}
  return {width, height, left, top};
}


function openCategories (tree, arr) {  // open and select all nodes in the tree with names in array arr; CAVE: tree not fully expanded; CAVE: persistence may keep some state
  // console.log ("will now open categories");
  function visi (node) {
     for (var i = 0; i < arr.length; i++) {
       if ( node.title.indexOf ( ">" + arr[i] + "<" ) != -1 ) {
          // console.log ("++++++++++ selecting " + node.title);
          node.setSelected (true); /* node.setExpanded(true); */  
         var run = node.parent; do {run.setExpanded (true);} while ( run = run.parent);  // expand all parents of the node, to make selection visible
       break;}
     }
  }
  tree.visit ( node => {node.setSelected (false);} );  // first, clear all selections (counteracting selection persistence) ////////////////////// IS THIS THE BEST STRATEGY ???????  do we need this ??????
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


////////////////// TODO: we should allow several edits of a category before dashing off into a reload since we want to do faster editing of categories.  BUT: how do we properly viauslize this then ?????


/** Several edits:

  When we do a simple click, then we edit the category immediately and during the edit prevent additional interaction with a smoke screen
  When we do a shift-click,  then we do not invoke the edit mechanism but add a red outline

*/

// instrument the checkboxes of the category tree
function instrumentCatCheckBoxes () {
  // console.log ("called instrumentCatCheckBoxes");
  const VERBOSE = false;
  if ( !RLCONF.wgUserName || document.body.classList.contains("action-edit") ) {  // when not logged on or when in edit view (probably of a page part only) we do not want this 
    $("span.fancytree-checkbox").not(".fancytree-is-instrumented").each( (idx, val) =>  {
       val.style.opacity = "0.3";
       val.addEventListener ("click", (e) => {                                  
          e.preventDefault(); e.stopPropagation(); 
         // alert ("Please do not edit categories in the Edit mode of a page. Do so in Read mode. There is a good reason for this design decision.");
     });
    }).addClass("fancytree-is-instrumented"); 
  }

  if ( document.body.classList.contains("action-view") ) {  // on view pages
    let addCats = new Set();  // categories we should add
    let delCats = new Set();  // categoires we should delete
    $("span.fancytree-checkbox").not(".fancytree-is-instrumented").each( (idx, val) =>  {
      val.addEventListener ("click", (e) => {
        let flag =  e.target.parentNode.classList.contains("fancytree-selected");
        let cat =   e.target.nextSibling.textContent; 
        //console.log ("before adjustment: flag: ", flag, "cat", cat, "will add:", addCats, "will remove:", delCats);
        if (flag) { delCats.add (cat); addCats.delete (cat) } else { delCats.delete (cat); addCats.add (cat); }
        // console.log ("after adjustment: will add: ", addCats, " and will remove: ", delCats);
        if (e.shiftKey) {addPendingDecoration();} else { modCat (addCats, delCats);}
    });
   }).addClass("fancytree-is-instrumented");
 }
}


// CAVE: We must not have a toggleEffect of smooth opening active in the moment of the tree construction, for if we have, the mechanism of persisting the tree opening status also makes smooth
//       openings which leads to a flicker of the tree during the opening. THUS, we register all trees in treeRegister and apply such options later

var treeRegister = [];


// This can be called again later and any unprepared trees and menus will get prepared - this was done so that trees and menus can work when received via Ajax  such as in a live preview
window.prepareTAMX = function prepareTAMX (id) {
  const VERBOSE = false;
  if (VERBOSE) {console.error ("TreeAndMenu:fancytree.js: prepareTAMX each was called for id=", id);}
  var myTree;
  
  $((id ? "#" + id : "") + ' .fancytree.todo').each(function(idx, ele) {   // convert all nodes with .fancytree and .todo into tree nodes
    ele.classList.remove ("todo");                          // node has been dealt with, remove the todo marker to prevent any double invocation
    var opts = {};                                          // object of options to be used when building the tree

    var parseOpts;                                          // now parse additional options we may have received by way of the mediawiki parse function and place them into parseOpts
    var div = $('div.opts', $(ele));                       // inside of our context, searh for a div element marked with class opts
    if (div.length > 0) {                                   // if we found one
      parseOpts = $.parseJSON(div.text());                  // parse its text content as JSON
      if (VERBOSE) {console.log ("************** found an option element ", div, " option parse produced: ", JSON.stringify(parseOpts));}
      // div.remove();                                         // and discard this div element
    }
    Object.assign (opts, parseOpts);                        // copy the found parsed options into the options object
    
    // ensure proper .extensions array, which contains at least mediawiki
    if (typeof opts.extensions == "undefined")   {opts.extensions = ['mediawiki'];}
    if (!opts.extensions.includes ('mediawiki')) {opts.extensions.push ('mediawiki');}
    
    if (id == "p-catus") {opts['extensions'] = ['persist'];  opts['checkbox'] = true; opts['icons'] = false;} 

    opts.toggleEffect = false; // as above: we MUST not have toggleEffect active in the current moment
    if (opts.icons=="false") {opts.icons=false;}  // the mediawiki parse function delivers "false" in string form and tree expects it in boolean form, so we must adjust here  // CAVE: newer versions of fancytree uses .icon instead of .icons
    opts.activeVisible = true;                    // option ensures that if a lower tree node is selected programmatically, then all higher nodes are visible
    // opts.init = (ev, data) => {console.log ("tree is initialized");}
    opts.dblclick = (ev, data) => { ev.preventDefault(); ev.stopPropagation();}  // disable the standard which is provided by fanctree
    
    myTree =  $(ele).fancytree(opts);  // add the tree which we just constructed to the tree Registry, together with some parameter for options which we must apply later 
    treeRegister.push ( {tree: myTree , options: { "toggleEffect": { effect: "blind", options: {direction: "vertical", scale: "box"}, duration: 200 } } } ); ///////////////////////////////////////////  ADJUST
  });


  $('.suckerfish.todo').each(function() {  // Prepare menus (add even and odd classes)
    $(this).removeClass('todo');
    $('li', this).each(function() { var li = $(this); li.addClass((li.index() & 1) ? 'odd' : 'even');  });
  });

  if (id == "p-catus") {
    instrumentCatCheckBoxes();
    $("#p-catus").click( (e) => {instrumentCatCheckBoxes(); });  // must reinstrument on every click since tree-nodes built only on demand
  }   // only for the category tree do an instrumenation of the check boxes

  return myTree;
};


function adjustToPersistence (name) {  // get portal area show/hide status from store and adjust it accordingly; called for all portlets
  // console.log ("fancytree.js: adjustToPresistence: called at name=", name);
  name = name + "-label";
  var status = localStorage.getItem (name);
  //console.log ("fanytree.js: adjusting to persistence, found: " + name, status);
  if (status === null) { localStorage.removeItem (name);}  // remove stray indicators

  if (status == "close") { 
    // console.log ("fancytree.js: closing: ", name);
    $("#switcher-"+name).addClass("img-negative").parent().parent().siblings(":first").hide(); }
  else                   { 
    // console.log ("fancytree.js: opening: ", name);
    $("#switcher-"+name).removeClass("img-negative").parent().parent().siblings(":first").show(); }
} 

const installSidebarScroll = () => { $("#mw-panel").scroll ( () => {localStorage.setItem ("panelScroll", $("#mw-panel").scrollTop() );} ) }; // install the feature that persists the scrolling status of the sidebar to localStorage

const installPortletToggle = () => {
  const toggleSidebar = (name) => {    // function which toggles the visibility of an entire area; called with string such as   switcher-p-catus-label
      // console.log ("fancytree.js: toggleSidebar function called on element with id=", name);
      var shortName = name.substring (9);    // removes the prefix "switcher-"  and delivers  p-catus-label
      var flag = localStorage.getItem (shortName);
      var newFlag = (flag == "open" ? "close" : "open");       // console.log ("toggleSidebar function setting name=", name, " to ",  newFlag);
      localStorage.setItem (shortName, newFlag);                                                                                                          // make area visibility persistant across reloads
      // console.log ("fancytree.js: setting ", shortName, "to ", flag);


      $("#"+name).toggleClass ("img-negative");    // toggles the plus / minus icon of the opener   
      // console.log ("Shortened name: ", name );    
      $("#"+shortName).next().slideToggle (200);        // toggles the area itself
  };

  $(".img-switcher").click(  (e) => {toggleSidebar (e.target.id); e.stopPropagation();});  // clicks on the plus/minus sign toggles the visibility of a sidebar portlet
  $(".img-switcher").parents(".vector-menu-heading").click(  (e) => {                      // clicks on the label of the sidebar portlet toggle the visibility of the sidebar portlet
      var name = e.currentTarget.id; 
      toggleSidebar ("switcher-"+name); e.stopPropagation();
  }); 
  
};


// a click on a fancytree-icon is delegated to the .fancytree-expander which proceeds it - if the parent is fancytree-has-children. Thus, a click on a folder icon also opens that 
const installPropagate = () => {
  const VERBOSE = false;
  $(".fancytree-icon").each( (idx,ele) => {
    if (ele.parentNode.classList.contains ("fancytree-has-children")) {
      ele.classList.add ("clickable-icon");  // and show a clickable cursor
      $(ele).click( () => { if (VERBOSE) {console.log ("saw a folder click");} $(ele.previousElementSibling).click(); });
    }       
  });  
  
  $(".fancytree-title").each ( (idx,ele) => {                                 // all title lines in trees
    if (ele.parentNode.classList.contains ("fancytree-has-children")) {       // if the parentnode has children, i.e. the title belongs to a non-leaf node
      if ($(ele).children('a').length) {                                      // and if the title contains a clickable anchor
        
      } else {  // if it does not contain an anchor element
        $(ele).click ( () => { console.log ("saw a click on title without anchor element"); $(ele.previousElementSibling).click();   });
      }
    }
    
  });
};


const logoPatcher = () => { 
  const logo = document.getElementById ("p-logo");   
  logo.innerHTML = ""; 
  var span = document.createElement ('span');
  span.innerHTML = mw.config.get('wgSiteName'); 
  span.className='personal-wiki-name-logo'; span.setAttribute ('title', 'Name of the specific dantewiki to distinguish multiple variants');
  logo.appendChild (span);
};



// window.setZeroTimeout polyfill
(function() {
  var timeouts = [];
  var messageName = "zero-timeout-message";
  function setZeroTimeout(fn) {timeouts.push(fn); window.postMessage(messageName, "*");}
  function handleMessage(event) {
    if (event.source == window && event.data == messageName) {
      event.stopPropagation();
      if (timeouts.length > 0) {var fn = timeouts.shift();fn();}
    }
  }
  window.addEventListener("message", handleMessage, true);
  window.setZeroTimeout = setZeroTimeout;
})();



// TODO: not yet working
// patch headers or the portlets in the sidebar so that they show an open/close icon next to their label
const patchPortletHeaders = () => {
  $("#mw-panel").find(".vector-menu-heading-label").each ( (idx, ele) =>   {  // was: only label
    // console.log ("TreeAndMenu: patchPortletHeaders: ", ele.parentNode.id);  
    $(ele).prepend ("<span class='img-switcher' id='switcher-"+ele.parentNode.id+"'></span>")  }  
  );      
};


// there are some links which need a reference to the current page in their reference. Mediawiki thus generates them dynamically in their TOOLBOX mechanism. We want to use them 
// in any place but in this case we have to patch them in. This is done in below function. The function is not time crtical and should be called after the page has been rendered as it does not effect the rendering
const patchMagicLinks = () => {
  /*
  $('a[href*="/index.php/Special:MagicWhatLinksHere"]').attr(        "href", "/index.php/Special:WhatLinksHere/" + mw.config.get ('wgPageName'));
  $('a[href="/index.php/MagicSpecial:RecentChangesLinked"]').attr(  "href", "/index.php/Special:RecentChangesLinked/" + mw.config.get ('wgPageName'));
  $('a[href="/index.php/MagicSpecial:PermanentLink"]').attr(        "href", "/index.php?title=" + mw.config.get ('wgPageName') + "&oldid=" + mw.config.get ("wgArticleId") );  
  $('a[href="/index.php/MagicSpecial:PageInformation"]').attr(      "href", "/index.php?title=" + mw.config.get ('wgPageName') + "&action=info" );  
  $('a[href="/index.php/MagicSpecial:CiteThisPage"]').attr(         "href", "/index.php?title=" + mw.config.get ('wgPageName') + "&page=Manual%3AInterface%2FJavaScript&id="+mw.config.get("wgCurRevisionId")+"&wpFormIdentifier=titleform" );    
*/
};


window.initializeCatTree = () => {
  // provide name to the sidebar tree area catus, since we cannot use "Categories", as this already has a special use in the portal system of Mediawiki and is ignored it we use it 
  const elem = document.getElementById ("p-catus-label"); 
  if (elem) {elem.innerHTML      = "<span data-fixi class='vector-menu-heading-label'>Categories</span>";}    // patch sidebar header name for catus
  
  const catTree = prepareTAMX ("p-catus");
  if (catTree) { 
    // console.log ("initializeCatTree: will determine categories");
    var arrs        = mw.config.get('wgCategories');              // get categories of the current page (here we get them without underscores)
    // console.log ("initializeCatTree: got categories:", arrs);
    openCategories( catTree.fancytree("getTree"), arrs);          // open and mark those categories which are realized by that page
    catTree.fancytree("option", "toggleEffect", { effect: "blind", options: {direction: "vertical", scale: "box"}, duration: 200 });  // toggle effect must be added after prepareTAMX has perpared the tree or the duration delays setting up the persistence of the tree
    
    instrumentCatCheckBoxes();   // ????????????????????????????????? TODO: THIS is the right place for instrumenting the cat boxes - maybe we do not need the other calls !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    document.getElementById ("sidebar-cattree").style.display="block";   // make categories portlet visible
  }
};


var sidebarIsPatched = false;  // used to make patchSidebar idempotent

function patchSidebar () {
  const VERBOSE = false;    if (VERBOSE) {console.error ("TreeAndMenu: doTreeFinalizer called");}
  if (sidebarIsPatched) { if (VERBOSE) {console.info ("fancytree.js: sidebar is already patched");}    return;}       // only do it once, may be called several times - function made idempotent by this

  // logoPatcher ();        // not used currently

  initializeCatTree ();     // must be done before we show the sidebar (involves tree modifications) and before we patchPortletHeaders
  patchPortletHeaders();    // patch headers or the portlets in the sidebar so that they show an open/close icon next to their label
  prepareTAMX ();           // convert the invisible data structure prepared for fancytree into a true tree
 
  $("#mw-panel").show();                                                      // only now show the sidepanel (prevent flicker during build up)
  $("#mw-panel").scrollTop( localStorage.getItem ("panelScroll") ) ;          // adjust the sidepanel to the persisted scroll position - MUST be done AFTER making it visible !
  $("#mw-panel .mw-portlet").each ( (idx, ele) => { adjustToPersistence (ele.id);});    // ensures that all side-bar portlets adjust their own open/hide state according to what is made persistent with localStorage

  sidebarIsPatched = true;
  
  window.setZeroTimeout ( () => {  // let the thread go to allow the renderer to kick in, only then complete stuff without effect on the layout

    // Implement a smooth blinding effect inside of every individual tree (of the tree, not of the container, which also animates its hiding)
   /// CAVE: The toggleEffect *MUST* be activated only after the tree construction; otherwise applying the persisted tree status (opening nodes) is delayed by the duration given here - which leads to klicker !!
    treeRegister.forEach ( item => { for (var key in item.options) {item.tree.fancytree ("option", key, item.options[key]);}  }  );
    
    installSidebarScroll();
    installPortletToggle ();
    installPropagate();
    patchMagicLinks ();
  });
};



////////////////////////////  DO tree finalization as early as possible - but we cannot be sure that the script is loaded and all trees are parsed at the same time so we currently do it twice.

// the load sequence due to mediawiki js asynchronous loading is not completely clear; $(document).ready comes quite late and executing it here directly may be too early: do it twice and ensure the function i idempotent
patchSidebar ();
$(document).ready( patchSidebar );
