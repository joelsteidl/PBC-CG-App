<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the To Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "email_to",
 *   label = @Translation("To"),
 *   description = @Translation("Sets the email to header."),
 * )
 */
class ToEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'to';

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email) {
    parent::build($email);
  }

}
