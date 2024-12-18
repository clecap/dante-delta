const connections = new Set();

onconnect = (e) => {
  const port = e.ports[0];
  connections.add(port);

  // Notify about the new connection
  port.onmessage = (event) => {
    if (event.data === 'getWindows') {
      port.postMessage([...connections].map(p => p !== port));
    }
  };

  // Clean up when a connection closes
  port.onclose = () => {
    connections.delete(port);
  };
};
