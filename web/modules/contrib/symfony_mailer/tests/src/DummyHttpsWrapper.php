<?php

namespace Drupal\Tests\symfony_mailer;

/**
 * Dummy replacement HTTPS wrapper for testing, controlling result of fopen().
 */
class DummyHttpsWrapper {

  /**
   * {@inheritdoc}
   */
  public $context;

  /**
   * Configuration.
   *
   * @var bool[]
   *   The configuration.
   */
  protected static $config;

  /**
   * Register the dummy HTTPS wrapper.
   *
   * @param bool[] $config
   *   The configuration. Array keys are base file names and values indicate
   *   whether to allow the open.
   */
  public static function register(array $config) {
    self::$config = $config;
    stream_wrapper_unregister('http');
    stream_wrapper_register('http', self::class, STREAM_IS_URL);
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps, Drupal.Commenting.FunctionComment.Missing
  public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool {
    return (bool) $this->url_stat($path, 0);
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps, Drupal.Commenting.FunctionComment.Missing
  public function url_stat(string $path, int $flags): array|false {
    $result = self::$config[basename($path)] ?? TRUE;
    return $result ? [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0] : FALSE;
  }

}
