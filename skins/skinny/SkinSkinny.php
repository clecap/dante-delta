<?php
class SkinSkinny extends SkinMustache {


  // passing additional information to the template
  public function getTemplateData() {
    $data = parent::getTemplateData();
    $data['html-hello'] = '<strong>HELLO WORLD</strong>';
    return $data;
}
 
}
