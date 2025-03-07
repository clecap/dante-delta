


window.INIT = function instrument () {

  let zoom = 1;

  const zoomImplement = () => {
    let list = document.querySelectorAll (".innerContent");
    for (let i = 0; i < list.length; i++) { list[i].style.zoom = zoom;}
    list = document.querySelectorAll (".zoomShow");
    for (let i = 0; i < list.length; i++) { list[i].innerHTML = '' + Math.floor (100*zoom).toString();}  
  }

  const zoomStore = () => {localStorage.setItem ("Dante-swipe-zoom", ""+zoom);}

  const zoomLoad = () => {
    let zoomStored = localStorage.getItem ("Dante-swipe-zoom");
    if (zoomStored === null) { zoom = 1;}
    else  {
      zoom = parseFloat (zoomStored);
      if ( isNaN(zoom) ) {zoom = 1;}
      if ( zoom < 0.1  ) {zoom = 0.1;}
      if ( zoom > 20   ) {zoom = 20;}
    }
    console.log ("zoomLoad: ", zoom);
  }


  const zoomPlus  = (e) => { zoom = zoom * 1.5; zoomStore (zoom); zoomImplement (zoom); };
  const zoomMinus = (e) => { zoom = zoom / 1.5; zoomStore (zoom); zoomImplement (zoom); };
  const zoomReset = (e) => { zoom = 1;          zoomStore (zoom); zoomImplement (zoom); };

  zoomLoad ();
  zoomImplement();

  let list;
  list = document.querySelectorAll (".zoomPlus");
  for (let i = 0; i < list.length; i++) { list[i].onclick= zoomPlus;}
  list = document.querySelectorAll (".zoomMinus");
  for (let i = 0; i < list.length; i++) { list[i].onclick= zoomMinus;}
  list = document.querySelectorAll (".zoomShow");
  for (let i = 0; i < list.length; i++) { list[i].onclick= zoomReset;}

  // arrow keys also produce a change in the shown slide
  document.addEventListener('keydown', function(event) {
    if (event.key === 'ArrowRight') { window.mySwipe.next();}
    if (event.key === 'ArrowLeft')  { window.mySwipe.prev();}
  });

  /* user changes in the selector element are reflected by moving in the required slide */
  const selectElement = document.getElementById("selector");
  selectElement.addEventListener("change", function() { const selectedValue = selectElement.value; window.mySwipe.slide (selectedValue-1, 300); });

}






