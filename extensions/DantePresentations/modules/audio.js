
// console.error ("audio.js loading now");

let player;

function init () {
  console.log ("ext.audio: initialized");
  player = document.createElement ("audio");
  player.style= "position:relative; top:0px; left:100px; width:300px;height:40px;";
  player.src = "file";
  player.setAttribute ("controls", "");

  let head = document.getElementById ("mw-head-base");

  head.appendChild (player);



     
 

 


audio.controls = true; 
  audio.style.position = 'absolute'; // Make it positionable
  audio.style.cursor = 'move'; // Change cursor to indicate draggable

  // Append the audio element to a specified container
  var container = document.getElementById(containerId);
  if (!container) {
    container = document.body; // Default to body if containerId not found
  }
  container.appendChild(audio);

  // Variables to store initial positions
  var offsetX = 0, offsetY = 0, mouseX = 0, mouseY = 0;

  // Function to start dragging
  audio.addEventListener('mousedown', function(e) {
    e.preventDefault();
    mouseX = e.clientX;
    mouseY = e.clientY;
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', stopDrag);
  });

  // Function to handle dragging
  function drag(e) {
    offsetX = mouseX - e.clientX;
    offsetY = mouseY - e.clientY;
    mouseX = e.clientX;
    mouseY = e.clientY;
    audio.style.top = (audio.offsetTop - offsetY) + "px";
    audio.style.left = (audio.offsetLeft - offsetX) + "px";
  }

  // Function to stop dragging
  function stopDrag() {
    document.removeEventListener('mousemove', drag);
    document.removeEventListener('mouseup', stopDrag);
  }





















}

init ();


console.log ("audio: loaded");



