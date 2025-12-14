const source = new EventSource("./extensions/DanteCommon/serviceEndpoint.php");


let maximalCallingsOfEventSourceLeft  = 20;  // set a default in case we forgot to send a "max" message

function handler (e) {
  // console.log (e);
  const log = document.getElementById('log');
 
  const down = (ele) => { if (ele) {ele.scrollTop = ele.scrollHeight;} window.scrollTo({  top: document.body.scrollHeight + 500 });} ;

  const doLog = (x) => { 
      const li = document.createElement('li');
      li.textContent = "Logging text message: " + x;
      log.appendChild(li);    
      log.scrollTop = log.scrollHeight;     // Auto-scroll to bottom
  };    

  let obj;
  try {obj = JSON.parse (e.data);} 
  catch (exc) {
    obj = "json conversion error";
  }

  switch (obj.command) {
    case "log":  {doLog (obj.data); break;}  // log it under li log

    // sets up a limit for the number of commands. if we receive more "setup" calls than defined by the limit
    // we do a hard shutdown of the eventsource. Goal of this is to set a limit in case something went wrong
    // this is in particular a development support, as in correct functioning code this never should trigger
    case "max": { maximalCallingsOfEventSourceLeft = obj.data; break;}

    // setup sets up another template for a command to receive further information updates  
    case "setup":  {
      if (maximalCallingsOfEventSourceLeft-- < 0 ) {
        source.close();  let ele=document.createElement ("h1"); ele.textContent = "Abnormally aborted loop. Error!!"; document.body.appendChild (ele); down (ele); break;}
      const fragment = 
        `<div id="container-${obj.num}" class="container">
           <h3 class="title"># ${obj.num} <span id="status-${obj.num}" class="status"></span> <span id="tick-${obj.num}" class="tick"></span> <span id="cmd-${obj.num}" class="cmd"></span></h3>
           <h4>Output</h4>
           <pre id="stdout-${obj.num}" class="stdout"></pre>
           <h4>Errors</h4>
           <pre id="stderr-${obj.num}" class="stderr"></pre>
         </div>
       `;
      const target = document.getElementById('commands');
      target.insertAdjacentHTML('beforeend', fragment);
      down (target);
      break;
    }

    case "stderr":     { let ele =document.getElementById ("stderr-" + obj.num); ele.classList.add ("stderr-active"); ele.textContent += obj.data; down(ele);  break; }
    case "stdout":     { let ele =document.getElementById ("stdout-" + obj.num); ele.classList.add ("stdout-active"); ele.textContent += obj.data;  down(ele); break; }
    case "cmd":        { let ele =document.getElementById ("cmd-" + obj.num); ele.textContent += obj.data; down (ele); break;    }
    case "status":     { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; break; }  // write in the closing status // TODO: can we deprecate this??
    case "running":    { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("running"); break; }  // write in the closing status    
    case "exitOk":     { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("exitOk");  
                             ele = document.getElementById ("stderr-" + obj.num); ele.classList.add ("stderr-ok");
                         break; }  // write in the closing status    
    case "exitErr":    { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("exitErr"); break; }  // write in the closing status    
    case "drainOk":    { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("drainOk");  break; }  // write in the closing status    
    case "drainErr":   { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("drainErr"); break; }  // write in the closing status        
    case "in-error":   { let ele =document.getElementById ("container-" + obj.num); ele.classList.add ("in-error"); break; }
    case "was-ok":     { let ele =document.getElementById ("container-" + obj.num); ele.classList.add ("was-ok"); break;}
    case "tick":       { let ele=document.getElementById ("tick-" + obj.num); ele.textContent = obj.data; break;;}      // we want to update the total running time (wall clock)
    case "close":      { source.close();  let ele=document.createElement ("h2"); ele.textContent = obj.data; document.body.appendChild (ele); down (ele); break;}

    // ret: this is a notification when we did not call a script but a PHP callable - in this case there is no exit value but a return value
    case "ret":        {let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("exitOk"); 
                         ele = document.getElementById ("stderr-" + obj.num); ele.classList.add ("stderr-ok");  // mark stderr output as ok
                         ele =document.getElementById ("container-" + obj.num); ele.classList.add ("was-ok");   // mark container as ok
                         break;}

    default: {
      const li = document.createElement('li');
      li.textContent = "Got text data: " + e.data;  // " origin=" + e.origin + " src=" + e.source + " at " + e.timeStamp; // e.data is a string from the server
      log.appendChild(li);    
      log.scrollTop = log.scrollHeight;     // Auto-scroll to bottom
      break;
    }
  }
}

source.onmessage=handler;

source.onerror = e => {console.error('SSE error', e);};
      
// returns a handler for logging messages in side of the general log area with a prefix
const logHandler = ( prefix ) => (e => {
  console.log (e);
  const log = document.getElementById('log');
  const li  = document.createElement('li');
  li.textContent = prefix + e.data;
  log.appendChild(li);    
  log.scrollTop = log.scrollHeight;     // Auto-scroll to bottom
});

// special event types are logged into the general log
source.addEventListener ("exception",     logHandler ("Exception: ")     );
source.addEventListener ("php-exception", logHandler ("PHP Exception: ") );
source.addEventListener ("php-error",     logHandler ("PHP Error: ")     );