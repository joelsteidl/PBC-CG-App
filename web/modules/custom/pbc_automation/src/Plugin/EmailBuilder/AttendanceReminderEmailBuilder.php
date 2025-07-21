<?php

namespace Drupal\pbc_automation\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\Core\Url;

/**
 * Defines the Email Builder plug-in for attendance reminder emails.
 *
 * @EmailBuilder(
 *   id = "pbc_automation.attendance_reminder",
 *   sub_types = {},
 *   common_adjusters = {"email_subject", "email_body"},
 *   import = @Translation("Attendance Reminder"),
 * )
 */
class AttendanceReminderEmailBuilder extends EmailBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function createParams(EmailInterface $email, $user = NULL, $attendance = NULL) {
    assert($user != NULL);
    assert($attendance != NULL);
    $email->setParam('user', $user);
    $email->setParam('attendance', $attendance);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    $user = $email->getParam('user');
    $attendance = $email->getParam('attendance');

    $email->setSubject($attendance->getTitle());

    // Create login URL with destination
    $options = [
      'absolute' => TRUE,
      'query' => [
        'destination' => 'node/' . $attendance->id(),
      ],
    ];
    $url = Url::fromRoute('user.login', [], $options);

    // Set the body using the render array approach
    $email->setBody([
      '#theme' => 'symfony_mailer__pbc_automation__attendance_reminder',
      '#user_name' => $user->field_first_name->getString(),
      '#login_url' => $url->toString(),
    ]);
  }

}
