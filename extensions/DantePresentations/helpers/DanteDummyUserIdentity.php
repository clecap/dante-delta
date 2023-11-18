<?php

class DanteDummyUserIdentity implements MediaWiki\User\UserIdentity {
  private User $user;

  public bool $danteEndpoint = true;

  function __construct ( ?string $userName ) {
    //EndpointLog ( "DanteDummyUserIdentity: constructor called \n");
    $mwServices      =  MediaWiki\MediaWikiServices::getInstance(); 
    $loadBalancer    =  $mwServices->getDBLoadBalancer();
    $userNameUtils   =  $mwServices->getUserNameUtils(); 
    $userFactory     =  new MediaWiki\User\UserFactory ( $loadBalancer, $userNameUtils );
    if ( $userName === null)  { 
      //EndpointLog ( "DanteDummyUserIdentity: constructor: userName is null, constructing anonymous user \n");
      $result = $userFactory->newAnonymous( ); 
      //EndpointLog ( "DanteDummyUserIdentity: constructor: having anonymouse user \n");
    } 
    else                      { 
      //EndpointLog ( "DanteDummyUserIdentity: userName is: ".print_r ($userName, true)." \n");
      $result = $userFactory->newFromName( $userName ); 

      //EndpointLog ( "DanteDummyUserIdentity: after newFromName call \n");
      if ($result === null) {
        //EndpointLog ("DanteDummyUserIdentity: userName was invalid, obtained null user, constructing anonymous user\n");
       $result = $userFactory->newAnonymous( ); 
        //EndpointLog ("DanteDummyUserIdentity: userName was invalid, obtained an anonymous user, who is: \n");
        //EndpointLog ("DanteDummyUserIdentity: anonymous user is: " .print_r ($result, true). " \n");

      }
      else {
        //EndpointLog ("DanteDummyUserIdentity: userName was invalid, obtained a user\n");
      }

    }

    $this->user = $result;  // only now, that we are guaranteed to have a correct value, assign the class variable (otherwise we might get a PHP typecheck error)

    //EndpointLog ( "DanteDummyUserIdentity: Found user: " . $this->user->getName(). "\n");
    //EndpointLog ( "DanteDummyUserIdentity: Is registered: " . ($this->user->isRegistered() ? "yes" : "no" ). "\n");
    //EndpointLog ( "DanteDummyUserIdentity: Is anon: " . ($this->user->isAnon() ? "yes" : "no" ). "\n\n");
  }

  public function getId( $wikiId = self::LOCAL ): int   { return $this->user->getId();   }
  public function getName(): string                     { return $this->user->getName(); }

  public function equals( ?MediaWiki\User\UserIdentity $user ): bool { if ($user === null) {return false;} else { return ($this->user->getId() === $user->getId()); }  }

  public function isRegistered(): bool                 { return $this->user->isRegistered(); ; }

  // implementation as required in WikiAwareEntity
  public function assertWiki( $wikiId ) { if ( $wikiId != $this->getWikiId() ) { throw new Exception ("DanteDummyUserIdentity found $wikiId different from getWikiId() "); } }
  
  public function getWikiId() {return self::LOCAL;}
}




