<?php

namespace Drupal\pbc_automation;

use Drupal\Component\Serialization\Json;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pco\Client\PcoClient;
use Drupal\pbc_groups\GroupsUtilityInterface;

/**
 * Class PcoTasks.
 *
 * @package Drupal\pbc_automation
 */
class PcoTasks implements PcoTasksInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;
  /**
   * Drupal\pco\Client\PcoClient definition.
   *
   * @var \Drupal\pco\Client\PcoClient
   */
  protected $pcoApiClient;
  /**
   * Drupal\pbc_groups\GroupsUtilityInterface.
   *
   * @var \Drupal\pbc_groups\GroupsUtilityInterface;
   */
  protected $groupsUtility;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    PcoClient $pco_api_client,
    GroupsUtilityInterface $groups_utility
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pcoApiClient = $pco_api_client;
    $this->groupsUtility = $groups_utility;
  }

  /**
   * { @inheritdoc }
   */
  public function createPcoPerson(NodeInterface $node) {
    $body = [
      'data' => [
        'type' => 'Person',
        'attributes' => [
          'first_name' => $node->field_first_name->getString(),
          'last_name' => $node->field_last_name->getString(),
        ],
      ],
    ];
    $request = $this->pcoApiClient->connect('post', 'people/v2/people', [], $body);
    $response = \json_decode($request);

    if (!isset($response->data->id)) {
      return FALSE;
    }

    $pcoId = $response->data->id;
    // Create their email address.
    if (!$node->field_email_address->isEmpty()) {
      $this->createPcoEmail($node, $pcoId);
    }

    return $response->data->id;
  }

  /**
   * { @inheritdoc }
   */
  public function createPcoEmail(NodeInterface $node, $personId) {
    $body = [
      'data' => [
        'type' => 'Email',
        'attributes' => [
          'address' => $node->field_email_address->getString(),
          'location' => 'Home',
        ],
      ],
    ];
    $endPoint = 'people/v2/people/' . $personId . '/emails';
    $request = $this->pcoApiClient->connect('post', $endPoint, [], $body);
    $response = \json_decode($request);

    return $response->data->id;
  }

  /**
   * { @inheritdoc }
   */
  public function getPcoPerson($pcoId) {
    $endpoint = 'people/v2/people/' . $pcoId;
    $request = $this->pcoApiClient->connect('get', $endpoint, [], []);
    if (!$request) {
      return FALSE;
    }
    $response = Json::decode($request);
    return $response;
  }

  /**
   * { @inheritdoc }
   */
  public function getPcoEmails($pcoId) {
    $email = NULL;
    $endpoint = 'people/v2/people/' . $pcoId . '/emails';
    $request = $this->pcoApiClient->connect('get', $endpoint, [], []);
    if (!$request) {
      return $email;
    }
    $response = Json::decode($request);

    if (empty($response['data'])) {
      return $email;
    }

    foreach ($response['data'] as $emailData) {
      $attributes = $emailData['attributes'];
      if ($attributes['blocked']) {
        continue;
      }

      // If empty, set email.
      if (empty($email)) {
        $email = $attributes['address'];
      }

      // Let primary win.
      if ($attributes['primary']) {
        $email = $attributes['address'];
        break;
      }
    }

    return $email;
  }

  /**
   * { @inheritdoc }
   */
  public function getPcoEmailParent(array $payload) {
    if (!isset($payload['meta']['parent']['id'])) {
      return FALSE;
    }
    return $payload['meta']['parent']['id'];
  }

  /**
   * { @inheritdoc }
   */
  public function getIndividualbyId($pcoId) {
    $storage = $this->entityTypeManager->getStorage('node');

    $individual = $storage->getQuery()
      ->condition('type', 'individual')
      ->condition('field_planning_center_id', $pcoId)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($individual)) {
      return FALSE;
    }

    if (count($individual) > 1) {
      // TODO Log something.
    }

    $nid = reset($individual);

    return $storage->load($nid);
  }

  /**
   * { @inheritdoc }
   */
  public function deleteIndividual(NodeInterface $individual) {
    $individual->set('field_pco_deleted', TRUE);
    return $individual->save();
  }

  /**
   * { @inheritdoc }
   */
  public function createIndividual($pcoId, array $payload) {
    $storage = $this->entityTypeManager->getStorage('node');
    // Set a couple default values.
    $values = [
      'type' => 'individual',
      'status' => 1,
      'field_planning_center_id' => $pcoId,
    ];
    $individual = $storage->create($values);
    $individual->setTitle($pcoId);
    $individual->save();

    return $this->updateIndividual($individual, $payload);
  }

  /**
   * { @inheritdoc }
   */
  public function updateIndividual(NodeInterface $individual, array $payload) {
    $attributes = $payload['data']['attributes'];
    $links = $payload['data']['links'];

    // Set a couple default values.
    $map = [
      'field_first_name' => 'first_name',
      'field_last_name' => 'last_name',
      'field_pco_updated' => 'updated_at',
      'field_pco_deleted' => 'status',
      'field_membership' => 'membership',
      'field_email_address' => 'emails',
    ];

    foreach ($map as $field => $value) {
      if (isset($attributes[$value])) {
        $value = $attributes[$value];
      }
      else {
        $value = NULL;
      }

      // Handle a couple more complicated fields.
      switch ($field) {
        case 'field_membership':
          if ($tid = $this->groupsUtility->getTidByName('membership', $value)) {
            $value = $tid;
          }
          break;

        case 'field_email_address':
          $pcoId = $payload['data']['id'];
          $value = $this->getPcoEmails($pcoId);
          break;

        case 'field_pco_deleted':
          $status = TRUE;
          if ($value === 'active') {
            $status = FALSE;
          }
          $value = $status;
          break;
      }

      $individual->set($field, $value);
    }

    return $individual->save();
  }

  /**
   * { @inheritdoc }
   */
  public function updateIndividualEmail(array $payload) {
    if (!$pcoId = $this->getPcoEmailParent($payload)) {
      return FALSE;
    }
    if (!$individual = $this->getIndividualbyId($pcoId)) {
      return FALSE;
    }

    $individual->set('field_email_address', $this->getPcoEmails($pcoId));
    return $individual->save();
  }

  /**
   * { @inheritdoc }
   */
  public function transferGroupConnections(NodeInterface $keep, NodeInterface $remove) {
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->condition('type', 'group_connection')
      ->condition('field_individual', $remove->id())
      ->accessCheck(FALSE)
      ->execute();

    if (empty($results)) {
      return FALSE;
    }

    $connections = $storage->loadMultiple($results);
    // Set Group connections the individual we are keeping.
    foreach ($connections as $connection) {
      $connection->field_individual->entity = $keep;
      $connection->save();
    }
    return TRUE;
  }

}
