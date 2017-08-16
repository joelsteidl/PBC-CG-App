<?php

namespace Drupal\pbc_reports;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\NodeInterface;

/**
 * Class ReportsUtility.
 *
 * @package Drupal\pbc_reports
 */
class ReportsUtility implements ReportsUtilityInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * { @inheritdoc }
   */
  public function getAttendanceByGroup($groupAttendanceId, $attendanceStatus, $connectionStatus) {
    $storage = $this->entityTypeManager->getStorage('node');

    // TODO: dynamically pass in status of group connection.
    $count = $storage->getQuery()->count()
      ->condition('type', 'individual_attendance_record')
      ->condition('field_group_attendance_record', $groupAttendanceId)
      ->condition('field_in_attendance', $attendanceStatus)
      ->condition('field_group_connection.entity.field_group_connection_status', $connectionStatus)
      ->condition('status', 1)
      ->execute();

    return $count;
  }

  /**
   * { @inheritdoc }
   */
  public function getGroupParticipants($groupId, $status) {
    $storage = $this->entityTypeManager->getStorage('node');

    $count = $storage->getQuery()->count()
      ->condition('type', 'group_connection')
      ->condition('field_group', $groupId)
      ->condition('field_group_connection_status', $status)
      ->condition('status', 1)
      ->execute();

    return $count;
  }

  /**
   * { @inheritdoc }
   */
  public function createPercent($dividend, $divisor) {
    $quotient = $dividend / $divisor;
    $percent = number_format($quotient * 100, 0) . '%';
    return $percent;
  }

}
