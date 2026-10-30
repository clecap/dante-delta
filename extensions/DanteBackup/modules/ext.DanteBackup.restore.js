// SCP restore in Special:DanteRestore: the Load button lists the dump files in the SCP directory as radio buttons named scpFile

/**
 * Create an element with a class and a text; text only, so names from the remote host cannot inject HTML.
 *
 * @param {string} tag Element name
 * @param {string} cls CSS class
 * @param {string} text Text content
 * @return {HTMLElement}
 */
function makeElement ( tag, cls, text ) {
  const ele = document.createElement ( tag );
  ele.className = cls;
  ele.textContent = text;
  return ele;
}

/**
 * Show the listing, or an error, in the list area of the SCP form.
 *
 * @param {HTMLElement} list Container for the radio buttons
 * @param {Object} result JSON answer of ?action=scplist: { ok, files } or { ok, error }
 */
function showListing ( list, result ) {
  list.replaceChildren ();
  if ( !result.ok )               { list.appendChild ( makeElement ( "div", "dante-scp-error", result.error ) ); return; }
  if ( result.files.length === 0 ) { list.appendChild ( makeElement ( "div", "dante-scp-error", "No dump files in this directory" ) ); return; }

  // one radio button per file, laid out like the AWS S3 list: prefix and name, time stamp, size
  result.files.forEach ( ( file, i ) => {
    const label = document.createElement ( "label" );
    const radio = document.createElement ( "input" );
    radio.type = "radio";  radio.name = "scpFile";  radio.value = file.name;  radio.required = true;  radio.checked = ( i === 0 );
    label.append ( radio, " ",
      makeElement ( "b",    "prefix-" + file.type.toLowerCase(), file.type + ": " ),
      makeElement ( "span", "table-name",  file.name ), " ",
      makeElement ( "span", "table-stamp", file.time ), " ",
      makeElement ( "span", "table-size",  ( file.size / ( 1024 * 1024 ) ).toFixed ( 2 ) + "[MB]" ) );
    list.append ( label, document.createElement ( "br" ) );
  } );
}

/**
 * Fetch the listing for the values currently entered in the SCP form and show it.
 *
 * @param {HTMLButtonElement} button The Load button inside the SCP form
 */
async function loadListing ( button ) {
  const form = button.form;
  const list = document.getElementById ( "dante-scp-list" );
  const data = new FormData ();
  [ "scpUser", "scpHost", "scpPath", "scpPassword" ].forEach ( name => data.append ( name, form.elements[name].value ) );
  if ( form.elements.scpSkipHostKey.checked ) { data.append ( "scpSkipHostKey", "1" ); }   // like a submitted checkbox: present only when checked
  data.append ( "token", mw.user.tokens.get ( "csrfToken" ) );   // the server accepts the request only with a valid CSRF token

  button.disabled = true;
  list.replaceChildren ( makeElement ( "div", "", "Loading ..." ) );
  try {
    const response = await fetch ( mw.util.getUrl ( mw.config.get ( "wgPageName" ), { action: "scplist" } ), { method: "POST", body: data, credentials: "same-origin" } );
    showListing ( list, await response.json () );
  } catch ( e ) {
    showListing ( list, { ok: false, error: "Could not load the listing: " + e.message } );   // e.g. no JSON because the permission check failed
  } finally {
    button.disabled = false;
  }
}

// wire up the Load button, if the SCP form is on this page
const loadButton = document.getElementById ( "dante-scp-load" );
if ( loadButton ) { loadButton.addEventListener ( "click", () => loadListing ( loadButton ) ); }
