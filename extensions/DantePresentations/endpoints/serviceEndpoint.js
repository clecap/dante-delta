
console.info ("hi");

const script = document.currentScript;
const url = script.src;
const params = new URL(url).searchParams;
const sn     = params.get('sn');
console.info ("seeing sn = ", sn);


const source = new EventSource("./extensions/DantePresentations/endpoints/serviceEndpoint.php?sn="+sn);


function handler (e) {
  console.log (e);
  const log = document.getElementById('log');
 
  const down = (ele) => { if (ele) {ele.scrollTop = ele.scrollHeight;} window.scrollTo({  top: document.body.scrollHeight + 500 });}

  let obj;
  try {obj = JSON.parse (e.data);} 
  catch (exc) {
    obj = "json conversion error";
  }

  switch (obj.command) {
    case "log":       // log it under li log
      const li = document.createElement('li');
      li.textContent = "Logging text message: " + obj.data;
      log.appendChild(li);    
      log.scrollTop = log.scrollHeight;     // Auto-scroll to bottom
      break;

    case "stderr": { let ele =document.getElementById ("stderr-" + obj.num); ele.classList.add ("stderr-active"); ele.textContent += obj.data; down(ele);  break; }
    case "stdout": { let ele =document.getElementById ("stdout-" + obj.num); ele.classList.add ("stdout-active"); ele.textContent += obj.data;  down(ele); break; }
    case "cmd":    { let ele =document.getElementById ("cmd-" + obj.num); ele.textContent += obj.data; down (ele); break;    }
      
    case "setup":  {
      const fragment = 
        `<div id="container-${obj.num}" class="container">
           <h3 class="title"># ${obj.num} <span id="status-${obj.num}" class="status"></span> <span id="cmd-${obj.num}" class="cmd"></span></h3>
           <h4>Output</h4>
           <pre id="stdout-${obj.num}" class="stdout"></pre>
           <h4>Errors</h4>
           <pre id="stderr-${obj.num}" class="stderr"></pre>
         </div>
       `;
      const target = document.getElementById('commands');
      target.insertAdjacentHTML('beforeend', fragment);
      down (ele);
      break;
    }

    case "status": { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; break; }  // write in the closing status // TODO: can we deprecate this??

    case "running":    { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("running"); break; }  // write in the closing status    
    case "exitOk":     { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("exitOk");  break; }  // write in the closing status    
    case "exitErr":    { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("exitErr"); break; }  // write in the closing status    
    case "drainOk":    { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("drainOk");  break; }  // write in the closing status    
    case "drainErr":   { let ele =document.getElementById ("status-" + obj.num); ele.textContent = obj.data; ele.classList.add ("drainErr"); break; }  // write in the closing status        

    case "in-error": { let ele =document.getElementById ("container-" + obj.num); ele.classList.add ("in-error"); break; }

    case "was-ok": { let ele =document.getElementById ("container-" + obj.num); ele.classList.add ("was-ok"); break;}


    case "close": { console.warn ("will close event source"); source.close();  
      let ele=document.createElement ("h1"); ele.textContent = obj.data; document.body.appendChild (ele);
      down (ele);
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


source.addEventListener ("exception",     logHandler ("Exception: ")     );
source.addEventListener ("php-exception", logHandler ("PHP Exception: ") );
source.addEventListener ("php-error",     logHandler ("PHP Error: ")     );