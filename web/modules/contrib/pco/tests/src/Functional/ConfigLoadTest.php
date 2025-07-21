<?php

namespace Drupal\Tests\pco\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Simple test to ensure that the config page loads.
 *
 * @group pco
 */
class ConfigLoadTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['pco', 'key'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->drupalCreateUser(['administer pco']);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the config page loads with a 200 response.
   */
  public function testConfigLoad() {
    $this->drupalGet(Url::fromRoute('pco.settings'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
