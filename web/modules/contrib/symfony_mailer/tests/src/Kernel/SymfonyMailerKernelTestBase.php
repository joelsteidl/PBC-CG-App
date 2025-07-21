<?php

namespace Drupal\Tests\symfony_mailer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\RandomGeneratorTrait;
use Drupal\symfony_mailer_test\MailerTestTrait;

/**
 * Tests basic email sending.
 *
 * @group symfony_mailer
 */
abstract class SymfonyMailerKernelTestBase extends KernelTestBase {

  use MailerTestTrait;
  use RandomGeneratorTrait;

  /**
   * Email address for the tests.
   *
   * @var string
   */
  protected string $addressTo = 'symfony-mailer-to@example.com';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['symfony_mailer', 'symfony_mailer_test', 'system', 'user', 'filter'];

  /**
   * The email factory.
   *
   * @var \Drupal\symfony_mailer\EmailFactoryInterface
   */
  protected $emailFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['symfony_mailer']);
    $this->installEntitySchema('user');
    $this->emailFactory = $this->container->get('email_factory');
    $this->config('system.site')
      ->set('name', 'Example')
      ->set('mail', 'sender@example.com')
      ->save();
  }

}
