// attempt to count and list all active pages from our domain - thus far not yet working.




const connections = new Set();   // Track all connected ports (representing tabs or windows)

onconnect = (event) => {
  const port = event.ports[0];
  connections.add(port);

  // Notify other tabs/windows that a new tab has connected
  for (const connection of connections) { connection.postMessage({ type: 'tabConnected', connectionsCount: connections.size }); }

  // Handle messages from the tab
  port.onmessage = (event) => {
    if (event.data === 'getWindows') {
      // Send the total number of connections to the requester
      port.postMessage({ type: 'connectionsList', connectionsCount: connections.size });
    }
  };

  // Clean up when a port is disconnected
  port.onclose = () => {
    console.log ("worker received a disconnect");
    connections.delete(port);
    for (const connection of connections) {
      connection.postMessage({ type: 'tabDisconnected', connectionsCount: connections.size });
    }
  };
};
