<?php

namespace Drupal\sendgrid_integration\Plugin\Mail;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Html2Text\Html2Text;
use SendGrid\Client;
use SendGrid\Exception\SendgridException;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Bcc;
use SendGrid\Mail\Cc;
use SendGrid\Mail\ClickTracking;
use SendGrid\Mail\From;
use SendGrid\Mail\Mail;
use SendGrid\Mail\OpenTracking;
use SendGrid\Mail\Personalization;
use SendGrid\Mail\ReplyTo;
use SendGrid\Mail\SandBoxMode;
use SendGrid\Mail\SpamCheck;
use SendGrid\Mail\To;
use SendGrid\Mail\TrackingSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @file
 * Implements Drupal MailSystemInterface.
 *
 * @Mail(
 *   id = "sendgrid_integration",
 *   label = @Translation("Sendgrid Integration"),
 *   description = @Translation("Sends the message using Sendgrid API.")
 * )
 */
class SendGridMail implements MailInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  const SENDGRID_INTEGRATION_EMAIL_REGEX = '/^\s*"?(.+?)"?\s*<\s*([^>]+)\s*>$/';

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger service for the sendgrid_integration module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;


  /**
   * The MIME Type Guesser service.
   *
   * @var \Drupal\Core\File\MimeType\MimeTypeGuesser
   */
  protected $mimeTypeGuesser;

  /**
   * SendGridMailSystem constructor.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $loggerChannelFactory, ModuleHandlerInterface $moduleHandler, QueueFactory $queueFactory) {
    $this->configFactory = $configFactory;
    $this->logger = $loggerChannelFactory->get('sendgrid_integration');
    $this->moduleHandler = $moduleHandler;
    $this->queueFactory = $queueFactory;
    $this->mimeTypeGuesser = \Drupal::service('file.mime_type.guesser');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('module_handler'),
      $container->get('queue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $message): array {
    // Join message array.
    $message['body'] = implode("\n\n", $message['body']);

    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message): bool {
    // Set mail to false by default.
    $mail = FALSE;
    try {
      // The doMail function returns a boolean.
      $mail = $this->doMail($message);
    }
      // Log the exception.
    catch (SendgridException $e) {
      $this->logger->error($e->getMessage());
    }
    return $mail;
  }

  /**
   * Worker method for ::mail.
   *
   * @param array $message
   *   The message array.
   *
   * @return bool
   *   True if the message is sent.
   *
   * @throws \SendGrid\Exception\SendgridException
   */
  protected function doMail(array $message): bool {
    // Begin by creating instances of objects needed.
    $sendgrid_message = new Mail();
    $personalization0 = $sendgrid_message->getPersonalization();
    $sandbox_mode = new SandBoxMode();

    $site_config = $this->configFactory->get('system.site');
    $sendgrid_config = $this->configFactory->get('sendgrid_integration.settings');

    if (isset($message['params']['apikey'])) {
      $key_secret = $message['params']['apikey'];
      unset($message['apikey']);
    }
    else {
      $key_secret = $sendgrid_config->get('apikey');
      if ($this->moduleHandler->moduleExists('key')) {
        $key = \Drupal::service('key.repository')->getKey($key_secret);
        if ($key) {
          $key_value = $key->getKeyValue();
          if ($key_value) {
            $key_secret = $key_value;
          }
        }
      }
    }

    if (empty($key_secret)) {
      // Set a error in the logs if there is no API key.
      $this->logger->error('No API Secret key has been set');
      // Return false to indicate message was not able to send.
      return FALSE;
    }
    $options = [
      'turn_off_ssl_verification' => FALSE,
      'protocol' => 'https',
      'port' => NULL,
      'url' => NULL,
      'raise_exceptions' => FALSE,
    ];
    // Create a new SendGrid object.
    $client = new Client($key_secret, $options);

    $sitename = $site_config->get('name');
    if (!mb_check_encoding($sitename, 'ASCII')) {
      $sitename = urlencode($sitename);
    }

    // If this is a password reset. Bypass spam filters.
    if (strpos($message['id'], 'password')) {
      $spam_check = new SpamCheck();
      $spam_check->setEnable(FALSE);
    }
    // If this is a Drupal Commerce message. Bypass spam filters.
    if (strpos($message['id'], 'commerce')) {
      $spam_check = new SpamCheck();
      $spam_check->setEnable(FALSE);
    }

    # Add UID metadata to the message that matches the drupal user ID.
    if (isset($message['params']['account']->uid)) {
      /** @var \Drupal\user\Entity\User $mailuser */
      $mailuser = $message['params']['account'];
      $uid = $mailuser->get('uid')->value;
      $sendgrid_message->addCustomArg("uid", strval($uid));
    }

    // Checking if 'From' email-address already exists.
    if (isset($message['headers']['From'])) {
      $fromaddrarray = $this->parseAddress($message['headers']['From']);
      $data['from'] = $fromaddrarray[0];
      $data['fromname'] = isset($fromaddrarray[1]) ? strval($fromaddrarray[1]) : strval($sitename);
      unset($fromaddrarray);
    }
    else {
      $data['from'] = $site_config->get('mail');
      $data['fromname'] = strval($sitename);
    }

    // Check if $send is set to be true.
    if ($message['send'] != 1) {
      $this->logger->notice('Email was not sent because send value was disabled.');
      return TRUE;
    }
    // Build the Sendgrid mail object.
    // The message MODULE and ID is used for the Category. Category is the only
    // thing in the Sendgrid UI you can use to sort mail.
    // This is an array of categories for Sendgrid statistics.
    $categories = [
      $sitename,
      $message['module'],
      $message['id'],
    ];

    // Allow other modules to modify categories.
    $result = $this->moduleHandler->invokeAll('sendgrid_integration_categories_alter', [
      $message,
      $categories,
    ]);

    // Check if we got any variable back.
    if (!empty($result)) {
      $categories = $result;
    }

    $sendgrid_message->addCategories($categories);
    $personalization0->setSubject((string) $message['subject']);
    $from = new From($data['from'], $data['fromname']);
    unset($data);
    # Set the from address and add a name if it exists.
    $sendgrid_message->setFrom($from);

    // If there are multiple recipients we have to explode and walk the values.
    if (strpos($message['to'], ',')) {
      $sendtosarry = explode(',', $message['to']);
      foreach ($sendtosarry as $value) {
        // Remove unnecessary spaces.
        $value = trim($value);
        $sendtoarrayparsed = $this->parseAddress($value);
        $personalization0->addTo(new To($sendtoarrayparsed[0], isset($sendtoarrayparsed[1]) ? $sendtoarrayparsed[1] : NULL));
      }
    }
    else {
      $toaddrarray = $this->parseAddress($message['to']);
      if (isset($toaddrarray[1])) {
        $toname = $toaddrarray[1];
      }
      else {
        $toname = NULL;
      }
      $personalization0->addTo(new To($toaddrarray[0], $toname));
    }

    // Empty array to process addresses from mail headers.
    $address_cc_bcc = [];

    // Beginning of consolidated header parsing.
    foreach ($message['headers'] as $key => $value) {
      switch (mb_strtolower($key)) {
        case 'content-type':
          // Parse several values on the Content-type header, storing them in an array like
          // key=value -> $vars['key']='value'.
          $vars = explode(';', $value);
          foreach ($vars as $i => $var) {
            if ($cut = strpos($var, '=')) {
              $new_var = trim(mb_strtolower(mb_substr($var, $cut + 1)));
              $new_key = trim(mb_substr($var, 0, $cut));
              unset($vars[$i]);
              $vars[$new_key] = $new_var;
            }
          }
          // If $vars is empty then set an empty value at index 0 to avoid a PHP warning in the next statement.
          $vars[0] = isset($vars[0]) ? $vars[0] : '';
          // Nested switch to process the various content types. We only care
          // about the first entry in the array.
          switch ($vars[0]) {
            case 'text/plain':
              // The message includes only a plain text part.
              $sendgrid_message->addContent('text/plain', MailFormatHelper::wrapMail(MailFormatHelper::htmlToText($message['body'])));
              break;

            case 'text/html':
              // Ensure body is a string before using it as HTML.
              $body = $message['body'];
              if ($body instanceof MarkupInterface) {
                $body = $body->__toString();
              }

              // The message includes only an HTML part.
              $sendgrid_message->addContent('text/html', $body);

              // Also include a text only version of the email.
              $converter = new Html2Text($message['body']);
              $body_plain = $converter->getText();
              $sendgrid_message->addContent('text/plain', MailFormatHelper::wrapMail($body_plain));
              break;


            case 'multipart/alternative':
              // Get the boundary ID from the Content-Type header.
              $boundary = $this->getSubString($message['body'], 'boundary', '"', '"');

              // Parse text and HTML portions.
              // Split the body based on the boundary ID.
              $body_parts = $this->boundrySplit($message['body'], $boundary);
              foreach ($body_parts as $body_part) {
                // If plain/text within the body part, add it to $mailer->AltBody.
                if (strpos($body_part, 'text/plain')) {
                  // Clean up the text.
                  $body_part = trim($this->removeHeaders(trim($body_part)));
                  // Include it as part of the mail object.
                  $sendgrid_message->addContent('text/plain', MailFormatHelper::wrapMail(MailFormatHelper::htmlToText($body_part)));
                }
                // If plain/html within the body part, add it to $mailer->Body.
                elseif (strpos($body_part, 'text/html')) {
                  // Clean up the text.
                  $body_part = trim($this->removeHeaders(trim($body_part)));
                  // Include it as part of the mail object.
                  $sendgrid_message->addContent('text/html', $body_part);
                }
              }
              break;

            case 'multipart/mixed':
              // Get the boundary ID from the Content-Type header.
              $boundary = $this->getSubString($value, 'boundary', '"', '"');
              // Split the body based on the boundary ID.
              $body_parts = $this->boundrySplit($message['body'], $boundary);

              // Parse text and HTML portions.
              foreach ($body_parts as $body_part) {
                if (strpos($body_part, 'multipart/alternative')) {
                  // Get the second boundary ID from the Content-Type header.
                  $boundary2 = $this->getSubString($body_part, 'boundary', '"', '"');
                  // Clean up the text.
                  $body_part = trim($this->removeHeaders(trim($body_part)));
                  // Split the body based on the internal boundary ID.
                  $body_parts2 = $this->boundrySplit($body_part, $boundary2);

                  // Process the internal parts.
                  foreach ($body_parts2 as $body_part2) {
                    // If plain/text within the body part, add it to $mailer->AltBody.
                    if (strpos($body_part2, 'text/plain')) {
                      // Clean up the text.
                      $body_part2 = trim($this->removeHeaders(trim($body_part2)));
                      $sendgrid_message->addContent('text/plain', MailFormatHelper::wrapMail(MailFormatHelper::htmlToText($body_part2)));
                    }
                    // If plain/html within the body part, add it to $mailer->Body.
                    elseif (strpos($body_part2, 'text/html')) {
                      // Get the encoding.
                      $body_part2_encoding = trim($this->getSubString($body_part2, 'Content-Transfer-Encoding', ':', "\n"));
                      // Clean up the text.
                      $body_part2 = trim($this->removeHeaders(trim($body_part2)));
                      // Check whether the encoding is base64, and if so, decode it.
                      if (mb_strtolower($body_part2_encoding) == 'base64') {
                        // Save the decoded HTML content.
                        $sendgrid_message->addContent('text/html', base64_decode($body_part2));
                      }
                      else {
                        // Save the HTML content.
                        $sendgrid_message->addContent('text/html', $body_part2);
                      }
                    }
                  }
                }
                else {
                  // This parses the message if there is no internal content
                  // type set after the multipart/mixed.
                  // If text/plain within the body part, add it to $mailer->Body.
                  if (strpos($body_part, 'text/plain')) {
                    // Clean up the text.
                    $body_part = trim($this->removeHeaders(trim($body_part)));
                    // Set the text message.
                    $sendgrid_message->addContent('text/plain', MailFormatHelper::wrapMail(MailFormatHelper::htmlToText($body_part)));
                  }
                  // If text/html within the body part, add it to $mailer->Body.
                  elseif (strpos($body_part, 'text/html')) {
                    // Clean up the text.
                    $body_part = trim($this->removeHeaders(trim($body_part)));
                    // Set the HTML message.
                    $sendgrid_message->addContent('text/html', $body_part);
                  }
                }
              }
              break;

            default:
              // Everything else is unknown so we log and send the message as text.
              \Drupal::messenger()
                ->addError($this->t('The %header of your message is not supported by SendGrid and will be sent as text/plain instead.', ['%header' => "Content-Type: $value"]));
              $this->logger->error("The Content-Type: $value of your message is not supported by PHPMailer and will be sent as text/plain instead.");
              // Force the email to be text.
              $sendgrid_message->addContent('text/plain', MailFormatHelper::wrapMail(MailFormatHelper::htmlToText($message['body'])));
          }
          break;
        // End Content-type parsing

        case 'reply-to':
          $sendreplyto = $this->parseAddress($message['headers'][$key]);
          $reply_to = new ReplyTo($sendreplyto[0], isset($sendreplyto[1]) ? $sendreplyto[1] : NULL);
          $sendgrid_message->setReplyTo($reply_to);
          break;
      }

      // -----------------------
      // BCC and CC Address Handling
      // -----------------------
      // Array to use for processing bcc and cc options.
      $cc_bcc_keys = ['cc', 'bcc'];

      // Handle latter case issue for cc and bcc key.
      if (in_array(mb_strtolower($key), $cc_bcc_keys)) {
        $mail_ids = explode(',', $value);
        foreach ($mail_ids as $mail_id) {
          $mail_id = trim($mail_id);
          $email_components = $this->parseAddress($mail_id);
          $mail_cc_address = $email_components[0];
          // If there was a name with the mail,
          // use it otherwise, use the email address as the name.
          $cc_name = $email_components[1] ?? $email_components[0];
          $address_cc_bcc[mb_strtolower($key)][] = [
            'mail' => $mail_cc_address,
            'name' => $cc_name,
          ];
        }
      }
    }
    if (isset($address_cc_bcc) && array_key_exists('cc', $address_cc_bcc)) {
      foreach ($address_cc_bcc['cc'] as $item) {
        $personalization0->addCc(new Cc($item['mail'], $item['name']));
      }
    }
    if (isset($address_cc_bcc) && array_key_exists('bcc', $address_cc_bcc)) {
      foreach ($address_cc_bcc['bcc'] as $item) {
        $personalization0->addBcc(new Bcc($item['mail'], $item['name']));
      }
    }
    // -----------------------
    // END - BCC and CC Address Handling
    // -----------------------

    // Prepare message attachments and params attachments.
    if (isset($message['attachments']) && !empty($message['attachments'])) {
      foreach ($message['attachments'] as $attachmentitem) {
        if (is_file($attachmentitem)) {
          try {
            $attach = new Attachment();
            $struct = $this->getAttachmentStruct($attachmentitem);
            $attach->setContent($struct['content']);
            $attach->setType($struct['type']);
            $attach->setFilename($struct['filename']);
            $attach->setDisposition("attachment");
            $sendgrid_message->addAttachment($attach);
          }
          catch (SendgridException $e) {
            $message = Xss::filter($e->getMessage());
            $this->logger->error('Attachment processing failed' . $message);
          }
          catch (\Exception $e) {
            $this->logger->error('Attachment processing failed' . $e->getMessage());
          }


        }
      }
    }

    // The Mime Mail module (mimemail) expects attachments as an array of file
    // arrays in $message['params']['attachments']. As many modules assume you
    // will be using Mime Mail to handle attachments, we need to parse this
    // array as well.
    if (isset($message['params']['attachments']) && !empty($message['params']['attachments'])) {
      foreach ($message['params']['attachments'] as $attachment) {
        $attach = new Attachment();
        if (isset($attachment['filepath'])) {
          $attachment_path = \Drupal::service('file_system')
            ->realpath($attachment['filepath']);
          if (is_file($attachment_path)) {
            try {
              $struct = $this->getAttachmentStruct($attachment_path);
              // Allow for customised filenames.
              if (!empty($attachment['filename'])) {
                $struct['name'] = $attachment['filename'];
              }
              $attach->setContent($struct['content']);
              $attach->setType($struct['type']);
              $attach->setFilename($struct['filename']);
              $attach->setDisposition("attachment");
            }
            catch (SendgridException $e) {
              $json = json_decode(Xss::filter($e->getMessage()));
              if ($e instanceof SendgridException) {
                $this->logger->error(json_encode($json, JSON_PRETTY_PRINT));
              }
            }
            catch (\Exception $e) {
              $this->logger->error('Error processing attachments' . $e->getMessage());
            }
            $sendgrid_message->addAttachment($attach);
          }
        }
        // Support attachments that are directly included without a file in the
        // filesystem.
        elseif (isset($attachment['filecontent'])) {
          $attach->setFilename($attachment['filename']);
          $attach->setType($attachment['filemime']);
          $attach->setContent(base64_encode($attachment['filecontent']));
          $sendgrid_message->addAttachment($attach);
        }
      }
      // Remove the file objects from $message['params']['attachments'].
      // (This prevents double-attaching in the drupal_alter hook below.)
      unset($message['params']['attachments']);
    }

    // Add template ID.
    if (isset($message['sendgrid']['template_id'])) {
      $sendgrid_message->setTemplateId($message['sendgrid']['template_id']);
    }

    // Add substitutions.
    if (isset($message['sendgrid']['substitutions'])) {
      foreach ($message['sendgrid']['substitutions'] as $key => $value) {
        $personalization0->addSubstitution($key, $value);
      }
    }

    // Tracking settings.
    $tracking_settings = new TrackingSettings();
    $click_tracking = new ClickTracking();
    if (!empty($sendgrid_config->get('trackclicks'))) {
      $click_tracking->setEnable(TRUE);
      $click_tracking->setEnableText(TRUE);
    }
    else {
      $click_tracking->setEnable(FALSE);
      $click_tracking->setEnableText(FALSE);
    }
    $tracking_settings->setClickTracking($click_tracking);
    $open_tracking = new OpenTracking();
    if (!empty($sendgrid_config->get('trackopens'))) {
      $open_tracking->setEnable(TRUE);
    }
    else {
      $open_tracking->setEnable(FALSE);
    }
    $tracking_settings->setOpenTracking($open_tracking);
    $sendgrid_message->setTrackingSettings($tracking_settings);

    // Allow other modules to alter the Mandrill message.
    $sendgrid_params = [
      'message' => $sendgrid_message,
    ];
    \Drupal::moduleHandler()
      ->alter('sendgrid_integration', $sendgrid_params, $message);

    // Lets try and send the message and catch the error.
    try {
      $response = $client->send($sendgrid_params['message']);
    }
    catch (SendgridException $e) {
      $this->logger->error('Sending emails to Sendgrid service failed with error code ' . Xss::filter($e->getCode()));
      $json = json_decode(Xss::filter($e->getMessage()));
      if ($e instanceof SendgridException) {
        $this->logger->error(json_encode($json, JSON_PRETTY_PRINT));
      }
      else {
        $this->logger->error($e->getMessage());
      }
      // Add message to queue if reason for failing was timeout or
      // another valid reason. This adds more error tolerance.
      $codes = [
        -110,
        404,
        408,
        500,
        502,
        503,
        504,
      ];
      if (in_array($e->getCode(), $codes)) {
        $this->queueFactory->get('SendGridResendQueue')->createItem($message);
      }
      return FALSE;
    }
    // Creating hook, allowing other modules react on sent email.
    $hook_args = [$message['to'], $response];
    $this->moduleHandler->invokeAll('sendgrid_integration_sent', $hook_args);
    $good_response_codes = [
      200,
      202,
    ];
    if (in_array($response->getCode(), $good_response_codes)) {
      // In the special case the email is coming from the module test we log
      // an info level message.
      if (isset($message['key']) && $message['key'] == 'sengrid_integration_troubleshooting_test') {
        $this->logger->info('Troubleshooting test email has been sent to %address.',
          ['%address' => $message['to']]);
      }
      // If the code is 200 we are good to finish and proceed.
      return TRUE;
    }
    // Default to low. Sending failed.
    $this->logger->error('Sending emails to Sendgrid service failed with error message %message.',
      ['%message' => 'Response Code: ' . $response->getCode() . "\n" . $response->getBody()]);
    return FALSE;
  }

  /**
   * Split an email address into it's name and address components.
   * Returns an array with the first element as the email address and the
   * second element as the name.
   *
   * @param $email string
   *
   * @return mixed
   */
  protected function parseAddress(string $email) {
    if (preg_match(self::SENDGRID_INTEGRATION_EMAIL_REGEX, $email, $matches)) {
      return [$matches[2], strval($matches[1])];
    }
    else {
      return [$email];
    }
  }

  /**
   * Returns a string that is contained within another string.
   *
   * Returns the string from within $source that is some where after $target
   * and is between $beginning_character and $ending_character.
   *
   * Swiped from SMTP module. Thanks!
   *
   * @param string $source
   *   A string containing the text to look through.
   * @param string $target
   *   A string containing the text in $source to start looking from.
   * @param string $beginning_character
   *   A string containing the character just before the sought after text.
   * @param string $ending_character
   *   A string containing the character just after the sought after text.
   *
   * @return string
   *   A string with the text found between the $beginning_character and the
   *   $ending_character.
   */
  protected function getSubString(string $source, string $target, string $beginning_character, string $ending_character): string {
    $search_start = strpos($source, $target) + 1;
    $first_character = strpos($source, $beginning_character, $search_start) + 1;
    $second_character = strpos($source, $ending_character, $first_character) + 1;
    $substring = mb_substr($source, $first_character, $second_character - $first_character);
    $string_length = mb_strlen($substring) - 1;

    if ($substring[$string_length] == $ending_character) {
      $substring = mb_substr($substring, 0, $string_length);
    }

    return $substring;
  }

  /**
   * Splits the input into parts based on the given boundary.
   *
   * Swiped from Mail::MimeDecode, with modifications based on Drupal's coding
   * standards and this bug report: http://pear.php.net/bugs/bug.php?id=6495
   *
   * @param string $input
   *   A string containing the body text to parse.
   * @param string $boundary
   *   A string with the boundary string to parse on.
   *
   * @return array
   *   An array containing the resulting mime parts
   */
  protected function boundrySplit(string $input, string $boundary): array {
    $parts = [];
    $bs_possible = mb_substr($boundary, 2, -2);
    $bs_check = '\"' . $bs_possible . '\"';

    if ($boundary == $bs_check) {
      $boundary = $bs_possible;
    }

    $tmp = explode('--' . $boundary, $input);

    for ($i = 1; $i < count($tmp); $i++) {
      if (trim($tmp[$i])) {
        $parts[] = $tmp[$i];
      }
    }

    return $parts;
  }

  /**
   * Strips the headers from the body part.
   *
   * @param string $input
   *   A string containing the body part to strip.
   *
   * @return string
   *   A string with the stripped body part.
   */
  protected function removeHeaders(string $input): string {
    $part_array = explode("\n", $input);

    // Will strip these headers according to RFC2045.
    $headers_to_strip = [
      'Content-Type',
      'Content-Transfer-Encoding',
      'Content-ID',
      'Content-Disposition',
    ];
    $pattern = '/^(' . implode('|', $headers_to_strip) . '):/';

    while (count($part_array) > 0) {

      // Ignore trailing spaces/newlines.
      $line = rtrim($part_array[0]);

      // If the line starts with a known header string.
      if (preg_match($pattern, $line)) {
        $line = rtrim(array_shift($part_array));
        // Remove line containing matched header.
        // If line ends in a ';' and the next line starts with four spaces, it's a continuation
        // of the header split onto the next line. Continue removing lines while we have this condition.
        while (substr($line, -1) == ';' && count($part_array) > 0 && substr($part_array[0], 0, 4) == '    ') {
          $line = rtrim(array_shift($part_array));
        }
      }
      else {
        // No match header, must be past headers; stop searching.
        break;
      }
    }

    return implode("\n", $part_array);
  }

  /**
   * Return an array structure for a message attachment.
   *
   * @param string $path
   *   Attachment path.
   *
   * @return array
   *   Attachment structure.
   *
   * @throws \Exception
   */
  public function getAttachmentStruct(string $path): array {
    $struct = [];
    if (!@is_file($path)) {
      throw new \Exception($path . ' is not a valid file.');
    }
    $filename = basename($path);
    $file_content = base64_encode(file_get_contents($path));
    $mime_type = $this->mimeTypeGuesser->guessMimeType($path);
    if (!$this->isValidContentType($mime_type)) {
      throw new \Exception($mime_type . ' is not a valid content type.');
    }
    $struct['type'] = $mime_type;
    $struct['filename'] = $filename;
    $struct['content'] = $file_content;
    return $struct;
  }

  /**
   * Helper to determine if an attachment is valid.
   *
   * @param string $file_type
   *   The file mime type.
   *
   * @return bool
   *   True or false.
   */
  protected function isValidContentType(string $file_type): bool {
    $valid_types = [
      'image/',
      'text/',
      'application/pdf',
      'application/x-zip',
      'application/xml',
    ];
    // Allow modules to alter the valid types array.
    \Drupal::moduleHandler()
      ->alter('sendgrid_integration_valid_attachment_types', $valid_types);
    foreach ($valid_types as $vct) {
      if (strpos($file_type, $vct) !== FALSE) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
