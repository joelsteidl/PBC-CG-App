<?php

namespace Drupal\pbc_groups;

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
   * Build a complete array to create a node with.
   *
   * @param array $values
   *   An array of values to create a node with.
   *
   * @return array
   *   An array of values to create a node with.
   */
  public function buildNodeValues($values);

}
