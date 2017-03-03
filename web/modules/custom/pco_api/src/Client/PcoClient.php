<?php

namespace Drupal\pco_api\Client;

use Drupal\Core\Config\ConfigFactory;
use Drupal\key\KeyRepositoryInterface;
use Drupal\pco_api\PcoClientInterface;
use \GuzzleHttp\ClientInterface;
use \GuzzleHttp\Exception\RequestException;

class PcoClient implements PcoClientInterface {

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  // protected $httpClient;


  /**
   * A configuration instance.
   *
   * @var \Drupal\Core\Config\ConfigInterface;
   */
  protected $config;

  /**
   * Planning Center Token.
   *
   * @var string
   */
  protected $token;

  /**
   * Planning Center Secret.
   *
   * @var string
   */
  protected $secret;

  /**
   * Planning Center Base URI.
   *
   * @var string
   */
  protected $base_uri;

  /**
   * Constructor.
   */
  public function __construct(KeyRepositoryInterface $key_repo, ConfigFactory $config_factory) {
    // $this->httpClient = $http_client;
    $config = $config_factory->get('pco_api.settings');
    $this->token = $config->get('token');
    $this->secret = $config->get('secret');
    $this->secret = $key_repo->getKey($this->secret)->getKeyValue();
    $this->base_uri = $config->get('base_uri');
  }

  /**
   * { @inheritdoc }
   */
  public function connect($method, $endpoint, $query, $body) {
    try {
      $response = \Drupal::httpClient()->{$method}(
        $this->base_uri . $endpoint,
        $this->buildOptions($query, $body)
      );
    }
    catch (RequestException $exception) {
      drupal_set_message(t('Failed to complete Planning Center Task "%error"', ['%error' => $exception->getMessage()]), 'error');

      \Drupal::logger('pco_api')->error('Failed to complete Planning Center Task "%error"', ['%error' => $exception->getMessage()]);
      return FALSE;
    }

    // TODO: Possibly allow returning the whole body.
    return $response->getBody()->getContents();
  }

  /**
   * Handle authentication.
   *
   * Does GuzzleHttp do this for us already?
   */
  private function checkStatusCode($statusCode) {
    // $status = TRUE;
    // $passing = [
    //   200,
    //   201,
    // ];
    // if (!in_array($statusCode, $passing)) {
    //   $status = FALSE;
    // }
    // return $status;
  }

  /**
   * Build options for the client.
   */
  private function buildOptions($query, $body) {
    $options = [];
    $options['auth'] = $this->auth();
    if ($body) {
      $options['body'] = $body;
    }
    if ($query) {
      $options['query'] = $query;
    }
    return $options;
  }

  /**
   * Handle authentication.
   */
  private function auth() {
    return [$this->token, $this->secret];
  }

}
