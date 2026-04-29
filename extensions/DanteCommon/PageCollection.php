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


// writes all $contents of the given PageCollection array into a temp manifest file; returns the file name
  public static function makeManifest ( array $collections ): string {
    $tmpFile = tempnam ( sys_get_temp_dir(), 'pc_' );
    $combined = implode ( "\n", array_map ( fn($c) => $c->contents, $collections ) );
    file_put_contents ( $tmpFile, $combined );
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






  // TODO: DEPRECATE THIS together with DanteInitialStore.php
  public function execute ( $owner, $repository, $token, $path, $out=false ) {
    global $IP;

    // generate a manifest file and upload that manifest file
    $response = DanteUtil::storeToGithub ($owner, $repository, "$path/$this->filename-manifest.txt", $token, $this->contents);
    if ($out) $out->addHTML( '<h3>got filepath:</h3><p><pre>' . $this->filename . '</pre></p>' );

    // dump the contents as requested into into variable $output
    $cmd = "php  $IP/maintenance/dumpBackup.php --current --uploads  --include-files  --pagelist=$this->filename";
    $ret = Executor::execute ( $cmd, $output, $error, $duration );

    if ($out) $out->addHTML ( "<h3>dumpBackup wrote to stderr:</h3><p><pre>" .$error. "</pre></p>");
    $response = DanteUtil::storeToGithub ($owner, $repository, "$path/$this->name.xml.gz",  $token,   gzencode ($output) );   // upload a .xml.gz variant
    $response = DanteUtil::storeToGithub ($owner, $repository, "$path/$this->name.xml",    $token,   $output );                      // upload a .xml variant

    $json = json_decode ($response);
    // if ($json->status != 200) { throw new Exception ("PROBLEM");}  // TODO: fix ?!?
    $json_indented_by_4 = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    $json_indented_by_2 = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $json_indented_by_4);
    if ($out) $out->addHTML( '<h3>Github server replied to API call:</h3><p><pre>' . $json_indented_by_2 . '</pre></p>' );
    @unlink ($filepath);
  }

} // end class PageCollection
