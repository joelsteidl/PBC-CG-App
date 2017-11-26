<?php

namespace Drupal\pbc_reports;

use Drupal\node\NodeInterface;

/**
 * Interface ReportsUtilityInterface.
 *
 * @package Drupal\pbc_reports
 */
interface ReportsUtilityInterface {

  /**
   * Given a Group Attendance NID, get attendance records.
   *
   * @param int $groupAttendanceId
   *   Group attendance NID.
   * @param bool $attendanceStatus
   *   Boolean...0 or 1.
   * @param int $connectionStatus
   *   Statuses: Active (1), Guest (3), Inactive (2)
   *
   * @return int
   *   the number of records.
   */
  public function getAttendanceByGroup($groupAttendanceId, $attendanceStatus, $connectionStatus);

  /**
   * Given a Group Attendance NID, get group_connection records.
   *
   * @param int $groupId
   *   Group attendance NID.
   * @param int $status
   *   Statuses: Active (1), Guest (3), Inactive (2)
   *
   * @return int
   *   the number of records.
   */
  public function getGroupParticipants($groupId, $status);

  /**
   * Given a dividenc and divisor, create a percent.
   *
   * @param int $dividence
   *   A number.
   * @param int $divisor
   *   A number.
   *
   * @return string
   *   A percent.
   */
  public function createPercent($dividend, $divisor, $format = TRUE);

  public function getGroupAttendance($groupId = '', $status);

  public function getCategoryLabels($dates);

  public function getDatesbyWeek($startDate, $endDate);

  public function getDaysBetween($startDate, $endDate);

  public function getAttendanceRecord($group, $startDate, $endDate);

  public function getAttendancePercent($attendanceRecord);

  public function getSeriesData($dates);
}
