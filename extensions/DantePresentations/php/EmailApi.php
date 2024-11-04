class ApiDoubleClickEmailSection extends ApiBase {
  public function execute() {
    $user = $this->getUser();
    $pageTitle = $this->getParameter('pageTitle');
    $sectionName = $this->getParameter('sectionName');

    if ( !$user->isLogged() ) {
      $this->getResult()->addValue( null, 'error', 'User must be logged in' );
      return;
    }

    $email = $user->getEmail();
    if ( empty( $email ) ) {
      $this->getResult()->addValue( null, 'error', 'User has no email set' );
      return;
    }

    // Send email
    $subject = "Double Clicked Page: " . $pageTitle;
    $body = "You double-clicked on the page: " . $pageTitle . "\n" .
            "Section clicked: " . $sectionName;
    $result = $this->sendEmail($email, $subject, $body);

    if ( $result ) {
      $this->getResult()->addValue( null, 'result', 'Email sent successfully' );
    } else {
      $this->getResult()->addValue( null, 'error', 'Failed to send email' );
    }
  }

  private function sendEmail( $to, $subject, $body ) {
    // Mail sending logic here
    return Mail::send( $to, $subject, $body );
  }

  public function getAllowedParams() {
    return [
      'pageTitle' => [ ApiBase::PARAM_REQUIRED => true ],
      'sectionName' => [ ApiBase::PARAM_REQUIRED => true ],
    ];
  }
}