<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Core\Access\AccessResult;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;

/**
 * Defines the Attachment access Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "mailer_attachment_access",
 *   label = @Translation("Attachment access"),
 *   description = @Translation("Grant basic attachment access."),
 *   automatic = TRUE,
 *   weight = 600,
 * )
 */
class AttachmentAccessEmailAdjuster extends EmailAdjusterBase {

  /**
   * The allowed schemes for the attachment URI.
   *
   * The value is a boolean:
   * - TRUE: check the URI can be opened.
   * - FALSE: no checking required.
   *
   * @var array
   */
  protected const ALLOWED_SCHEMES = [
    'http' => TRUE,
    'https' => TRUE,
    'public' => FALSE,
    '_data_' => FALSE,
  ];

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email) {
    foreach ($email->getAttachments() as $attachment) {
      $uri = $attachment->getUri();
      $scheme = $uri ? parse_url($uri, PHP_URL_SCHEME) : '_data_';
      $check = self::ALLOWED_SCHEMES[$scheme] ?? NULL;

      if (!is_null($check)) {
        if ($check) {
          $handle = @fopen($uri, 'r');
          if (!$handle) {
            continue;
          }
          fclose($handle);
        }
        $attachment->setAccess(AccessResult::allowed());
      }
    }
  }

}
