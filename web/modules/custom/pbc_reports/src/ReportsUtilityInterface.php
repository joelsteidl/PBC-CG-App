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
   * @param bool $status
   *   Boolean...0 or 1.
   *
   * @return int
   *   the number of records.
   */
  public function getAttendanceByGroup($groupAttendanceId, $status);

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

}
