<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\symfony_mailer\Attachment;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;

/**
 * Defines the Email Builder plug-in for test mails.
 *
 * @EmailBuilder(
 *   id = "symfony_mailer",
 *   sub_types = { "test" = @Translation("Test email") },
 *   common_adjusters = {"email_subject", "email_body"},
 * )
 */
class TestEmailBuilder extends EmailBuilderBase {

  use TokenProcessorTrait;

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param mixed $to
   *   The to addresses, see Address::convert().
   */
  public function createParams(EmailInterface $email, $to = NULL) {
    if ($to) {
      // For back-compatibility, allow $to to be NULL.
      $email->setParam('to', $to);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email) {
    parent::init($email);
    if ($to = $email->getParam('to')) {
      $email->setTo($to);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $logo = \Drupal::service('extension.list.module')->getPath('symfony_mailer') . '/logo.png';
    $logo_uri = \Drupal::service('file_url_generator')->generateString($logo);

    // - Add a custom CSS library, defined in symfony_mailer.libraries.yml.
    // - The CSS is defined in test.email.css.
    // - Set variables, used by the mailer policy defined in
    //   symfony_mailer.mailer_policy.symfony_mailer.test.yml.
    // - Add an attachment.
    $email->addLibrary('symfony_mailer/test')
      ->attach(Attachment::fromPath($logo_uri, isUri: TRUE))
      ->setVariable('logo_url', $logo_uri)
      ->setVariable('day', date("l"));
  }

}
