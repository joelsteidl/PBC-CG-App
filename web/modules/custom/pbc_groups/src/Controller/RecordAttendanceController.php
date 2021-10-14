<?php

namespace Drupal\pbc_groups\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\NodeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\pbc_groups\GroupsUtility;

/**
 * Class RecordAttendanceController.
 *
 * @package Drupal\pbc_groups\Controller
 */
class RecordAttendanceController extends ControllerBase {

  /**
   * Drupal\pbc_groups\GroupsUtility definition.
   *
   * @var \Drupal\pbc_groups\GroupsUtility
   */
  protected $pbcGroupsUtility;
  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(GroupsUtility $pbc_groups_utility, EntityTypeManager $entity_type_manager) {
    $this->pbcGroupsUtility = $pbc_groups_utility;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pbc_groups.utility'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Callback.
   *
   * @return string
   *   Return Hello string.
   */
  public function callback(NodeInterface $group_connection, NodeInterface $group_attendance_record, $in_attendance, $status) {

    if ($indAttendanceValues = $this->pbcGroupsUtility->buildIndivdualAttendanceNodeValues($group_connection, $group_attendance_record, $in_attendance)) {
      // Create individual_attendance_record node.
      if ($this->pbcGroupsUtility->createNode($indAttendanceValues)) {
        // Add error if not true?
      }
    }

    // Change their conntection status if becoming active.
    if ($status == 1) {
      $group_connection->field_group_connection_status->target_id = 1;
      $group_connection->save();
    }

    \Drupal::messenger()->addStatus(t('Attendance has been recorded for @name.', ['@name' => $group_connection->field_individual->entity->field_first_name->getString()]));
    return $this->redirect('entity.node.canonical', ['node' => $group_attendance_record->id()]);

    // $response = new AjaxResponse();
    // $response->addCommand(new InvokeCommand('.group-connection-' . $group_connection->id() . ' .action', 'html', [$text]));
    // return $response;
  }

}
