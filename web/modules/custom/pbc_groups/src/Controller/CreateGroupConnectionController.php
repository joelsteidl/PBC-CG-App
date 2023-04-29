<?php

namespace Drupal\pbc_groups\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_groups\GroupsUtility;
use Drupal\node\NodeInterface;

/**
 * Class CreateGroupConnectionController.
 *
 * @package Drupal\pbc_groups\Controller
 */
class CreateGroupConnectionController extends ControllerBase {

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
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entity_type_manager, GroupsUtility $pbc_groups_utility) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pbcGroupsUtility = $pbc_groups_utility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pbc_groups.utility')
    );
  }

  /**
   * Create.
   *
   * @return string
   *   Return Hello string.
   */
  public function add(NodeInterface $redirect, NodeInterface $individual, $status) {
    $storage = $this->entityTypeManager->getStorage('node');

    if ($redirect->getType() === 'group_attendance_record') {
      $groupId = $redirect->field_group->target_id;
    }
    elseif ($redirect->getType() === 'group') {
      $groupId = $redirect->id();
    }

    // TODO: add to a utlity service.
    $connections = $storage->getQuery()->count()
      ->condition('type', 'group_connection')
      ->condition('status', 1)
      ->condition('field_group', $groupId)
      ->condition('field_individual', $individual->id())
      ->accessCheck(FALSE)
      ->execute();

    if ($connections > 0) {
      \Drupal::messenger()->addError($this->t('@name is already part of this group. Duplicates are not allowed.', ['@name' => $individual->getTitle()]));
      return $this->redirect('entity.node.canonical', ['node' => $redirect->id()]);
    }

    // Create group connection.
    $groupConnectValues = [
      'type' => 'group_connection',
      'field_group' => $groupId,
      'field_individual' => $individual->id(),
      'field_group_connection_status' => $status,
    ];

    $groupConnection = $this->pbcGroupsUtility->createNode($groupConnectValues);

    // Create group_connection node.
    if ($groupConnection && $redirect->getType() === 'group_attendance_record') {
      if ($indAttendanceValues = $this->pbcGroupsUtility->buildIndivdualAttendanceNodeValues($groupConnection, $redirect, 1)) {
        $this->pbcGroupsUtility->createNode($indAttendanceValues);
      }
    }

    \Drupal::messenger()->addStatus(t('Success! @name has been added.', ['@name' => $individual->field_first_name->getString()]));
    return $this->redirect('entity.node.canonical', ['node' => $redirect->id()]);
  }

}
