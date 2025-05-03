

class MyButton extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  static get observedAttributes() {
    return ['label']; // Observe the "label" attribute
  }

  connectedCallback() {
    this.render();
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (name === 'label') {
      this.render(); // Re-render when the "label" attribute changes
    }
  }

  render() {
    const label = this.getAttribute('label') || 'Click Me';
    this.shadowRoot.innerHTML = `
      <style>
        button {
          background-color: #4CAF50;
          color: white;
          padding: 10px 20px;
          border: none;
          cursor: pointer;
          border-radius: 5px;
        }
        button:hover {
          background-color: #45a049;
        }
      </style>
      <button>${label}</button>
    `;
  }
}

customElements.define('my-button', MyButton);


