

/* NAMESPACE for displaying the screen configuration */

const SCREENCONFIG = ( () => {

  var screenDetails; 

  // returns some details on the screen with the given number
  function getScreenByNumber (num) {
    let ret=screenDetails.screens[num];
    if (ret) {return ret;} else {alert ("Sorry, could not find this screen with number " + num);}
  }
  
    // list of all the properties of screen details which we want to see in the table (CAVE: need them as they are not enumerable props)
  var props = [
      "label",    
      "availLeft", "availTop", "availWidth", "availHeight",
      "left", "top", "width", "height",
      "colorDepth", "devicePixelRatio", 
      "isInternal", "isPrimary",
    ];
  
/** inject info on the screen configuration into the UI as based on info screens 
 *  screens:  array with screen details
 *  selEcs:   array with selector elements which we should fill
*/
  function multiScreenInfo (selEcs) { 

    // inject screen choice info into all selectors of class  monitorSelector
    for (let sel of document.querySelectorAll (".monitorSelector")) {
      // first clear the selectors from earlier entries 
      console.log ("we have " + sel.childNodes.length + " kids");
      for (const node of document.querySelectorAll (".some")) { 
        console.log ("visiting node", node);
        node.parentNode.removeChild (node);    }



  
      // then inject the newly found ones
      let number = 0;
      for (let screen of screenDetails.screens) {
        let opt = document.createElement ("option");
        opt.title=`Left: ${screen.left} Top: ${screen.top} Size: ${screen.width} x ${screen.height} ${screen.isInternal ? "Internal": "External"}`;
        opt.setAttribute("value", number++);
        opt.classList.add ("some");
        opt.innerHTML = `${screen.isPrimary ? "**PRIMARY**" : ""} ${screen.label} ` ;
        sel.append (opt);
      }
    }
  
    // inject detailed table info into all elements of class details
    for (let ele of document.querySelectorAll (".details")) {
      var num = screenDetails.screens.length;
      var out = "<table>";
      for (var i = 0; i < props.length; i++) {
        out += "<tr>";
        out += "<td class='colZero'>" + props[i] + "</td>";
        for (var j=0; j < screenDetails.screens.length; j++) {
          out += "<td class='colNext'>" + screenDetails.screens[j][props[i]] + "</td>";
        }
        out += "</tr>";
      }
      out += "</table>";
      ele.innerHTML =  out ;
    }
  }


  /*


  window.info = async function info () {
    var text = "", ele = document.getElementById("multiscreenAPI").classList;
    if ('getScreenDetails' in window) {
      ele.add ("positive"); ele.remove("negative");
      // we support the multiscreen API, so include the respective controls
  
      screenDetails = await window.getScreenDetails();
      multiScreenInfo ( [document.getElementById ("selector1"), document.getElementById ("selector2"), document.getElementById ("selector3")] );  // get the multiscreen info NOW
      screenDetails.addEventListener('screenschange', (event) => {multiScreenInfo ( [document.getElementById ("selector1"), document.getElementById ("selector2"), document.getElementById ("selector3")])}); // and schedule to get them when config changes
    }  else  {ele.add("negative"); ele.remove("positive");}
  
    ele = document.getElementById ("presentationAPI").classList;
    if (window.screen.isExtended) {ele.add ("positive"); ele.remove("negative"); }  else  {ele.add("negative"); ele.remove("positive");}
  
    ele = document.getElementById ("isExtended").classList;
    if (window.screen.isExtended) {ele.add ("positive"); ele.remove("negative"); }  else  {ele.add("negative"); ele.remove("positive");}
  
    let granted = false;
    try {
      const { state } = await navigator.permissions.query({ name: 'window-placement' });
      console.log ("Permission API returned ", state);
      granted = state === 'granted';} 
    catch (ex) {console.error ("Exception quering permission API", ex);}
    ele = document.getElementById ("isPermitted").classList;
    if (granted) {ele.add ("positive"); ele.remove("negative"); }  else  {ele.add("negative"); ele.remove("positive");}
  }
  
  */

  async function init (update) {
    update = update || multiScreenInfo;
    if ('getScreenDetails' in window) {
      screenDetails = await window.getScreenDetails();
      update ();
      screenDetails.addEventListener('screenschange', (event) => {update();} );
    }
    else {alert ("Your browser does not support the Mutli-Screen Window Placement API");}
  }

  return {getScreenByNumber, init};   // export
  
  })();