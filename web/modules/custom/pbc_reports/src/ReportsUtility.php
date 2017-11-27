<?php

namespace Drupal\pbc_reports;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use DateInterval;

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
  public function getGroupAttendance($groupId = '', $status) {
    $storage = $this->entityTypeManager->getStorage('node');

    $count = $storage->getQuery()->count()
      ->condition('type', 'group_attendance_record')
      ->condition('field_group_meeting_status', $status)
      ->condition('status', 1);

    // Alter query...
    // Date and Group ID
    if ($groupId) {
      $count->condition('field_group', $groupId);
    }

    return $count->execute();
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
  public function createPercent($dividend, $divisor, $format = TRUE) {
    $quotient = $dividend / $divisor;
    $percent = number_format($quotient * 100, 0);
    if ($format) {
      $format .= '%';
    }
    return $percent;
  }

  /**
   * { @inheritdoc }
   */
  public function getCategoryLabels($dates) {
    // TODO: make more generic to support non-date based.
    $labels = [];
    foreach ($dates as $date) {
      $labels[] = 'Week of ' . $date['start_display'];
    }
    return $labels;
  }

  /**
   * { @inheritdoc }
   */
  public function getSeriesData($groups, $dates) {
    $storage = $this->entityTypeManager->getStorage('node');

    $groups = $storage->loadMultiple($groups);

    $seriesData = [];
    $delta = 0;
    foreach ($groups as $group) {
      $seriesData[$delta]['name'] = $group->getTitle();
      foreach ($dates as $date) {
        $attendanceRecord = $this->getAttendanceRecord($group, $date['start_query'], $date['end_query']);
        if (!$attendanceRecord) {
          $seriesData[$delta]['data'][] = 0;
          $seriesData[$delta]['extra'][] = 'No Data';
        }
        else {
          $seriesData[$delta]['data'][] = $this->getAttendancePercent($attendanceRecord);
          $seriesData[$delta]['extra'][] = $attendanceRecord->field_group_meeting_status->getString();
        }
      }
      $delta++;
    }
    return $seriesData;
  }

  /**
   * { @inheritdoc }
   */
  public function getDatesbyWeek($startDate, $endDate) {
    $dates = [];

    // Get Sunday of date input.
    $startDate = new DrupalDateTime($startDate);
    $startDate->modify('last saturday + 1 day');

    // Get Saturday of date input.
    $endDate = new DrupalDateTime($endDate);
    $endDate->modify('last saturday + 7 days');

    $interval = $this->getDaysBetween($startDate, $endDate);

    $weeks = floor(($interval->days + 1) / 7);

    for ($i = 1; $i <= $weeks; $i++) {
      $display = $startDate;
      $dates[$i]['start_display'] = $display->format('M j');
      $dates[$i]['start_query'] = $display->format('Y-m-d');
      $startDate->add(new DateInterval('P6D'));
      $display = $startDate;
      $dates[$i]['end_query'] = $display->format('Y-m-d');
      $dates[$i]['end_display'] = $display->format('M j, Y');
      $startDate->add(new DateInterval('P1D'));
    }
    return $dates;
  }

  /**
   * { @inheritdoc }
   */
  public function getDaysBetween($startDate, $endDate) {
    return $startDate->diff($endDate);
  }

  /**
   * { @inheritdoc }
   */
  public function getAttendanceRecord($group, $startDate, $endDate) {
    $storage = $this->entityTypeManager->getStorage('node');
    // See if a record already exists for the week.
    $attendanceRecord = $storage->getQuery()
      ->condition('type', 'group_attendance_record')
      ->condition('status', 1)
      ->condition('field_group', $group->id())
      ->condition('field_meeting_date', [$startDate, $endDate], 'BETWEEN')
      ->execute();

    if ($attendanceRecord) {
      $attendanceRecord = $storage->load(array_shift($attendanceRecord));
      return $attendanceRecord;
    }
    return FALSE;
  }

  /**
   * { @inheritdoc }
   */
  public function getAttendancePercent($attendanceRecord) {
    $in_attendance = $this->getAttendanceByGroup($attendanceRecord->id(), 1, 1);
    $active_in_group = $this->getGroupParticipants($attendanceRecord->field_group->target_id, 1);

    $percent = $this->createPercent($in_attendance, $active_in_group, FALSE);
    return intval($percent);
  }

}
