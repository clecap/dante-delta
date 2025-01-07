( function ( $, mw ) {
	'use strict';

  const TITLE = new URLSearchParams (window.location.search).get('title');

	const non_nesting = {
		'H1': 'H1',
		'H2': 'H1,H2',
		'H3': 'H1,H2,H3',
		'H4': 'H1,H2,H3,H4',
		'H5': 'H1,H2,H3,H4,H5',
		'H6': 'H1,H2,H3,H4,H5,H6',
		'H7': 'H1,H2,H3,H4,H5,H6,H7'
	};
	const hide_classes = [ 'hs-hide-H1','hs-hide-H2','hs-hide-H3','hs-hide-H4','hs-hide-H5','hs-hide-H6','hs-hide-H7' ];  
// using several hide classes down thru the hierarchy is a neat trick which allows us to maintain the hiding status in deeper hierarchy levels
// when switching the hiding status in a higher hierarchy level!


  let ALL_HIDDEN = false;      // status switch for toggeling top level via click on title

// e is the event which triggered the call
// $link is the anchor which was clicked
function hidesection (e, $link) {
  if (e) e.preventDefault();
  $link ||= $( this );

  var $editlinks = $link.parents('.mw-editsection').first();
  var $textlink  = $link.attr('class') == "hidesection-link"  ? $link : $editlinks.find('.hidesection-link');

  var $show =  $textlink.html() == $link.data('show');


  if ($show) {  $(this).closest("h1,h2,h3,h4,h5").children().first().remove();} else {$( this ).closest( "h1,h2,h3,h4,h5").prepend("<span>+</span>");}

  var $toggleClass = $show ? 'removeClass' : 'addClass';  // name of function to be used for toggeling class membership
  var $actionFunctions = $show ? 'slideDown' : 'slideUp';  // CHC added
  $textlink.text( $textlink.data ( $show ? 'hide' : 'show' ) );     // set the text to be displayed in UI action link

  // Toggle visibility
  var $header  = $link.parents('h1,h2,h3,h4,h5,h6,h7').first();
  var headtype = $header.prop('tagName');

  // include <tag> in class name, so section can be hidden by more than one link
  //  $header.nextUntil( non_nesting[headtype] )[$actionFunctions]('hs-hide-' + headtype); 
  $header.nextUntil( non_nesting[headtype] )[$toggleClass]('hs-hide-' + headtype);
}


/*
for (let i = 0; i < localStorage.length; i++) {
  const key = localStorage.key(i);
  const value = localStorage.getItem(key);
  console.log(`Key: ${key}, Value: ${value}`);
}
*/

function storeStatus () {
  let arr = []; arr.push (null);
  $('[data-section]').each( (num,ele) => { arr.push ( ele.dataset.hidden ); });
  localStorage.setItem (TITLE, JSON.stringify(arr));
}



function  setStatus () {
  let arr = localStorage.getItem (TITLE);
  arr = JSON.parse (arr);
  console.log ("DanteLinks found status: ", arr);
  arr.forEach ( (ele, idx) => { 
    if      (ele === "true" )  { doHideSection (idx); }
    // else if (ele === "false")  { doShowSection (idx); }
    else                      {}
  });
}




function doHideAll () {  // switch all top-level element to hidden
  $('[data-section]').each( (idx,ele) => {
    if ( ["H1"].includes (ele.tagName) ) { 
      if (ele.dataset.hidden != "true") {doHideSection ( ele.dataset.section );} }
  } );
  ALL_HIDDEN = true;
}


function doShowAll () {  // switch all top-level elements to visible
  $('[data-section]').each( (idx,ele) => {
    if ( ["H1"].includes (ele.tagName) ) { 
      if (ele.dataset.hidden == "true") {doShowSection ( ele.dataset.section );} }
  } );
  ALL_HIDDEN = false;
}


function doToggleAll () {
  //console.log ();
  if (ALL_HIDDEN === true) { doShowAll();} 
  else if (ALL_HIDDEN === false) { doHideAll ();}
  else {} 
}


function doShowAllLevels () { // switch all levels to visible
 $('[data-section]').each( (idx,ele) => { if (ele.dataset.hidden == "true") {doShowSection ( ele.dataset.section );} } )
}




// hide the section contents below the section heading identified by the given sectionNumber
function doHideSection ( sectionNumber ) {
  sectionNumber = parseInt (sectionNumber);
  let $header = $('[data-section="'+sectionNumber+'"]');                     // find the header according to the numbering
  $header.prepend("<span>+</span>");                                         // prepend a plus elment to this header element
  $header.attr("data-hidden", "true");
  let headtype = $header.prop('tagName');                                    // get tagname of that header
  $header.nextUntil( non_nesting[headtype] )["addClass"]('hs-hide-' + headtype);    // iterate down the hierarchy for hiding everything below
}

function doShowSection ( sectionNumber ) {
  sectionNumber = parseInt (sectionNumber);
  let $header = $('[data-section="'+sectionNumber+'"]');                     // find the header according to the numbering
  $header.closest("h1,h2,h3,h4,h5").children().first().remove();
  $header.attr("data-hidden", "false");
  let headtype = $header.prop('tagName');                                    // get tagname of that header
  $header.nextUntil( non_nesting[headtype] )["removeClass"]('hs-hide-' + headtype);    // iterate down the hierarchy for hiding everything below
}

function doToggleSection ( sectionNumber) {
  sectionNumber = parseInt (sectionNumber);
  let $header = $('[data-section="'+sectionNumber+'"]');                     // find the header according to the numbering
  if ( $header.attr("data-hidden") == "true" ) { doShowSection (sectionNumber); }  else { doHideSection (sectionNumber); }
}



// TODO: deprecate
function hideall (e) {
  // console.error ("HIDEALL");

  e.preventDefault();

  var $link = $( '.hidesection-all' );
  // Toggle text shown in the menu portion of the section links
  var $show = 0;
  if ( $link.html() == $link.data('hide') ) {$link.text( $link.data('show') );} 
  else {$link.text( $link.data('hide') ); $show = 1;}

  var $textlink = $(".hidesection-link");
//  var $imglink  = $(".hidesection-image");

  if ($show) {
			// just brute-force through this
			$('.hs-hide-H1,.hs-hide-H2,.hs-hide-H3,.hs-hide-H4,.hs-hide-H5,.hs-hide-H6,.hs-hide-H7').removeClass( hide_classes );
			$textlink.text( $textlink.data('hide') );
			// $imglink.attr( 'src', $imglink.data('hide') );

    //  $("h1").children().first().remove();

$('#mw-content-text h1').each(function() {
  $(this).children().first().remove();
});

		} else {
			$('.hidesection-link').each( function (i,el) { hidesection( undefined, $(el)) });
      $( "#mw-content-text h1" ).prepend("<span>+</span>");
		}
}


$.fn.longpress = function(longCallback, shortCallback, duration) {   // install longpress plugin for jquery
  if (typeof duration === "undefined") {duration = 500;}
  return this.each(function() {
    var $this = $(this);
    var mouse_down_time;
    var timeout;
            // mousedown or touchstart callback
    function mousedown_callback(e) {
       mouse_down_time = new Date().getTime();
       var context = $(this);
      timeout = setTimeout(function() {
        if (typeof longCallback === "function") {longCallback.call(context, e);} 
        else                                    {$.error('Callback required for long press. You provided: ' + typeof longCallback);} }, duration);
    }

    function mouseup_callback(e) {
      var press_time = new Date().getTime() - mouse_down_time;
      if (press_time < duration) {clearTimeout(timeout);
        if      (typeof shortCallback === "function")  {shortCallback.call($(this), e);} 
        else if (typeof shortCallback === "undefined")  {;} 
        else                                            {$.error('Optional callback for short press should be a function.');}
      }
    }

    function move_callback(e) {clearTimeout(timeout);}

    $this.on('mousedown',  mousedown_callback);  $this.on('mouseup',    mouseup_callback);  $this.on('mousemove',  move_callback);      // install for mouse devices
    // $this.on('touchstart', mousedown_callback);  $this.on('touchend',   mouseup_callback);  $this.on('touchmove',  move_callback);   // install for touch devices
  });
 };


mw.hook( 'wikipage.content' ).add( function () {  // as soon as the relevant content has been loaded execute this function
  $('.mw-headline').parent().each( function (num, ele) { ele.dataset.section=num+1; ele.title="Section number "+(num+1)+". Click to toggle visibility."; } );  // number the sections h_ elements
  $('.mw-headline').on ('click', (e) => { 
    doToggleSection (e.target.parentNode.dataset.section );
    storeStatus ();
 });

  $('#firstHeading >span').longpress (  (e) => { doShowAllLevels (); } , (e) => { doToggleAll (); } );   // longpress like this, NOT via .on handler

  setStatus ();

//  $('.hidesection-link').click( hidesection );
//  $('.hidesection-all').click( hideall );
//  $('.mw-headline').on('click', function () { console.log ("DanteHideSection: delegating"); $(this).next().children('.hidesection-link').trigger('click');}); // delegate clicks on section headlines to clicks on show/hide links

//  $('#firstHeading').click( hideall );  // click on the title invokes hiding all top level sections


});




























}( jQuery, mediaWiki ) );
