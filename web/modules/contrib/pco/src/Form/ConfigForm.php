<?php

declare(strict_types=1);

namespace Drupal\pco\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;

/**
 * Build PCO API admin form UI.
 */
class ConfigForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pco_api_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PCO API Token'),
      '#config_target' => 'pco.settings:token',
      '#description' => $this->t('Your Planning Center API token.'),
    ];

    $form['base_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PCO API Base URL'),
      '#config_target' => 'pco.settings:base_uri',
      '#description' => $this->t('Include trailing slash.'),
      '#required' => TRUE,
    ];

    $form['secret'] = [
      '#type' => 'key_select',
      '#title' => $this->t('PCO API Secret'),
      '#config_target' => 'pco.settings:secret',
      '#description' => $this->t('Your Planning Center API secret.'),
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

}
