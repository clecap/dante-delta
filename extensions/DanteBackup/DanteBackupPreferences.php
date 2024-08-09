<?php

class DanteBackupPreferences {

public static function onUserSaveSettings( User $user ) {}

public static function onGetPreferences ( $user, &$preferences ) { 

  if ( $user->isAllowed ("dante-dump") || $user->isAllowed ("dante-restore") ) {

    $preferences['aws-accesskey']       = ['type' => 'text',  'section' => 'dante/aws', 'label-message' => 'aws-prefs-label-key',     'help-message' => 'aws-prefs-key'];
    $preferences['aws-secretaccesskey'] = ['type' => 'text',  'section' => 'dante/aws', 'label-message' => 'aws-prefs-label-skey',    'help-message' => 'aws-prefs-skey'];
    $preferences['aws-bucketname']      = ['type' => 'text',  'section' => 'dante/aws', 'label-message' => 'aws-prefs-label-bucket',  'help-message' => 'aws-prefs-bucket'];
    $preferences['aws-region']          = ['type' => 'text',  'section' => 'dante/aws', 'label-message' => 'aws-prefs-label-region',  'help-message' => 'aws-prefs-region'];
    $preferences['aws-encpw']           = ['type' => 'text',  'section' => 'dante/aws', 'label-message' => 'aws-prefs-label-encpw',   'help-message' => 'aws-prefs-encpw'];

    $preferences['ssh-host']            = ['type' => 'text',  'section' => 'dante/ssh', 'label-message' => 'prefs-label-ssh-host',         'help-message' => 'prefs-ssh-host'];
    $preferences['ssh-dump-user']       = ['type' => 'text',  'section' => 'dante/ssh', 'label-message' => 'prefs-label-ssh-dump-user',    'help-message' => 'prefs-ssh-dump-user'];
    $preferences['ssh-dump-pw']         = ['type' => 'text',  'section' => 'dante/ssh', 'label-message' => 'prefs-label-ssh-dump-pw',      'help-message' => 'prefs-ssh-dump-pw'];
    $preferences['ssh-restore-user']    = ['type' => 'text',  'section' => 'dante/ssh', 'label-message' => 'prefs-label-ssh-restore-user', 'help-message' => 'prefs-ssh-restore-user'];
    $preferences['ssh-restore-pw']      = ['type' => 'text',  'section' => 'dante/ssh', 'label-message' => 'prefs-label-ssh-restore-pw',   'help-message' => 'prefs-ssh-restore-pw'];
    $preferences['ssh-encpw']           = ['type' => 'text',  'section' => 'dante/ssh', 'label-message' => 'prefs-label-ssh-encpw',        'help-message' => 'prefs-ssh-encpw'];

    // NOTE: secretaccesskey should stay text and not be password. It looks like mediawiki does not fill the field in when opening the dialogue (which is more secure) but then
    //       a subsequent store also stores an empty password. This is a problem. 
  }
  else {return;}

}

}