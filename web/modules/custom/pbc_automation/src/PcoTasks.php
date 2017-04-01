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
    if (count($individual)) {
      $nid = array_shift($individual);
      $node = $storage->load($nid);
      // Only update records that have updated since the last time.
      if ($node->field_pco_updated->getString() != $pcoRecord->attributes->updated_at) {
        $values = $this->convertPcoToNode($pcoRecord);
        $this->groupsUtility->updateNode($values, $nid);
      }
    }
    else {
      $values = $this->convertPcoToNode($pcoRecord);
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
      'field_pco_updated' => $pcoRecord->attributes->updated_at,
    ];

    // Membership.
    if (isset($pcoRecord->attributes->membership)) {
      $membership = $pcoRecord->attributes->membership;
      if ($tid = $this->groupsUtility->getTidByName('membership', $membership)) {
        $values['field_membership'] = $tid;
      }
    }

    // Handle Email Address.
    if (isset($pcoRecord->relationships->emails->data[0]->id)) {
      $emailId = $pcoRecord->relationships->emails->data[0]->id;
      $values['field_email_address'] = $this->getPcoEmail($emailId);
    }

    // Handle Field Data.
    if ($fieldData = $this->getPcoFieldData($pcoRecord->id)) {
      foreach ($fieldData as $itemData) {
        $id = $itemData->relationships->field_definition->data->id;
        $value = $itemData->attributes->value;

        switch ($id) {
          // field_below_poverty_line.
          case '118307':
            $poverty = 0;
            if ($value) {
              $poverty = 1;
            }
            $values['field_below_poverty_line'] = $poverty;
            break;

          // field_ethnicity.
          case '118308':
            if ($tid = $this->groupsUtility->getTidByName('ethnicity', $value)) {
              $values['field_ethnicity'] = $tid;
            }
            break;

          // field_neighborhood.
          case '118309':
            if ($tid = $this->groupsUtility->getTidByName('neighborhood', $value)) {
              $values['field_neighborhood'] = $tid;
            }
            break;

        }
      }
    }

    return $values;
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
  public function getPcoFieldData($personId) {
    $query = ['include' => 'field_definition'];
    $endpoint = 'people/v2/people/' . $personId . '/field_data/';
    $request = $this->pcoApiClient->connect('get', $endpoint, $query, []);
    $results = json_decode($request);
    if (count($results->data)) {
      return $results->data;
    }

    return FALSE;
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
  public function getPcoUpdatedPeople($offset, $perPage) {
    // Refresh the PCO list.
    // See https://people.planningcenteronline.com/lists/195220
    $listId = 195220;
    $this->refreshPcoUpdateList($listId);
    $query = [
      'per_page' => $perPage,
      'include' => 'emails,field_data',
      'offset' => $offset,
    ];
    // Grab results from the updated list.
    $request = $this->pcoApiClient->connect('get', 'people/v2/lists/' . $listId . '/people', $query, []);
    $results = json_decode($request);
    if (!count($results->data)) {
      return FALSE;
    }

    return $results;
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
