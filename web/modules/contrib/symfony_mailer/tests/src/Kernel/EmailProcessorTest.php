<?php

namespace Drupal\Tests\symfony_mailer\Kernel;

/**
 * Tests EmailProcessor plug-ins.
 *
 * @group symfony_mailer
 */
class EmailProcessorTest extends SymfonyMailerKernelTestBase {

  /**
   * Inline CSS adjuster test.
   */
  public function testInlineCss() {
    // Test an email including the test library.
    $this->emailFactory->newTypedEmail('symfony_mailer', 'test', $this->addressTo)->addLibrary('symfony_mailer_test/inline_css_test')->send();
    $this->readMail();
    $this->assertNoError();
    // The inline CSS from inline.text-small.css should appear.
    $this->assertBodyContains('<h4 class="text-small" style="padding-top: 3px; padding-bottom: 3px; text-align: center; color: white; background-color: #0678be; font-size: smaller; font-weight: bold;">');
    // The imported CSS from inline.day.css should appear.
    $this->assertBodyContains('<span class="day" style="font-style: italic;">');
  }

}
