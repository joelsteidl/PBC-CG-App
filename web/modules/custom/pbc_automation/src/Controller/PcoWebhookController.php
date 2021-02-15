<?php

namespace Drupal\pbc_automation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\key\KeyRepositoryInterface;

/**
 * Class PcoWebhookController.
 *
 * @package Drupal\pbc_automation\Controller
 */
class PcoWebhookController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

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
   * Enable or disable debugging.
   *
   * @var bool
   */
  protected $debug = TRUE;


  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    RequestStack $request_stack,
    KeyRepositoryInterface $key_repository
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('key.repository')
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
      'message' => 'Webhook payload captured!',
      'data' => [],
    ];

    // Capture the content.
    $content = $request->getContent();

    // Ability to debug the incoming payload.
    if ($this->debug) {
      \Drupal::logger('pco_automation')->debug('<pre><code>' . print_r($content, TRUE) . '</code></pre>');
    }

    // Check if the content is empty.
    if (empty($content)) {
      return new JsonResponse($response);
    }

    $data = Json::decode($content);
    if (!isset($data['data'][0]['attributes']['name'], $data['data'][0]['attributes']['payload'])) {
      return new JsonResponse($response);
    }

    $subscription = $data['data'][0]['attributes']['name'];
    $subscriptions = [
      'people.v2.events.person.destroyed',
      'people.v2.events.person.updated',
    ];
    if (!in_array($subscription, $subscriptions)) {
      return new JsonResponse($response);
    }

    $flag = FALSE;
    $payload = $data['data'][0]['attributes']['payload'];
    $payload = Json::decode($payload);

    // See if someone was deleted or set to inactive.
    if ($subscription === 'people.v2.events.person.destroyed') {
      $flag = TRUE;
    }
    elseif ($payload['data']['attributes']['status'] === 'inactive') {
      $flag = TRUE;
    }

    $pcoId = $payload['data']['id'];

    // TODO: Refactor and just handle updates. So easy.
    // If found, loop over and flag for deletion.
    $individuals = $this->getIndividualsbyId($pcoId);
    if ($individuals && $flag) {
      foreach ($individuals as $individual) {
        $individual->set('field_pco_deleted', TRUE);
        $individual->save();
      }
    }

    return new JsonResponse($response);
  }

  /**
   * Lookup individuals by the PCO id.
   *
   * @param int $pcoId
   *   Planning center individual id.
   *
   * @return mixed
   *   FALSE if not found.
   */
  public function getIndividualsbyId($pcoId) {
    $storage = $this->entityTypeManager->getStorage('node');

    $individuals = $storage->getQuery()
      ->condition('type', 'individual')
      ->condition('field_planning_center_id', $pcoId)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($individuals)) {
      return FALSE;
    }

    return $storage->loadMultiple($individuals);
  }

  /**
   * Authorize the payload.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   AccessResult allowed or forbidden.
   */
  public function authorize() {
    $request = $this->requestStack->getCurrentRequest();
    if (!$authenticity = $this->getPcoAuthenticity($request)) {
      return AccessResult::forbidden();
    }

    $secret = $this->keyRepository->getKey('pco_webhook_secret')->getKeyValue();
    if ($this->getSubscriptionEvent($request) === 'people.v2.events.person.destroyed') {
      $secret = $this->keyRepository->getKey('pco_webhook_destroy')->getKeyValue();
    }
    $hashHmac = hash_hmac('sha256', $request->getContent(), $secret);

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
