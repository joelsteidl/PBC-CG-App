<?php

namespace Drupal\pbc_groups;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_automation\PcoTasks;
use Drupal\node\NodeInterface;

/**
 * Class GroupsUtility.
 *
 * @package Drupal\pbc_groups
 */
class GroupsUtility implements GroupsUtilityInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;
  /**
   * Drupal\pbc_groups\GroupsUtility definition.
   *
   * @var \Drupal\pbc_groups\GroupsUtility
   */
  protected $pbcGroupsUtility;
  /**
   * Constructor.
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * { @inheritdoc }
   */
  public function termsToOptions($vocab) {
    $options = [];
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $terms = $storage->loadByProperties([
      'vid' => $vocab,
    ]);

    foreach ($terms as $term) {
      $options[$term->id()] = $term->getName();
    }

    return $options;
  }

  /**
   * { @inheritdoc }
   */
  public function updateNode($values, $nid) {
    $fields = [];
    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->load($nid);

    switch ($node->getType()) {
      case 'individual_attendance_record':
        $fields = [
          'field_in_attendance',
        ];
        break;

      case 'individual':
        $fields = [
          'field_first_name',
          'field_last_name',
          'field_email_address',
          'field_below_poverty_line',
          'field_ethnicity',
          'field_membership',
          'field_neighborhood',
          'field_pco_updated',
        ];
        break;

      case 'group_attendance_record':
        $fields = [
          'field_notes',
          'field_group_meeting_status',
        ];
        break;

    }

    foreach ($values as $key => $value) {
      if (in_array($key, $fields)) {
        $node->{$key}->setValue($value);
      }
    }
    $node->save();
  }

  /**
   * { @inheritdoc }
   */
  public function createNode($values) {
    $values = $this->buildNodeValues($values);
    $storage = $this->entityTypeManager->getStorage('node');
    try {
      $node = $storage->create($values);
      $node->save();
    }
    catch (Exception $exception) {
      drupal_set_message(t('Content was not created. "%error"', ['%error' => $exception->getMessage()]), 'warning');

      \Drupal::logger('pbc_groups')->warning('Failed to complete Planning Center Task "%error"', ['%error' => $exception->getMessage()]);
      return FALSE;
    }

    return $node;
  }

  /**
   * { @inheritdoc }
   */
  public function getTidByName($vocab, $term) {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $result = $storage->getQuery()
      ->condition('vid', $vocab)
      ->condition('name', $term)
      ->execute();

    if (!count($result)) {
      return FALSE;
    }

    return array_shift($result);
  }

  /**
   * { @inheritdoc }
   */
  public function buildNodeValues($values) {
    // Add some defaults.
    $values['status'] = 1;

    return $values;
  }

  /**
   * { @inheritdoc }
   */
  public function buildIndivdualAttendanceNodeValues(NodeInterface $group_connection, NodeInterface $group_attendance_record, $in_attendance) {
    $individual = $group_connection->field_individual->entity;
    $values = [
      'type' => 'individual_attendance_record',
      'field_group_attendance_record' => $group_attendance_record->id(),
      'field_in_attendance' => $in_attendance,
      'field_group_connection' => $group_connection->id(),
      'field_group_connection_status' => $group_connection->field_group_connection_status->target_id,
      'field_neighborhood' => $individual->field_neighborhood->target_id,
      'field_membership' => $individual->field_membership->target_id,
      'field_below_poverty_line' => $individual->field_below_poverty_line->value,
    ];

    return $values;
  }

  /**
   * { @inheritdoc }
   */
  public function getGroupNodes($status = NULL, $return = 'id') {
    $storage = $this->entityTypeManager->getStorage('node');

    $groups = $storage->getQuery()
      ->condition('type', 'group')
      ->condition('status', 1)
      ->sort('field_group_status', 'ASC')
      ->sort('title', 'ASC');

    if ($status) {
      $groups->condition('field_group_status', $status);
    }

    $groups = $groups->execute();

    if (!$groups) {
      return FALSE;
    }

    // Load all the published & active groups.
    if ($return === 'id') {
      return array_shift($groups);
    }
    elseif ($return === 'object') {
      return $storage->loadMultiple($groups);
    }
    else {
      return FALSE;
    }

  }

}
