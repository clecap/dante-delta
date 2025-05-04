

class PastedText extends HTMLElement {
  constructor() { super(); this.attachShadow({ mode: 'open' }); }

//  static get observedAttributes() {return ['label']; }

  connectedCallback() {this.render();}

  // Re-render when the "label" attribute changes
//  attributeChangedCallback(name, oldValue, newValue) { if (name === 'label') {this.render(); } }

  render() {
    // const label = this.getAttribute('label') || 'Click Me';
    this.shadowRoot.innerHTML = `
      <style>
        div {background-color: #4CAF50; color: white; padding: 10px 20px;
          border-radius: 5px;
        }
        div:hover {background-color: #45a049;}
      </style>
      <div>${label}</div>
    `;
  }
}

customElements.define('pasted-text', PastedText);


