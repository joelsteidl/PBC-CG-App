<?php

namespace Drupal\pbc_automation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Class PcoWebhookController.
 *
 * @package Drupal\pbc_automation\Controller
 */
class PcoWebhookController extends ControllerBase {


  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The KeyRepositoryInterface.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * Drupal\Core\Queue\QueueFactory definition.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Enable or disable debugging.
   *
   * @var bool
   */
  protected $debug = FALSE;


  /**
   * {@inheritdoc}
   */
  public function __construct(
    RequestStack $request_stack,
    KeyRepositoryInterface $key_repository,
    QueueFactory $queue
  ) {
    $this->requestStack = $request_stack;
    $this->keyRepository = $key_repository;
    $this->queueFactory = $queue->get('pco_webhooks_processor');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('key.repository'),
      $container->get('queue')
    );
  }

  /**
   * Capture the incoming payload.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A simple JSON response.
   */
  public function capture(Request $request) {
    $response = [
      'success' => TRUE,
      'message' => 'Thanks for the data PCO!',
      'data' => [],
    ];

    // Capture the content.
    $content = $request->getContent();

    // Ability to debug the incoming payload.
    if ($this->debug) {
      \Drupal::logger('pco_automation')->debug('<pre><code>' . print_r($content, TRUE) . '</code></pre>');
    }

    $this->queueFactory->createItem($content);

    return new JsonResponse($response, 202);
  }

  /**
   * Authorize the payload.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   AccessResult allowed or forbidden.
   */
  public function authorize() {
    if ($this->debug) {
      return AccessResult::allowed();
    }

    $request = $this->requestStack->getCurrentRequest();
    if (!$authenticity = $this->getPcoAuthenticity($request)) {
      return AccessResult::forbidden();
    }

    $event = $this->getSubscriptionEvent($request);
    // Contains JSON to parse into array.
    $key = $this->keyRepository->getKey('pco_webhook_secrets')->getKeyValue();
    $secrets = Json::decode($key);

    // No key defined for the event.
    if (!isset($secrets[$event])) {
      // TODO: Add logging.
      AccessResult::forbidden();
    }

    $hashHmac = hash_hmac('sha256', $request->getContent(), $secrets[$event]);

    if (hash_equals($authenticity, $hashHmac)) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Gets the X-PCO-Webhooks-Authenticity header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   FALSE or the authorization header.
   */
  protected function getPcoAuthenticity(Request $request) {
    if (!$request->headers->has('X-PCO-Webhooks-Authenticity')) {
      return FALSE;
    }
    return $request->headers->get('X-PCO-Webhooks-Authenticity');
  }

  /**
   * Figures out the type of event.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return string
   *   The PCO subscription event string.
   */
  protected function getSubscriptionEvent(Request $request) {
    $data = Json::decode($request->getContent());

    return $data['data'][0]['attributes']['name'];
  }

}
