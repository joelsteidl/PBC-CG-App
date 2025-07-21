<?php

namespace Drupal\sendgrid_integration_reports;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class SendGridReportsController.
 *
 * @package Drupal\sendgrid_integration_reports\Controller
 */
class Api {

  use StringTranslationTrait;

  /**
   * Api Key of SendGrid.
   *
   * @var array|mixed|null
   */
  protected $apiKey = NULL;

  /**
   * Cache bin of SendGrid Reports module.
   *
   * @var string
   */
  protected $bin = 'sendgrid_integration_reports';

  /**
   * Include the messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The cache factory service.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $cacheFactory;

  /**
   * Api constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cacheFactory
   *   The cache factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger, LoggerChannelFactoryInterface $logger_factory, ModuleHandlerInterface $moduleHandler, CacheFactoryInterface $cacheFactory) {
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $moduleHandler;
    $this->cacheFactory = $cacheFactory;

    // Load key from variables and throw errors if not there.
    $key_secret = $this->configFactory
      ->get('sendgrid_integration.settings')
      ->get('apikey');

    if ($this->moduleHandler->moduleExists('key')) {
      $key = \Drupal::service('key.repository')->getKey($key_secret);
      if ($key && $key->getKeyValue()) {
        $this->apiKey = $key->getKeyValue();
      }
    }
    else {
      $this->apiKey = $key_secret;
    }

    // Display message one time if api key is not set.
    if (empty($this->apiKey)) {
      $this->loggerFactory->get('sendgrid_integration_reports')
        ->warning($this->t('SendGrid Module is not setup with API key.'));
      $this->messenger->addWarning('Sendgrid Module is not setup with an API key.');
    }
  }

  /**
   * Returns stats.
   *
   * @param string $cid
   *   Cache Id.
   * @param array $categories
   *   Array of categories.
   * @param string|null $start_date
   *   Start date.
   * @param string|null $end_date
   *   End date.
   * @param bool $refresh
   *   Flag is cache should be refreshed.
   * @param string $subuser
   *   Optional subuser to report on.
   *
   * @return array|bool
   *   Array of stats data.
   */
  public function getStats($cid, array $categories = [], $start_date = NULL, $end_date = NULL, $refresh = FALSE, $subuser = '') {

    if (!$refresh && $cache = $this->cacheFactory->get($this->bin)->get($cid)) {
      return $cache->data;
    }

    // Load key from variables and throw errors if not there.
    if (empty($this->apiKey)) {
      return [];
    }

    // Get config.
    $config = $this->configFactory->get('sendgrid_integration_reports.settings')
      ->get();
    if ($start_date) {
      $start_date = date('Y-m-d', strtotime($start_date));
    }
    else {
      // Set start date and end date for global stats - default 30 days back.
      $start_date = empty($config['start_date']) ? date('Y-m-d', strtotime('today - 30 days')) : $config['start_date'];
    }

    if ($end_date) {
      $end_date = date('Y-m-d', strtotime($end_date));
    }
    else {
      // Set the end date which defaults to today.
      $end_date = empty($config['end_date']) ? date('Y-m-d', strtotime('today')) : $config['end_date'];
    }

    // Set aggregation of stats - default day.
    $aggregated_by = isset($config['aggregated_by']) ? $config['aggregated_by'] : 'day';
    $path = 'stats';
    $query = [
      'start_date' => $start_date,
      'end_date' => $end_date,
      'aggregated_by' => $aggregated_by,
    ];

    if ($categories) {
      $path = 'categories/stats';
      $query['categories'] = $categories;
      $query_str = http_build_query($query, NULL, '&', PHP_QUERY_RFC3986);
      $query = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $query_str);
    }
    // Lets attempt the request and catch an error if it fails.
    $stats_data = $this->getResponse($path, $query, $subuser);
    if (!$stats_data) {
      return [];
    }

    $data = [];
    foreach ($stats_data as $item) {
      $data['global'][] = [
        'date' => $item->date,
        'opens' => $item->stats[0]->metrics->opens,
        'processed' => $item->stats[0]->metrics->processed,
        'requests' => $item->stats[0]->metrics->requests,
        'clicks' => $item->stats[0]->metrics->clicks,
        'delivered' => $item->stats[0]->metrics->delivered,
        'deferred' => $item->stats[0]->metrics->deferred,
        'unsubscribes' => $item->stats[0]->metrics->unsubscribes,
        'unsubscribe_drops' => $item->stats[0]->metrics->unsubscribe_drops,
        'invalid_emails' => $item->stats[0]->metrics->invalid_emails,
        'bounces' => $item->stats[0]->metrics->bounces,
        'bounce_drops' => $item->stats[0]->metrics->bounce_drops,
        'unique_clicks' => $item->stats[0]->metrics->unique_clicks,
        'blocks' => $item->stats[0]->metrics->blocks,
        'spam_report_drops' => $item->stats[0]->metrics->spam_report_drops,
        'spam_reports' => $item->stats[0]->metrics->spam_reports,
        'unique_opens' => $item->stats[0]->metrics->unique_opens,
      ];
    }

    // Save data to cache.
    $this->setCache($cid, $data);

    return $data;
  }

  /**
   * Returns response from SendGrid.
   *
   * @param string $path
   *   Part of SendGrid endpoint.
   * @param array $query
   *   Query params to the request.
   * @param string $onBehalfOf
   *   Subuser to peform this request on behalf of.
   *
   * @return bool|mixed
   *   Decoded json or FALSE.
   */
  protected function getResponse($path, array $query, string $onBehalfOf = '') {
    // Set headers and create a Guzzle client to communicate with Sendgrid.
    $headers['Authorization'] = 'Bearer ' . $this->apiKey;
    if ($onBehalfOf) {
      $headers['on-behalf-of'] = $onBehalfOf;
    }
    $clienttest = new Client([
      'base_uri' => 'https://api.sendgrid.com/v3/',
      'headers' => $headers,
    ]);

    // Lets attempt the request and catch an error if it fails.
    try {
      $response = $clienttest->get($path, ['query' => $query]);
    }
    catch (ClientException $e) {
      $code = Xss::filter($e->getCode());
      $this->loggerFactory->get('sendgrid_integration_reports')
        ->error($this->t('SendGrid Reports module failed to receive data. HTTP Error Code @errno', ['@errno' => $code]));
      $this->messenger->addError($this->t('SendGrid Reports module failed to receive data. See logs.'));
      return FALSE;
    }
    // Sanitize return before using in Drupal.
    $body = Xss::filter($response->getBody());
    return json_decode($body);
  }

  /**
   * Sets the cache to sendgrid_integration_reports bin.
   *
   * @param string $cid
   *   Cache Id.
   * @param array $data
   *   The data should be cached.
   */
  protected function setCache($cid, array $data) {
    if (!empty($data)) {
      $this->cacheFactory->get($this->bin)->set($cid, $data);
    }
  }

  /**
   * Returns browser stats.
   */
  public function getStatsBrowser($subuser = '') {
    $cid = 'sendgrid_reports_browsers:' . ($subuser ? $subuser : 'global');
    if ($cache = $this->cacheFactory->get($this->bin)->get($cid)) {
      return $cache->data;
    }

    // Load key from variables and throw errors if not there.
    if (empty($this->apiKey)) {
      return [];
    }

    // Get config.
    $config = $this
      ->configFactory
      ->get('sendgrid_integration_reports.settings')
      ->get();
    // Set start date and end date for global stats - default 30 days back.
    $start_date = empty($config['start_date']) ? date('Y-m-d', strtotime('today - 30 days')) : $config['start_date'];
    $end_date = empty($config['end_date']) ? date('Y-m-d', strtotime('today')) : $config['end_date'];
    // Set aggregation of stats - default day.
    $aggregated_by = isset($config['aggregated_by']) ? $config['aggregated_by'] : 'day';
    $path = 'browsers/stats';
    $query = [
      'start_date' => $start_date,
      'end_date' => $end_date,
      'aggregated_by' => $aggregated_by,
    ];

    // Lets try and retrieve the browser statistics.
    $stats_data = $this->getResponse($path, $query);
    if (!$stats_data) {
      return [];
    }
    $data = [];
    // Determine all browsers. Nested foreach to
    // iterate over all data returned per aggregation.
    foreach ($stats_data as $item) {
      foreach ($item->stats as $inneritem) {
        if (array_key_exists($inneritem->name, $data)) {
          $data[$inneritem->name] += $inneritem->metrics->clicks;
        }
        else {
          $data[$inneritem->name] = $inneritem->metrics->clicks;
        }
      }
    }

    // Save data to cache.
    $this->setCache($cid, $data);

    return $data;
  }

  /**
   * Returns devices stats.
   */
  public function getStatsDevices($subuser = '') {
    $cid = 'sendgrid_reports_devices:' . ($subuser ? $subuser : 'global');
    if ($cache = $this->cacheFactory->get($this->bin)->get($cid)) {
      return $cache->data;
    }

    // Load key from variables and throw errors if not there.
    if (empty($this->apiKey)) {
      return FALSE;
    }

    // Set start date and end date for global stats - default 30 days back.
    $start_date = empty($config['start_date']) ? date('Y-m-d', strtotime('today - 30 days')) : $config['start_date'];
    $end_date = empty($config['end_date']) ? date('Y-m-d', strtotime('today')) : $config['end_date'];
    // Set aggregation of stats - default day.
    $aggregated_by = isset($config['aggregated_by']) ? $config['aggregated_by'] : 'day';

    $path = 'devices/stats';
    $query = [
      'start_date' => $start_date,
      'end_date' => $end_date,
      'aggregated_by' => $aggregated_by,
    ];

    // Lets try and retrieve the browser statistics.
    $stats_data = $this->getResponse($path, $query);
    if (!$stats_data) {
      return [];
    }
    $data = [];
    // Determine all browsers. Nested foreach to
    // iterate over all data returned per aggregation.
    foreach ($stats_data as $item) {
      foreach ($item->stats as $inneritem) {
        if (array_key_exists($inneritem->name, $data)) {
          $data[$inneritem->name] += $inneritem->metrics->opens;
        }
        else {
          $data[$inneritem->name] = $inneritem->metrics->opens;
        }
      }
    }

    // Save data to cache.
    $this->setCache($cid, $data);

    return $data;
  }

  /**
   * Get bounces by subuser.
   */
  public function getBouncesBySubuser($startTime = 0, $endTime = 0, $subuser = '') {
    $cid = 'sendgrid_reports_bounces';
    if ($cache = $this->cacheFactory->get($this->bin)->get($cid)) {
      return $cache->data;
    }
    $path = 'suppression/bounces';
    $query = [
      'start_time' => $startTime ? $startTime : strtotime('-1 month'),
      'end_time' => $endTime ? $endTime : time(),
    ];
    $subusers = $subuser ? [(object) ['username' => $subuser]] : $this->getSubusers();
    $bounces = [];
    foreach ($subusers as $subuser) {
      $username = $subuser->username;
      $response = $this->getResponse($path, $query, $username);
      if (!empty($response)) {
        $bounces[$username] = $response;
      }
    }
    $this->setCache($cid, $bounces);
    return $bounces;
  }

  /**
   * Get list of subusers.
   */
  public function getSubusers() {
    $cid = 'sendgrid_reports_subusers';
    if ($cache = $this->cacheFactory->get($this->bin)->get($cid)) {
      return $cache->data;
    }
    $path = 'subusers';
    $query = [
      'limit' => 500,
      'offset' => 0,
    ];
    $subusers = [];
    do {
      $response = $this->getResponse($path, $query);
      $query['offset'] += 500;
      $subusers = array_merge($subusers, $response);
    } while (!empty($response));
    $this->setCache($cid, $subusers);
    return $subusers;
  }

}
