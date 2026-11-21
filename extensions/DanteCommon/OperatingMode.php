<?php

class OperatingMode extends SpecialPage {

  public function __construct() {
    parent::__construct( 'DanteDevelopment', 'developDante' );
  }

  public function getGroupName() { return 'dante'; }

  public function execute( $subPage ) {
    global $wgServer, $wgScriptPath;
    $this->setHeaders();
    if ( !$this->getUser()->isAllowed( 'developDante' ) ) {
      $this->displayRestrictionError();
      return;
    }

    $this->showOperatingModeForm();
    $this->showThirdForm();
  }


  // ---------------------------------------------------------------------------
  // Form 1: switch operating mode
  // ---------------------------------------------------------------------------

  private function showOperatingModeForm() {
    global $wgDanteOperatingMode, $wgServer, $wgScriptPath;
    $out = $this->getOutput();
    $out->addHTML( "<h2>Operating mode</h2>" );
    $out->addHTML( "<p>Current operating mode is: <b>$wgDanteOperatingMode</b>.</p>" );
    $out->addHTML( "<p><b>Running in development mode contains security risks and is discouraged!</b><br>" .
                   "Unless you know exactly what you are doing, please close this browser window immediately.</p>" );
   

    $formDescriptor = [
      'radio' => ['type'    => 'radio', 'label'   => 'Operative mode', 'default' => '', 'options' => ['Production' => 0, 'Development' => 1, ], ],
    ];

    $form = HTMLForm::factory( 'table', $formDescriptor, $this->getContext() );
    $form->setFormIdentifier( 'operatingMode' );
    $form->setSubmitText( 'Set operating mode' );
    $form->setSubmitCallback( [ $this, 'processOperatingModeForm' ] );
    $form->show();
  }

  public function processOperatingModeForm( array $data ) {
    global $IP;
    if ( $data['radio'] == 0 ) { copy( "$IP/DanteSettings-production.php",  "$IP/DanteSettings-used.php" ); }
    if ( $data['radio'] == 1 ) { copy( "$IP/DanteSettings-development.php", "$IP/DanteSettings-used.php" ); }
    if ( function_exists( 'apcu_clear_cache' ) ) { apcu_clear_cache(); }
    opcache_reset();
    $this->getOutput()->redirect( Title::newFromText( 'Main Page' )->getFullURL() );
    return true;
  }

 



private function showThirdForm() {
    global $wgServer, $wgScriptPath;
    $out = $this->getOutput();
    $out->addHTML( "<h2>Provide Links to Analytic Test Pages</h2>" );
    $out->addHTML( "<ul>" );
    $out->addHTML( "<li><a href='$wgServer/$wgScriptPath/extensions/DanteExperiments/Clipboard.html'>Analyze Clipboard Contents</a></li>" );
    $out->addHTML( "<li><a href='$wgServer/$wgScriptPath/extensions/DanteExperiments/DragDrop.html'>Data Transfer Inspector</a></li>" );
     $out->addHTML( "<li><a href='$wgServer/$wgScriptPath/docs/html/index.html'>Doxygen Docs (if generated)</a></li>" );   
    $out->addHTML( "<ul>" );
  
  }






  public function processSecondForm() {
    global $IP;


   

    $cmd = [
      "sudo apt update",                              // update apt
      "sudo apt install -y --no-install-recommends doxygen",                     // install doxygen which is not normally installed
      "rm -rf /var/lib/apt/lists/*",                   // remove cached files of installer again
      "cd $IP/maintenance; php mwdocgen.php"
    ];

    return true;
  }

}
