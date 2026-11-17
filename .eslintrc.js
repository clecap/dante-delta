// ESLint configuration (legacy eslintrc format, ESLint 8) used by .github/workflows/eslint.yml for code scanning
module.exports = {
  root: true,                                                     // do not pick up configs from parent directories

  // our own code runs in the browser, inside MediaWiki with jQuery
  env: { browser: true, jquery: true, es2022: true },
  parserOptions: { ecmaVersion: 'latest', sourceType: 'script' },
  globals: { mw: 'readonly', OO: 'readonly', Reveal: 'readonly' },

  extends: 'eslint:recommended',

  // third-party libraries and minified builds are not ours to fix
  ignorePatterns: [
    '**/vendor/**',
    '**/node_modules/**',
    '**/*.min.js',
    '**/*-min.js',
    'extensions/DanteTree/fancytree/jquery.fancytree.js',
    'extensions/DanteTree/fancytree/jquery.fancytree.persist.js',
    'extensions/DanteTree/suckerfish/**',
  ],

  overrides: [
    // build and config files run under node
    { files: [ '**/Gruntfile.js', '.eslintrc.js' ], env: { node: true, browser: false } },
    // shared worker script runs in a worker scope
    { files: [ 'extensions/DantePresentations/js/allWindowsSharedWorker.js' ], env: { worker: true, browser: false } },
  ],
};
