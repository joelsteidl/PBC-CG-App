<?php

namespace Drupal\Tests\symfony_mailer\Kernel;

/**
 * Tests basic module functions.
 *
 * @group symfony_mailer
 */
class BasicTest extends SymfonyMailerKernelTestBase {

  /**
   * Basic email sending test.
   */
  public function testEmail() {
    // Test email error.
    $this->emailFactory->sendTypedEmail('symfony_mailer', 'test');
    $this->readMail();
    $this->assertError('An email must have a "To", "Cc", or "Bcc" header.');

    // Test email success.
    $this->emailFactory->sendTypedEmail('symfony_mailer', 'test', $this->addressTo);
    $this->readMail();
    $this->assertNoError();
    $this->assertSubject("Test email from Example");
    $this->assertTo($this->addressTo);
  }

}
