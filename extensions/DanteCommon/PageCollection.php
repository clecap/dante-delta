<?php

// a memo is a single record for controlling the functionality of uploading DanteWiki system contents files
class PageCollection {

  public string $name;        // name of the collection of contents
  public string $filename;    // temporary file name used as pagelist for this collection
  public string $contents;    // list of files as stored in the files
  public string $num;         // number of elements of the collection
  public string $label;       // label for the collection
  public string $url;         // url for looking up by user


// returns an array of PageCollections, as reflecting the initial system contents
  public static function getConfig () {
    $config = [
      new PageCollection ( "Cat_DanteInitialContents",         DanteUtil::catList           ("DanteInitialContents"),               "Category:DanteInitialContents" ),
      new PageCollection ( "Cat_DanteInitialCustomize",        DanteUtil::catList           ("DanteInitialCustomize"),              "Category:DanteInitialCustomize"),
      new PageCollection ( "MediaWiki_DanteInitialContents",   DanteUtil::listOfListed      ("MediaWiki:DanteInitialContents"),    "MediaWiki:DanteInitialContents" ),
      new PageCollection ( "MediaWiki_DanteInitialCustomize",  DanteUtil::listOfListed      ("MediaWiki:DanteInitialCustomize"),   "MediaWiki:DanteInitialCustomize" ),
      new PageCollection ( "Test",                             DanteUtil::listOfNamespace   (NS_TEST),                          "Special:AllPages&from=&to=&namespace=3000" ),
      new PageCollection ( "MainPage",                         DanteUtil::singleList        ("Main Page"),                         "Main_Page" ),
      new PageCollection ( "MediaWiki_Sidebar",                DanteUtil::singleList        ("MediaWiki:Sidebar"),                 "MediaWiki:Sidebar" )
    ];
    return $config;
  }


// writes all $contents of the given PageCollection array into a temp manifest file, sorted, unique and without empty lines; returns the file name
  public static function makeManifest ( array $collections ): string {
    $tmpFile = tempnam ( sys_get_temp_dir(), 'pc_' );
    $titles  = explode ( "\n", implode ( "\n", array_map ( fn($c) => $c->contents, $collections ) ) );   // one title per line, across all collections
    $titles  = array_unique ( array_filter ( array_map ( 'trim', $titles ), fn($t) => $t !== '' ) );     // a page may belong to several collections
    sort ( $titles, SORT_STRING );
    file_put_contents ( $tmpFile, implode ( "\n", $titles ) . "\n" );
    return $tmpFile;
  }


// constructs one instance of a PageCollection
  public function __construct ( string $name, string $filename, string $url ) {
    global $wgServer, $wgScriptPath;
    $this->name      = $name;
    $this->filename  = $filename;
    $this->url       = $url;
    $this->contents  = file_get_contents ($this->filename);
    $this->num       = substr_count($this->contents, "\n");

    $this->label = "<details><summary><b>$this->num pages </b> belonging to <a href='$wgServer/$wgScriptPath/index.php?title=$this->url'>$this->url</a></summary>
    <div style='max-height:200px; overflow-y:scroll; overflow-x:hidden; margin:20px;'><pre style='margin:0px; padding:0px;'>$this->contents</pre></div></details>";
  }

  public function cleanUp (): void {
    @unlink ( $this->filename );
  }

} // end class PageCollection
