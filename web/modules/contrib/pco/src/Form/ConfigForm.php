<?php

namespace Drupal\pco\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Build PCO API admin form UI.
 */
class ConfigForm extends ConfigFormBase {

  const CONFIG_NAME = 'pco.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pco_api_settings_form';
  }

  /**
   * Returns this modules configuration object.
   */
  protected function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();

    $config = $this->getConfig();

    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PCO API Token'),
      '#default_value' => $config->get('token'),
      '#description' => $this->t('Your Planning Center API token.'),
    ];

    $form['base_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PCO API Base URL'),
      '#default_value' => $config->get('base_uri'),
      '#description' => $this->t('Include trailing slash.'),
      '#required' => TRUE,
    ];

    $form['secret'] = [
      '#type' => 'key_select',
      '#title' => $this->t('PCO API Secret'),
      '#default_value' => $config->get('secret'),
      '#description' => $this->t('Your Planning Center API secret.'),
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $values = $form_state->getValues();
    $config->set('secret', $values['secret']);
    $config->set('token', $values['token']);
    $config->set('base_uri', $values['base_uri']);
    $config->save();
  }

  /**
   * Returns this modules configuration object.
   */
  protected function getConfig() {
    return $this->config(self::CONFIG_NAME);
  }

}
