<?php

namespace Drupal\pbc_groups\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class AccessDenied.
 *
 * @package Drupal\pbc_groups\Controller
 */
class AccessDenied extends ControllerBase {

  /**
   * Content.
   *
   * @return string
   *   Return Hello string.
   */
  public function content() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('You do not have access to the page you are trying to view.')
    ];
  }

}
