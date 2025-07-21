<?php

namespace Drupal\sendgrid_integration\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SendGridSettingsForm.
 *
 * @package Drupal\sendgrid_integration\Form
 */
class SendGridSettingsForm extends ConfigFormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sendgrid_integration_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sendgrid_integration.settings');

    $form['authentication'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Authentication'),
    ];

    $key_exists = $this->moduleHandler->moduleExists('key');

    $requirenewkey = TRUE;
    if (!$key_exists && !empty($config->get('apikey'))) {
      $form['authentication']['secretkeynotice'] = [
        '#markup' => $this->t('You have saved a secret key. You may change the key by inputing a new one in the field directly below.'),
      ];
      $requirenewkey = FALSE;
    }

    if ($key_exists) {
      $form['authentication']['sendgrid_integration_apikey'] = [
        '#type' => 'key_select',
        '#required' => TRUE,
        '#default_value' => $config->get('apikey'),
        '#title' => $this->t('API Secret Key'),
        '#description' => $this->t('The secret key of your key pair. These are only generated once by Sendgrid.'),
      ];
    }
    else {
      $form['authentication']['sendgrid_integration_apikey'] = [
        '#type' => 'password',
        '#required' => $requirenewkey,
        '#title' => $this->t('API Secret Key'),
        '#description' => $this->t('The secret key of your key pair. These are only generated once by Sendgrid. Your existing key is hidden. If you need to change this, provide a new key here.'),
      ];
    }
    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Settings'),
    ];

    $form['settings']['sendgrig_intergration_trackopens'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Track Opens'),
      '#description' => $this->t('Track opening of emails. This will include a small image in each email. Set to off by default.'),
      '#default_value' => !empty($config->get('trackopens')) ? $config->get('trackopens') : 0,
    ];

    $form['settings']['sendgrig_intergration_trackclicks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Track Clicks'),
      '#description' => $this->t('Track the clicking of links in email. Set to off by default.'),
      '#default_value' => !empty($config->get('trackclicks')) ? $config->get('trackclicks') : 0,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->moduleHandler->moduleExists('key')) {
      parent::validateForm($form, $form_state);
      return;
    }

    $config = $this->config('sendgrid_integration.settings');
    // Check for API secret key. If missing throw error.
    if (empty($config->get('apikey')) && empty($form_state->getValue('sendgrid_integration_apikey'))) {
      $form_state->setError($form['authentication']['sendgrid_integration_apikey'], $this->t('You have not stored an API Secret Key.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sendgrid_integration.settings');

    if ($this->moduleHandler->moduleExists('key')) {
      $key_name = $form_state->getValue('sendgrid_integration_apikey');
      $config->set('apikey', $key_name);
    }
    else {
      if ($form_state->hasValue('sendgrid_integration_apikey') && !empty($form_state->getValue('sendgrid_integration_apikey'))) {
        $config->set('apikey', $form_state->getValue('sendgrid_integration_apikey'));
      }
    }

    if ($form_state->hasValue('sendgrig_intergration_trackopens') && !empty($form_state->getValue('sendgrig_intergration_trackopens'))) {
      $config->set('trackopens', 1);
    }
    elseif (empty($form_state->getValue('sendgrig_intergration_trackopens'))) {
      $config->set('trackopens', 0);
    }

    if ($form_state->hasValue('sendgrig_intergration_trackclicks') && !empty($form_state->getValue('sendgrig_intergration_trackclicks'))) {
      $config->set('trackclicks', 1);
    }
    elseif (empty($form_state->getValue('sendgrig_intergration_trackclicks'))) {
      $config->set('trackclicks', 0);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sendgrid_integration.settings'];
  }

}
