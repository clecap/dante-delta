<?php


class DanteSpecialPage extends SpecialPage {


protected function getGroupName() {return 'dante';}


public function execute( $par ) {
  $this->setHeaders();
  $this->checkPermissions();
  $this->outputHeader();
  $request = $this->getRequest();
  $action = $request->getVal( 'action', 'view' );  // Read `action` query parameter; if not present use 'view' as fallback value

  if ( $action === 'submit' && $request->wasPosted() ) { $this->handleSubmission ( $request ); } 
  else {$this->showForm();}
}


}



