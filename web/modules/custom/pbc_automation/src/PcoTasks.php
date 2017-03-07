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
    $storage = $this->entityTypeManager->getStorage('node');

    $individual = $storage->getQuery()
      ->condition('type', 'individual')
      ->condition('field_planning_center_id', $pcoRecord->id)
      ->condition('status', 1)
      ->execute();

    // If a record exists, just update it.
    $values = $this->convertPcoToNode($pcoRecord);

    if (count($individual)) {
      $nid = array_shift($individual);
      $this->groupsUtility->updateNode($values, $nid);
    }
    else {
      $this->groupsUtility->createNode($values);
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
    ];

    // Handle Email Address.
    if (isset($pcoRecord->relationships->emails->data[0]->id)) {
      $emailId = $pcoRecord->relationships->emails->data[0]->id;
      $values['field_email_address'] = $this->getPcoEmail($emailId);
    }

    // Handle Field Data.
    if (isset($pcoRecord->relationships->field_data->data)) {
      $fields = $pcoRecord->relationships->field_data->data;
      foreach ($fields as $field) {
        $data = $this->getPcoFieldData($pcoRecord->id, $field->id);

        switch ($data['field_name']) {
          case 'ethnicity':
            if ($tid = $this->groupsUtility->getTidByName('ethnicity', $data['field_value'])) {
              $values['field_ethnicity'] = $tid;
            }
            break;

          case 'neighborhood':
            if ($tid = $this->groupsUtility->getTidByName('neighborhood', $data['field_value'])) {
              $values['field_neighborhood'] = $tid;
            }
            break;

          case 'below_poverty_line':
            $values['field_below_poverty_line'] = $data['field_value'];
            break;
        }
      }
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

    if (!isset($response->data->id)) {
      return FALSE;
    }

    $pcoId = $response->data->id;
    // Create their email address.
    if (!$node->field_email_address->isEmpty()) {
      $this->createPcoEmail($node, $pcoId);
    }
    // Create custom field data.
    // Keys are field IDs in PCO.
    $fields = [
      '118307' => 'field_below_poverty_line',
      '118308' => 'field_ethnicity',
      '118309' => 'field_neighborhood',
    ];

    foreach ($fields as $fieldId => $field) {
      $fieldInfo = [];
      if (!$node->{$field}->isEmpty()) {
        switch ($field) {
          case 'field_ethnicity':
          case 'field_neighborhood':
            $fieldInfo['value'] = $node->{$field}->entity->getName();
            break;

          case 'field_below_poverty_line':
            $value = $node->{$field}->value;
            if ($value == 1) {
              $fieldInfo['value'] = 'true';
            }
            elseif ($value == 0) {
              $fieldInfo['value'] = 'false';
            }
            break;
        }
        $fieldInfo['id'] = $fieldId;
        $this->createPcoFieldData($node, $fieldInfo, $pcoId);
      }
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
  public function createPcoFieldData(NodeInterface $node, $fieldInfo, $personId) {
    $body = [
      'data' => [
        'type' => 'FieldDatum',
        'attributes' => [
          'value' => $fieldInfo['value'],
        ],
        'relationships' => [
          'field_definition' => [
            'data' => [
              'type' => 'FieldDefinition',
              'id' => $fieldInfo['id'],
            ]
          ]
        ]
      ]
    ];
    $body = json_encode($body);
    $endPoint = 'people/v2/people/' . $personId . '/field_data';
    $request = $this->pcoApiClient->connect('post', $endPoint, [], $body);
    $response = json_decode($request);

    return $response->data->id;
  }

  /**
   * { @inheritdoc }
   */
  public function getPcoFieldData($personId, $fieldId) {
    $query = ['include' => 'field_definition'];
    $endpoint = 'people/v2/people/' . $personId . '/field_data/' . $fieldId;
    $request = $this->pcoApiClient->connect('get', $endpoint, $query, []);
    $response = json_decode($request);
    $data = [];
    $data['field_name'] = $response->included[0]->attributes->slug;
    $data['field_value'] = $response->data->attributes->value;

    return $data;
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
  public function refreshPcoUpdateList($listId) {
    // Refresh the recently updated list on PCO.
    $this->pcoApiClient->connect('post', 'people/v2/lists/' . $listId . '/run', [], []);
  }

  /**
   * { @inheritdoc }
   */
  public function getPcoUpdatedPeople() {
    // Refresh the PCO list.
    // See https://people.planningcenteronline.com/lists/195220
    $listId = 195220;
    $this->refreshPcoUpdateList($listId);
    $query = [
      'per_page' => 99,
      'include' => 'emails,field_data',
    ];
    // Grab results from the updated list.
    $request = $this->pcoApiClient->connect('get', 'people/v2/lists/' . $listId . '/people', $query, []);
    $results = json_decode($request);
    if (!count($results->data)) {
      return FALSE;
    }

    return $results->data;
  }

  /**
   * { @inheritdoc }
   */
  public function getPcoPeople($offset, $perPage) {
    $query = [
      'per_page' => $perPage,
      'include' => 'emails',
      'offset' => $offset,
    ];
    $request = $this->pcoApiClient->connect('get', 'people/v2/people', $query, []);
    $results = json_decode($request);
    return $results->data;
  }

}
