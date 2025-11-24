<?php

declare(strict_types=1);

namespace Drupal\Tests\pco\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Functional tests for the PCO configuration page.
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
  public function testConfigPageLoads(): void {
    $this->drupalGet(Url::fromRoute('pco.settings'));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the config page contains expected form elements.
   */
  public function testConfigPageFormElements(): void {
    $this->drupalGet(Url::fromRoute('pco.settings'));
    $this->assertSession()->fieldExists('token');
    $this->assertSession()->fieldExists('secret');
    $this->assertSession()->fieldExists('base_uri');
    $this->assertSession()->buttonExists('Save configuration');
  }

  /**
   * Tests that the config page is accessible only to authorized users.
   */
  public function testConfigPageAccessControl(): void {
    // Create a user without permission.
    $unprivileged_user = $this->drupalCreateUser();
    $this->drupalLogout();
    $this->drupalLogin($unprivileged_user);

    $this->drupalGet(Url::fromRoute('pco.settings'));
    $this->assertSession()->statusCodeEquals(403);
  }

}
