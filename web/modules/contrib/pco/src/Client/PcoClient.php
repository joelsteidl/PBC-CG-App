<?php

namespace Drupal\pco\Client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\pco\PcoClientInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Uses http client to interact with the PCO API.
 */
class PcoClient implements PcoClientInterface {
  use StringTranslationTrait;

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Immutable Config Object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Psr\Log\LoggerInterface definition.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The KeyRepositoryInterface.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

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
  protected $baseUri;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Client interface.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   Key repository interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory interface.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Time.
   */
  public function __construct(
    ClientInterface $http_client,
    KeyRepositoryInterface $keyRepository,
    ConfigFactoryInterface $configFactory,
    LoggerInterface $logger,
    MessengerInterface $messenger
    ) {
    $this->httpClient = $http_client;
    $this->keyRepository = $keyRepository;
    $this->config = $configFactory->get('pco.settings');
    $this->token = $this->config->get('token');
    $this->secret = $this->getKeyValue('secret');
    $this->baseUri = $this->config->get('base_uri');
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * Utilizes Drupal's httpClient to connect to Planning Center Online CRM.
   *
   * Info: https://planning.center/.
   * API Docs: http://planningcenter.github.io/api-docs/.
   *
   * @param string $method
   *   get, post, patch, delete, etc. See Guzzle documentation.
   * @param string $endpoint
   *   The PCO API endpoint (ex. people/v2/people)
   * @param array $query
   *   Query string parameters the endpoint allows (ex. ['per_page' => 50].
   * @param array $body
   *   Gets converted to JSON. Utilized for some endpoints.
   *
   * @return mixed
   *   \GuzzleHttp\Psr7\Response body or false.
   */
  public function connect($method, $endpoint, array $query = [], array $body = []) {
    try {
      $response = $this->httpClient->{$method}(
        $this->baseUri . $endpoint,
        $this->buildOptions($query, $body)
      );
    }
    catch (RequestException $exception) {
      $this->messenger->addError($this->t('Failed to complete Planning Center Task "%error"', ['%error' => $exception->getMessage()]));

      $this->logger->error('Failed to complete Planning Center Task "%error"', ['%error' => $exception->getMessage()]);
      return FALSE;
    }

    $headers = $response->getHeaders();
    $this->throttle($headers);
    // TODO: Possibly allow returning the whole body.
    return $response->getBody()->getContents();
  }

  /**
   * Build options for the client.
   */
  private function buildOptions($query, $body) {
    $options = [];
    $options['auth'] = $this->auth();
    if (!empty($body)) {
      $options['json'] = $body;
    }
    if (!empty($query)) {
      $options['query'] = $query;
    }
    return $options;
  }

  /**
   * Throttle response.
   *
   * 100 per 60s allowed.
   * TODO: Handle without sleep.
   */
  private function throttle($headers) {
    if ($headers['X-PCO-API-Request-Rate-Count'][0] > 99) {
      return sleep(60);
    }
    return TRUE;
  }

  /**
   * Handle authentication.
   */
  private function auth() {
    return [$this->token, $this->secret];
  }

  /**
   * Return a KeyValue.
   *
   * @param string $whichConfig
   *   Name of the config in which the key name is stored.
   *
   * @return mixed
   *   Null or string.
   */
  protected function getKeyValue($whichConfig) {
    if (empty($this->config->get($whichConfig))) {
      return NULL;
    }
    $whichKey = $this->config->get($whichConfig);
    $keyValue = $this->keyRepository->getKey($whichKey)->getKeyValue();

    if (empty($keyValue)) {
      return NULL;
    }

    return $keyValue;
  }

}
