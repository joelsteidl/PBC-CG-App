<?php

namespace Drupal\pbc_automation;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pco_api\Client\PcoClient;
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
   * Drupal\pco_api\Client\PcoClient definition.
   *
   * @var \Drupal\pco_api\Client\PcoClient
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
  public function __construct(EntityTypeManager $entity_type_manager, PcoClient $pco_api_client,
    GroupsUtilityInterface $groups_utility) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pcoApiClient = $pco_api_client;
    $this->groupsUtility = $groups_utility;
  }

  /**
   * { @inheritdoc }
   */
  public function createOrUpdateNode($pcoRecord) {
    // if !pcoid return FALSE
    $individual = $storage->getQuery()
      ->condition('type', 'individual')
      ->condition('field_planning_center_id', $pcoRecord->id)
      ->condition('status', 1)
      ->execute();

    // If a record exists, just update it.
    $values = $this->convertPcoToNode($pcoRecord);
    if (count($individual)) {
      $this->updateNode($values);
    }
    else {
      $this->createNode($values);
    }
  }

  /**
   * { @inheritdoc }
   */
  public function convertPcoToNode($pcoRecord) {

    $values = [
      'type' => 'individual',
      'field_first_name' => $pcoRecord->attributes->first_name,
      'field_last_name' => $pcoRecord->attributes->last_name,
      'field_planning_center_id' => $pcoRecord->id,
      // 'field_email_address' =>,
      // 'field_neighborhood' =>,
      // 'field_ethnicity' =>,
    ];


    if (isset($pcoRecord->relationships->emails->data[0]->id)) {
      $emailId = $pcoRecord->relationships->emails->data[0]->id;
      $values['field_email_address'] = $this->getPcoEmail($emailId);
    }

    return $values;
  }

  /**
   * { @inheritdoc }
   */
  public function buildPcoPersonData(NodeInterface $node) {
    // maybe deprecate.
  }

  /**
   * { @inheritdoc }
   * field_below_poverty_line|12634815
   * field_ethnicity|12634816
   * field_neighborhood|12634817
   */
  public function createPcoPerson(NodeInterface $node) {
    $body = [
      'data' => [
        'type' => 'Person',
        'attributes' => [
          'first_name' => $node->field_first_name->getString(),
          'last_name' => $node->field_last_name->getString(),
        ]
      ]
    ];
    $body = json_encode($body);
    $request = $this->pcoApiClient->connect('post', 'people/v2/people', [], $body);
    $response = json_decode($request);
    kint($response);
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
        ]
      ]
    ];
    $body = json_encode($body);
    $endPoint = 'people/v2/people/' . $personId . '/emails';
    $request = $this->pcoApiClient->connect('post', $endPoint, [], $body);
    $response = json_decode($request);

    return $response->data->id;
  }

/**
 * { @inheritdoc }
 */
  public function createPcoFieldData(NodeInterface $node, $fieldId, $personId) {
    $body = [
      'data' => [
        'type' => 'Email',
        'attributes' => [
          'address' => $node->field_email_address->getString(),
          'location' => 'Home',
        ]
      ]
    ];
    $body = json_encode($body);
    $endPoint = 'people/v2/people/' . $personId . '/emails';
    $request = $this->pcoApiClient->connect('post', $endPoint, [], $body);
    $response = json_decode($request);

    return $response->data->id;
  }

  /**
   * { @inheritdoc }
   */
  public function getPcoEmail($emailId) {
    $endpoint = 'people/v2/emails/' . $emailId;
    $request = $this->pcoApiClient->connect('get', $endpoint, [], []);
    $response = json_decode($request);

    if (isset($response->data->attributes->address)) {
      return $response->data->attributes->address;
    }

    return FALSE;
  }

  /**
   * { @inheritdoc }
   */
  public function getPcoPeople($offset, $perPage) {
    $query = [
      // 'order' => 'last_name',
      'per_page' => $perPage,
      'include' => 'emails',
      'offset' => $offset,
    ];
    $request = $this->pcoApiClient->connect('get', 'people/v2/people', $query, []);
    $results = json_decode($request);
    return $results->data;
  }

}
