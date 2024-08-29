<?php

class DantePresentationsPreferences {

public static function onUserSaveSettings( User $user ) {}

public static function onGetPreferences ( $user, &$preferences ) { 
  $preferences['pref-deepl-apikey']       = ['type' => 'text',  'section' => 'dante/keys', 'label-message' => 'dante-label-deepl-apikey',     'help-message' => 'dante-message-deepl-apikey'];
}

}