( function ( $, mw ) {
	'use strict';

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

// console.error ("INISIDE");

function hidesection (e, $link) {
  if (e) e.preventDefault();
  $link ||= $( this );

  var $editlinks = $link.parents('.mw-editsection').first();
  var $textlink  = $link.attr('class') == "hidesection-link"  ? $link : $editlinks.find('.hidesection-link');
  var $imglink  = $link.attr('class') == "hidesection-image" ? $link : $editlinks.find('.hidesection-image');

		// Did we click text or an image?
		var $show = $link.prop('tagName') == 'IMG'
				? $imglink.attr('src') == $link.data('show')
				: $textlink.html() == $link.data('show');

  if ($show) {  $(this).closest("h1,h2,h3,h4,h5").children().first().remove();} else {$( this ).closest( "h1,h2,h3,h4,h5").prepend("<span>+</span>");}
		var $toggle      = $show ? 'show' : 'hide';
		var $nexttext    = $show ? 'hide' : 'show';
		var $toggleClass = $show ? 'removeClass' : 'addClass';

    var $actionFunctions = $show ? 'slideDown' : 'slideUp';  // CHC added

		// toggle text and/or image
		$textlink.text( $textlink.data($nexttext) );
		$imglink.attr( 'src', $imglink.data($nexttext) );

		// Toggle visibility
		var $header  = $link.parents('h1,h2,h3,h4,h5,h6,h7').first();
		var headtype = $header.prop('tagName');

		// include <tag> in class name, so section can be hidden by more than one link
//		$header.nextUntil( non_nesting[headtype] )[$actionFunctions]('hs-hide-' + headtype);  // CHC added
		$header.nextUntil( non_nesting[headtype] )[$toggleClass]('hs-hide-' + headtype);

	}

function hideall (e) {

  console.error ("HIDEALL");

  e.preventDefault();

  var $link = $( '.hidesection-all' );
  // Toggle text shown in the menu portion of the section links
  var $show = 0;
  if ( $link.html() == $link.data('hide') ) {$link.text( $link.data('show') );} 
  else {$link.text( $link.data('hide') ); $show = 1;}

  var $textlink = $(".hidesection-link");
  var $imglink  = $(".hidesection-image");
  if ($show) {
			// just brute-force through this
			$('.hs-hide-H1,.hs-hide-H2,.hs-hide-H3,.hs-hide-H4,.hs-hide-H5,.hs-hide-H6,.hs-hide-H7').removeClass( hide_classes );
			$textlink.text( $textlink.data('hide') );
			$imglink.attr( 'src', $imglink.data('hide') );

    //  $("h1").children().first().remove();

$('#mw-content-text h1').each(function() {
  $(this).children().first().remove();
});

		} else {
			$('.hidesection-link, .hidesection-image').each( function (i,el) { hidesection( undefined, $(el)) });

      $( "#mw-content-text h1" ).prepend("<span>+</span>");


//        $( this ).closest( "h1,h2,h3,h4,h5").prepend("<span>+</span>");


		}
	}



	mw.hook( 'wikipage.content' ).add( function () {
		$('.hidesection-link, .hidesection-image').click( hidesection );
		$('.hidesection-all').click( hideall );
	} );
}( jQuery, mediaWiki ) );
