<?php

namespace Drupal\pbc_groups;

use Drupal\node\NodeInterface;

/**
 * Interface GroupsUtilityInterface.
 *
 * @package Drupal\pbc_groups
 */
interface GroupsUtilityInterface {

  /**
   * Given values, create a node.
   *
   * @param array $values
   *   An array of values to create a node with.
   *
   * @return object
   *   a node object.
   */
  public function createNode($values);

  /**
   * Given values, create a node.
   *
   * @param array $values
   *   Node values to update.
   * @param int $nid
   *   A node id.
   *
   * @return object
   *   a node object.
   */
  public function updateNode($values, $nid);

  /**
   * Given a term name, return ID.
   *
   * @param string $vocab
   *   The terms vocabulary.
   * @param string $term
   *   A taxonomy term name.
   *
   * @return int
   *   a term id
   */
  public function getTidByName($vocab, $term);

  /**
   * Terms to options.
   *
   * @param string $vocab
   *   The terms vocabulary.
   *
   * @return array
   *   array that can be used in a select
   */
  public function termsToOptions($vocab);

  /**
   * Build a complete array to create a node with.
   *
   * @param array $values
   *   An array of values to create a node with.
   *
   * @return array
   *   An array of values to create a node with.
   */
  public function buildNodeValues($values);

  /**
   * Build a complete array to create a Individual Attendance Record node with.
   *
   * @param object $group_connection
   *   Drupal\node\NodeInterface;
   * @param object $group_attendance_record
   *   Drupal\node\NodeInterface;
   * @param bool $in_attendance
   *   Bool 0 or 1
   *
   * @return array
   *   An array of values to create a node with.
   */
  public function buildIndivdualAttendanceNodeValues(NodeInterface $group_connection, NodeInterface $group_attendance_record, bool $in_attendance);

}
