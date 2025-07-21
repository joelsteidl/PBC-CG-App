<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\SubscriberInterface;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;

/**
 * Defines the Email Builder plug-in for simplenews_newsletter entity.
 *
 * @EmailBuilder(
 *   id = "simplenews_newsletter",
 *   sub_types = {
 *     "node" = @Translation("Issue"),
 *   },
 *   has_entity = TRUE,
 *   override = {"simplenews.node", "simplenews.test", "simplenews.extra"},
 *   common_adjusters = {"email_subject", "email_from"},
 *   import = @Translation("Simplenews newsletter settings"),
 *   form_alter = {
 *     "*" = {
 *       "remove" = {
 *         "email",
 *         "simplenews_sender_information",
 *         "simplenews_subject"
 *       },
 *       "entity_sub_type" = "node",
 *     },
 *   },
 * )
 *
 * @todo Notes for adopting Symfony Mailer into simplenews. Can remove the
 * MailBuilder class, and many methods of MailEntity.
 */
class SimplenewsNewsletterEmailBuilder extends SimplenewsEmailBuilderBase {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\Core\Entity\ContentEntityInterface $issue
   *   The newsletter issue to send.
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber.
   * @param bool|string $mode
   *   (Optional) The mode of sending: test, extra or node.
   */
  public function createParams(EmailInterface $email, ?ContentEntityInterface $issue = NULL, ?SubscriberInterface $subscriber = NULL, $mode = NULL) {
    assert($subscriber != NULL);
    if ($mode === TRUE) {
      @trigger_error('Passing TRUE to SimplenewsNewsletterEmailBuilder::createParams() is deprecated in symfony_mailer:1.4.1 and is removed from symfony_mailer:2.0.0. Instead pass "test" or "extra". See https://www.drupal.org/node/3414408', E_USER_DEPRECATED);
      $mode = 'test';
    }
    $email->setParam('issue', $issue)
      ->setParam('simplenews_subscriber', $subscriber)
      ->setParam('newsletter', $issue->simplenews_issue->entity)
      ->setParam($issue->getEntityTypeId(), $issue)
      ->setVariable('mode', $mode);
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    $mail = $message['params']['simplenews_mail'];
    $mode = $mail->getKey();
    return $factory->newEntityEmail($mail->getNewsletter(), 'node', $mail->getIssue(), $mail->getSubscriber(), $mode);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    parent::build($email);
    $mode = $email->getVariables()['mode'];
    $temp_subscriber = $mode && !$email->getParam('simplenews_subscriber')->id();
    $email->setBodyEntity($email->getParam('issue'), 'email_html')
      ->addTextHeader('Precedence', 'bulk')
      ->setVariable('opt_out_hidden', !$email->getEntity()->isAccessible() || $temp_subscriber)
      ->setVariable('reason', $email->getParam('newsletter')->reason ?? '')
      // @deprecated
      ->setVariable('test', $mode == 'test');

    // @todo Create SubscriberInterface::getUnsubscribeUrl().
    if ($unsubscribe_url = \Drupal::token()->replace('[simplenews-subscriber:unsubscribe-url]', $email->getParams(), ['clear' => TRUE])) {
      $email->addTextHeader('List-Unsubscribe', "<$unsubscribe_url>");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $helper = $this->helper();

    $settings = $this->helper()->config()->get('simplenews.settings');
    $from = new Address($settings->get('newsletter.from_address'), $settings->get('newsletter.from_name'));
    $config['email_from'] = $helper->policyFromAddresses([$from]);
    $config['email_subject']['value'] = '[[simplenews-newsletter:name]] [node:title]';
    MailerPolicy::import('simplenews_newsletter', $config);

    foreach (Newsletter::loadMultiple() as $id => $newsletter) {
      $from = new Address($newsletter->from_address, $newsletter->from_name);
      $config['email_from'] = $helper->policyFromAddresses([$from]);
      $config['email_subject']['value'] = $newsletter->subject;
      MailerPolicy::import("simplenews_newsletter.node.$id", $config);
    }
  }

}
