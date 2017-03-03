<?php

namespace Drupal\pbc_groups;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_automation\PcoTasks;

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
  public function updateNode($values, $nid) {
    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->load($nid);
    // UPDATE FROM PCO VALUES.
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
  public function buildNodeValues($values) {
    // Add some defaults.
    $values['status'] = 1;

    return $values;
  }

}
