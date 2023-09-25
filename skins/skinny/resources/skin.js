/* JavaScript for the Example skin */


// adjust the zoom inside of the window
async function one () {
  console.log("outer: " + window.outerWidth+' x '+window.outerHeight + "\n" + "screen: " + screen.width+ ' x '+screen.height + "\n"
   + "inner:" + window.innerWidth + " x " + window.innerHeight + "\n" + "body:" + 
   document.body.clientWidth + " x " + document.body.clientHeight   + "\n" + 
   "html:" + document.documentElement.clientWidth + " x " + document.documentElement.clientHeight);


}

  
function two () {
  var scale = window.innerHeight / document.body.clientHeight;
  console.log ("scale = " + scale + " = " + window.innerHeight + " / " + document.body.clientHeight);
  document.body.style.transform = "scale("+scale+")";
  console.log (document.body.style.transform);
}



window.addEventListener('load', () => {

console.log ("loaded");
  two();  



});


console.log (111);

//alert (111);



// one();

// two();


// window.setTimeout (one, 1000);


//goFull();



let connectionIdx = 0;
let messageIdx = 0;

function addConnection (connection) {
  connection.connectionId = ++connectionIdx;
  addMessage('New connection #' + connectionIdx);

  connection.addEventListener('message', function(event) {
    messageIdx++;
    const data = JSON.parse(event.data);
    const logString = 'Message ' + messageIdx + ' from connection #' +
        connection.connectionId + ': ' + data.message;
    addMessage(logString, data.lang);
    maybeSetFruit(data.message);
    connection.send('Received message ' + messageIdx);
  });

  connection.addEventListener('close', function(event) {
    addMessage('Connection #' + connection.connectionId + ' closed, reason = ' +
        event.reason + ', message = ' + event.message);
  });
};

/* Utils */

const fruitEmoji = {
  'grapes':      '\u{1F347}',
  'watermelon':  '\u{1F349}',
  'melon':       '\u{1F348}',
  'tangerine':   '\u{1F34A}',
  'lemon':       '\u{1F34B}',
  'banana':      '\u{1F34C}',
  'pineapple':   '\u{1F34D}',
  'green apple': '\u{1F35F}',
  'apple':       '\u{1F34E}',
  'pear':        '\u{1F350}',
  'peach':       '\u{1F351}',
  'cherries':    '\u{1F352}',
  'strawberry':  '\u{1F353}'
};

function addMessage(content, language) {

  console.log ("addMessage", content, language);

return;

  const listItem = document.createElement("li");
  if (language) {
    listItem.lang = language;
  }
  listItem.textContent = content;
  document.querySelector("#message-list").appendChild(listItem);
};

function maybeSetFruit(message) {
  const fruit = message.toLowerCase();
  if (fruit in fruitEmoji) {
    document.querySelector('#main').textContent = fruitEmoji[fruit];
  }
};

document.addEventListener('DOMContentLoaded', function() {
  console.log ("DOMContentLoaded");
  if (navigator.presentation.receiver) {
    console.log ("presentation receiver supported");
    navigator.presentation.receiver.connectionList.then(list => {
      console.log ("list of connections: ", list);
      list.connections.map(connection => addConnection(connection));
      list.addEventListener('connectionavailable', function(event) {
        addConnection(event.connection);
      });
    });
  } 
  else {
    console.warn ("No support for presentation.receiver");
  }
});

window.addEventListener ("load", ()=> {console.log ("onload");});



console.log ("instrumented");



if (navigator.presentation.receiver) {
  console.log ("presentation receiver supported");
  navigator.presentation.receiver.connectionList.then(list => {
    console.log ("list of connections: ", list);
    list.connections.map(connection => addConnection(connection));
    list.addEventListener('connectionavailable', function(event) {
      addConnection(event.connection);
          });
  });
} 
else {
  console.warn ("No support for presentation.receiver");
}






