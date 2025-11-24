<?php

namespace Drupal\condition_query\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Request Param' condition.
 *
 * @Condition(
 *   id = "request_param",
 *   label = @Translation("Request Param"),
 * )
 */
class RequestParam extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The wildcard token.
   *
   * @var string
   */
  const TOKEN_WILDCARD = '*';

  /**
   * The wildcard exclusion token.
   *
   * @var string
   */
  const TOKEN_WILDCARD_EXCLUSION = '\\';

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a RequestPath condition plugin.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(RequestStack $request_stack, array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('request_stack'),
      $configuration,
      $plugin_id,
      $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'request_param' => '',
      'case_sensitive' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['request_param'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query Parameters'),
      '#default_value' => $this->configuration['request_param'],
      '#description' => $this->t("Specify the request parameters. Enter one parameter per line. The '*' character acts as a wildcard. Examples: %example_1, %example_2, %example_3 and %example_4.", [
        '%example_1' => 'visibility=show',
        '%example_2' => 'visibility[]=show',
        '%example_3' => 'page=* (all pages)',
        '%example_4' => 'page=*\0,1,2 (all pages but the first three)',
      ]),
    ];

    $form['case_sensitive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Case sensitive'),
      '#default_value' => $this->configuration['case_sensitive'],
      '#description' => $this->t('Apply case sensitive evaluation.'),
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['request_param'] = $form_state->getValue('request_param');
    $this->configuration['case_sensitive'] = (bool) $form_state->getValue('case_sensitive');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $params = array_map('trim', explode("\n", $this->configuration['request_param']));
    $params = implode(', ', $params);
    if (!empty($this->configuration['negate'])) {
      return $this->t('Do not return true on the following query parameters: @params', ['@params' => $params]);
    }
    return $this->t('Return true on the following query parameters: @params', ['@params' => $params]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // Evaluate to TRUE if no parameters are available.
    $parameters = $this->getConfiguredParameters();
    if (empty($parameters)) {
      return TRUE;
    }

    // Evaluate all parameters separately, until we find a match.
    foreach ($parameters as $key => $values) {
      foreach ((array) $values as $value) {
        if ($this->evaluateParameter($key, $value)) {
          return TRUE;
        }
      }
    }

    // No matches at all, this condition failed for the current request.
    return FALSE;
  }

  /**
   * Evaluates the condition for a single parameter.
   *
   * @param string $key
   *   The parameter key to compare.
   * @param string $value
   *   The parameter values to compare.
   *
   * @return bool
   *   Returns TRUE if the given value appears in the current request.
   */
  protected function evaluateParameter($key, $value) {
    $request_values = $this->getRequestValues($key);

    // Check if the parameter appears in the request.
    if (empty($request_values)) {
      return FALSE;
    }

    // Check for the presence of a wildcard (e.g. '*').
    if (strpos($value, self::TOKEN_WILDCARD) !== FALSE) {
      // Check for the presence of exclusions (e.g. '*\0,1,2').
      if (strpos($value, self::TOKEN_WILDCARD_EXCLUSION) !== FALSE) {
        list (, $list) = explode(self::TOKEN_WILDCARD_EXCLUSION, $value);
        $exclusions = explode(',', $list);
        foreach ($exclusions as $exclusion) {
          if (in_array($exclusion, $request_values)) {
            return FALSE;
          }
        }
      }
      return TRUE;
    }

    // Check if the given value is present in the request.
    return in_array($value, $request_values);
  }

  /**
   * Get the configured parameters.
   *
   * @return array
   *   The configured parameters with their corresponding values.
   */
  protected function getConfiguredParameters() {
    // Get the parameters from configuration.
    $request_param = $this->configuration['request_param'];

    // Check if there are any parameters configured.
    if (!$request_param) {
      return [];
    }

    // If not case sensitive, convert all parameters to lowercase.
    if (!$this->configuration['case_sensitive']) {
      $request_param = mb_strtolower($request_param);
    }

    // Parse the query string into parameters.
    $query = preg_replace('/\n|\r\n?/', '&', $request_param);
    parse_str($query, $parameters);

    return $parameters;
  }

  /**
   * Get the values for a given request parameter.
   *
   * @param string $key
   *   The parameter key.
   *
   * @return array
   *   The values for the given parameter in the current request.
   */
  protected function getRequestValues($key) {
    $values = (array) ($this->requestStack->getCurrentRequest()->query->all()[$key] ?? []);

    // If not case sensitive, convert all values to lowercase.
    if (!$this->configuration['case_sensitive']) {
      $values = array_map('mb_strtolower', $values);
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.query_args';
    return $contexts;
  }

}
